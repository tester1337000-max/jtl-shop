<?php

declare(strict_types=1);

namespace JTL\Catalog;

use InvalidArgumentException;
use JTL\Helpers\Tax;
use JTL\MagicCompatibilityTrait;
use JTL\Session\Frontend;
use JTL\Shop;
use stdClass;

/**
 * Class Currency
 * @package JTL\Catalog
 * @phpstan-consistent-constructor
 */
class Currency
{
    use MagicCompatibilityTrait;

    private int $id;

    private string $code;

    private string $name;

    private string $htmlEntity;

    private float $conversionFactor;

    private bool $isDefault = false;

    private bool $forcePlacementBeforeNumber = false;

    private string $decimalSeparator;

    private string $thousandsSeparator;

    private ?string $cURL = null;

    private ?string $cURLFull = null;

    /**
     * @var array<string, string>
     */
    protected static array $mapping = [
        'kWaehrung'            => 'ID',
        'cISO'                 => 'Code',
        'cName'                => 'Name',
        'cNameHTML'            => 'HtmlEntity',
        'fFaktor'              => 'ConversionFactor',
        'cStandard'            => 'IsDefault',
        'cVorBetrag'           => 'forcePlacementBeforeNumber',
        'cTrennzeichenCent'    => 'DecimalSeparator',
        'cTrennzeichenTausend' => 'ThousandsSeparator',
        'cURL'                 => 'URL',
        'cURLFull'             => 'URLFull'
    ];

    /**
     * @throws InvalidArgumentException
     */
    public function __construct(?int $id = null)
    {
        if ($id > 0) {
            $data = Shop::Container()->getDB()->select('twaehrung', 'kWaehrung', $id);
            if ($data === null) {
                throw new InvalidArgumentException('Cannot load currency with id ' . $id);
            }
            $data->kWaehrung = (int)$data->kWaehrung;
            $this->extract($data);
        }
    }

    public static function fromISO(string $iso): self
    {
        $data     = Shop::Container()->getDB()->select('twaehrung', 'cISO', $iso);
        $instance = new static();

        if ($data !== null) {
            $data->kWaehrung = (int)$data->kWaehrung;
            $instance->extract($data);
        } else {
            $instance->getDefault();
        }

        return $instance;
    }

    public function getID(): ?int
    {
        return $this->id;
    }

    public function setID(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getHtmlEntity(): ?string
    {
        return $this->htmlEntity;
    }

    public function setHtmlEntity(string $htmlEntity): self
    {
        $this->htmlEntity = $htmlEntity;

        return $this;
    }

    public function getConversionFactor(): ?float
    {
        return $this->conversionFactor;
    }

    public function setConversionFactor(float|int|string $conversionFactor): self
    {
        $this->conversionFactor = (float)$conversionFactor;

        return $this;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool|string $isDefault): self
    {
        if (\is_string($isDefault)) {
            $isDefault = $isDefault === 'Y';
        }
        $this->isDefault = $isDefault;

        return $this;
    }

    public function getForcePlacementBeforeNumber(): bool
    {
        return $this->forcePlacementBeforeNumber;
    }

    public function setForcePlacementBeforeNumber(bool|string $forcePlacementBeforeNumber): self
    {
        if (\is_string($forcePlacementBeforeNumber)) {
            $forcePlacementBeforeNumber = $forcePlacementBeforeNumber === 'Y';
        }
        $this->forcePlacementBeforeNumber = $forcePlacementBeforeNumber;

        return $this;
    }

    public function getDecimalSeparator(): ?string
    {
        return $this->decimalSeparator;
    }

    public function setDecimalSeparator(string $decimalSeparator): self
    {
        $this->decimalSeparator = $decimalSeparator;

        return $this;
    }

    public function getThousandsSeparator(): ?string
    {
        return $this->thousandsSeparator;
    }

    public function setThousandsSeparator(string $thousandsSeparator): self
    {
        $this->thousandsSeparator = $thousandsSeparator;

        return $this;
    }

    public function getURL(): ?string
    {
        return $this->cURL;
    }

    public function setURL(string $url): self
    {
        $this->cURL = $url;

        return $this;
    }

    public function getURLFull(): ?string
    {
        return $this->cURLFull;
    }

    public function setURLFull(string $url): self
    {
        $this->cURLFull = $url;

        return $this;
    }

    public function getDefault(): self
    {
        $data = Shop::Container()->getDB()->select('twaehrung', 'cStandard', 'Y');
        if ($data !== null) {
            $data->kWaehrung = (int)$data->kWaehrung;
            $this->extract($data);
        }

        return $this;
    }

    public function extract(stdClass $obs): self
    {
        foreach (\get_object_vars($obs) as $var => $value) {
            /** @var string|null $mapped */
            if (($mapped = self::getMapping($var)) !== null) {
                $method = 'set' . $mapped;
                $this->$method($value);
            }
        }

        return $this;
    }

    public static function getCurrencyConversion(
        float|int|string|null $priceNet,
        float|int|string|null $priceGross,
        string $class = '',
        bool $forceTax = true
    ): string {
        self::setCurrencies();

        $res        = '';
        $currencies = Frontend::getCurrencies();
        if (\count($currencies) > 0) {
            $priceNet   = (float)\str_replace(',', '.', (string)($priceNet ?? 0));
            $priceGross = (float)\str_replace(',', '.', (string)($priceGross ?? 0));
            $taxClass   = Shop::Container()->getDB()->select('tsteuerklasse', 'cStandard', 'Y');
            $taxClassID = $taxClass !== null ? (int)$taxClass->kSteuerklasse : 1;
            if ($priceNet > 0) {
                $priceGross = Tax::getGross($priceNet, Tax::getSalesTax($taxClassID));
            } elseif ($priceGross > 0) {
                $priceNet = Tax::getNet($priceGross, Tax::getSalesTax($taxClassID));
            }

            $res = '<span class="preisstring ' . $class . '">';
            foreach ($currencies as $i => $currency) {
                $priceLocalized = \number_format(
                    $priceNet * $currency->getConversionFactor(),
                    2,
                    $currency->getDecimalSeparator(),
                    $currency->getThousandsSeparator()
                );
                $grossLocalized = \number_format(
                    $priceGross * $currency->getConversionFactor(),
                    2,
                    $currency->getDecimalSeparator(),
                    $currency->getThousandsSeparator()
                );
                if ($currency->getForcePlacementBeforeNumber() === true) {
                    $priceLocalized = $currency->getHtmlEntity() . ' ' . $priceLocalized;
                    $grossLocalized = $currency->getHtmlEntity() . ' ' . $grossLocalized;
                } else {
                    $priceLocalized .= ' ' . $currency->getHtmlEntity();
                    $grossLocalized .= ' ' . $currency->getHtmlEntity();
                }
                // Wurde geändert weil der Preis nun als Betrag gesehen wird
                // und die Steuer direkt in der Versandart als eSteuer Flag eingestellt wird
                if ($i > 0) {
                    $res .= $forceTax
                        ? ('<br><strong>' . $grossLocalized . '</strong>' .
                            ' (<em>' . $priceLocalized . ' ' .
                            Shop::Lang()->get('net') . '</em>)')
                        : ('<br> ' . $grossLocalized);
                } else {
                    $res .= $forceTax
                        ? ('<strong>' . $grossLocalized . '</strong>' .
                            ' (<em>' . $priceLocalized . ' ' .
                            Shop::Lang()->get('net') . '</em>)')
                        : '<strong>' . $grossLocalized . '</strong>';
                }
            }
            $res .= '</span>';
        }

        return $res;
    }

    /**
     * @param float|int|numeric-string $price
     */
    public static function convertCurrency(
        float|int|string $price,
        ?string $iso = null,
        ?int $id = null,
        bool $round = true,
        int $precision = 2
    ): false|float {
        self::setCurrencies();

        foreach (Frontend::getCurrencies() as $currency) {
            if (($iso !== null && $currency->getCode() === $iso) || ($id !== null && $currency->getID() === $id)) {
                $newprice = $price * ($currency->getConversionFactor() ?? 1.0);

                return $round ? \round($newprice, $precision) : $newprice;
            }
        }

        return false;
    }

    /**
     * @return self[]
     */
    public static function loadAll(): array
    {
        $currencies = [];
        foreach (Shop::Container()->getDB()->selectAll('twaehrung', [], []) as $item) {
            $item->kWaehrung = (int)$item->kWaehrung;
            $currency        = new self();
            $currency->extract($item);
            $currencies[] = $currency;
        }

        return $currencies;
    }

    public static function setCurrencies(bool $update = false): void
    {
        if ($update || \count(Frontend::getCurrencies()) === 0) {
            $_SESSION['Waehrungen'] = self::loadAll();
        }
    }
}
