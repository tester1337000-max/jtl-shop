<?php

declare(strict_types=1);

namespace JTL\RMA\DomainObjects\dbeS;

use JTL\Abstracts\AbstractDbeSObject;

/**
 * Class RMASyncObject
 *
 * @package JTL\RMA\DomainObjects\dbeS
 * @description Container for RMA data imported from WAWI via dbeS
 */
class RMASyncObject extends AbstractDbeSObject
{
    /**
     * @param RMAItemSyncObject[] $item
     */
    public function __construct(
        public array $item,
        public RMAAddressSyncObject $adresse,
        public int $kRMRetoure,
        public string $cRetoureNr,
        public string $cKommentarExtern,
        public int $nHerkunft,
        public int $cShopID,
        public bool $nKuponGutschriftGutschreiben,
        public int $kKundeShop,
        public bool $nVersandkostenErstatten,
        public string $dErstellt,
    ) {
        parent::__construct();
    }
}
