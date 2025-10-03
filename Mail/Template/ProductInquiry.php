<?php

declare(strict_types=1);

namespace JTL\Mail\Template;

use JTL\Catalog\Product\Artikel;
use JTL\Helpers\Text;
use JTL\Smarty\JTLSmarty;

/**
 * Class ProductInquiry
 * @package JTL\Mail\Template
 */
class ProductInquiry extends ProductTemplate
{
    protected ?string $id = \MAILTEMPLATE_PRODUKTANFRAGE;

    /**
     * @inheritdoc
     */
    public function preRender(JTLSmarty $smarty, mixed $data): void
    {
        parent::preRender($smarty, $data);
        if (!empty($this->config['artikeldetails']['produktfrage_absender_name'])) {
            $this->setFromName($this->config['artikeldetails']['produktfrage_absender_name']);
        }
        if (!empty($this->config['artikeldetails']['produktfrage_absender_mail'])) {
            $this->setFromMail($this->config['artikeldetails']['produktfrage_absender_mail']);
        }
        $checkedData = $this->sanitizeData($data);
        if ($checkedData === null) {
            return;
        }
        $escapedMessage = clone $checkedData->tnachricht;
        foreach (get_object_vars($escapedMessage) as $property => $value) {
            if (\is_string($value)) {
                $escapedMessage->$property = Text::escapeSmarty($value);
            }
        }
        $smarty->assign('Nachricht', $escapedMessage)
            ->assign('Artikel', $checkedData->tartikel);
    }

    /**
     * @return object{tnachricht: object, tartikel: Artikel}|null
     */
    protected function sanitizeData(mixed $data): object|null
    {
        if (\is_object($data) === false || isset($data->tnachricht, $data->tartikel) === false) {
            return null;
        }
        if (
            \property_exists($data->tartikel, 'cName')
            && \property_exists($data->tartikel, 'originalName')
        ) {
            $data->tartikel->cName = $data->tartikel->originalName;
        }

        return (object)[
            'tnachricht' => $data->tnachricht,
            'tartikel'   => $data->tartikel,
        ];
    }
}
