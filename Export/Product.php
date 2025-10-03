<?php

declare(strict_types=1);

namespace JTL\Export;

use JTL\Catalog\Category\Kategorie;
use JTL\Catalog\Currency;
use JTL\Catalog\Product\Artikel;
use JTL\Helpers\Tax;
use JTL\Helpers\Text;
use JTL\Model\DataModelInterface;
use JTL\Session\Frontend;

/**
 * Class Product
 * @package JTL\Export
 */
class Product extends Artikel
{
    public ?string $cBeschreibungHTML = null;

    public ?string $cKurzBeschreibungHTML = null;

    public string|int|null|float $fUst = null;

    public ?string $Lieferbar = null;

    public ?int $Lieferbar_01 = null;

    public ?int $kKundengruppe = null;

    public ?string $campaignValue = null;

    public ?int $kWaehrung = null;

    public string|int|null|float $Versandkosten = null;

    public ?float $currencyConversionFactor = null;

    public ?int $kSprache = null;

    public ?Kategorie $Kategorie = null;

    public ?string $Kategoriepfad = null;

    public ?string $cDeeplink = null;

    public ?string $Artikelbild = null;

    /**
     * @param array<string, mixed> $config
     * @param Model|null           $model
     */
    public function augmentProduct(array $config, ?DataModelInterface $model = null): self
    {
        $this->cleanupWhitespace($config);
        if ($this->Preise === null) {
            return $this;
        }
        $this->fUst              = Tax::getSalesTax((int)$this->kSteuerklasse);
        $this->Preise->fVKBrutto = Tax::getGross(
            $this->Preise->fVKNetto * $this->currencyConversionFactor,
            $this->fUst
        );
        $this->Preise->fVKNetto  = \round($this->Preise->fVKNetto, 2);
        $favourableShipping      = $this->getShippingService()->getFavourableShippingForProduct(
            $this,
            $config['exportformate_lieferland'] ?? '',
            Frontend::getCustomer(),
            $this->getCustomerGroup(),
            Frontend::getCurrency(),
        );
        if ($favourableShipping !== null) {
            $price = $this->getCustomerGroup()->isMerchant()
                ? Currency::convertCurrency($favourableShipping->finalNetCost, null, $this->kWaehrung)
                : Currency::convertCurrency($favourableShipping->finalGrossCost, null, $this->kWaehrung);
            if ($price !== false) {
                $this->Versandkosten = $price;
            }
        }
        if ($model !== null && !empty($model->getCampaignParameter())) {
            $sep        = (\str_contains($this->cURL ?? '', '.php')) ? '&' : '?';
            $this->cURL .= $sep . $model->getCampaignParameter() . '=' . $model->getCampaignValue();
        }
        $this->Lieferbar    = $this->fLagerbestand <= 0 ? 'N' : 'Y';
        $this->Lieferbar_01 = $this->fLagerbestand <= 0 ? 0 : 1;

        return $this;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function cleanupWhitespace(array $config): void
    {
        $findTwo    = ["\r\n", "\r", "\n", "\x0B", "\x0"];
        $replaceTwo = [' ', ' ', ' ', ' ', ''];

        if (($config['exportformate_quot'] ?? 'N') !== 'N') {
            $findTwo[]    = '"';
            $replaceTwo[] = match ($config['exportformate_quot']) {
                's'     => ' ',
                'qq'    => '""',
                'bq'    => '\"',
                'sq'    => "'",
                default => '"',
            };
        }
        if (($config['exportformate_equot'] ?? 'N') !== 'N') {
            $findTwo[]    = "'";
            $replaceTwo[] = match ($config['exportformate_equot']) {
                's'     => ' ',
                'bs'    => "\\'",
                'q'     => '\"',
                'ss'    => "''",
                default => "'",
            };
        }
        if (($config['exportformate_semikolon'] ?? 'N') !== 'N') {
            $findTwo[]    = ';';
            $replaceTwo[] = match ($config['exportformate_equot']) {
                's'     => ' ',
                'c'     => ',',
                default => ';',
            };
        }

        $find                        = ['<br />', '<br>', '</'];
        $replace                     = [' ', ' ', ' </'];
        $this->cBeschreibungHTML     = Text::removeWhitespace(
            \str_replace(
                $findTwo,
                $replaceTwo,
                \str_replace('"', '&quot;', $this->cBeschreibung ?? '')
            )
        );
        $this->cKurzBeschreibungHTML = Text::removeWhitespace(
            \str_replace(
                $findTwo,
                $replaceTwo,
                \str_replace('"', '&quot;', $this->cKurzBeschreibung ?? '')
            )
        );
        $this->cName                 = Text::removeWhitespace(
            \str_replace(
                $findTwo,
                $replaceTwo,
                Text::unhtmlentities(\strip_tags(\str_replace($find, $replace, $this->cName ?? '')))
            )
        );
        $this->cBeschreibung         = Text::removeWhitespace(
            \str_replace(
                $findTwo,
                $replaceTwo,
                Text::unhtmlentities(\strip_tags(\str_replace($find, $replace, $this->cBeschreibung ?? '')))
            )
        );
        $this->cKurzBeschreibung     = Text::removeWhitespace(
            \str_replace(
                $findTwo,
                $replaceTwo,
                Text::unhtmlentities(\strip_tags(\str_replace($find, $replace, $this->cKurzBeschreibung ?? '')))
            )
        );
    }

    public function addCategoryData(bool $fallback = false): void
    {
        $productCategoryID = $this->gibKategorie($this->kKundengruppe);
        if ($fallback === true) {
            // since 4.05 the product class only stores category IDs in Artikel::oKategorie_arr
            // but old google base exports rely on category attributes that wouldn't be available anymore
            // so in that case we replace oKategorie_arr with an array of real Kategorie objects
            $categories = [];
            /** @var int $categoryID */
            foreach ($this->oKategorie_arr ?? [] as $categoryID) {
                $categories[] = new Kategorie(
                    $categoryID,
                    (int)$this->kSprache,
                    (int)$this->kKundengruppe,
                    false,
                    $this->getDB()
                );
            }
            $this->oKategorie_arr = $categories;
        }
        $this->Kategorie = new Kategorie(
            $productCategoryID,
            (int)$this->kSprache,
            (int)$this->kKundengruppe,
            false,
            $this->getDB()
        );
    }
}
