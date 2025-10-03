<?php

declare(strict_types=1);

namespace JTL\Filter\States;

use JTL\Filter\AbstractFilter;
use JTL\Filter\FilterInterface;
use JTL\Filter\Join;
use JTL\Filter\ProductFilter;
use JTL\Helpers\SearchSpecial;
use JTL\MagicCompatibilityTrait;
use JTL\Router\RoutableTrait;
use JTL\Router\Router;
use JTL\Session\Frontend;
use JTL\Shop;

/**
 * Class BaseSearchSpecial
 * @package JTL\Filter\States
 */
class BaseSearchSpecial extends AbstractFilter
{
    use MagicCompatibilityTrait;
    use RoutableTrait;

    /**
     * @var array<string, string>
     */
    public static array $mapping = [
        'kKey' => 'ValueCompat'
    ];

    public function __construct(ProductFilter $productFilter)
    {
        parent::__construct($productFilter);
        $this->setRouteType(Router::TYPE_SEARCH_SPECIAL);
        $this->setIsCustom(false)
            ->setUrlParam(\QUERY_PARAM_SEARCH_SPECIAL)
            ->setUrlParamSEO(null);
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
        $helper = new SearchSpecial($this->getProductFilter()->getDB(), $this->getProductFilter()->getCache());
        $slugs  = $helper->getAllSlugs();
        foreach ($languages as $language) {
            $this->cSeo[$language->kSprache] = '';
        }
        $this->slugs = $slugs[$this->getValue()] ?? [];
        $this->createBySlug();
        foreach ($this->getURLPaths() as $langID => $slug) {
            $this->cSeo[$langID] = \ltrim($slug, '/');
        }
        switch ($this->getValue()) {
            case \SEARCHSPECIALS_BESTSELLER:
                $this->setName(Shop::Lang()->get('bestsellers'));
                break;
            case \SEARCHSPECIALS_SPECIALOFFERS:
                $this->setName(Shop::Lang()->get('specialOffers'));
                break;
            case \SEARCHSPECIALS_NEWPRODUCTS:
                $this->setName(Shop::Lang()->get('newProducts'));
                break;
            case \SEARCHSPECIALS_TOPOFFERS:
                $this->setName(Shop::Lang()->get('topOffers'));
                break;
            case \SEARCHSPECIALS_UPCOMINGPRODUCTS:
                $this->setName(Shop::Lang()->get('upcomingProducts'));
                break;
            case \SEARCHSPECIALS_TOPREVIEWS:
                $this->setName(Shop::Lang()->get('topReviews'));
                break;
            default:
                // invalid search special ID
                $this->fail();
                break;
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getRoute(array $additional): ?string
    {
        $this->createBySlug(null, $additional);

        return \ltrim($this->getURLPath($this->getLanguageID()) ?? '', '/');
    }

    /**
     * @inheritdoc
     */
    public function getPrimaryKeyRow(): string
    {
        return 'kKey';
    }

    /**
     * @inheritdoc
     */
    public function getSQLCondition(): string
    {
        switch ($this->value) {
            case \SEARCHSPECIALS_BESTSELLER:
                return 'tbestseller.isBestseller = 1';

            case \SEARCHSPECIALS_SPECIALOFFERS:
                $tasp = 'tartikelsonderpreis';
                $tsp  = 'tsonderpreise';
                if (!$this->getProductFilter()->hasPriceRangeFilter()) {
                    $tasp = 'tasp';
                    $tsp  = 'tsp';
                }

                return $tasp . ' .kArtikel = tartikel.kArtikel
                                    AND ' . $tasp . ".cAktiv = 'Y' AND " . $tasp . '.dStart <= NOW()
                                    AND (' . $tasp . '.nIstDatum = 0
                                        OR ' . $tasp . '.dEnde >= CURDATE()
                                        OR ' . $tasp . '.dEnde IS NULL)
                                    AND (' . $tasp . '.nIstAnzahl = 0
                                        OR ' . $tasp . '.nAnzahl <= tartikel.fLagerbestand)
                                    AND ' . $tsp . ' .kKundengruppe = ' . Frontend::getCustomerGroup()->getID();

            case \SEARCHSPECIALS_NEWPRODUCTS:
                $days = (($age = $this->getConfig('boxen')['box_neuimsortiment_alter_tage']) > 0)
                    ? (int)$age
                    : 30;

                return "tartikel.cNeu = 'Y' 
                    AND DATE_SUB(NOW(), INTERVAL " . $days . " DAY) < tartikel.dErstellt 
                    AND tartikel.cNeu = 'Y'";

            case \SEARCHSPECIALS_TOPOFFERS:
                return "tartikel.cTopArtikel = 'Y'";

            case \SEARCHSPECIALS_UPCOMINGPRODUCTS:
                return 'NOW() < tartikel.dErscheinungsdatum';

            case \SEARCHSPECIALS_TOPREVIEWS:
                if (!$this->getProductFilter()->hasRatingFilter()) {
                    $minStars = ($min = $this->getConfig('boxen')['boxen_topbewertet_minsterne']) > 0
                        ? (int)$min
                        : 4;

                    return ' ROUND(taex.fDurchschnittsBewertung) >= ' . $minStars;
                }
                break;

            default:
                break;
        }

        return '';
    }

    /**
     * @inheritdoc
     */
    public function getSQLJoin()
    {
        return match ($this->value) {
            \SEARCHSPECIALS_BESTSELLER    => (new Join())
                ->setType('JOIN')
                ->setTable('tbestseller')
                ->setOn('tbestseller.kArtikel = tartikel.kArtikel')
                ->setOrigin(__CLASS__),
            \SEARCHSPECIALS_SPECIALOFFERS => $this->getProductFilter()->hasPriceRangeFilter()
                ? []
                : (new Join())
                    ->setType('JOIN')
                    ->setTable('tartikelsonderpreis AS tasp')
                    ->setOn(
                        'tasp.kArtikel = tartikel.kArtikel JOIN tsonderpreise AS tsp 
                                    ON tsp.kArtikelSonderpreis = tasp.kArtikelSonderpreis'
                    )
                    ->setOrigin(__CLASS__),
            \SEARCHSPECIALS_TOPREVIEWS    => $this->getProductFilter()->hasRatingFilter()
                ? []
                : (new Join())
                    ->setType('JOIN')
                    ->setTable('tartikelext AS taex ')
                    ->setOn('taex.kArtikel = tartikel.kArtikel')
                    ->setOrigin(__CLASS__),
            default                       => [],
        };
    }
}
