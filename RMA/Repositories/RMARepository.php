<?php

declare(strict_types=1);

namespace JTL\RMA\Repositories;

use JTL\Abstracts\AbstractDBRepository;
use JTL\Catalog\Product\Artikel;
use JTL\Helpers\Typifier;
use JTL\RMA\DomainObjects\RMADomainObject;
use JTL\RMA\DomainObjects\RMAItemDomainObject;
use JTL\RMA\DomainObjects\RMAReasonLangDomainObject;
use JTL\RMA\DomainObjects\RMAReturnAddressDomainObject;
use JTL\RMA\Helper\RMAItems;
use stdClass;

use function Functional\pluck;

/**
 * Class RMARepository
 * @package JTL\RMA\Repositories
 * @since 5.3.0
 * @description This is a layer between the RMA Service and the database.
 */
class RMARepository extends AbstractDBRepository
{
    /**
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'rma';
    }

    /**
     * @param array<mixed> $filter
     * @return array{param: array, queries: array}
     */
    private function buildParameterAndQueries(array $filter): array
    {
        $param   = [];
        $queries = [];
        if (!empty($filter['customerID'])) {
            $param['customerID']   = $filter['customerID'];
            $queries['customerID'] = ' AND rma.customerID = :customerID';
        }
        if (!empty($filter['id'])) {
            $param['id']   = $filter['id'];
            $queries['id'] = ' AND rma.id = :id';
        }
        if (!empty($filter['status'])) {
            $param['status']   = $filter['status'];
            $queries['status'] = ' AND rma.status = :status';
        }
        if (!empty($filter['beforeDate'])) {
            $param['beforeDate']   = $filter['beforeDate'];
            $queries['beforeDate'] = ' AND rma.createDate < :beforeDate';
        }
        if (!empty($filter['product'])) {
            $param['pID']       = $filter['product'];
            $queries['product'] = ' AND items.productID = :pID';
        }
        if (!empty($filter['shippingNote'])) {
            $param['sID']            = $filter['shippingNote'];
            $queries['shippingNote'] = ' AND shippingNote.kLieferschein = :sID';
        }
        if (!empty($filter['synced'])) {
            $param['synced']   = $filter['synced'];
            $queries['synced'] = ' AND rma.synced = :synced';
        }

        return [$param, $queries];
    }

    /**
     * @param int                          $langID
     * @param array{customerID: int, id: int, status: int, beforeDate: string, product: int,
     *     shippingNote: int, synced: int} $filter
     * @return object{params: array, stmnt: string}
     */
    private function buildQuery(int $langID, array $filter = []): object
    {
        $result = new stdClass();

        [$param, $queries] = $this->buildParameterAndQueries($filter);

        $param['langID'] = $langID;

        $result->params = $param;
        $result->stmnt  =
            'SELECT
                rma.id AS rma_id, rma.wawiID, rma.customerID, rma.replacementOrderID, rma.rmaNr,
                rma.voucherCredit, rma.refundShipping, rma.synced AS rma_synced, rma.status AS rma_status,
                rma.comment AS rma_comment, rma.createDate AS rma_createDate,
                rma.lastModified AS rma_lastModified,
                
                items.id AS rma_item_id, items.orderPosID, items.orderID, items.productID, items.reasonID,
                items.name, items.variationProductID, items.variationName, items.variationValue,
                items.partListProductID, items.partListProductName, items.partListProductURL,
                items.partListProductNo, items.unitPriceNet, items.quantity, items.vat, items.unit,
                
                items.shippingNotePosID, items.comment AS rma_item_comment, items.status AS rma_item_status,
                items.createDate AS rma_item_createDate,
    
                return_address.id AS return_address_id, return_address.salutation AS return_address_salutation,
                return_address.firstName AS return_address_firstName,
                return_address.lastName AS return_address_lastName,
                return_address.academicTitle AS return_address_academicTitle,
                return_address.companyName AS return_address_companyName,
                return_address.companyAdditional AS return_address_companyAdditional,
                return_address.street AS return_address_street,
                return_address.houseNumber AS return_address_houseNumber,
                return_address.addressAdditional AS return_address_addressAdditional,
                return_address.postalCode AS return_address_postalCode,
                return_address.city AS return_address_city, return_address.state AS return_address_state,
                return_address.countryISO AS return_address_countryISO,
                return_address.phone AS return_address_phone,
                return_address.mobilePhone AS return_address_mobilePhone,
                return_address.fax AS return_address_fax, return_address.mail AS return_address_mail,
                
                rma_reasons.id AS rma_reasons_id, rma_reasons.wawiID AS rma_reasons_wawiID,
                
                rma_reasons_lang.id AS rma_reasons_lang_id,
                rma_reasons_lang.reasonID AS rma_reasons_lang_reasonID,
                rma_reasons_lang.langID AS rma_reasons_lang_langID,
                rma_reasons_lang.title AS rma_reasons_lang_title,
                
                shippingNote.kLieferschein AS shippingNoteID,

                tbestellung.cBestellNr AS orderNo, tbestellung.dErstellt AS orderDate,
                
                tseo.cSeo AS seo,
                
                tartikel.cArtNr AS productNo, tartikel.cTeilbar AS isDivisible
            FROM
                rma
            RIGHT JOIN rma_items AS items
                ON rma.id = items.rmaID' . ($queries['product'] ?? '') . '
            LEFT JOIN tlieferscheinpos AS shippingNote
                ON items.shippingNotePosID = shippingNote.kLieferscheinPos' . ($queries['shippingNote'] ?? '') . '
            LEFT JOIN rma_reasons
                ON items.reasonID = rma_reasons.id
            LEFT JOIN rma_reasons_lang
                ON items.reasonID = rma_reasons_lang.reasonID
                AND rma_reasons_lang.langID = :langID
            LEFT JOIN return_address
                ON rma.id = return_address.rmaID
            LEFT JOIN tbestellung
                ON items.orderID = tbestellung.kBestellung
            LEFT JOIN twarenkorbpos
                ON twarenkorbpos.kBestellpos = items.orderPosID
            LEFT JOIN twarenkorbposeigenschaft
                ON twarenkorbposeigenschaft.kWarenkorbPos = twarenkorbpos.kWarenkorbPos
            LEFT JOIN teigenschaftsprache
                ON teigenschaftsprache.kEigenschaft = twarenkorbposeigenschaft.kEigenschaft
                AND teigenschaftsprache.kSprache = :langID
            LEFT JOIN teigenschaftwertsprache
                ON teigenschaftwertsprache.kEigenschaftWert = twarenkorbposeigenschaft.kEigenschaftWert
                AND teigenschaftwertsprache.kSprache = :langID
            LEFT JOIN tseo
                ON tseo.kKey = items.productID
                AND tseo.cKey = "kArtikel"
                AND tseo.kSprache = :langID
            LEFT JOIN tartikel
                ON tartikel.kArtikel = items.productID
            WHERE rma.id > 0'
            . ($queries['customerID'] ?? '')
            . ($queries['id'] ?? '')
            . ($queries['status'] ?? '')
            . ($queries['beforeDate'] ?? '')
            . ($queries['synced'] ?? '')
            . ' GROUP BY items.id'
            . ' ORDER BY rma.id DESC';

        return $result;
    }

    /**
     * @param array<int, object> $positions
     * @param int                $langID
     * @return array<int, array>
     */
    private function getReturnsAsArray(array $positions, int $langID): array
    {
        $result = [];
        foreach ($positions as $position) {
            $position                    = (array)$position;
            $position['rma_item_status'] = RMADomainObject::orderStatusToString($position['rma_status']);
            $position['product']         = $this->initArtikel(
                productID: $position['variationProductID'] ?? $position['productID'],
                data: $position
            );

            $position['rmaReason'] = $this->initReason(
                langID: $langID,
                data: $position
            );

            if (isset($result[$position['rma_id']]) === false) {
                $position['returnAddress']   = $this->initReturnAddress($position);
                $result[$position['rma_id']] = $position;
            }

            $result[$position['rma_id']]['items'][] = $this->initRMAItem((object)$position);
        }

        return $result;
    }

    /**
     * @param int          $langID
     * @param array<mixed> $filter
     * @param int|null     $limit
     * @return RMADomainObject[]
     * @throws \Exception
     * @since 5.3.0
     */
    public function getReturns(
        int $langID,
        array $filter = [],
        ?int $limit = null
    ): array {
        $result = [];

        foreach (
            $this->getReturnsAsArray(
                positions: $this->getReturnPositions(
                    readableQuery: $this->buildQuery(
                        langID: $langID,
                        filter: $filter
                    )
                ),
                langID: $langID
            ) as $rma
        ) {
            if ($limit !== null) {
                if ($limit <= 0) {
                    break;
                }
                $limit--;
            }
            $result[$rma['rma_id']] = new RMADomainObject(
                id: $rma['rma_id'],
                wawiID: $rma['wawiID'],
                customerID: $rma['customerID'],
                replacementOrderID: $rma['replacementOrderID'],
                rmaNr: $rma['rmaNr'] ?? null,
                voucherCredit: (bool)$rma['voucherCredit'],
                refundShipping: (bool)$rma['refundShipping'],
                synced: (bool)$rma['rma_synced'],
                status: $rma['rma_status'],
                comment: $rma['rma_comment'] ?? null,
                createDate: $rma['rma_createDate'],
                lastModified: $rma['rma_lastModified'] ?? null,
                items: new RMAItems($rma['items']),
                returnAddress: $rma['returnAddress']
            );
        }

        return $result;
    }

    /**
     * @param int $customerID
     * @param int $languageID
     * @param int $cancellationTime
     * @param int $orderID
     * @return RMAItemDomainObject[]
     * @since 5.3.0
     */
    public function getReturnableProducts(
        int $customerID,
        int $languageID,
        int $cancellationTime,
        int $orderID = 0
    ): array {
        $result   = [];
        $products = [];

        foreach (
            $this->getReturnableProductsFromDB(
                orderID: $orderID,
                customerID: $customerID,
                languageID: $languageID,
                cancellationTime: $cancellationTime
            ) as $product
        ) {
            if ($product->quantity <= 0) {
                continue;
            }

            if (!empty($product->partListProductIDs)) {
                $this->handlePartlistProducts(
                    partListProductIDs: $product->partListProductIDs,
                    languageID: $languageID,
                    product: $product,
                    products: $products
                );
                continue;
            }
            $products[] = $product;
        }

        foreach ($products as $product) {
            $product->variationName  = $product->propertyNameLocalized ?? $product->propertyName ?? null;
            $product->variationValue = $product->propertyValueLocalized ?? $product->propertyValue ?? null;
            $product->product        = $this->initArtikel(
                productID: $product->id,
                data: (array)$product
            );

            $result[] = $this->initRMAItem($product);
        }

        return $result;
    }

    /**
     * @param array<mixed> $orderIDs
     * @return array<mixed>
     * @since 5.3.0
     */
    public function getOrderNumbers(array $orderIDs): array
    {
        $result = [];
        $this->db->getCollection(
            'SELECT tbestellung.kBestellung AS orderID, tbestellung.cBestellNr AS orderNo
                FROM tbestellung
                WHERE tbestellung.kBestellung IN (' . \implode(',', $orderIDs) . ')'
        )->each(function ($obj) use (&$result): void {
            $result[(int)$obj->orderID] = $obj->orderNo;
        });

        return $result;
    }

    /**
     * @param RMADomainObject[] $rmaDomainObjects
     * @return bool
     * @description Mark returns as synced and return a boolean result.
     */
    public function markedAsSynced(array $rmaDomainObjects): bool
    {
        if (\count($rmaDomainObjects) === 0) {
            return false;
        }
        $affected = $this->db->getAffectedRows(
            'UPDATE ' . $this->getTableName() . ' SET synced = 1
             WHERE ' . $this->getKeyName()
            . ' IN (' . \implode(',', pluck($rmaDomainObjects, 'id')) . ')',
        );

        return $affected > 0;
    }

    /**
     * @param int          $productID
     * @param array<mixed> $data
     * @return Artikel
     */
    private function initArtikel(int $productID, array $data): Artikel
    {
        $product           = new Artikel($this->db);
        $product->kArtikel = $productID;
        $product->cName    = '';
        $product->cSeo     = $data['seo'] ?? '';
        $product->holBilder();
        $product->cTeilbar = $data['isDivisible'] ?? 'N';

        return $product;
    }

    /**
     * @return stdClass[]
     */
    private function getReturnableProductsFromDB(
        int $orderID,
        int $customerID,
        int $languageID,
        int $cancellationTime
    ): array {
        return $this->db->getCollection(
            "SELECT twarenkorbpos.kArtikel AS id, twarenkorbpos.cEinheit AS unit,
                twarenkorbpos.cArtNr AS productNo, twarenkorbpos.fPreisEinzelNetto AS unitPriceNet,
                twarenkorbpos.fMwSt AS vat, twarenkorbpos.cName AS name, twarenkorbpos.kBestellpos AS orderPosID,
                tbestellung.kKunde AS customerID, tbestellung.kLieferadresse AS shippingAddressID,
                tbestellung.cStatus AS orderStatus, tbestellung.cBestellNr AS orderNo,
                tbestellung.dErstellt AS orderDate, tbestellung.kBestellung AS orderID,
                tlieferscheinpos.kLieferscheinPos AS shippingNotePosID,
                tlieferscheinpos.kLieferschein AS shippingNoteID,
                (tlieferscheinpos.fAnzahl - SUM(IFNULL(rma_items.quantity, 0))) AS quantity,
                tartikel.cSeo AS seo, tartikel.cTeilbar AS isDivisible,
                DATE_FORMAT(FROM_UNIXTIME(tversand.dErstellt), '%d-%m-%Y') AS createDate,
                twarenkorbposeigenschaft.cEigenschaftName AS propertyName,
                twarenkorbposeigenschaft.cEigenschaftWertName AS propertyValue,
                teigenschaftsprache.cName AS propertyNameLocalized,
                teigenschaftwertsprache.cName AS propertyValueLocalized,
                GROUP_CONCAT(tstueckliste.kArtikel SEPARATOR ',') AS partListProductIDs
                FROM tbestellung
                    INNER JOIN twarenkorbpos
                        ON twarenkorbpos.kWarenkorb = tbestellung.kWarenkorb
                               AND twarenkorbpos.kArtikel > 0
                    INNER JOIN tlieferscheinpos
                        ON tlieferscheinpos.kBestellPos = twarenkorbpos.kBestellpos
                    LEFT JOIN tversand
                        ON tversand.kLieferschein = tlieferscheinpos.kLieferschein
                               AND DATE(FROM_UNIXTIME(tversand.dErstellt)) >=
                                   DATE_SUB(NOW(), INTERVAL :cancellationTime DAY)
                    LEFT JOIN tartikelattribut
                        ON tartikelattribut.kArtikel = twarenkorbpos.kArtikel
                               AND tartikelattribut.cName = :notReturnable
                    LEFT JOIN tartikeldownload
                        ON tartikeldownload.kArtikel = twarenkorbpos.kArtikel
                    LEFT JOIN twarenkorbposeigenschaft
                        ON twarenkorbposeigenschaft.kWarenkorbPos = twarenkorbpos.kWarenkorbPos
                    LEFT JOIN teigenschaftsprache
                        ON teigenschaftsprache.kEigenschaft = twarenkorbposeigenschaft.kEigenschaft
                               AND teigenschaftsprache.kSprache = :langID
                    LEFT JOIN teigenschaftwertsprache
                        ON teigenschaftwertsprache.kEigenschaftWert = twarenkorbposeigenschaft.kEigenschaftWert
                               AND teigenschaftwertsprache.kSprache = :langID
                    INNER JOIN tartikel
                        ON tartikel.kArtikel = twarenkorbpos.kArtikel
                    LEFT JOIN tstueckliste
                        ON tartikel.kStueckliste = tstueckliste.kStueckliste
                    LEFT JOIN rma_items
                        ON rma_items.shippingNotePosID = tlieferscheinpos.kLieferscheinPos
                               AND tlieferscheinpos.kBestellPos = twarenkorbpos.kBestellpos
                               AND rma_items.productID = twarenkorbpos.kArtikel
                WHERE tbestellung.kKunde = :customerID
                  AND tbestellung.cStatus IN (:status_versandt, :status_teilversandt)
                  AND tartikelattribut.cWert IS NULL
                  AND tartikeldownload.kArtikel IS NULL"
            . ($orderID > 0 ? ' AND tbestellung.kBestellung = :orderID' : '')
            . ' GROUP BY tlieferscheinpos.kLieferscheinPos',
            [
                'customerID'          => $customerID,
                'langID'              => $languageID,
                'orderID'             => $orderID,
                'status_versandt'     => \BESTELLUNG_STATUS_VERSANDT,
                'status_teilversandt' => \BESTELLUNG_STATUS_TEILVERSANDT,
                'cancellationTime'    => $cancellationTime,
                'notReturnable'       => \FKT_ATTRIBUT_PRODUCT_NOT_RETURNABLE
            ]
        )->each(static function (stdClass $item): void {
            $item->id                     = (int)$item->id;
            $item->unit                   = $item->unit ?? '';
            $item->unitPriceNet           = (float)$item->unitPriceNet;
            $item->vat                    = (float)($item->vat ?? 0.00);
            $item->name                   = $item->name ?? '';
            $item->orderPosID             = (int)$item->orderPosID;
            $item->customerID             = (int)$item->customerID;
            $item->shippingAddressID      = (int)$item->shippingAddressID;
            $item->orderDate              = $item->orderDate ?? '';
            $item->orderID                = (int)$item->orderID;
            $item->shippingNotePosID      = (int)$item->shippingNotePosID;
            $item->shippingNoteID         = (int)$item->shippingNoteID;
            $item->quantity               = (float)$item->quantity;
            $item->propertyName           = $item->propertyName ?? null;
            $item->propertyValue          = $item->propertyValue ?? null;
            $item->propertyNameLocalized  = $item->propertyNameLocalized ?? null;
            $item->propertyValueLocalized = $item->propertyValueLocalized ?? null;
            $item->partListProductIDs     = $item->partListProductIDs ?? '';
            $item->rmaID                  = 0;
            $item->partListProductID      = 0;
            $item->partListProductName    = '';
            $item->partListProductNo      = '';
            $item->partListProductURL     = '';
        })->all();
    }

    /**
     * @return stdClass[]
     */
    private function getPartListProducts(string $partListProductIDs, int $languageID, object $product): array
    {
        return $this->db->getCollection(
            'SELECT tartikel.kArtikel AS id, tartikel.cName AS name, tartikel.cSeo AS seo,
                tartikel.cTeilbar AS isDivisible, tartikel.cArtNr AS productNo,
                tartikelsprache.cName AS nameLocalized, tartikelsprache.cSeo AS seoLocalized,
                rma_items.quantity AS returnedQuantity, tstueckliste.fAnzahl AS partListQuantity
                FROM tartikel
                    LEFT JOIN tartikelsprache
                        ON tartikelsprache.kArtikel = tartikel.kArtikel
                               AND tartikelsprache.kSprache = :langID
                    LEFT JOIN rma_items
                        ON rma_items.productID = tartikel.kArtikel
                               AND rma_items.shippingNotePosID = :shippingNotePosID
                    LEFT JOIN tstueckliste
                        ON tstueckliste.kArtikel = tartikel.kArtikel
                WHERE tartikel.kArtikel IN (' . $partListProductIDs . ')',
            [
                'langID'            => $languageID,
                'shippingNotePosID' => $product->shippingNotePosID
            ]
        )->each(static function (stdClass $item): void {
            $item->id               = (int)$item->id;
            $item->productNo        = $item->productNo ?? '';
            $item->nameLocalized    = $item->nameLocalized ?? null;
            $item->returnedQuantity = (float)$item->returnedQuantity;
            $item->partListQuantity = (float)$item->partListQuantity;
        })->all();
    }

    /**
     * @param object $product
     * @param object $partListProduct
     * @param float  $returnableQuantity
     * @return object
     */
    private function mergePartListProductData(
        object $product,
        object $partListProduct,
        float $returnableQuantity
    ): object {
        $compoundData                      = clone($product);
        $compoundData->partListProductID   = $product->id;
        $compoundData->partListProductName = $product->name;
        $compoundData->partListProductNo   = $product->productNo;
        $compoundData->partListProductURL  = $product->seo;

        $compoundData->id          = $partListProduct->id;
        $compoundData->name        = $partListProduct->nameLocalized;
        $compoundData->quantity    = $returnableQuantity;
        $compoundData->productNo   = $partListProduct->productNo;
        $compoundData->isDivisible = $partListProduct->isDivisible;
        $compoundData->seo         = $partListProduct->seoLocalized ?? $partListProduct->seo;

        return $compoundData;
    }

    /**
     * @param object $product
     * @return RMAItemDomainObject
     */
    private function initRMAItem(object $product): RMAItemDomainObject
    {
        return new RMAItemDomainObject(
            id: $product->id ?? 0,
            rmaID: $product->rmaID ?? 0,
            shippingNotePosID: $product->shippingNotePosID ?? null,
            orderID: $product->orderID ?? null,
            orderPosID: $product->orderPosID ?? null,
            productID: $product->productID ?? null,
            reasonID: $product->reasonID ?? null,
            name: $product->name,
            variationProductID: $product->variationProductID ?? null,
            variationName: $product->variationName ?? null,
            variationValue: $product->variationValue ?? null,
            partListProductID: $product->partListProductID ?? null,
            partListProductName: $product->partListProductName ?? null,
            partListProductURL: $product->partListProductURL ?? null,
            partListProductNo: $product->partListProductNo ?? null,
            unitPriceNet: $product->unitPriceNet ?? 0.00,
            quantity: $product->quantity ?? 0.00,
            vat: $product->vat ?? 0.00,
            unit: $product->unit ?? null,
            comment: $product->rma_item_comment ?? null,
            status: $product->rma_item_status ?? null,
            createDate: $product->rma_item_createDate ?? null,
            history: $product->rmaHistory ?? null,
            product: $product->product ?? null,
            reason: $product->rmaReason ?? null,
            productNo: $product->productNo ?? null,
            orderStatus: $product->orderStatus ?? null,
            seo: $product->seo ?? null,
            orderNo: $product->orderNo ?? null,
            orderDate: $product->orderDate ?? null,
            customerID: $product->customerID ?? null,
            shippingAddressID: $product->shippingAddressID ?? null,
            shippingNoteID: $product->shippingNoteID ?? null,
        );
    }

    /**
     * @param string       $partListProductIDs
     * @param int          $languageID
     * @param object       $product
     * @param array<mixed> $products
     */
    private function handlePartlistProducts(
        string $partListProductIDs,
        int $languageID,
        object $product,
        array &$products
    ): void {
        foreach (
            $this->getPartListProducts(
                partListProductIDs: $partListProductIDs,
                languageID: $languageID,
                product: $product
            ) as $partListProduct
        ) {
            $partListProduct->nameLocalized = $partListProduct->nameLocalized ?? $partListProduct->name;
            $returnableQuantity             = ((float)$partListProduct->partListQuantity * $product->quantity)
                - (float)$partListProduct->returnedQuantity;

            if ($returnableQuantity <= 0) {
                continue;
            }
            $products[] = $this->mergePartListProductData($product, $partListProduct, $returnableQuantity);
        }
    }

    /**
     * @param array<mixed> $addressData
     * @return RMAReturnAddressDomainObject
     */
    private function initReturnAddress(array $addressData): RMAReturnAddressDomainObject
    {
        return new RMAReturnAddressDomainObject(
            id: $addressData['return_address_id'],
            rmaID: $addressData['rma_id'],
            customerID: $addressData['customerID'],
            salutation: $addressData['return_address_salutation'] ?? '',
            firstName: $addressData['return_address_firstName'],
            lastName: $addressData['return_address_lastName'],
            academicTitle: $addressData['return_address_academicTitle'] ?? null,
            companyName: $addressData['return_address_companyName'] ?? null,
            companyAdditional: $addressData['return_address_companyAdditional'] ?? null,
            street: $addressData['return_address_street'],
            houseNumber: $addressData['return_address_houseNumber'],
            addressAdditional: $addressData['return_address_addressAdditional'] ?? null,
            postalCode: $addressData['return_address_postalCode'],
            city: $addressData['return_address_city'],
            state: $addressData['return_address_state'],
            countryISO: $addressData['return_address_countryISO'],
            phone: $addressData['return_address_phone'] ?? null,
            mobilePhone: $addressData['return_address_mobilePhone'] ?? null,
            fax: $addressData['return_address_fax'] ?? null,
            mail: $addressData['return_address_mail'] ?? null,
        );
    }

    /**
     * @param object $readableQuery
     * @return object[]
     */
    private function getReturnPositions(object $readableQuery): array
    {
        return $this->db->getCollection(
            stmt: $readableQuery->stmnt,
            params: $readableQuery->params,
        )->each(static function (stdClass $item): void {
            $item->rma_id                    = (int)$item->rma_id;
            $item->wawiID                    = Typifier::intifyOrNull($item->wawiID);
            $item->customerID                = (int)$item->customerID;
            $item->replacementOrderID        = Typifier::intifyOrNull($item->replacementOrderID);
            $item->voucherCredit             = (int)$item->voucherCredit;
            $item->refundShipping            = (int)$item->refundShipping;
            $item->rma_synced                = (int)$item->rma_synced;
            $item->rma_status                = (int)$item->rma_status;
            $item->rma_item_id               = (int)$item->rma_item_id;
            $item->orderPosID                = Typifier::intifyOrNull($item->orderPosID);
            $item->orderID                   = Typifier::intifyOrNull($item->orderID);
            $item->productID                 = Typifier::intifyOrNull($item->productID);
            $item->reasonID                  = Typifier::intifyOrNull($item->reasonID);
            $item->variationProductID        = Typifier::intifyOrNull($item->variationProductID);
            $item->partListProductID         = Typifier::intifyOrNull($item->partListProductID);
            $item->unitPriceNet              = (float)$item->unitPriceNet;
            $item->quantity                  = (float)$item->quantity;
            $item->vat                       = (float)$item->vat;
            $item->shippingNotePosID         = Typifier::intifyOrNull($item->shippingNotePosID);
            $item->return_address_id         = (int)$item->return_address_id;
            $item->rma_reasons_id            = (int)$item->rma_reasons_id;
            $item->rma_reasons_wawiID        = (int)$item->rma_reasons_wawiID;
            $item->rma_reasons_lang_id       = (int)$item->rma_reasons_lang_id;
            $item->rma_reasons_lang_reasonID = (int)$item->rma_reasons_lang_reasonID;
            $item->rma_reasons_lang_langID   = (int)$item->rma_reasons_lang_langID;
            $item->shippingNoteID            = (int)$item->shippingNoteID;
        })->all();
    }

    /**
     * @param int          $langID
     * @param array<mixed> $data
     * @return RMAReasonLangDomainObject|null
     */
    private function initReason(int $langID, array $data): ?RMAReasonLangDomainObject
    {
        return new RMAReasonLangDomainObject(
            id: $data['rma_reasons_lang_id'],
            reasonID: $data['rma_reasons_id'],
            langID: $langID,
            title: $data['rma_reasons_lang_title'] ?? '',
        );
    }
}
