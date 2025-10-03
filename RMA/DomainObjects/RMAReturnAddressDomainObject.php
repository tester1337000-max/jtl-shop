<?php

declare(strict_types=1);

namespace JTL\RMA\DomainObjects;

use JTL\DataObjects\AbstractDomainObject;

/**
 * Class RMAHistoryDomainObject
 * @package JTL\RMA
 * @description This is a data container holding the customer address for an RMA request
 * @comment The public properties represent the database table columns
 */
class RMAReturnAddressDomainObject extends AbstractDomainObject
{
    /**
     * @param int          $id
     * @param int          $rmaID
     * @param int          $customerID
     * @param string       $salutation
     * @param string       $firstName
     * @param string       $lastName
     * @param string|null  $academicTitle
     * @param string|null  $companyName
     * @param string|null  $companyAdditional
     * @param string       $street
     * @param string       $houseNumber
     * @param string|null  $addressAdditional
     * @param string       $postalCode
     * @param string       $city
     * @param string       $state
     * @param string       $countryISO
     * @param string|null  $phone
     * @param string|null  $mobilePhone
     * @param string|null  $fax
     * @param string|null  $mail
     * @param array<mixed> $modifiedKeys
     */
    public function __construct(
        public readonly int $id = 0,
        public readonly int $rmaID = 0,
        public readonly int $customerID = 0,
        public readonly string $salutation = '',
        public readonly string $firstName = '',
        public readonly string $lastName = '',
        public readonly ?string $academicTitle = null,
        public readonly ?string $companyName = null,
        public readonly ?string $companyAdditional = null,
        public readonly string $street = '',
        public readonly string $houseNumber = '',
        public readonly ?string $addressAdditional = null,
        public readonly string $postalCode = '',
        public readonly string $city = '',
        public readonly string $state = '',
        public readonly string $countryISO = '',
        public readonly ?string $phone = null,
        public readonly ?string $mobilePhone = null,
        public readonly ?string $fax = null,
        public readonly ?string $mail = null,
        array $modifiedKeys = []
    ) {
        parent::__construct($modifiedKeys);
    }
}
