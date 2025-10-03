<?php

declare(strict_types=1);

namespace JTL\Extensions\Upload;

use JTL\Nice;
use JTL\Shop;
use stdClass;

/**
 * Class Scheme
 * @package JTL\Extensions\Upload
 */
final class Scheme
{
    public ?int $kUploadSchema = null;

    public int $kCustomID = 0;

    public int $nTyp = 0;

    public ?string $cName = null;

    public ?string $cBeschreibung = null;

    public ?string $cDateiTyp = null;

    public int $nPflicht = 0;

    private bool $licenseOK;

    public function __construct(int $id = 0)
    {
        $this->licenseOK = self::checkLicense();
        if ($id > 0 && $this->licenseOK === true) {
            $this->loadFromDB($id);
        }
    }

    public static function checkLicense(): bool
    {
        return Nice::getInstance()->checkErweiterung(\SHOP_ERWEITERUNG_UPLOADS);
    }

    private function loadFromDB(int $id): void
    {
        $upload = Shop::Container()->getDB()->getSingleObject(
            'SELECT tuploadschema.kUploadSchema, tuploadschema.kCustomID, tuploadschema.nTyp, 
                tuploadschema.cDateiTyp, tuploadschema.nPflicht, tuploadschemasprache.cName, 
                tuploadschemasprache.cBeschreibung
                FROM tuploadschema
                LEFT JOIN tuploadschemasprache
                    ON tuploadschemasprache.kArtikelUpload = tuploadschema.kUploadSchema
                    AND tuploadschemasprache.kSprache = :lid
                WHERE kUploadSchema = :uid',
            [
                'lid' => Shop::getLanguageID(),
                'uid' => $id
            ]
        );
        if ($upload !== null && $upload->kUploadSchema > 0) {
            $this->kUploadSchema = (int)$upload->kUploadSchema;
            $this->kCustomID     = (int)$upload->kCustomID;
            $this->nTyp          = (int)$upload->nTyp;
            $this->nPflicht      = (int)$upload->nPflicht;
            $this->cName         = $upload->cName;
            $this->cBeschreibung = $upload->cBeschreibung;
            $this->cDateiTyp     = $upload->cDateiTyp;
        }
    }

    /**
     * @return stdClass[]
     */
    public function fetchAll(int $kCustomID, int $type): array
    {
        if (!$this->licenseOK) {
            return [];
        }
        $sql = $type === \UPLOAD_TYP_WARENKORBPOS
            ? ' AND kCustomID = ' . $kCustomID
            : '';

        return \array_map(
            static function (stdClass $item): stdClass {
                $item->kUploadSchema = (int)$item->kUploadSchema;
                $item->kCustomID     = (int)$item->kCustomID;
                $item->nTyp          = (int)$item->nTyp;
                $item->nPflicht      = (int)$item->nPflicht;

                return $item;
            },
            Shop::Container()->getDB()->getObjects(
                'SELECT tuploadschema.kUploadSchema, tuploadschema.kCustomID, tuploadschema.nTyp, 
                    tuploadschema.cDateiTyp, tuploadschema.nPflicht, 
                    IFNULL(tuploadschemasprache.cName,tuploadschema.cName ) cName,
                    IFNULL(tuploadschemasprache.cBeschreibung, tuploadschema.cBeschreibung) cBeschreibung
                    FROM tuploadschema
                    LEFT JOIN tuploadschemasprache
                        ON tuploadschemasprache.kArtikelUpload = tuploadschema.kUploadSchema
                        AND tuploadschemasprache.kSprache = :lid
                    WHERE nTyp = :tpe' . $sql,
                ['tpe' => $type, 'lid' => Shop::getLanguageID()]
            )
        );
    }
}
