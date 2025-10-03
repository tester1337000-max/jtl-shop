<?php

declare(strict_types=1);

namespace JTL\Catalog;

use Exception;
use JTL\Catalog\Product\Artikel;
use JTL\Catalog\Product\Merkmal;
use JTL\Helpers\Request;
use JTL\Session\Frontend;
use JTL\Shop;
use stdClass;

use function Functional\map;
use function Functional\some;
use function Functional\sort;

/**
 * Class ComparisonList
 * @package JTL\Catalog
 */
class ComparisonList
{
    /**
     * @var Artikel[]|stdClass[]
     */
    public array $oArtikel_arr = [];

    /**
     * @param array<mixed> $variations
     */
    public function __construct(int $productID = 0, array $variations = [])
    {
        if ($productID > 0) {
            $this->addProduct($productID, $variations);
        } else {
            $this->loadFromSession();
        }
    }

    /**
     * load comparelist from session
     */
    private function loadFromSession(): void
    {
        $compareList = Frontend::get('Vergleichsliste');
        if ($compareList === null) {
            return;
        }
        $db             = Shop::Container()->getDB();
        $cache          = Shop::Container()->getCache();
        $defaultOptions = Artikel::getDefaultOptions();
        $baseURL        = Shop::Container()->getLinkService()->getStaticRoute('vergleichsliste.php');
        $customerGroup  = Frontend::getCustomerGroup();
        $currency       = Frontend::getCurrency();
        foreach ($compareList->oArtikel_arr as $key => $item) {
            $product = new Artikel($db, $customerGroup, $currency, $cache);
            $product->fuelleArtikel($item->kArtikel, $defaultOptions);
            if ($product->getID() === null) {
                unset($compareList->oArtikel_arr[$key]);
                continue;
            }
            $product->cURLDEL = $baseURL . '?' . \QUERY_PARAM_COMPARELIST_PRODUCT . '=' . $item->kArtikel;
            if (isset($item->oVariationen_arr) && \count($item->oVariationen_arr) > 0) {
                $product->Variationen = $item->oVariationen_arr;
            }
            $this->oArtikel_arr[] = $product;
        }
    }

    public function umgebungsWechsel(): self
    {
        $compareList = Frontend::get('Vergleichsliste');
        if ($compareList === null) {
            return $this;
        }
        $defaultOptions = Artikel::getDefaultOptions();
        $db             = Shop::Container()->getDB();
        $cache          = Shop::Container()->getCache();
        $customerGroup  = Frontend::getCustomerGroup();
        $currency       = Frontend::getCurrency();
        foreach ($compareList->oArtikel_arr as $i => $item) {
            $tmpProduct = new Artikel($db, $customerGroup, $currency, $cache);
            try {
                $tmpProduct->fuelleArtikel($item->kArtikel, $defaultOptions);
            } catch (Exception) {
                continue;
            }
            $product                       = new stdClass();
            $product->kArtikel             = $item->kArtikel;
            $product->cName                = $tmpProduct->cName ?? '';
            $product->cURLFull             = $tmpProduct->cURLFull ?? '';
            $product->image                = $tmpProduct->Bilder[0] ?? '';
            $compareList->oArtikel_arr[$i] = $product;
        }

        return $this;
    }

    /**
     * @param array<mixed> $variations
     */
    public function addProduct(int $productID, array $variations = []): self
    {
        $product           = new stdClass();
        $tmpProduct        = (new Artikel())->fuelleArtikel($productID, Artikel::getDefaultOptions());
        $product->kArtikel = $productID;
        $product->cName    = $tmpProduct->cName ?? '';
        $product->cURLFull = $tmpProduct->cURLFull ?? '';
        $product->image    = $tmpProduct->Bilder[0] ?? '';
        if (\count($variations) > 0) {
            $product->Variationen = $variations;
        }
        $this->oArtikel_arr[] = $product;

        Frontend::set('Vergleichsliste', $this);

        \executeHook(\HOOK_VERGLEICHSLISTE_CLASS_EINFUEGEN);

        return $this;
    }

    public function productExists(int $productID): bool
    {
        return some($this->oArtikel_arr, fn($e): bool => (int)$e->kArtikel === $productID);
    }

    /**
     * @return array<mixed>
     * @former baueMerkmalundVariation()
     * @since 5.0.0
     */
    public function buildAttributeAndVariation(): array
    {
        $characteristics = [];
        $variations      = [];
        foreach ($this->oArtikel_arr as $product) {
            if (\count($product->oMerkmale_arr ?? []) > 0) {
                // Falls das Merkmal Array nicht leer ist
                if (\count($characteristics) > 0) {
                    foreach ($product->oMerkmale_arr as $characteristic) {
                        if (!$this->containsCharacteristic($characteristics, $characteristic->getID())) {
                            $characteristics[] = $characteristic;
                        }
                    }
                } else {
                    $characteristics = $product->oMerkmale_arr;
                }
            }
            // Falls ein Artikel min. eine Variation enthält
            if (\count($product->Variationen) > 0) {
                if (\count($variations) > 0) {
                    foreach ($product->Variationen as $variation) {
                        if (!$this->containsVariation($variations, $variation->cName)) {
                            $variations[] = $variation;
                        }
                    }
                } else {
                    $variations = $product->Variationen;
                }
            }
        }
        \uasort($characteristics, static fn(Merkmal $a, Merkmal $b): int => $a->getSort() <=> $b->getSort());

        return [$characteristics, $variations];
    }

    /**
     * @param Merkmal[] $characteristics
     * @former istMerkmalEnthalten()
     * @since 5.2.0
     */
    public function containsCharacteristic(array $characteristics, int $id): bool
    {
        return some($characteristics, fn(Merkmal $e): bool => $e->getID() === $id);
    }

    /**
     * @param array<mixed> $variations
     * @former istVariationEnthalten()
     * @since 5.0.0
     */
    public function containsVariation(array $variations, string $name): bool
    {
        return some($variations, fn($e): bool => $e->cName === $name);
    }

    /**
     * @param array<mixed> $exclude
     * @param array<mixed> $config
     * @return string
     * @since 5.0.0
     * @former gibMaxPrioSpalteV()
     */
    public function getMaxPrioCol(array $exclude, array $config): string
    {
        $max  = 0;
        $col  = '';
        $conf = $config['vergleichsliste'];
        if ($conf['vergleichsliste_artikelnummer'] > $max && !\in_array('cArtNr', $exclude, true)) {
            $max = $conf['vergleichsliste_artikelnummer'];
            $col = 'cArtNr';
        }
        if ($conf['vergleichsliste_hersteller'] > $max && !\in_array('cHersteller', $exclude, true)) {
            $max = $conf['vergleichsliste_hersteller'];
            $col = 'cHersteller';
        }
        if ($conf['vergleichsliste_beschreibung'] > $max && !\in_array('cBeschreibung', $exclude, true)) {
            $max = $conf['vergleichsliste_beschreibung'];
            $col = 'cBeschreibung';
        }
        if ($conf['vergleichsliste_kurzbeschreibung'] > $max && !\in_array('cKurzBeschreibung', $exclude, true)) {
            $max = $conf['vergleichsliste_kurzbeschreibung'];
            $col = 'cKurzBeschreibung';
        }
        if ($conf['vergleichsliste_artikelgewicht'] > $max && !\in_array('fArtikelgewicht', $exclude, true)) {
            $max = $conf['vergleichsliste_artikelgewicht'];
            $col = 'fArtikelgewicht';
        }
        if ($conf['vergleichsliste_versandgewicht'] > $max && !\in_array('fGewicht', $exclude, true)) {
            $max = $conf['vergleichsliste_versandgewicht'];
            $col = 'fGewicht';
        }
        if ($conf['vergleichsliste_merkmale'] > $max && !\in_array('Merkmale', $exclude, true)) {
            $max = $conf['vergleichsliste_merkmale'];
            $col = 'Merkmale';
        }
        if ($conf['vergleichsliste_variationen'] > $max && !\in_array('Variationen', $exclude, true)) {
            $col = 'Variationen';
        }

        return $col;
    }

    /**
     * @return array<mixed>
     */
    public function getPrioRows(bool $keysOnly = false, bool $newStandard = true): array
    {
        $conf = Shop::getSettingSection(\CONF_VERGLEICHSLISTE);
        $rows = [
            'vergleichsliste_artikelnummer',
            'vergleichsliste_hersteller',
            'vergleichsliste_beschreibung',
            'vergleichsliste_kurzbeschreibung',
            'vergleichsliste_artikelgewicht',
            'vergleichsliste_versandgewicht',
            'vergleichsliste_merkmale',
            'vergleichsliste_variationen'
        ];
        if ($newStandard) {
            $rows[] = 'vergleichsliste_verfuegbarkeit';
            $rows[] = 'vergleichsliste_lieferzeit';
        }
        $prioRows  = [];
        $ignoreRow = 0;
        foreach ($rows as $row) {
            if ($conf[$row] > $ignoreRow) {
                $prioRows[$row] = $this->getMappedRowNames($row, $conf);
            }
        }
        $prioRows = sort($prioRows, fn(array $left, array $right): int => $right['priority'] <=> $left['priority']);

        return $keysOnly ? map($prioRows, fn(array $row) => $row['key']) : $prioRows;
    }

    /**
     * @param array<string, mixed> $conf
     * @return array<string, mixed>
     */
    private function getMappedRowNames(string $confName, array $conf): array
    {
        return match ($confName) {
            'vergleichsliste_artikelnummer'    => [
                'key'      => 'cArtNr',
                'name'     => Shop::Lang()->get('productNumber', 'comparelist'),
                'priority' => $conf[$confName]
            ],
            'vergleichsliste_hersteller'       => [
                'key'      => 'cHersteller',
                'name'     => Shop::Lang()->get('manufacturer', 'comparelist'),
                'priority' => $conf[$confName]
            ],
            'vergleichsliste_beschreibung'     => [
                'key'      => 'cBeschreibung',
                'name'     => Shop::Lang()->get('description', 'comparelist'),
                'priority' => $conf[$confName]
            ],
            'vergleichsliste_kurzbeschreibung' => [
                'key'      => 'cKurzBeschreibung',
                'name'     => Shop::Lang()->get('shortDescription', 'comparelist'),
                'priority' => $conf[$confName]
            ],
            'vergleichsliste_artikelgewicht'   => [
                'key'      => 'fArtikelgewicht',
                'name'     => Shop::Lang()->get('productWeight', 'comparelist'),
                'priority' => $conf[$confName]
            ],
            'vergleichsliste_versandgewicht'   => [
                'key'      => 'fGewicht',
                'name'     => Shop::Lang()->get('shippingWeight', 'comparelist'),
                'priority' => $conf[$confName]
            ],
            'vergleichsliste_merkmale'         => [
                'key'      => 'Merkmale',
                'name'     => Shop::Lang()->get('characteristics', 'comparelist'),
                'priority' => $conf[$confName]
            ],
            'vergleichsliste_variationen'      => [
                'key'      => 'Variationen',
                'name'     => Shop::Lang()->get('variations', 'comparelist'),
                'priority' => $conf[$confName]
            ],
            'vergleichsliste_verfuegbarkeit'   => [
                'key'      => 'verfuegbarkeit',
                'name'     => Shop::Lang()->get('availability', 'productOverview'),
                'priority' => $conf[$confName]
            ],
            'vergleichsliste_lieferzeit'       => [
                'key'      => 'lieferzeit',
                'name'     => Shop::Lang()->get('shippingTime'),
                'priority' => $conf[$confName]
            ],
            default                            => [
                'key'      => '',
                'name'     => '',
                'priority' => 0
            ],
        };
    }

    /**
     * Fügt nach jedem Preisvergleich eine Statistik in die Datenbank.
     * Es sind allerdings nur 3 Einträge pro IP und Tag möglich
     */
    public function save(): void
    {
        if (\count($this->oArtikel_arr) === 0) {
            return;
        }
        $db   = Shop::Container()->getDB();
        $data = $db->getSingleObject(
            'SELECT COUNT(kVergleichsliste) AS nVergleiche
                FROM tvergleichsliste
                WHERE cIP = :ip
                    AND dDate > DATE_SUB(NOW(),INTERVAL 1 DAY)',
            ['ip' => Request::getRealIP()]
        );
        if ($data !== null && $data->nVergleiche < 3) {
            $ins        = new stdClass();
            $ins->cIP   = Request::getRealIP();
            $ins->dDate = \date('Y-m-d H:i:s');
            $id         = $db->insert('tvergleichsliste', $ins);
            foreach ($this->oArtikel_arr as $product) {
                $item                   = new stdClass();
                $item->kVergleichsliste = $id;
                $item->kArtikel         = $product->kArtikel;
                $item->cArtikelName     = $product->cName;
                $db->insert('tvergleichslistepos', $item);
            }
        }
    }
}
