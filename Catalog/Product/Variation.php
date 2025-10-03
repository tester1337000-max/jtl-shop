<?php

declare(strict_types=1);

namespace JTL\Catalog\Product;

use stdClass;

/**
 * Class Variation
 * @package JTL\Catalog\Product
 */
class Variation
{
    /**
     * @var VariationValue[]
     */
    public array $Werte = [];

    public int $kEigenschaft;

    public int $kArtikel;

    public string $cWaehlbar;

    public string $cTyp;

    public int $nSort;

    public string $cName;

    public int $nLieferbareVariationswerte = 0;

    public function init(stdClass $data): void
    {
        $this->kEigenschaft = (int)$data->kEigenschaft;
        $this->kArtikel     = (int)$data->kArtikel;
        $this->cWaehlbar    = $data->cWaehlbar;
        $this->cTyp         = $data->cTyp;
        $this->nSort        = (int)$data->nSort;
        $this->cName        = empty($data->cName_teigenschaftsprache)
            ? $data->cName
            : $data->cName_teigenschaftsprache;
        if ($data->cTyp === 'FREIFELD' || $data->cTyp === 'PFLICHT-FREIFELD') {
            $this->nLieferbareVariationswerte = 1;
        }
    }
}
