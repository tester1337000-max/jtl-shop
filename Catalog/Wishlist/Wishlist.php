<?php

declare(strict_types=1);

namespace JTL\Catalog\Wishlist;

use Exception;
use Illuminate\Support\Collection;
use JTL\Campaign;
use JTL\Catalog\Product\Artikel;
use JTL\Catalog\Product\Preise;
use JTL\Customer\Customer;
use JTL\DB\DbInterface;
use JTL\Helpers\Date;
use JTL\Helpers\Product;
use JTL\Helpers\Request;
use JTL\Helpers\Text;
use JTL\Mail\Mail\Mail;
use JTL\Session\Frontend;
use JTL\Settings\Option\Globals;
use JTL\Settings\Settings;
use JTL\Shop;
use JTL\SimpleMail;
use stdClass;

use function Functional\select;

/**
 * Class Wishlist
 * @package JTL\Catalog\Wishlist
 */
class Wishlist
{
    /**
     * @var array<string, string>
     */
    protected static array $mapping = [
        'kWunschliste'        => 'ID',
        'kKunde'              => 'CustomerID',
        'nStandard'           => 'isDefault',
        'nOeffentlich'        => 'isPublic',
        'cName'               => 'Name',
        'cURLID'              => 'URL',
        'dErstellt'           => 'DateCreated',
        'dErstellt_DE'        => 'DateCreatedLocalized',
        'CWunschlistePos_arr' => 'Items',
        'oKunde'              => 'Customer'
    ];

    public int $kWunschliste = 0;

    public ?int $kKunde = 0;

    public int $nStandard = 0;

    public int $nOeffentlich = 0;

    public string $cName = '';

    public string $cURLID = '';

    public string $dErstellt = '';

    public string $dErstellt_DE = '';

    /**
     * @var WishlistItem[]
     */
    public array $CWunschlistePos_arr = [];

    public Customer $oKunde;

    public int $productCount = 0;

    private DbInterface $db;

    public function __wakeup(): void
    {
        if ($this->kKunde === null) {
            return;
        }
        $this->oKunde = new Customer($this->kKunde);
    }

    /**
     * @return string[]
     */
    public function __sleep(): array
    {
        return select(\array_keys(\get_object_vars($this)), fn(string $e): bool => $e !== 'oKunde');
    }

    public function __construct(int $id = 0, ?DbInterface $db = null)
    {
        $this->db = $db ?? Shop::Container()->getDB();
        if ($id > 0) {
            $this->ladeWunschliste($id);
        } else {
            $this->reset();
        }
    }

    /**
     * @since  5.0.0
     */
    public static function instanceByID(int $wishlistID, ?DbInterface $db = null): self
    {
        return new self($wishlistID, $db ?? Shop::Container()->getDB());
    }

    /**
     * @since 5.0.0
     */
    public static function instanceByURLID(string $urlID): self
    {
        $db       = Shop::Container()->getDB();
        $instance = new self(0, $db);
        $data     = $db->getSingleObject(
            "SELECT *, DATE_FORMAT(dErstellt, '%d.%m.%Y %H:%i') AS dErstellt_DE
                FROM twunschliste
                WHERE cURLID = :wlID
                    AND nOeffentlich = 1",
            ['wlID' => $urlID]
        );

        return $data ? $instance->setRecord($data) : $instance;
    }

    /**
     * @since 5.0.0
     */
    public static function instanceByCustomerID(int $customerID): self
    {
        $db       = Shop::Container()->getDB();
        $instance = new self(0, $db);
        $data     = $db->getSingleObject(
            "SELECT *, DATE_FORMAT(dErstellt, '%d.%m.%Y %H:%i') AS dErstellt_DE
                FROM twunschliste
                WHERE kKunde = :customerID
                    AND nStandard = 1",
            ['customerID' => $customerID]
        );

        return $data ? $instance->setRecord($data) : $instance->schreibeDB();
    }

    /**
     * @since  5.0.0
     */
    private function reset(): self
    {
        $this->kWunschliste        = 0;
        $this->kKunde              = Frontend::getCustomer()->getID();
        $this->nStandard           = 1;
        $this->nOeffentlich        = 0;
        $this->cName               = Shop::Lang()->get('wishlist');
        $this->dErstellt           = 'NOW()';
        $this->cURLID              = '';
        $this->CWunschlistePos_arr = [];
        $this->productCount        = 0;

        return $this;
    }

    /**
     * @since 5.0.0
     */
    private function validate(stdClass $wishlist): bool
    {
        $wishlistID = self::checkeParameters();
        $customerID = Frontend::getCustomer()->getID();

        return ($customerID > 0 && $customerID === (int)$wishlist->kKunde)
            || ($wishlistID > 0 && $wishlistID === (int)$wishlist->kWunschliste);
    }

    /**
     * fügt eine Position zur Wunschliste hinzu
     * @param array<mixed>             $attributes
     * @param float|int|numeric-string $qty
     */
    public function fuegeEin(int $productID, string $productName, array $attributes, float|int|string $qty): int
    {
        $exists = false;
        $index  = 0;
        foreach ($this->CWunschlistePos_arr as $i => $item) {
            if ($exists) {
                break;
            }
            if ($item->getProductID() !== $productID) {
                continue;
            }
            $index  = $i;
            $exists = true;
            if (\count($item->getProperties()) === 0) {
                continue;
            }
            foreach ($attributes as $attr) {
                if (!$item->istEigenschaftEnthalten($attr->kEigenschaft, $attr->kEigenschaftWert)) {
                    $exists = false;
                    break;
                }
            }
        }

        if ($exists) {
            $this->CWunschlistePos_arr[$index]->setQty($this->CWunschlistePos_arr[$index]->getQty() + $qty);
            $this->CWunschlistePos_arr[$index]->updateDB();
            $itemID = $this->CWunschlistePos_arr[$index]->getID();
        } else {
            $item = new WishlistItem(
                $productID,
                $productName,
                $qty,
                $this->kWunschliste
            );
            $item->setDateAdded(\date('Y-m-d H:i:s'));
            $item->schreibeDB();
            $itemID = $item->getID();
            $item->erstellePosEigenschaften($attributes);
            $product = new Artikel();
            try {
                $product->fuelleArtikel($productID, Artikel::getDefaultOptions());
                $item->setProduct($product);
                $this->CWunschlistePos_arr[] = $item;
            } catch (Exception) {
            }
        }
        $this->setProductCount(\count($this->CWunschlistePos_arr));

        \executeHook(\HOOK_WUNSCHLISTE_CLASS_FUEGEEIN);

        return $itemID;
    }

    public function entfernePos(int $itemID): self
    {
        $customer = $this->db->getSingleObject(
            'SELECT twunschliste.kKunde
                FROM twunschliste
                JOIN twunschlistepos 
                    ON twunschliste.kWunschliste = twunschlistepos.kWunschliste
                WHERE twunschlistepos.kWunschlistePos = :wliid',
            ['wliid' => $itemID]
        );
        // Prüfen ob der eingeloggte Kunde auch der Besitzer der zu löschenden WunschlistenPos ist
        if ($customer !== null && (int)$customer->kKunde === Frontend::getCustomer()->getID()) {
            // Alle Eigenschaften löschen
            $this->db->delete('twunschlisteposeigenschaft', 'kWunschlistePos', $itemID);
            // Die Posiotion mit ID $kWunschlistePos löschen
            $this->db->delete('twunschlistepos', 'kWunschlistePos', $itemID);
            // Wunschliste Position aus der Session löschen
            foreach ($this->CWunschlistePos_arr as $i => $wlPosition) {
                if ($wlPosition->getID() === $itemID) {
                    unset($this->CWunschlistePos_arr[$i]);
                }
            }
            // Positionen Array in der Wunschliste neu nummerieren
            $this->CWunschlistePos_arr = \array_merge($this->CWunschlistePos_arr);
        }
        $this->setProductCount(\count($this->CWunschlistePos_arr));
        self::updateInSesssion($this->kWunschliste);

        return $this;
    }

    public function entferneAllePos(): int
    {
        $this->CWunschlistePos_arr = [];
        $this->setProductCount(0);
        self::updateInSesssion($this->kWunschliste);

        return $this->db->getAffectedRows(
            'DELETE twunschlistepos, twunschlisteposeigenschaft 
                FROM twunschlistepos
                LEFT JOIN twunschlisteposeigenschaft 
                    ON twunschlisteposeigenschaft.kWunschlistePos = twunschlistepos.kWunschlistePos
                WHERE twunschlistepos.kWunschliste = :wlID',
            ['wlID' => $this->getID()]
        );
    }

    /**
     * Falls die Einstellung global_wunschliste_artikel_loeschen_nach_kauf auf Y (Ja) steht und
     * Artikel vom aktuellen Wunschzettel gekauft wurden, sollen diese vom Wunschzettel geloescht werden
     *
     * @param int          $wishlistID
     * @param array<mixed> $items
     * @return false|int
     */
    public static function pruefeArtikelnachBestellungLoeschen(int $wishlistID, array $items): false|int
    {
        if (
            $wishlistID < 1
            || Settings::boolValue(Globals::WISHLIST_DELETE_PURCHASED) === false
        ) {
            return false;
        }
        $count    = 0;
        $wishlist = new self($wishlistID);
        if (!($wishlist->kWunschliste > 0 && \count($wishlist->CWunschlistePos_arr) > 0 && \count($items) > 0)) {
            return false;
        }
        foreach ($wishlist->CWunschlistePos_arr as $item) {
            foreach ($items as $product) {
                if ($item->getProductID() !== (int)$product->kArtikel) {
                    continue;
                }
                // mehrfache Variationen beachten
                if (!empty($product->WarenkorbPosEigenschaftArr) && !empty($item->getProperties())) {
                    $matchesFound = 0;
                    $index        = 0;
                    foreach ($item->getProperties() as $wpAttr) {
                        if ($index === $matchesFound) {
                            foreach ($product->WarenkorbPosEigenschaftArr as $attr) {
                                if (
                                    (int)$wpAttr->getPropertyValueID() !== 0
                                    && $wpAttr->getPropertyValueID() === $attr->kEigenschaftWert
                                ) {
                                    ++$matchesFound;
                                    break;
                                }
                                if (
                                    $attr->kEigenschaftWert === 0
                                    && !empty($attr->cFreifeldWert)
                                    && !empty($wpAttr->getFreeTextValue())
                                    && $wpAttr->getPropertyValueID() === 0
                                    && $wpAttr->getFreeTextValue() === $attr->cFreifeldWert
                                ) {
                                    ++$matchesFound;
                                    break;
                                }
                            }
                        }
                        ++$index;
                    }
                    if ($matchesFound === \count($product->WarenkorbPosEigenschaftArr)) {
                        $wishlist->entfernePos($item->getID());
                    }
                } else {
                    $wishlist->entfernePos($item->getID());
                }
                ++$count;
            }
        }

        return $count;
    }

    /**
     * @return array<int, WishlistItem>
     */
    public function sucheInWunschliste(string $query): array
    {
        if (empty($query)) {
            return [];
        }
        $customerGroup = Frontend::getCustomerGroup();
        $currency      = Frontend::getCurrency();
        $options       = Artikel::getDefaultOptions();
        $searchResults = [];
        $data          = $this->db->getObjects(
            "SELECT twunschlistepos.*, date_format(twunschlistepos.dHinzugefuegt, '%d.%m.%Y %H:%i') AS dHinzugefuegt_de
                FROM twunschliste
                JOIN twunschlistepos 
                    ON twunschlistepos.kWunschliste = twunschliste.kWunschliste
                    AND (twunschlistepos.cArtikelName LIKE :search
                    OR twunschlistepos.cKommentar LIKE :search)
                WHERE twunschliste.kWunschliste = :wlID",
            [
                'search' => '%' . $query . '%',
                'wlID'   => $this->getID()
            ]
        );
        foreach ($data as $i => $result) {
            $result->kWunschliste    = (int)$result->kWunschliste;
            $result->kWunschlistePos = (int)$result->kWunschlistePos;
            $result->kArtikel        = (int)$result->kArtikel;

            $item = new WishlistItem(
                $result->kArtikel,
                $result->cArtikelName,
                $result->fAnzahl,
                $result->kWunschliste
            );

            $item->setID($result->kWunschlistePos);
            $item->setComment($result->cKommentar);
            $item->setDateAdded($result->dHinzugefuegt);
            $item->setDateAddedLocalized($result->dHinzugefuegt_de);

            $wlPositionAttributes = $this->db->getObjects(
                'SELECT twunschlisteposeigenschaft.*, teigenschaftsprache.cName
                    FROM twunschlisteposeigenschaft
                    JOIN teigenschaftsprache 
                        ON teigenschaftsprache.kEigenschaft = twunschlisteposeigenschaft.kEigenschaft
                    WHERE twunschlisteposeigenschaft.kWunschlistePos = :wlID
                    GROUP BY twunschlisteposeigenschaft.kWunschlistePosEigenschaft',
                ['wlID' => $result->kWunschlistePos]
            );
            foreach ($wlPositionAttributes as $wlPositionAttribute) {
                if ($wlPositionAttribute->cFreifeldWert !== '') {
                    $wlPositionAttribute->cEigenschaftName     = $wlPositionAttribute->cName;
                    $wlPositionAttribute->cEigenschaftWertName = $wlPositionAttribute->cFreifeldWert;
                }
                $wlAttribute = new WishlistItemProperty(
                    (int)$wlPositionAttribute->kEigenschaft,
                    (int)$wlPositionAttribute->kEigenschaftWert,
                    $wlPositionAttribute->cFreifeldWert,
                    $wlPositionAttribute->cEigenschaftName,
                    $wlPositionAttribute->cEigenschaftWertName,
                    (int)$wlPositionAttribute->kWunschlistePos
                );

                $wlAttribute->setID((int)$wlPositionAttribute->kWunschlistePosEigenschaft);

                $item->addProperty($wlAttribute);
            }

            $product = new Artikel($this->db, $customerGroup, $currency);
            try {
                $product->fuelleArtikel($result->kArtikel, $options);
            } catch (Exception) {
                continue;
            }
            $item->setProduct($product);
            $item->setProductName($product->cName ?? '');
            if ($product->Preise === null) {
                continue;
            }
            if ($customerGroup->isMerchant()) {
                $price = (int)$item->getQty() * $product->Preise->fVKNetto;
            } else {
                $price = (int)$item->getQty()
                    * ($product->Preise->fVKNetto * (100 + $_SESSION['Steuersatz'][$product->kSteuerklasse]) / 100);
            }

            $item->setPrice(Preise::getLocalizedPriceString($price, $currency));
            $searchResults[$i] = $item;
        }

        return $searchResults;
    }

    /**
     * @since 5.0.0
     */
    public function filterPositions(string $query): self
    {
        $query = (string)Text::filterXSS($query);
        if ($query !== '') {
            $this->CWunschlistePos_arr = $this->sucheInWunschliste($query);
        }

        return $this;
    }

    public function schreibeDB(): self
    {
        $ins               = new stdClass();
        $ins->kKunde       = $this->kKunde;
        $ins->cName        = $this->cName;
        $ins->nStandard    = $this->nStandard;
        $ins->nOeffentlich = $this->nOeffentlich;
        $ins->dErstellt    = $this->dErstellt;
        $ins->cURLID       = $this->cURLID;

        $this->kWunschliste = $this->db->insert('twunschliste', $ins);

        return $this;
    }

    public function ladeWunschliste(int $id = 0): self
    {
        if ($id <= 0) {
            $id = $this->kWunschliste;
        }
        if ($id <= 0) {
            return $this->reset();
        }
        $data = $this->db->getSingleObject(
            "SELECT *, DATE_FORMAT(dErstellt, '%d.%m.%Y %H:%i') AS dErstellt_DE
                FROM twunschliste
                WHERE kWunschliste = :wlID",
            ['wlID' => $id]
        );
        if (!$data || !$this->validate($data)) {
            return $this->reset();
        }

        return $this->setRecord($data);
    }

    public function ueberpruefePositionen(): string
    {
        $names    = [];
        $notice   = '';
        $cgroupID = Frontend::getCustomerGroup()->getID();
        foreach ($this->CWunschlistePos_arr as $wlPosition) {
            if ($wlPosition->getProductID() <= 0) {
                continue;
            }
            $exists = $this->db->getSingleObject(
                'SELECT kArtikel, kEigenschaftKombi
                    FROM tartikel
                    WHERE kArtikel = :pid',
                ['pid' => $wlPosition->getProductID()]
            );
            if (
                $exists !== null
                && (int)$exists->kArtikel > 0
                && Product::checkProductVisibility($wlPosition->getProductID(), $cgroupID) === true
            ) {
                if (\count($wlPosition->getProperties()) > 0) {
                    if (Product::isVariChild($wlPosition->getProductID())) {
                        foreach ($wlPosition->getProperties() as $wlAttribute) {
                            $attrValExists = $this->db->select(
                                'teigenschaftkombiwert',
                                'kEigenschaftKombi',
                                (int)$exists->kEigenschaftKombi,
                                'kEigenschaftWert',
                                $wlAttribute->getPropertyValueID(),
                                'kEigenschaft',
                                $wlAttribute->getPropertyID(),
                                false,
                                'kEigenschaftKombi'
                            );
                            if ($attrValExists === null || empty($attrValExists->kEigenschaftKombi)) {
                                $names[] = $wlPosition->getProductName();
                                $this->delWunschlistePosSess($wlPosition->getProductID());
                                break;
                            }
                        }
                    } else {
                        $attributes = $this->db->selectAll(
                            'teigenschaft',
                            'kArtikel',
                            $wlPosition->getProductID(),
                            'kEigenschaft, cName, cTyp'
                        );
                        if (\count($attributes) > 0) {
                            foreach ($wlPosition->getProperties() as $wlAttribute) {
                                $attrValExists = null;
                                if (!empty($wlAttribute->getPropertyID())) {
                                    $attrValExists = $this->db->select(
                                        'teigenschaftwert',
                                        'kEigenschaftWert',
                                        (int)$wlAttribute->getPropertyValueID(),
                                        'kEigenschaft',
                                        $wlAttribute->getPropertyID()
                                    );
                                    if ($attrValExists === null) {
                                        $attrValExists = $this->db->select(
                                            'twunschlisteposeigenschaft',
                                            'kEigenschaft',
                                            $wlAttribute->getPropertyID()
                                        );
                                    }
                                }
                                if ($attrValExists === null) {
                                    $names[] = $wlPosition->getProductName();
                                    $this->delWunschlistePosSess($wlPosition->getProductID());
                                    break;
                                }
                            }
                        } else {
                            $attributes = $this->db->selectAll(
                                'teigenschaft',
                                'kArtikel',
                                $wlPosition->getProductID(),
                                'kEigenschaft, cName, cTyp'
                            );
                            if (\count($attributes) > 0) {
                                foreach ($wlPosition->getProperties() as $wlAttribute) {
                                    $attrValExists = null;
                                    if (!empty($wlAttribute->getPropertyID())) {
                                        $attrValExists = $this->db->select(
                                            'teigenschaftwert',
                                            'kEigenschaftWert',
                                            (int)$wlAttribute->getPropertyValueID(),
                                            'kEigenschaft',
                                            $wlAttribute->getPropertyID()
                                        );
                                        if ($attrValExists === null) {
                                            $attrValExists = $this->db->select(
                                                'twunschlisteposeigenschaft',
                                                'kEigenschaft',
                                                $wlAttribute->getPropertyID()
                                            );
                                        }
                                    }
                                    if ($attrValExists === null) {
                                        $names[] = $wlPosition->cArtikelName;
                                        $this->delWunschlistePosSess($wlPosition->getProductID());
                                        break;
                                    }
                                }
                            } else {
                                $this->delWunschlistePosSess($wlPosition->getProductID());
                            }
                        }
                    }
                }
            } else {
                $names[] = $wlPosition->getProductName();
                $this->delWunschlistePosSess($wlPosition->getProductID());
            }
        }
        if (!empty($names)) {
            $notice = Shop::Lang()->get('noProductWishlist', 'messages') . \implode(', ', $names);
            Shop::Container()->getAlertService()->addNotice($notice, 'wlNote');
        }
        return $notice;
    }

    public function delWunschlistePosSess(int $productID): bool
    {
        if (!$productID) {
            return false;
        }
        $wishlist = Frontend::getWishList();
        foreach ($wishlist->getItems() as $i => $item) {
            if ($productID !== $item->getProductID()) {
                continue;
            }
            $wishlist->deleteItemAtIndex($i);
            $this->db->delete(
                'twunschlistepos',
                'kWunschlistePos',
                $item->getID()
            );
            $this->db->delete(
                'twunschlisteposeigenschaft',
                'kWunschlistePos',
                $item->getID()
            );
            break;
        }

        return true;
    }

    public function deleteItemAtIndex(int $idx): void
    {
        unset($this->CWunschlistePos_arr[$idx]);
        $this->CWunschlistePos_arr = \array_merge($this->CWunschlistePos_arr);
    }

    public function umgebungsWechsel(): self
    {
        if (\count(Frontend::getWishList()->getItems()) === 0) {
            return $this;
        }
        $defaultOptions = Artikel::getDefaultOptions();
        $customerGroup  = Frontend::getCustomerGroup();
        $currency       = Frontend::getCurrency();
        foreach (Frontend::getWishList()->getItems() as $item) {
            $product = new Artikel($this->db, $customerGroup, $currency);
            try {
                $product->fuelleArtikel($item->getProductID(), $defaultOptions);
            } catch (Exception) {
                continue;
            }
            $item->setProduct($product);
            $item->setProductName($product->cName ?? '');
        }

        return $this;
    }

    /**
     * Überprüft Parameter und gibt falls erfolgreich kWunschliste zurück, ansonten 0
     *
     * @former checkeWunschlisteParameter()
     * @since  5.0.0
     */
    public static function checkeParameters(): int
    {
        $urlID = (string)Text::filterXSS(Request::verifyGPDataString('wlid'));
        if ($urlID === '') {
            return 0;
        }
        $db       = Shop::Container()->getDB();
        $campaign = new Campaign(\KAMPAGNE_INTERN_OEFFENTL_WUNSCHZETTEL, $db);
        $id       = $campaign->kKampagne > 0
            ? ($urlID . '&' . $campaign->cParameter . '=' . $campaign->cWert)
            : $urlID;
        $keys     = ['nOeffentlich', 'cURLID'];
        $values   = [1, $id];
        $wishList = $db->select('twunschliste', $keys, $values, null, null, null, null, false, 'kWunschliste');
        if ($wishList !== null && $wishList->kWunschliste > 0) {
            return (int)$wishList->kWunschliste;
        }

        return 0;
    }

    /**
     * @since  5.0.0
     */
    public function isSelfControlled(): bool
    {
        return $this->kKunde > 0 && $this->kKunde === Frontend::getCustomer()->getID();
    }

    /**
     * Holt für einen Kunden die aktive Wunschliste (falls vorhanden) aus der DB und fügt diese in die Session
     */
    public static function persistInSession(): void
    {
        if (Frontend::getCustomer()->getID() <= 0) {
            return;
        }
        $data = Shop::Container()->getDB()->select(
            'twunschliste',
            ['kKunde', 'nStandard'],
            [Frontend::getCustomer()->getID(), 1]
        );
        if ($data !== null && isset($data->kWunschliste)) {
            $_SESSION['Wunschliste'] = new self((int)$data->kWunschliste);
            $_SESSION['Wunschliste']->ueberpruefePositionen();
        }
    }

    public static function delete(int $id, bool $force = false): string
    {
        $msg = '';
        if ($id === 0) {
            return $msg;
        }
        $db       = Shop::Container()->getDB();
        $data     = $db->select('twunschliste', 'kWunschliste', $id);
        $customer = Frontend::getCustomer();
        if ($data !== null && isset($data->kKunde) && ((int)$data->kKunde === $customer->getID() || $force)) {
            $items = $db->selectAll(
                'twunschlistepos',
                'kWunschliste',
                $id,
                'kWunschlistePos'
            );
            foreach ($items as $item) {
                $db->delete(
                    'twunschlisteposeigenschaft',
                    'kWunschlistePos',
                    (int)$item->kWunschlistePos
                );
            }
            $db->delete('twunschlistepos', 'kWunschliste', $id);
            $db->delete('twunschliste', 'kWunschliste', $id);
            if (!$force && Frontend::getWishList()->kWunschliste === $id) {
                unset($_SESSION['Wunschliste']);
            }
            // Wenn die gelöschte Wunschliste nStandard = 1 war => neue setzen
            if ((int)$data->nStandard === 1) {
                // Neue Wunschliste holen (falls vorhanden) und nStandard=1 neu setzen
                $data = $db->select('twunschliste', 'kKunde', $data->kKunde);
                if (isset($data->kWunschliste)) {
                    $db->queryPrepared(
                        'UPDATE twunschliste
                            SET nStandard = 1
                            WHERE kWunschliste = :wlid',
                        ['wlid' => (int)$data->kWunschliste]
                    );
                    if (!$force) {
                        // Neue Standard Wunschliste in die Session laden
                        $_SESSION['Wunschliste'] = new Wishlist((int)$data->kWunschliste, $db);
                        $_SESSION['Wunschliste']->ueberpruefePositionen();
                    }
                }
            }
            $msg = Shop::Lang()->get('wishlistDelete', 'messages');
        }

        return $msg;
    }

    /**
     * @param array<mixed>|null $post
     */
    public static function update(int $id, ?array $post = null): string
    {
        $post = $post ?? $_POST;
        $db   = Shop::Container()->getDB();
        foreach (['wishlistName', 'WunschlisteName'] as $wishlistName) {
            if (Request::postVar($wishlistName, '') !== '') {
                $name = Text::htmlentities(Text::filterXSS(\mb_substr($post[$wishlistName], 0, 254)));
                $db->update('twunschliste', 'kWunschliste', $id, (object)['cName' => $name]);
            }
        }
        $items = $db->selectAll(
            'twunschlistepos',
            'kWunschliste',
            $id,
            'kWunschlistePos'
        );
        // Prüfen ab Positionen vorhanden
        if (\count($items) === 0) {
            return '';
        }
        foreach ($items as $item) {
            $itemID = (int)$item->kWunschlistePos;
            $idx    = 'Kommentar_' . $itemID;
            if (isset($post[$idx])) {
                $upd             = new stdClass();
                $upd->cKommentar = Text::htmlentities(Text::filterXSS(\mb_substr($post[$idx], 0, 254)));
                $db->update('twunschlistepos', 'kWunschlistePos', $itemID, $upd);
            }

            $idx = 'Anzahl_' . $itemID;
            if (isset($post[$idx])) {
                $quantity = \str_replace(',', '.', $post[$idx]);
                if ((float)$quantity > 0) {
                    $db->update(
                        'twunschlistepos',
                        'kWunschlistePos',
                        $itemID,
                        (object)['fAnzahl' => (float)$quantity]
                    );
                }
            }
        }
        self::updateInSesssion($id);

        return Shop::Lang()->get('wishlistUpdate', 'messages');
    }

    public static function setDefault(int $id): string
    {
        $msg = '';
        if ($id === 0) {
            return $msg;
        }
        // Prüfe ob die Wunschliste dem eingeloggten Kunden gehört
        $db   = Shop::Container()->getDB();
        $data = $db->select('twunschliste', 'kWunschliste', $id);
        if ($data !== null && (int)$data->kKunde === Frontend::getCustomer()->getID()) {
            // Wunschliste auf Standard setzen
            $db->update(
                'twunschliste',
                'kKunde',
                Frontend::getCustomer()->getID(),
                (object)['nStandard' => 0]
            );
            $db->update(
                'twunschliste',
                'kWunschliste',
                $id,
                (object)['nStandard' => 1]
            );
            unset($_SESSION['Wunschliste']);
            $_SESSION['Wunschliste'] = new Wishlist($id, $db);
            $_SESSION['Wunschliste']->ueberpruefePositionen();

            $msg = Shop::Lang()->get('wishlistStandard', 'messages');
        }

        return $msg;
    }

    public static function save(string $name): string
    {
        if (empty($name) || Frontend::getCustomer()->getID() <= 0) {
            return '';
        }
        $list            = new self();
        $list->cName     = $name;
        $list->nStandard = 0;
        unset(
            $list->CWunschlistePos_arr,
            $list->oKunde,
            $list->kWunschliste,
            $list->productCount,
            $list->dErstellt_DE
        );
        Shop::Container()->getDB()->insert('twunschliste', $list);

        return Shop::Lang()->get('wishlistAdd', 'messages');
    }

    /**
     * @param string[] $recipients
     */
    public static function send(array $recipients, int $id): string
    {
        if (\count($recipients) === 0) {
            return Shop::Lang()->get('noEmail', 'messages');
        }
        $msg                        = '';
        $maxRecipients              = Settings::intValue(Globals::WISHLIST_MAX_RECIPIENTS);
        $data                       = new stdClass();
        $data->tkunde               = Frontend::getCustomer();
        $data->twunschliste         = self::buildPrice(new Wishlist($id));
        $history                    = new stdClass();
        $history->kWunschliste      = $id;
        $history->dZeit             = 'NOW()';
        $history->nAnzahlEmpfaenger = \min(\count($recipients), $maxRecipients);
        $history->nAnzahlArtikel    = \count($data->twunschliste->CWunschlistePos_arr);
        Shop::Container()->getDB()->insert('twunschlisteversand', $history);
        $validEmails = [];
        $mailer      = Shop::Container()->getMailer();
        for ($i = 0; $i < $history->nAnzahlEmpfaenger; $i++) {
            // Email auf "Echtheit" prüfen
            $address = Text::filterXSS($recipients[$i]);
            if (SimpleMail::checkBlacklist($address)) {
                $validEmails[] = $address;
            } else {
                $data->mail          = new stdClass();
                $data->mail->toEmail = $address;
                $data->mail->toName  = $address;
                $mailer->send((new Mail())->createFromTemplateID(\MAILTEMPLATE_WUNSCHLISTE, $data));
            }
        }
        // Gab es Emails die nicht validiert wurden?
        if (\count($validEmails) > 0) {
            $msg = Shop::Lang()->get('novalidEmail', 'messages') . \implode(', ', $validEmails) . '<br />';
        }
        // Hat der Benutzer mehr Emails angegeben als erlaubt sind?
        if (\count($recipients) > $maxRecipients) {
            $max = \count($recipients) - $maxRecipients;
            $msg .= '<br />';
            if (!\str_contains($msg, Shop::Lang()->get('novalidEmail', 'messages'))) {
                $msg = Shop::Lang()->get('novalidEmail', 'messages');
            }

            for ($i = 0; $i < $max; $i++) {
                if (!\str_contains($msg, $recipients[(\count($recipients) - 1) - $i])) {
                    if ($i > 0) {
                        $msg .= ', ' . $recipients[(\count($recipients) - 1) - $i];
                    } else {
                        $msg .= $recipients[(\count($recipients) - 1) - $i];
                    }
                }
            }

            $msg .= '<br />';
        }
        $msg .= Shop::Lang()->get('emailSeccessfullySend', 'messages');

        return $msg;
    }

    /**
     * @return stdClass[]|false
     */
    public static function getAttributesByID(int $wishListID, int $itemID): false|array
    {
        if ($wishListID <= 0 || $itemID <= 0) {
            return false;
        }
        $data       = [];
        $attributes = Shop::Container()->getDB()->selectAll(
            'twunschlisteposeigenschaft',
            'kWunschlistePos',
            $itemID
        );
        foreach ($attributes as $attribute) {
            $value                       = new stdClass();
            $value->kEigenschaftWert     = (int)$attribute->kEigenschaftWert;
            $value->kEigenschaft         = (int)$attribute->kEigenschaft;
            $value->cEigenschaftName     = $attribute->cEigenschaftName;
            $value->cEigenschaftWertName = $attribute->cEigenschaftWertName;
            $value->cFreifeldWert        = $attribute->cFreifeldWert;

            $data[] = $value;
        }

        return $data;
    }

    /**
     * @param int $itemID
     * @return stdClass|false
     */
    public static function getWishListPositionDataByID(int $itemID): bool|stdClass
    {
        if ($itemID <= 0) {
            return false;
        }
        $db   = Shop::Container()->getDB();
        $item = $db->select('twunschlistepos', 'kWunschlistePos', $itemID);
        if ($item === null) {
            return false;
        }
        $item->kWunschlistePos = (int)$item->kWunschlistePos;
        $item->kWunschliste    = (int)$item->kWunschliste;
        $item->kArtikel        = (int)$item->kArtikel;
        try {
            $product = new Artikel($db);
            $product->fuelleArtikel($item->kArtikel, Artikel::getDefaultOptions());
        } catch (Exception) {
            return false;
        }
        if ($product->kArtikel > 0) {
            $item->bKonfig = $product->bHasKonfig;
        }

        return $item;
    }

    public static function getWishListDataByID(int $id = 0, string $cURLID = ''): bool|stdClass
    {
        $wishlist = null;
        if ($id > 0) {
            $wishlist = Shop::Container()->getDB()->select('twunschliste', 'kWunschliste', $id);
        } elseif ($cURLID !== '') {
            $wishlist = Shop::Container()->getDB()->getSingleObject(
                'SELECT * FROM twunschliste WHERE cURLID LIKE :id',
                ['id' => $cURLID]
            );
        }
        if (isset($wishlist->kWunschliste) && $wishlist->kWunschliste > 0) {
            $wishlist->kWunschliste = (int)$wishlist->kWunschliste;
            $wishlist->kKunde       = (int)$wishlist->kKunde;
            $wishlist->nStandard    = (int)$wishlist->nStandard;
            $wishlist->nOeffentlich = (int)$wishlist->nOeffentlich;

            return $wishlist;
        }

        return false;
    }

    public static function buildPrice(Wishlist $wishList): Wishlist
    {
        $currency = Frontend::getCurrency();
        $merchant = Frontend::getCustomerGroup()->isMerchant();
        foreach ($wishList->getItems() as $item) {
            $product = $item->getProduct();
            if ($product === null) {
                continue;
            }
            if ($merchant) {
                $price = isset($product->Preise->fVKNetto)
                    ? (int)$item->getQty() * $product->Preise->fVKNetto
                    : 0;
            } else {
                $price = isset($product->Preise->fVKNetto)
                    ? (int)$item->getQty()
                    * ($product->Preise->fVKNetto * (100 + $_SESSION['Steuersatz'][$product->kSteuerklasse]) / 100)
                    : 0;
            }
            $item->setPrice(Preise::getLocalizedPriceString($price, $currency));
        }

        return $wishList;
    }

    public static function mapMessage(int $code): string
    {
        return match ($code) {
            1       => Shop::Lang()->get('basketAdded', 'messages'),
            2       => Shop::Lang()->get('basketAllAdded', 'messages'),
            default => '',
        };
    }

    /**
     * @since 5.0.0
     */
    public function setRecord(?stdClass $record): self
    {
        if ($record === null || !$this->validate($record)) {
            return $this->reset();
        }
        $this->kWunschliste = (int)$record->kWunschliste;
        $this->kKunde       = (int)$record->kKunde;
        $this->nStandard    = (int)$record->nStandard;
        $this->nOeffentlich = (int)$record->nOeffentlich;
        $this->cName        = $record->cName;
        $this->cURLID       = $record->cURLID;
        $this->dErstellt    = $record->dErstellt;
        $this->dErstellt_DE = $record->dErstellt_DE
            ?? Date::safeDateFormat(
                $record->dErstellt,
                'd.m.Y H:i',
                '',
                'Y-m-d H:i:s'
            );
        if ($this->kKunde > 0) {
            $this->oKunde            = new Customer($this->kKunde);
            $this->oKunde->cPasswort = null;
            $this->oKunde->fRabatt   = null;
            $this->oKunde->fGuthaben = null;
            $this->oKunde->cUSTID    = null;
        }
        $langID         = Shop::getLanguageID();
        $items          = $this->db->selectAll(
            'twunschlistepos',
            'kWunschliste',
            $this->kWunschliste,
            '*, date_format(dHinzugefuegt, \'%d.%m.%Y %H:%i\') AS dHinzugefuegt_de'
        );
        $defaultOptions = Artikel::getDefaultOptions();
        // Hole alle Eigenschaften für eine Position
        foreach ($items as $item) {
            $item->kWunschlistePos = (int)$item->kWunschlistePos;
            $item->kWunschliste    = (int)$item->kWunschliste;
            $item->kArtikel        = (int)$item->kArtikel;
            try {
                $product = (new Artikel($this->db))->fuelleArtikel($item->kArtikel, $defaultOptions, 0, $langID);
            } catch (Exception) {
                continue;
            }
            if ($product === null || $product->aufLagerSichtbarkeit() === false) {
                continue;
            }
            $wlItem = new WishlistItem(
                $item->kArtikel,
                $item->cArtikelName,
                $item->fAnzahl,
                $item->kWunschliste
            );

            $wlItem->setID($item->kWunschlistePos);
            $wlItem->setComment($item->cKommentar);
            $wlItem->setDateAdded($item->dHinzugefuegt);
            $wlItem->setDateAddedLocalized($item->dHinzugefuegt_de);

            $wlPositionAttributes = $this->db->getObjects(
                'SELECT twunschlisteposeigenschaft.*,
                    IF(LENGTH(teigenschaftsprache.cName) > 0,
                        teigenschaftsprache.cName,
                        twunschlisteposeigenschaft.cEigenschaftName) AS cName,
                    IF(LENGTH(teigenschaftwertsprache.cName) > 0,
                        teigenschaftwertsprache.cName,
                        twunschlisteposeigenschaft.cEigenschaftWertName) AS cWert
                    FROM twunschlisteposeigenschaft
                    LEFT JOIN teigenschaftsprache
                        ON teigenschaftsprache.kEigenschaft = twunschlisteposeigenschaft.kEigenschaft
                        AND teigenschaftsprache.kSprache = :langID
                    LEFT JOIN teigenschaftwertsprache
                        ON teigenschaftwertsprache.kEigenschaftWert = twunschlisteposeigenschaft.kEigenschaftWert
                        AND teigenschaftwertsprache.kSprache = :langID
                    WHERE twunschlisteposeigenschaft.kWunschlistePos = :wlID
                    GROUP BY twunschlisteposeigenschaft.kWunschlistePosEigenschaft',
                [
                    'langID' => $langID,
                    'wlID'   => $item->kWunschlistePos
                ]
            );
            foreach ($wlPositionAttributes as $wlPositionAttribute) {
                if ($wlPositionAttribute->cFreifeldWert !== '') {
                    if (empty($wlPositionAttribute->cName)) {
                        $localized                  = $this->db->getSingleObject(
                            'SELECT IF(LENGTH(teigenschaftsprache.cName) > 0,
                                teigenschaftsprache.cName,
                                teigenschaft.cName) AS cName
                                FROM teigenschaft
                                LEFT JOIN teigenschaftsprache
                                    ON teigenschaftsprache.kEigenschaft = teigenschaft.kEigenschaft
                                    AND teigenschaftsprache.kSprache = :langID
                                WHERE teigenschaft.kEigenschaft = :attrID',
                            [
                                'langID' => $langID,
                                'attrID' => (int)$wlPositionAttribute->kEigenschaft
                            ]
                        );
                        $wlPositionAttribute->cName = $localized->cName ?? '';
                    }
                    $wlPositionAttribute->cWert = $wlPositionAttribute->cFreifeldWert;
                }
                $prop = new WishlistItemProperty(
                    (int)$wlPositionAttribute->kEigenschaft,
                    (int)$wlPositionAttribute->kEigenschaftWert,
                    $wlPositionAttribute->cFreifeldWert,
                    $wlPositionAttribute->cName,
                    $wlPositionAttribute->cWert,
                    (int)$wlPositionAttribute->kWunschlistePos
                );

                $prop->setID((int)$wlPositionAttribute->kWunschlistePosEigenschaft);
                $wlItem->addProperty($prop);
            }
            $wlItem->setProduct($product);
            $wlItem->setProductName($product->cName ?: $wlItem->getProductName());
            $this->addItem($wlItem);
        }
        $this->setProductCount(\count($this->CWunschlistePos_arr));

        return $this;
    }

    public function setVisibility(bool $public): void
    {
        if ($public === true) {
            $urlID    = \uniqid('', true);
            $campaign = new Campaign(\KAMPAGNE_INTERN_OEFFENTL_WUNSCHZETTEL, $this->db);
            if ($campaign->kKampagne > 0) {
                $urlID .= '&' . $campaign->cParameter . '=' . $campaign->cWert;
            }
            $this->nOeffentlich = 1;
            $this->cURLID       = $urlID;
            $upd                = (object)['nOeffentlich' => 1, 'cURLID' => $urlID];
        } else {
            $this->nOeffentlich = 0;
            $this->cURLID       = '';
            $upd                = (object)['nOeffentlich' => 0, 'cURLID' => ''];
        }
        $this->db->update('twunschliste', 'kWunschliste', $this->kWunschliste, $upd);
        self::updateInSesssion($this->kWunschliste);
    }

    public static function setPrivate(int $id): void
    {
        $upd               = new stdClass();
        $upd->nOeffentlich = 0;
        $upd->cURLID       = '';
        Shop::Container()->getDB()->update('twunschliste', 'kWunschliste', $id, $upd);
        self::updateInSesssion($id);
    }

    public static function setPublic(int $id): string
    {
        $db       = Shop::Container()->getDB();
        $urlID    = \uniqid('', true);
        $campaign = new Campaign(\KAMPAGNE_INTERN_OEFFENTL_WUNSCHZETTEL, $db);
        if ($campaign->kKampagne > 0) {
            $urlID .= '&' . $campaign->cParameter . '=' . $campaign->cWert;
        }
        $upd               = new stdClass();
        $upd->nOeffentlich = 1;
        $upd->cURLID       = $urlID;
        $db->update('twunschliste', 'kWunschliste', $id, $upd);
        self::updateInSesssion($id);

        return $urlID;
    }

    /**
     * @return Collection<int, Wishlist>
     */
    public static function getWishlists(): Collection
    {
        $customer   = Frontend::getCustomer();
        $customerID = $customer->getID();
        if ($customerID === 0) {
            return new Collection();
        }
        $db = Shop::Container()->getDB();

        return $db->getCollection(
            'SELECT tw.*, COUNT(twp.kArtikel) AS productCount
                FROM twunschliste AS tw
                    LEFT JOIN twunschlistepos AS twp USING (kWunschliste)
                WHERE kKunde = :customerID
                GROUP BY tw.kWunschliste
                ORDER BY tw.nStandard DESC',
            ['customerID' => $customerID]
        )->map(static function (stdClass $list) use ($customer, $db): self {
            $wl = new self(0, $db);
            $wl->setID((int)$list->kWunschliste);
            $wl->setCustomerID((int)$list->kKunde);
            $wl->setIsPublic((int)$list->nOeffentlich === 1);
            $wl->setIsDefault((int)$list->nStandard === 1);
            $wl->setProductCount((int)$list->productCount);
            $wl->setName($list->cName);
            $wl->setDateCreated($list->dErstellt);
            $wl->setURL($list->cURLID);
            $wl->setCustomer($customer);

            return $wl;
        });
    }

    /**
     * @param array<mixed> $params
     */
    public static function checkVariOnList(int $productID, array $params): int
    {
        $variationCount = \count($params);
        $wishlist       = Frontend::getWishList();
        foreach ($wishlist->CWunschlistePos_arr as $item) {
            if ($productID !== $item->getProductID()) {
                continue;
            }
            $variCountTMP = 0;
            foreach ($item->getProperties() as $property) {
                if (
                    isset($params[$property->getPropertyID()])
                    && ((string)$property->getPropertyValueID() === $params[$property->getPropertyID()]
                        || $property->getFreeTextValue() === $params[$property->getPropertyID()])
                ) {
                    $variCountTMP++;
                }
            }
            if ($variCountTMP === $variationCount) {
                return $item->getID();
            }
        }

        return 0;
    }

    /**
     * @param self[]|Collection<int, self> $wishlists
     */
    public static function getInvisibleItemCount(iterable $wishlists, Wishlist $currentWishlist, int $wishlistID): int
    {
        foreach ($wishlists as $wishlist) {
            if ($wishlist->getID() === $wishlistID) {
                return $wishlist->getProductCount() - \count($currentWishlist->getItems());
            }
        }

        return 0;
    }

    private static function updateInSesssion(int $id): void
    {
        if (Frontend::getWishList()->kWunschliste === $id) {
            unset($_SESSION['Wunschliste']);
            $_SESSION['Wunschliste'] = new Wishlist($id);
            $_SESSION['Wunschliste']->ueberpruefePositionen();
        }
    }

    public function getID(): int
    {
        return $this->kWunschliste;
    }

    public function setID(int $kWunschliste): void
    {
        $this->kWunschliste = $kWunschliste;
    }

    public function getCustomerID(): int
    {
        return (int)$this->kKunde;
    }

    public function setCustomerID(int $kKunde): void
    {
        $this->kKunde = $kKunde;
    }

    public function isDefault(): bool
    {
        return (bool)$this->nStandard;
    }

    public function setIsDefault(bool $default): void
    {
        $this->nStandard = (int)$default;
    }

    public function isPublic(): bool
    {
        return (bool)$this->nOeffentlich;
    }

    public function setIsPublic(bool $public): void
    {
        $this->nOeffentlich = (int)$public;
    }

    public function getName(): string
    {
        return $this->cName;
    }

    public function setName(string $name): void
    {
        $this->cName = $name;
    }

    public function getURL(): string
    {
        return $this->cURLID;
    }

    public function setURL(string $url): void
    {
        $this->cURLID = $url;
    }

    public function getDateCreated(): string
    {
        return $this->dErstellt;
    }

    public function setDateCreated(string $date): void
    {
        $this->dErstellt = $date;
    }

    public function getDateCreatedLocalized(): string
    {
        return $this->dErstellt_DE;
    }

    public function setDateCreatedLocalized(string $date): void
    {
        $this->dErstellt_DE = $date;
    }

    /**
     * @return WishlistItem[]
     */
    public function getItems(): array
    {
        return $this->CWunschlistePos_arr;
    }

    /**
     * @param WishlistItem[] $items
     */
    public function setItems(array $items): void
    {
        $this->CWunschlistePos_arr = $items;
        $this->setProductCount(\count($items));
    }

    public function addItem(WishlistItem $item): void
    {
        $this->CWunschlistePos_arr[] = $item;
        $this->setProductCount(\count($this->CWunschlistePos_arr));
    }

    public function getCustomer(): ?Customer
    {
        return $this->oKunde;
    }

    public function setCustomer(Customer $customer): void
    {
        $this->oKunde = $customer;
    }

    public function getProductCount(): int
    {
        return $this->productCount;
    }

    public function setProductCount(int $productCount): void
    {
        $this->productCount = $productCount;
    }
}
