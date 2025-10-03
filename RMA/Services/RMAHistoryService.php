<?php

declare(strict_types=1);

namespace JTL\RMA\Services;

use JTL\Abstracts\AbstractService;
use JTL\Helpers\Date;
use JTL\Helpers\Typifier;
use JTL\Language\LanguageHelper;
use JTL\RMA\DomainObjects\RMADomainObject;
use JTL\RMA\DomainObjects\RMAEventDataDomainObject;
use JTL\RMA\DomainObjects\RMAHistoryDomainObject;
use JTL\RMA\Helper\RMAHistoryEventData;
use JTL\RMA\Helper\RMAHistoryEvents;
use JTL\RMA\Repositories\RMAHistoryRepository;
use JTL\Shop;

/**
 * Class RMAHistoryService
 * @Description Service for logging and display modifications of a return request.
 * @package JTL\RMA
 * @since 5.3.0
 */
class RMAHistoryService extends AbstractService
{
    public function __construct(public RMAHistoryRepository $RMAHistoryRepository = new RMAHistoryRepository())
    {
    }

    protected function getRepository(): RMAHistoryRepository
    {
        return $this->RMAHistoryRepository;
    }

    /**
     * @param RMADomainObject $originalDO
     * @param RMADomainObject $modifiedDO
     * @return RMAHistoryEventData[]
     */
    public function detectEvents(RMADomainObject $originalDO, RMADomainObject $modifiedDO): array
    {
        $result       = [];
        $originalData = $originalDO->toArray(true);
        $modifiedData = $modifiedDO->toArray(true);
        foreach ($modifiedData as $key => $modifiedValue) {
            $mappedName = $originalDO::class . \ucfirst($key);
            $eventName  = RMAHistoryEventData::mapEventName($mappedName);
            if (!\array_key_exists($key, $originalData)) {
                continue;
            }
            if (
                $originalData[$key] !== $modifiedValue
                && $eventName !== ''
                && $originalData[$key] !== null
                && Typifier::typeify(
                    $originalData[$key],
                    \gettype($modifiedValue)
                ) !== $modifiedValue
            ) {
                $result[] = new RMAHistoryEventData(
                    eventName: $eventName,
                    originalDO: $originalDO,
                    modifiedDO: $modifiedDO
                );
            }
        }

        return $result;
    }

    /**
     * @param RMADomainObject       $originalDO
     * @param RMADomainObject       $modifiedDO
     * @param RMAHistoryEventData[] $events
     */
    public function dispatchEvents(
        RMADomainObject $originalDO,
        RMADomainObject $modifiedDO,
        ?array $events = null
    ): void {
        foreach ($events ?? $this->detectEvents($originalDO, $modifiedDO) as $eventData) {
            match ($eventData->eventName) {
                RMAHistoryEvents::ITEM_MODIFIED->value     => $this->itemModified($eventData),
                RMAHistoryEvents::STATUS_CHANGED->value    => $this->statusChanged($eventData),
                RMAHistoryEvents::ADDRESS_MODIFIED->value  => $this->addressModified($eventData),
                RMAHistoryEvents::REFUND_SHIPPING->value   => $this->refundShipping($eventData),
                RMAHistoryEvents::VOUCHER_CREDIT->value    => $this->voucherCredit($eventData),
                RMAHistoryEvents::REPLACEMENT_ORDER->value => $this->replacementOrderAssigned($eventData)
            };
        }
    }

    /**
     * @description Item added, removed, modified quantity or reason.
     * @since 5.3.0
     */
    private function itemModified(RMAHistoryEventData $eventData): void
    {
        $originalItems = $eventData->originalDO->getRMAItems()->uniqueArrayKeys();
        foreach ($eventData->modifiedDO->getRMAItems()->uniqueArrayKeys() as $key => $modifiedItem) {
            if (isset($originalItems[$key])) {
                $originalItem = $originalItems[$key];
                // Item quantity has changed
                if ($modifiedItem->quantity !== $originalItem->quantity) {
                    $domainObject = new RMAHistoryDomainObject(
                        id: 0,
                        rmaID: $eventData->originalDO->id,
                        eventName: RMAHistoryEvents::ITEM_MODIFIED_QUANTITY->value,
                        eventDataJson: (new RMAEventDataDomainObject(
                            shippingNotePosID: $modifiedItem->shippingNotePosID,
                            productID: $modifiedItem->productID,
                            dataBefore: ['quantity' => $originalItem->quantity],
                            dataAfter: ['quantity' => $modifiedItem->quantity]
                        ))->toJson()
                    );
                    $this->getRepository()->insert(
                        $domainObject
                    );
                }
                // Item reason has changed
                if ($modifiedItem->reasonID !== $originalItem->reasonID) {
                    $domainObject = new RMAHistoryDomainObject(
                        id: 0,
                        rmaID: $eventData->originalDO->id,
                        eventName: RMAHistoryEvents::ITEM_MODIFIED_REASON->value,
                        eventDataJson: (new RMAEventDataDomainObject(
                            shippingNotePosID: $modifiedItem->shippingNotePosID,
                            productID: $modifiedItem->productID,
                            dataBefore: ['reasonID' => $originalItem->reasonID],
                            dataAfter: ['reasonID' => $modifiedItem->reasonID]
                        ))->toJson()
                    );
                    $this->getRepository()->insert(
                        $domainObject
                    );
                }
                unset($originalItems[$key]);
            } else {
                // Item has been added
                $domainObject = new RMAHistoryDomainObject(
                    id: 0,
                    rmaID: $eventData->originalDO->id,
                    eventName: RMAHistoryEvents::ITEM_ADDED->value,
                    eventDataJson: (new RMAEventDataDomainObject(
                        shippingNotePosID: $modifiedItem->shippingNotePosID,
                        productID: $modifiedItem->productID,
                        dataBefore: ['quantity' => 0],
                        dataAfter: ['quantity' => $modifiedItem->quantity]
                    ))->toJson()
                );
                $this->getRepository()->insert(
                    $domainObject
                );
            }
        }
        foreach ($originalItems as $removedItem) {
            // Item has been removed
            $domainObject = new RMAHistoryDomainObject(
                id: 0,
                rmaID: $eventData->originalDO->id,
                eventName: RMAHistoryEvents::ITEM_REMOVED->value,
                eventDataJson: (new RMAEventDataDomainObject(
                    shippingNotePosID: $removedItem->shippingNotePosID,
                    productID: $removedItem->productID,
                    dataBefore: ['quantity' => $removedItem->quantity],
                    dataAfter: ['quantity' => 0]
                ))->toJson()
            );
            $this->getRepository()->insert(
                $domainObject
            );
        }
    }

    private function replacementOrderAssigned(RMAHistoryEventData $eventData): void
    {
        $domainObject = new RMAHistoryDomainObject(
            id: 0,
            rmaID: $eventData->originalDO->id,
            eventName: RMAHistoryEvents::REPLACEMENT_ORDER->value,
            eventDataJson: (new RMAEventDataDomainObject(
                shippingNotePosID: 0,
                productID: 0,
                dataBefore: ['replacementOrderID' => $eventData->originalDO->replacementOrderID],
                dataAfter: ['replacementOrderID' => $eventData->modifiedDO->replacementOrderID]
            ))->toJson()
        );
        $this->getRepository()->insert(
            $domainObject
        );
    }

    private function statusChanged(RMAHistoryEventData $eventData): void
    {
        $domainObject = new RMAHistoryDomainObject(
            id: 0,
            rmaID: $eventData->originalDO->id,
            eventName: RMAHistoryEvents::STATUS_CHANGED->value,
            eventDataJson: (new RMAEventDataDomainObject(
                shippingNotePosID: 0,
                productID: 0,
                dataBefore: ['status' => $eventData->originalDO->status],
                dataAfter: ['status' => $eventData->modifiedDO->status]
            ))->toJson()
        );
        $this->getRepository()->insert(
            $domainObject
        );
    }

    private function addressModified(RMAHistoryEventData $eventData): void
    {
        $originalAddressArray = [];
        $modifiedAddressArray = [];
        if ($eventData->originalDO->getReturnAddress() !== null) {
            $originalAddressArray = $eventData->originalDO->getReturnAddress()->toArray();
        }
        if ($eventData->modifiedDO->getReturnAddress() !== null) {
            $modifiedAddressArray = $eventData->modifiedDO->getReturnAddress()->toArray();
        }
        $differences = \array_diff(
            $originalAddressArray,
            $modifiedAddressArray
        );
        if (\array_key_exists('id', $differences)) {
            unset($differences['id']);
        }
        if (\array_key_exists('rmaID', $differences)) {
            unset($differences['rmaID']);
        }
        if (\count($differences) > 0) {
            $domainObject = new RMAHistoryDomainObject(
                id: 0,
                rmaID: $eventData->originalDO->id,
                eventName: RMAHistoryEvents::ADDRESS_MODIFIED->value,
                eventDataJson: (new RMAEventDataDomainObject(
                    shippingNotePosID: 0,
                    productID: 0,
                    dataBefore: ['returnAddress' => $originalAddressArray],
                    dataAfter: ['returnAddress' => $modifiedAddressArray]
                ))->toJson()
            );
            $this->getRepository()->insert(
                $domainObject
            );
        }
    }

    private function refundShipping(RMAHistoryEventData $eventData): void
    {
        $domainObject = new RMAHistoryDomainObject(
            id: 0,
            rmaID: $eventData->originalDO->id,
            eventName: RMAHistoryEvents::REFUND_SHIPPING->value,
            eventDataJson: (new RMAEventDataDomainObject(
                shippingNotePosID: 0,
                productID: 0,
                dataBefore: ['refundShipping' => $eventData->originalDO->refundShipping],
                dataAfter: ['refundShipping' => $eventData->modifiedDO->refundShipping]
            ))->toJson()
        );
        $this->getRepository()->insert(
            $domainObject
        );
    }

    private function voucherCredit(RMAHistoryEventData $eventData): void
    {
        $domainObject = new RMAHistoryDomainObject(
            id: 0,
            rmaID: $eventData->originalDO->id,
            eventName: RMAHistoryEvents::VOUCHER_CREDIT->value,
            eventDataJson: (new RMAEventDataDomainObject(
                shippingNotePosID: 0,
                productID: 0,
                dataBefore: ['voucherCredit' => $eventData->originalDO->voucherCredit],
                dataAfter: ['voucherCredit' => $eventData->modifiedDO->voucherCredit]
            ))->toJson()
        );
        $this->getRepository()->insert(
            $domainObject
        );
    }

    /**
     * @param RMADomainObject $rma
     * @return RMAHistoryDomainObject[]
     * @throws \JsonException
     * @since 5.3.0
     */
    public function getHistory(RMADomainObject $rma): array
    {
        $historyEvents = [];
        foreach (
            $this->getRepository()->getList(
                ['rmaID' => $rma->id]
            ) as $historyEvent
        ) {
            $eventDataJson  = Typifier::stringify($historyEvent->eventDataJson);
            $eventDataArray = \json_decode($eventDataJson, true, 512, JSON_THROW_ON_ERROR);

            $historyEvents[] = new RMAHistoryDomainObject(
                id: Typifier::intify($historyEvent->id),
                rmaID: Typifier::intify($historyEvent->rmaID),
                eventName: Typifier::stringify($historyEvent->eventName),
                eventDataJson: $eventDataJson,
                eventDataDomainObject: new RMAEventDataDomainObject(
                    shippingNotePosID: Typifier::intify($eventDataArray['shippingNotePosID']),
                    productID: Typifier::intify($eventDataArray['productID']),
                    dataBefore: Typifier::arrify($eventDataArray['dataBefore']),
                    dataAfter: Typifier::arrify($eventDataArray['dataAfter'])
                ),
                createDate: Typifier::stringify($historyEvent->createDate)
            );
        }

        return $historyEvents;
    }

    /**
     * @param RMAHistoryDomainObject $RMAHistoryDomainObject
     * @return object
     * @throws \Exception
     * @since 5.3.0
     */
    public function getLocalizedEventDataAsObject(RMAHistoryDomainObject $RMAHistoryDomainObject): object
    {
        $result                = new \stdClass();
        $result->eventName     = '';
        $result->localizedText = '';
        $result->createDate    = '';
        $lang                  = Shop::Lang();

        $eventDataDomainObject = $RMAHistoryDomainObject->getEventDataDomainObject();
        if ($eventDataDomainObject === null) {
            return $result;
        }
        $rmaService       = new RMAService();
        $rmaReasonService = new RMAReasonService();
        $firstArrayKey    = \array_key_first($eventDataDomainObject->dataBefore);
        $productName      = '';

        [$year, $month, $day] = \explode('-', \substr($RMAHistoryDomainObject->createDate, 0, 10));

        $result->dateObject        = new \stdClass();
        $result->dateObject->day   = $day;
        $result->dateObject->month = \substr(
            $lang->get(\strtolower(Date::getMonthName($month)), 'news'),
            0,
            3
        );
        $result->dateObject->year  = $year;

        if (
            $eventDataDomainObject->shippingNotePosID > 0
            && $eventDataDomainObject->productID > 0
        ) {
            $productName = $this->getRepository()->getProductNameFromDB(
                $eventDataDomainObject->productID,
                $eventDataDomainObject->shippingNotePosID
            )->name ?? '';
        }

        try {
            $this->getLocalizedEventText(
                $RMAHistoryDomainObject,
                $lang,
                $productName,
                $eventDataDomainObject,
                $firstArrayKey,
                $rmaReasonService,
                $rmaService,
                $result
            );
        } catch (\UnhandledMatchError $e) {
            Shop::Container()->getLogService()->error(
                'Unknown event name ' . $RMAHistoryDomainObject->eventName
                . ' in RMAHistoryService::getLocalizedEventDataAsObject.' . $e->getMessage()
            );
        }

        return $result;
    }

    /**
     * @param array<mixed> $addressArray
     * @return string
     * @todo The display of such information could be solved in more elegant way.
     */
    private function convertAddressToString(array $addressArray): string
    {
        $result = '';

        if (!empty($addressArray['companyName'])) {
            $result .= $addressArray['companyName'];
            if (isset($addressArray['companyAdditional']) && $addressArray['companyAdditional'] !== '') {
                $result .= ' ' . $addressArray['companyAdditional'];
            }
            $result .= '<br>';
        }

        if ($addressArray['salutation'] === 'm') {
            $result .= 'Herr ';
        } elseif ($addressArray['salutation'] === 'w') {
            $result .= 'Frau ';
        }
        if (!empty($addressArray['academicTitle'])) {
            $result .= $addressArray['academicTitle'] . ' ';
        }
        $result .= Typifier::stringify($addressArray['firstName']) . ' '
            . Typifier::stringify($addressArray['lastName']) . '<br>';
        if (!empty($addressArray['street'])) {
            $result .= $addressArray['street'];
            if (!empty($addressArray['houseNumber'])) {
                $result .= ', ' . $addressArray['houseNumber'];
            }
            if (!empty($addressArray['addressAdditional'])) {
                $result .= ' ' . $addressArray['addressAdditional'];
            }
            $result .= '<br>';
        }

        if (!empty($addressArray['postalCode'])) {
            $result .= $addressArray['postalCode'];
        }
        if (!empty($addressArray['city'])) {
            if (\strpos($result, ' ', -1) !== false) {
                $result = \substr($result, 0, -1);
            }
            $result .= ' ' . $addressArray['city'];
        }
        $result .= '<br>';

        if (!empty($addressArray['state'])) {
            $result .= $addressArray['state'] . ', ';
        }
        if (!empty($addressArray['countryISO'])) {
            $result .= $addressArray['countryISO'];
        }

        if (\strpos($result, ', ', -2) !== false) {
            $result = \substr($result, 0, -2);
        }
        $result .= '<br>';

        if (!empty($addressArray['phone'])) {
            $result .= $addressArray['phone'] . ', ';
        }
        if (!empty($addressArray['mobilePhone'])) {
            $result .= '<br>' . $addressArray['mobilePhone'] . ', ';
        }
        if (!empty($addressArray['fax'])) {
            $result .= '<br>' . $addressArray['fax'] . ', ';
        }
        if (!empty($addressArray['mail'])) {
            $result .= '<br>' . $addressArray['mail'];
        }
        if (\strpos($result, ', ', -2) !== false) {
            $result = \substr($result, 0, -2);
        }

        return $result;
    }

    /**
     * @throws \Exception
     */
    private function getLocalizedEventText(
        RMAHistoryDomainObject $RMAHistoryDomainObject,
        LanguageHelper $lang,
        string $productName,
        RMAEventDataDomainObject $eventDataDomainObject,
        int|string|null $firstArrayKey,
        RMAReasonService $rmaReasonService,
        RMAService $rmaService,
        \stdClass $result
    ): void {
        [$result->eventName, $result->localizedText] = match ($RMAHistoryDomainObject->eventName) {
            'itemModified'             => [
                $lang->get('rmaHistoryItemModifiedTitle', 'rma'),
                \sprintf(
                    $lang->get('rmaHistoryItemModifiedText', 'rma'),
                    $productName,
                    $eventDataDomainObject->dataBefore[$firstArrayKey]
                )
            ],
            'itemAdded'                => [
                $lang->get('rmaHistoryItemAddedTitle', 'rma'),
                \sprintf(
                    $lang->get('rmaHistoryItemAddedText', 'rma'),
                    $productName
                )
            ],
            'itemRemoved'              => [
                $lang->get('rmaHistoryItemRemovedTitle', 'rma'),
                \sprintf(
                    $lang->get('rmaHistoryItemRemovedText', 'rma'),
                    $productName
                )
            ],
            'itemModifiedReason'       => [
                $lang->get('rmaHistoryItemModifiedReasonTitle', 'rma'),
                \sprintf(
                    $lang->get('rmaHistoryItemModifiedReasonText', 'rma'),
                    $productName,
                    $rmaReasonService->getReason(
                        (int)$eventDataDomainObject->dataBefore[$firstArrayKey],
                        $lang->currentLanguageID
                    )->title,
                    $rmaReasonService->getReason(
                        (int)$eventDataDomainObject->dataAfter[$firstArrayKey],
                        $lang->currentLanguageID
                    )->title
                )
            ],
            'replacementOrderAssigned' => [
                $lang->get('rmaHistoryReplacementOrderAssignedTitle', 'rma'),
                \sprintf(
                    $lang->get('rmaHistoryReplacementOrderAssignedText', 'rma'),
                    $eventDataDomainObject->dataAfter[$firstArrayKey]
                )
            ],
            'statusChanged'            => [
                $lang->get('rmaHistoryStatusChangedTitle', 'rma'),
                \sprintf(
                    $lang->get('rmaHistoryStatusChangedText', 'rma'),
                    $rmaService->getStatusTextByID((int)$eventDataDomainObject->dataBefore[$firstArrayKey]),
                    $rmaService->getStatusTextByID((int)$eventDataDomainObject->dataAfter[$firstArrayKey])
                )
            ],
            'addressModified'          => [
                $lang->get('rmaHistoryAddressModifiedTitle', 'rma'),
                \sprintf(
                    $lang->get('rmaHistoryAddressModifiedText', 'rma'),
                    $this->convertAddressToString($eventDataDomainObject->dataBefore[$firstArrayKey])
                )
            ],
            'refundShipping'           => [
                $lang->get('rmaHistoryRefundShippingTitle', 'rma'),
                \sprintf(
                    $lang->get('rmaHistoryRefundShippingText', 'rma'),
                    (bool)$eventDataDomainObject->dataAfter[$firstArrayKey] === true
                        ? $lang->get('rmaHistoryRefundAccepted', 'rma')
                        : $lang->get('rmaHistoryRefundDenied', 'rma')
                )
            ],
            'voucherCredit'            => [
                $lang->get('rmaHistoryVoucherCreditTitle', 'rma'),
                \sprintf(
                    $lang->get('rmaHistoryVoucherCreditText', 'rma'),
                    (bool)$eventDataDomainObject->dataAfter[$firstArrayKey] === true
                        ? $lang->get('rmaHistoryRefundAccepted', 'rma')
                        : $lang->get('rmaHistoryRefundDenied', 'rma')
                )
            ],
        };
    }
}
