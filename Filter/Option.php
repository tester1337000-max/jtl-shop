<?php

declare(strict_types=1);

namespace JTL\Filter;

use JTL\Media\MultiSizeImage;

/**
 * Class Option
 *
 * @package JTL\Filter
 *
 * @property int      $kHersteller
 * @property int      $nAnzahlTagging
 * @property int      $kKategorie
 * @property int      $nVon
 * @property string   $cVonLocalized
 * @property int      $nBis
 * @property string   $cBisLocalized
 * @property int      $nAnzahlArtikel
 * @property int      $nStern
 * @property int      $kKey
 * @property string   $cSuche
 * @property int      $kSuchanfrage
 * @property Option[] $options
 */
class Option extends AbstractFilter
{
    use MultiSizeImage;

    private string $param = '';

    private ?string $url = null;

    /**
     * if set to true, ProductFilterURL::getURL() will not return a SEO URL
     */
    private bool $disableSeoURLs = false;

    private string $class = '';

    /**
     * @var array<mixed>
     */
    private array $data = [];

    public int $nAktiv = 0;

    /**
     * @var array<string, string>
     */
    public static array $mapping = [
        'cName'          => 'Name',
        'nAnzahl'        => 'Count',
        'nAnzahlArtikel' => 'Count',
        'cURL'           => 'URL',
        'Klasse'         => 'Class',
        'nSortNr'        => 'Sort',
        'kSuchanfrage'   => 'Value',
        'kTag'           => 'Value',
        'kKey'           => 'Value',
        'kKategorie'     => 'Value',
        'kMerkmal'       => 'Value',
        'nSterne'        => 'Value',
    ];

    public function __construct(?ProductFilter $productFilter = null)
    {
        parent::__construct($productFilter);
        $this->isInitialized = true;
        $this->options       = [];
    }

    private static function getMapping(string $value): ?string
    {
        return self::$mapping[$value] ?? null;
    }

    public function setIsActive(bool $active): self
    {
        $this->isActive = $active;
        $this->nAktiv   = (int)$active;

        return $this;
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function setClass(string $class): self
    {
        $this->class = $class;

        return $this;
    }

    public function getParam(): string
    {
        return $this->param;
    }

    public function setParam(string $param): self
    {
        $this->param = $param;

        return $this;
    }

    public function getURL(): ?string
    {
        return $this->url;
    }

    public function setURL(?string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getDisableSeoURLs(): bool
    {
        return $this->disableSeoURLs;
    }

    public function setDisableSeoURLs(bool $disableSeoURLs): self
    {
        $this->disableSeoURLs = $disableSeoURLs;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getOptions($mixed = null): array
    {
        return $this->options;
    }

    public function addOption(Option $option): self
    {
        $this->options[] = $option;

        return $this;
    }

    public function setData(string $name, mixed $value): self
    {
        $this->data[$name] = $value;

        return $this;
    }

    public function getData(string $name): mixed
    {
        return $this->data[$name] ?? null;
    }

    public function __set(string $name, mixed $value): void
    {
        if (($mapped = self::getMapping($name)) !== null) {
            $method = 'set' . $mapped;
            $this->$method($value);
            return;
        }
        $this->data[$name] = $value;
    }

    /**
     * @return mixed|null
     */
    public function __get(string $name)
    {
        if (($mapped = self::getMapping($name)) !== null) {
            $method = 'get' . $mapped;

            return $this->$method();
        }

        return $this->data[$name] ?? null;
    }

    public function __isset(string $name): bool
    {
        return \property_exists($this, $name) || self::getMapping($name) !== null || isset($this->data[$name]);
    }

    /**
     * @inheritdoc
     */
    public function setSeo(array $languages): self
    {
        return $this;
    }

    /**
     * @inheritdoc
     * @return array{}
     */
    public function getSQLJoin(): array
    {
        return [];
    }
}
