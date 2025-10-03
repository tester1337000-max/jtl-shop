<?php

declare(strict_types=1);

namespace JTL\Filter\States;

use JTL\Catalog\Category\Kategorie;
use JTL\Filter\AbstractFilter;
use JTL\Filter\FilterInterface;
use JTL\Filter\Join;
use JTL\Filter\ProductFilter;
use JTL\MagicCompatibilityTrait;

/**
 * Class BaseCategory
 * @package JTL\Filter\States
 */
class BaseCategory extends AbstractFilter
{
    use MagicCompatibilityTrait;

    /**
     * @var array<int, string>
     */
    protected array $slugs = [];

    /**
     * @var array<string, string>
     */
    public static array $mapping = [
        'kKategorie' => 'ValueCompat',
        'cName'      => 'Name'
    ];

    private bool $includeSubCategories = false;

    public function __construct(ProductFilter $productFilter)
    {
        parent::__construct($productFilter);
        $this->setIsCustom(false)
            ->setUrlParam(\QUERY_PARAM_CATEGORY)
            ->setUrlParamSEO(\SEP_KAT);
    }

    public function getIncludeSubCategories(): bool
    {
        return $this->includeSubCategories;
    }

    public function setIncludeSubCategories(bool $includeSubCategories): self
    {
        $this->includeSubCategories = $includeSubCategories;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setValue($value): FilterInterface
    {
        $this->value = (int)$value;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setSeo(array $languages): FilterInterface
    {
        if ($this->getValue() <= 0) {
            return $this;
        }
        $seoData           = [];
        $currentLanguageID = $this->getLanguageID();
        foreach ((array)$this->getValue() as $id) {
            $category = new Kategorie($id, $currentLanguageID);
            if ($category->getID() === 0) {
                $this->fail();
            }
            $seoData[] = $category;
        }
        foreach ($languages as $language) {
            $id              = $language->getId();
            $this->cSeo[$id] = '';
            foreach ($seoData as $seo) {
                $this->cSeo[$id]  = \ltrim($seo->getURLPath($id) ?? '', '/');
                $this->slugs[$id] = $seo->getSlug($id);
            }
        }
        if (\count($seoData) > 0) {
            $this->setName($seoData[0]->getName($currentLanguageID));
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getRoute(array $additional): ?string
    {
        if ($this->getValue() <= 0) {
            return null;
        }
        $currentLanguageID = $this->getLanguageID();
        foreach ((array)$this->getValue() as $id) {
            $category = new Kategorie($id, $currentLanguageID);
            $category->createBySlug($id, $additional);

            return \ltrim($category->getURLPath($currentLanguageID) ?? '', '/');
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function getPrimaryKeyRow(): string
    {
        return 'kKategorie';
    }

    /**
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'tkategorie';
    }

    /**
     * @inheritdoc
     */
    public function getSQLCondition(): string
    {
        /** @var int $id */
        $id = $this->getValue();

        return $this->getIncludeSubCategories() === true
            ? ' tkategorieartikel.kKategorie IN (
                        SELECT tchild.kKategorie FROM tkategorie AS tparent
                            JOIN tkategorie AS tchild
                                ON tchild.lft BETWEEN tparent.lft AND tparent.rght
                                WHERE tparent.kKategorie = ' . $id . ')'
            : 'tkategorieartikel.kKategorie = ' . $id;
    }

    /**
     * @inheritdoc
     */
    public function getSQLJoin(): Join
    {
        return (new Join())
            ->setType('JOIN')
            ->setOrigin(__CLASS__)
            ->setTable('tkategorieartikel')
            ->setOn('tartikel.kArtikel = tkategorieartikel.kArtikel');
    }
}
