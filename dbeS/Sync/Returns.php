<?php

declare(strict_types=1);

namespace JTL\dbeS\Sync;

use Exception;
use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\dbeS\Starter;
use JTL\Language\LanguageHelper;
use JTL\RMA\DomainObjects\dbeS\RMAAddressSyncObject;
use JTL\RMA\DomainObjects\dbeS\RMAItemSyncObject;
use JTL\RMA\DomainObjects\dbeS\RMAReasonLocalizationSyncObject;
use JTL\RMA\DomainObjects\dbeS\RMAReasonSyncObject;
use JTL\RMA\DomainObjects\dbeS\RMASyncObject;
use JTL\RMA\DomainObjects\RMADomainObject;
use JTL\RMA\DomainObjects\RMAItemDomainObject;
use JTL\RMA\DomainObjects\RMAReasonDomainObject;
use JTL\RMA\DomainObjects\RMAReasonLangDomainObject;
use JTL\RMA\DomainObjects\RMAReturnAddressDomainObject;
use JTL\RMA\Helper\RMAItems;
use JTL\RMA\Services\RMAHistoryService;
use JTL\RMA\Services\RMAReasonService;
use JTL\RMA\Services\RMAService;
use JTL\XML;
use Psr\Log\LoggerInterface;

/**
 * Class Returns
 * @package JTL\dbeS\Sync
 * @since 5.3.0
 */
final class Returns extends AbstractSync
{
    public function __construct(
        protected DbInterface $db,
        protected JTLCacheInterface $cache,
        protected LoggerInterface $logger,
        private readonly RMAService $rmaService = new RMAService(),
        private readonly RMAHistoryService $rmaHistoryService = new RMAHistoryService(),
        private readonly RMAReasonService $rmaReasonService = new RMAReasonService(),
    ) {
        parent::__construct($db, $cache, $logger);
    }

    /**
     * @throws Exception
     * @since 5.3.0
     */
    public function handle(Starter $starter): void
    {
        foreach ($starter->getXML() as $item) {
            $file = \key($item);
            $xml  = \reset($item);
            if (empty($xml) || !\is_string($file)) {
                $this->logger->warning(
                    'No returns found in XML: ' . XML::getLastParseError() . ' in:' . \print_r($xml, true)
                );
                continue;
            }
            if (\str_contains($file, 'upd_return.xml')) {
                $this->upsertRMA($xml);
            } elseif (\str_contains($file, 'rma_reasons.xml')) {
                $this->upsertReasons($xml);
            }
        }
    }

    /**
     * @param array<mixed> $xml
     * @throws Exception
     * @since 5.4.0
     */
    private function upsertRMA(array $xml): void
    {
        foreach ($xml['rmas'] ?? [] as $returns) {
            if (!isset($returns[0])) {
                $returns = [$returns];
            }
            foreach ($returns as $return) {
                $return['adresse'] = RMAAddressSyncObject::initFromXML($return['adresse']);

                if (!isset($return['item'][0])) {
                    $return['item'] = [$return['item']];
                }
                foreach ($return['item'] as $index => $item) {
                    $return['item'][$index] = RMAItemSyncObject::initFromXML($item);
                }
                $rmaSyncObject = RMASyncObject::initFromXML($return);

                if ($rmaSyncObject->kRMRetoure > 0) {
                    $this->updateRMA($rmaSyncObject);
                } else {
                    $this->insertRMA($rmaSyncObject);
                }
            }
        }
    }

    /**
     * @throws Exception
     */
    private function upsertReasons(mixed $xml): void
    {
        $result = [];
        foreach ($xml['reasons'] ?? [] as $reasons) {
            if (!isset($reasons[0])) {
                $reasons = [$reasons];
            }
            foreach ($reasons as $reason) {
                if (!isset($reason['localization'][0])) {
                    $reason['localization'] = [$reason['localization']];
                }
                foreach ($reason['localization'] as $index => $localization) {
                    $reason['localization'][$index] = RMAReasonLocalizationSyncObject::initFromXML($localization);
                }
                $result[] = RMAReasonSyncObject::initFromXML($reason);
            }
        }

        $this->resetRMAReason($result);
    }

    /**
     * @throws Exception
     * @since 5.4.0
     */
    private function insertRMA(RMASyncObject $rmaSyncObject): void
    {
        $rmaFromWaWi = $this->initRMADomainObject($rmaSyncObject);
        $this->rmaService->insertRMA($rmaFromWaWi);
    }

    /**
     * @param RMASyncObject $rmaSyncObject
     * @throws Exception
     * @since 5.3.0
     */
    private function updateRMA(RMASyncObject $rmaSyncObject): void
    {
        $rmaFromWaWi = $this->initRMADomainObject($rmaSyncObject);
        $rmaFromShop = $this->rmaService->getReturn(
            id: $rmaFromWaWi->id,
            customerID: $rmaFromWaWi->customerID,
            langID: LanguageHelper::getDefaultLanguage()->id
        );

        if ($this->rmaService->updateRMA($rmaFromWaWi)) {
            $this->rmaHistoryService->dispatchEvents($rmaFromWaWi, $rmaFromShop);
        }
    }

    /**
     * @param RMASyncObject $rmaSyncObject
     * @return RMADomainObject
     * @throws Exception
     * @since 5.4.0
     */
    private function initRMADomainObject(RMASyncObject $rmaSyncObject): RMADomainObject
    {
        $items = new RMAItems();
        foreach ($rmaSyncObject->item as $item) {
            $items->append(
                new RMAItemDomainObject(
                    id: 0,
                    rmaID: $rmaSyncObject->kRMRetoure,
                    shippingNotePosID: $item->kLieferscheinPos,
                    orderID: 0,
                    orderPosID: 0,
                    productID: $item->kArtikel,
                    reasonID: $item->kRMGrund,
                    name: $item->cName,
                    variationProductID: null,
                    variationName: null,
                    variationValue: null,
                    partListProductID: null,
                    partListProductName: null,
                    partListProductURL: null,
                    partListProductNo: null,
                    unitPriceNet: 0.0,
                    quantity: $item->fAnzahl,
                    vat: 0.0,
                    unit: null,
                    comment: null,
                    status: null,
                    createDate: $item->dErstellt
                )
            );
        }
        // If the house number is set inside the cStrasse property, extract it as cHausnummer
        $this->extractStreet($rmaSyncObject->adresse);

        $returnAddress = new RMAReturnAddressDomainObject(
            id: 0,
            rmaID: $rmaSyncObject->kRMRetoure,
            customerID: $rmaSyncObject->kKundeShop,
            salutation: $rmaSyncObject->adresse->cAnrede,
            firstName: $rmaSyncObject->adresse->cVorname,
            lastName: $rmaSyncObject->adresse->cName,
            academicTitle: $rmaSyncObject->adresse->cTitel,
            companyName: $rmaSyncObject->adresse->cFirma,
            companyAdditional: $rmaSyncObject->adresse->cZusatz,
            street: $rmaSyncObject->adresse->cStrasse,
            houseNumber: $rmaSyncObject->adresse->cHausnummer ?? '',
            addressAdditional: $rmaSyncObject->adresse->cAdressZusatz,
            postalCode: $rmaSyncObject->adresse->cPLZ,
            city: $rmaSyncObject->adresse->cOrt,
            state: $rmaSyncObject->adresse->cBundesland,
            countryISO: $rmaSyncObject->adresse->cISO,
            phone: $rmaSyncObject->adresse->cTel,
            mobilePhone: $rmaSyncObject->adresse->cMobil,
            fax: $rmaSyncObject->adresse->cFax,
            mail: $rmaSyncObject->adresse->cMail
        );

        return new RMADomainObject(
            id: $rmaSyncObject->cShopID,
            wawiID: $rmaSyncObject->kRMRetoure,
            customerID: $rmaSyncObject->kKundeShop,
            replacementOrderID: null,
            rmaNr: $rmaSyncObject->cRetoureNr,
            voucherCredit: $rmaSyncObject->nKuponGutschriftGutschreiben,
            refundShipping: $rmaSyncObject->nVersandkostenErstatten,
            synced: true,
            status: 1,
            comment: $rmaSyncObject->cKommentarExtern,
            createDate: $rmaSyncObject->dErstellt,
            items: $items,
            returnAddress: $returnAddress
        );
    }

    /**
     * @param RMAReasonSyncObject[] $reasonsFromWaWi
     */
    private function resetRMAReason(array $reasonsFromWaWi): void
    {
        $langID = LanguageHelper::getDefaultLanguage()->id;
        $this->db->executeQuery('SET FOREIGN_KEY_CHECKS = 0');
        $this->db->executeQuery(
            'TRUNCATE TABLE ' . $this->rmaReasonService->RMAReasonLangRepository->getTableName()
        );
        $this->db->executeQuery(
            'TRUNCATE TABLE ' . $this->rmaReasonService->RMAReasonRepository->getTableName()
        );
        $this->db->executeQuery('SET FOREIGN_KEY_CHECKS = 1');

        foreach ($reasonsFromWaWi as $reasonFromWaWi) {
            $localizations = [];
            $rmaReason     = new RMAReasonDomainObject(
                wawiID: $reasonFromWaWi->wawiID,
                productTypeGroupID: $reasonFromWaWi->productTypeGroupID
            );
            foreach ($reasonFromWaWi->localization as $localization) {
                $localizations[] = new RMAReasonLangDomainObject(
                    langID: $langID,
                    title: $localization->title
                );
            }
            $this->rmaReasonService->saveReason(
                reason: $rmaReason,
                reasonsLocalized: $localizations
            );
        }
    }
}
