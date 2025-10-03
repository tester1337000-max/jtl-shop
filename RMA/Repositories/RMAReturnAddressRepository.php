<?php

declare(strict_types=1);

namespace JTL\RMA\Repositories;

use JTL\Abstracts\AbstractDBRepository;
use JTL\Checkout\DeliveryAddressTemplate;
use JTL\Staat;

/**
 * Class RMAReasonRepository
 * @package JTL\RMA
 * @description This is a layer between the RMA Return Address Service and the database.
 */
class RMAReturnAddressRepository extends AbstractDBRepository
{
    /**
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'return_address';
    }

    public function generateID(): int
    {
        return $this->db->getSingleInt('SELECT MAX(id) as id FROM ' . $this->getTableName(), 'id') + 1;
    }

    /**
     * @param int $deliveryAddressTemplateID
     * @return array<mixed>|null
     */
    public function createFromDeliveryAddressTemplate(int $deliveryAddressTemplateID): ?array
    {
        $deliveryTemplate = new DeliveryAddressTemplate($this->db, $deliveryAddressTemplateID);
        if ($deliveryTemplate->kKunde === null) {
            return null;
        }
        if (!empty($deliveryTemplate->cBundesland)) {
            $region = Staat::getRegionByIso($deliveryTemplate->cBundesland, $deliveryTemplate->cLand);
            if ($region !== null) {
                $deliveryTemplate->cBundesland = $region->cName;
            }
        }

        return [
            'id'                => $this->generateID(),
            'customerID'        => $deliveryTemplate->kKunde,
            'salutation'        => $deliveryTemplate->cAnrede,
            'firstName'         => $deliveryTemplate->cVorname,
            'lastName'          => $deliveryTemplate->cNachname,
            'academicTitle'     => $deliveryTemplate->cTitel,
            'companyName'       => $deliveryTemplate->cFirma,
            'companyAdditional' => $deliveryTemplate->cZusatz,
            'street'            => $deliveryTemplate->cStrasse,
            'houseNumber'       => $deliveryTemplate->cHausnummer,
            'addressAdditional' => $deliveryTemplate->cAdressZusatz,
            'postalCode'        => $deliveryTemplate->cPLZ,
            'city'              => $deliveryTemplate->cOrt,
            'state'             => $deliveryTemplate->cBundesland,
            'countryISO'        => $deliveryTemplate->cLand,
            'phone'             => $deliveryTemplate->cTel,
            'mobilePhone'       => $deliveryTemplate->cMobil,
            'fax'               => $deliveryTemplate->cFax,
            'mail'              => $deliveryTemplate->cMail
        ];
    }
}
