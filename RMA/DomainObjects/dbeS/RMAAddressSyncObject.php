<?php

declare(strict_types=1);

namespace JTL\RMA\DomainObjects\dbeS;

use JTL\Abstracts\AbstractDbeSObject;

/**
 * Class RMAAddressSyncObject
 *
 * @package JTL\RMA\DomainObjects\dbeS
 * @description Container for RMA data imported from WAWI via dbeS. Is a child from SyncDomainObject
 */
class RMAAddressSyncObject extends AbstractDbeSObject
{
    public function __construct(
        public string $cFirma,
        public string $cZusatz,
        public string $cAnrede,
        public string $cTitel,
        public string $cVorname,
        public string $cName,
        public string $cStrasse,
        public string $cAdressZusatz,
        public string $cPLZ,
        public string $cOrt,
        public string $cLand,
        public string $cTel,
        public string $cMobil,
        public string $cMail,
        public string $cFax,
        public string $cBundesland,
        public string $cISO,
    ) {
        parent::__construct();
    }
}
