<?php

declare(strict_types=1);

namespace JTL\Mail\Template;

use JTL\Catalog\Product\Artikel;
use JTL\Smarty\JTLSmarty;

/**
 * Class ProductAvailable
 * @package JTL\Mail\Template
 */
class ProductAvailable extends ProductTemplate
{
    protected ?string $id = \MAILTEMPLATE_PRODUKT_WIEDER_VERFUEGBAR;

    /**
     * @inheritdoc
     */
    public function preRender(JTLSmarty $smarty, mixed $data): void
    {
        parent::preRender($smarty, $data);
        $checkedData = $this->sanitizeData($data);
        if ($checkedData === null) {
            return;
        }
        $smarty->assign('Benachrichtigung', $checkedData->tverfuegbarkeitsbenachrichtigung)
            ->assign('Artikel', $checkedData->tartikel);
    }

    /**
     * @return object{tverfuegbarkeitsbenachrichtigung: array<mixed>, tartikel: Artikel}|null
     */
    protected function sanitizeData(mixed $data): object|null
    {
        if (\is_object($data) === false || isset($data->tverfuegbarkeitsbenachrichtigung, $data->tartikel) === false) {
            return null;
        }
        if (
            \property_exists($data->tartikel, 'cName')
            && \property_exists($data->tartikel, 'originalName')
        ) {
            $data->tartikel->cName = $data->tartikel->originalName;
        }

        return (object)[
            'tverfuegbarkeitsbenachrichtigung' => $data->tverfuegbarkeitsbenachrichtigung,
            'tartikel'                         => $data->tartikel,
        ];
    }
}
