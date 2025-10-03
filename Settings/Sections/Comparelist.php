<?php

declare(strict_types=1);

namespace JTL\Backend\Settings\Sections;

use JTL\DB\SqlObject;

/**
 * Class Comparelist
 * @package JTL\Backend\Settings\Sections
 */
class Comparelist extends Base
{
    /**
     * @inheritdoc
     */
    public function load(?SqlObject $sql = null): void
    {
        if ($sql === null) {
            $sql = new SqlObject();
            $sql->setWhere('ec.kEinstellungenSektion = :sid OR ec.kEinstellungenConf IN (469, 470)');
            $sql->addParam('sid', $this->id);
        }
        parent::load($sql);
    }
}
