<?php

declare(strict_types=1);

namespace JTL\RMA\Services;

use Exception;
use JTL\Abstracts\AbstractService;
use JTL\Helpers\Typifier;
use JTL\RMA\DomainObjects\RMADomainObject;
use JTL\RMA\DomainObjects\RMAReturnAddressDomainObject;
use JTL\RMA\Repositories\RMAReturnAddressRepository;

/**
 * Class RMAReturnAddressService
 * @package JTL\RMA\Services
 */
class RMAReturnAddressService extends AbstractService
{
    public function __construct(
        public RMAReturnAddressRepository $RMAReturnAddressRepository = new RMAReturnAddressRepository()
    ) {
    }

    protected function getRepository(): RMAReturnAddressRepository
    {
        return $this->RMAReturnAddressRepository;
    }

    /**
     * @description Returns null if the RMA request has not been inserted into the DB yet
     * @throws Exception
     * @since 5.3.0
     */
    public function getReturnAddress(RMADomainObject $rma): ?RMAReturnAddressDomainObject
    {
        $result = $rma->getReturnAddress();
        if ($result === null && $rma->id > 0) {
            $returnAddress = $this->getRepository()->filter(['rmaID' => $rma->id]);
            if ($returnAddress !== null) {
                $result = new RMAReturnAddressDomainObject(
                    id: Typifier::intify($returnAddress->id ?? 0),
                    rmaID: Typifier::intify($returnAddress->rmaID ?? 0),
                    customerID: Typifier::intify($returnAddress->customerID ?? 0),
                    salutation: Typifier::stringify($returnAddress->salutation ?? ''),
                    firstName: Typifier::stringify($returnAddress->firstname ?? ''),
                    lastName: Typifier::stringify($returnAddress->lastname ?? ''),
                    academicTitle: Typifier::stringify($returnAddress->academicTitle ?? null, null),
                    companyName: Typifier::stringify($returnAddress->companyName ?? null, null),
                    companyAdditional: Typifier::stringify($returnAddress->companyAdditional ?? null, null),
                    street: Typifier::stringify($returnAddress->street ?? ''),
                    houseNumber: Typifier::stringify($returnAddress->houseNumber ?? ''),
                    addressAdditional: Typifier::stringify($returnAddress->addressAdditional ?? null, null),
                    postalCode: Typifier::stringify($returnAddress->postalCode ?? ''),
                    city: Typifier::stringify($returnAddress->city ?? ''),
                    state: Typifier::stringify($returnAddress->state ?? ''),
                    countryISO: Typifier::stringify($returnAddress->countryISO ?? ''),
                    phone: Typifier::stringify($returnAddress->phone ?? null, null),
                    mobilePhone: Typifier::stringify($returnAddress->mobilePhone ?? null, null),
                    fax: Typifier::stringify($returnAddress->fax ?? null, null),
                    mail: Typifier::stringify($returnAddress->mail ?? null, null),
                );
            }
        }

        return $result;
    }

    /**
     * @since 5.3.0
     */
    public function returnAddressFromDeliveryAddressTemplateID(
        int $deliveryAddressTemplateID
    ): RMAReturnAddressDomainObject {
        $deliveryAddressTemplate = $this->getRepository()->createFromDeliveryAddressTemplate(
            $deliveryAddressTemplateID
        );
        if ($deliveryAddressTemplate !== null) {
            return new RMAReturnAddressDomainObject(
                id: Typifier::intify($deliveryAddressTemplate['id']),
                rmaID: Typifier::intify($deliveryAddressTemplate['rmaID']),
                customerID: Typifier::intify($deliveryAddressTemplate['customerID']),
                salutation: Typifier::stringify($deliveryAddressTemplate['salutation']),
                firstName: Typifier::stringify($deliveryAddressTemplate['firstName']),
                lastName: Typifier::stringify($deliveryAddressTemplate['lastName']),
                academicTitle: Typifier::stringify($deliveryAddressTemplate['academicTitle'], null),
                companyName: Typifier::stringify($deliveryAddressTemplate['companyName'], null),
                companyAdditional: Typifier::stringify($deliveryAddressTemplate['companyAdditional'], null),
                street: Typifier::stringify($deliveryAddressTemplate['street']),
                houseNumber: Typifier::stringify($deliveryAddressTemplate['houseNumber']),
                addressAdditional: Typifier::stringify($deliveryAddressTemplate['addressAdditional'], null),
                postalCode: Typifier::stringify($deliveryAddressTemplate['postalCode']),
                city: Typifier::stringify($deliveryAddressTemplate['city']),
                state: Typifier::stringify($deliveryAddressTemplate['state']),
                countryISO: Typifier::stringify($deliveryAddressTemplate['countryISO']),
                phone: Typifier::stringify($deliveryAddressTemplate['phone'], null),
                mobilePhone: Typifier::stringify($deliveryAddressTemplate['mobilePhone'], null),
                fax: Typifier::stringify($deliveryAddressTemplate['fax'], null),
                mail: Typifier::stringify($deliveryAddressTemplate['mail'], null),
            );
        }

        return new RMAReturnAddressDomainObject();
    }

    public function insertReturnAddress(RMAReturnAddressDomainObject $rmaReturnAddress): int
    {
        return $this->RMAReturnAddressRepository->insert($rmaReturnAddress);
    }

    /**
     * @since 5.4.0
     */
    public function updateReturnAddress(RMAReturnAddressDomainObject $returnAddress): void
    {
        $this->RMAReturnAddressRepository->update($returnAddress);
    }
}
