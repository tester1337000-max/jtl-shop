<?php

declare(strict_types=1);

namespace JTL\dbeS;

use JTL\DB\DbInterface;
use JTL\Helpers\Request;
use JTL\Shop;

/**
 * Class Test
 * @package JTL\dbeS
 */
readonly class Test
{
    public function __construct(private DbInterface $db)
    {
    }

    public function execute(): string
    {
        if (Request::pInt('wawiversion') < \JTL_MIN_WAWI_VERSION) {
            \syncException(
                'Ihr JTL-Shop Version ' . \APPLICATION_VERSION
                . ' benötigt für den Datenabgleich mindestens JTL-Wawi Version ' . (\JTL_MIN_WAWI_VERSION / 100000.0)
                . ". \nEine aktuelle Version erhalten Sie unter: https://jtl-url.de/wawidownload",
                \FREIDEFINIERBARER_FEHLER
            );
        }
        foreach (
            [
                'kKunde'           => 'tkunde',
                'kBestellung'      => 'tbestellung',
                'kLieferadresse'   => 'tlieferadresse',
                'kZahlungseingang' => 'tzahlungseingang'
            ] as $idField => $table
        ) {
            if (($id = Request::postInt($idField)) > 0) {
                $state = $this->db->getSingleObject('SHOW TABLE STATUS LIKE :tbl', ['tbl' => $table]);
                if ($state !== null && (int)$state->Auto_increment < $id) {
                    $this->db->queryPrepared(
                        'ALTER TABLE ' . $table . ' AUTO_INCREMENT = :newId',
                        ['newId' => $id]
                    );
                }
            }
        }
        $version = Shop::getShopDatabaseVersion();

        return \sprintf('0;JTL4;%d%02d;', $version->getMajor(), $version->getMinor());
    }
}
