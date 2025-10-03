<?php

declare(strict_types=1);

namespace JTL\dbeS\Push;

use Exception;
use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\Language\LanguageHelper;
use JTL\RMA\DomainObjects\RMADomainObject;
use JTL\RMA\Services\RMAService;
use Psr\Log\LoggerInterface;

/**
 * Class Orders
 * @package JTL\dbeS\Push
 */
final class Returns extends AbstractPush
{
    private RMAService $rmaService;

    public function __construct(
        protected DbInterface $db,
        protected JTLCacheInterface $cache,
        protected LoggerInterface $logger
    ) {
        parent::__construct($db, $cache, $logger);
        $this->rmaService = new RMAService();
    }

    private function externalComment(RMADomainObject $rma, bool $clientPrefersPickup = false): string
    {
        $textComment = '';
        foreach ($rma->getRMAItems()->getArray() as $item) {
            if ($item->comment !== null && $item->comment !== '') {
                if ($textComment !== '') {
                    // Double quotes are needed here in order to parse correctly the tab character
                    $textComment .= PHP_EOL . "\t\t";
                }
                $textComment .= $item->name . ' - Nr.: ' . $item->getProductNo() . ': ' . $item->comment;
            }
        }

        if ($clientPrefersPickup) {
            $textComment .= PHP_EOL . "\t\t" . 'Der Kunde wünscht die Abholung seiner Ware';
        }

        return $textComment;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function returnNullIfEmpty(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return $value === '' ? null : $value;
    }

    /**
     * @return array<mixed>
     * @throws Exception
     */
    public function getData(): array
    {
        $xml  = [];
        $rmas = [];

        $this->rmaService->loadReturns(
            LanguageHelper::getDefaultLanguage()->id,
            ['synced' => 0]
        );

        foreach ($this->rmaService->rmas as $rma) {
            $return = ['items' => []];
            foreach ($rma->getRMAItems()->getArray() as $item) {
                $return['items'][] = \array_map(fn($val): mixed => $this->returnNullIfEmpty($val), $item->toArray());
            }

            $clientPrefersPickup = false;
            $returnAddress       = $rma->getReturnAddress();
            if ($returnAddress !== null) {
                $clientPrefersPickup = true;
                $return['adresse']   = [
                    'cFirma'        => $this->returnNullIfEmpty($returnAddress->companyName),
                    'cZusatz'       => $this->returnNullIfEmpty($returnAddress->companyAdditional),
                    'cAnrede'       => $this->returnNullIfEmpty($returnAddress->salutation),
                    'cTitel'        => $this->returnNullIfEmpty($returnAddress->academicTitle),
                    'cVorname'      => $this->returnNullIfEmpty($returnAddress->firstName),
                    'cName'         => $this->returnNullIfEmpty($returnAddress->lastName),
                    'cStrasse'      => $this->returnNullIfEmpty($returnAddress->street),
                    'cAdressZusatz' => $this->returnNullIfEmpty($returnAddress->addressAdditional),
                    'cPLZ'          => $this->returnNullIfEmpty($returnAddress->postalCode),
                    'cOrt'          => $this->returnNullIfEmpty($returnAddress->city),
                    'cLand'         => \locale_get_display_region(
                        'sl-Latn-' . $returnAddress->countryISO . '-nedis',
                        'de' // LanguageHelper::getDefaultLanguage()->cISO
                    ),
                    'cTel'          => $this->returnNullIfEmpty($returnAddress->phone),
                    'cMobil'        => $this->returnNullIfEmpty($returnAddress->mobilePhone),
                    'cMail'         => $this->returnNullIfEmpty($returnAddress->mail),
                    'cFax'          => $this->returnNullIfEmpty($returnAddress->fax),
                    'cBundesland'   => $this->returnNullIfEmpty($returnAddress->state),
                    'cISO'          => $this->returnNullIfEmpty($returnAddress->countryISO),
                ];
            } else {
                $return['adresse'] = [];
            }
            $return['cKommentarExtern'] = $this->externalComment($rma, $clientPrefersPickup);
            $return['cShopID']          = $rma->id;
            $return['kKundeShop']       = $rma->customerID;
            $return['dErstellt']        = $rma->createDate;

            $rmas[] = $return;
        }

        $xml['rmas']['rma']         = $rmas;
        $xml['rmas attr']['anzahl'] = \count($rmas);

        /*
         * @todo Mark RMAs as synced
        $notSyncedIDs = $this->rmaService->RMARepository->markedAsSynced($this->rmaService->rmas);
        if (\count($notSyncedIDs) > 0) {
            $this->logger->warning('RMAs with the following IDs could not be marked as synced: '
            . \implode(', ', $notSyncedIDs));
        }
        */

        return $xml;
    }
}
