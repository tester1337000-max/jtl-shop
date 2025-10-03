<?php

declare(strict_types=1);

namespace JTL\Customer;

use InvalidArgumentException;
use JTL\DB\DbInterface;
use JTL\MagicCompatibilityTrait;
use JTL\Session\Frontend;
use JTL\Settings\Option\Globals;
use JTL\Settings\Settings;
use JTL\Shop;
use stdClass;

use function Functional\first;

/**
 * Class CustomerGroup
 * @package JTL\Customer
 */
class CustomerGroup
{
    use MagicCompatibilityTrait;

    protected int $id = 0;

    protected string $name = '';

    protected float $discount = 0.0;

    protected string $default = 'N';

    protected string $cShopLogin = 'N';

    protected int $isMerchant = 0;

    protected int $mayViewPrices = 1;

    protected int $mayViewCategories = 1;

    protected int $languageID = 0;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $Attribute = null;

    private ?string $nameLocalized = null;

    private DbInterface $db;

    /**
     * @var array<string, string>
     */
    protected static array $mapping = [
        'kKundengruppe'              => 'ID',
        'kSprache'                   => 'LanguageID',
        'nNettoPreise'               => 'IsMerchant',
        'darfPreiseSehen'            => 'MayViewPrices',
        'darfArtikelKategorienSehen' => 'MayViewCategories',
        'cName'                      => 'Name',
        'cStandard'                  => 'Default',
        'fRabatt'                    => 'Discount',
        'cNameLocalized'             => 'nameLocalized'
    ];

    public function __construct(int $id = 0, ?DbInterface $db = null)
    {
        $this->db = $db ?? Shop::Container()->getDB();
        if ($id > 0) {
            $this->loadFromDB($id);
        }
    }

    public function loadDefaultGroup(): self
    {
        $res = $this->db->getObjects(
            'SELECT G.*, S.cName AS nameLocalized, A.cName AS attributeName, A.cWert AS attributeValue
                FROM tkundengruppe G
                LEFT JOIN tkundengruppensprache S
                    ON G.kKundengruppe = S.kKundengruppe
                    AND S.kSprache = :lid
                LEFT JOIN tkundengruppenattribut A
                    ON G.kKundengruppe = A.kKundengruppe
                WHERE G.cStandard = \'Y\'',
            ['lid' => $this->languageID]
        );
        if (\count($res) === 0) {
            return $this;
        }
        $this->loadData($res);

        return $this;
    }

    private function loadFromDB(int $id = 0): self
    {
        $res = $this->db->getObjects(
            'SELECT G.*, S.cName AS nameLocalized, A.cName AS attributeName, A.cWert AS attributeValue
                FROM tkundengruppe G
                LEFT JOIN tkundengruppensprache S
                    ON G.kKundengruppe = S.kKundengruppe
                    AND S.kSprache = :lid
                LEFT JOIN tkundengruppenattribut A
                    ON G.kKundengruppe = A.kKundengruppe
                WHERE G.kKundengruppe = :id',
            ['lid' => $this->languageID, 'id' => $id]
        );
        if (\count($res) === 0) {
            throw new InvalidArgumentException('Cannot load customer group with id ' . $id);
        }
        $this->loadData($res);

        return $this;
    }

    /**
     * @param stdClass[] $res
     */
    private function loadData(array $res): void
    {
        /** @var stdClass $item */
        $item = first($res);
        $conf = Settings::intValue(Globals::VISIBILITY_GUESTS);
        $this->setID((int)$item->kKundengruppe)
            ->setName($item->cName)
            ->setDiscount($item->fRabatt)
            ->setDefault($item->cStandard)
            ->setShopLogin($item->cShopLogin)
            ->setIsMerchant((int)$item->nNettoPreise);
        if ($item->nameLocalized !== null) {
            $this->nameLocalized = $item->nameLocalized;
        }
        $this->Attribute = [];
        foreach ($res as $attribute) {
            if ($attribute->attributeName === null) {
                continue;
            }
            $this->Attribute[\mb_convert_case($attribute->attributeName, \MB_CASE_LOWER)] = $attribute->attributeValue;
        }
        if ($this->isDefault()) {
            if ($conf === 2) {
                $this->mayViewPrices = 0;
            } elseif ($conf === 3) {
                $this->mayViewPrices     = 0;
                $this->mayViewCategories = 0;
            }
        }
    }

    /**
     * @param bool $primary
     * @return ($primary is true ? int|false : bool)
     */
    public function save(bool $primary = true): bool|int
    {
        $ins               = new stdClass();
        $ins->cName        = $this->name;
        $ins->fRabatt      = $this->discount;
        $ins->cStandard    = \mb_convert_case($this->default, \MB_CASE_UPPER);
        $ins->cShopLogin   = $this->cShopLogin;
        $ins->nNettoPreise = $this->isMerchant;

        $key = $this->db->insert('tkundengruppe', $ins);
        if ($key < 1) {
            return false;
        }

        return $primary ? $key : true;
    }

    public function update(): int
    {
        $upd               = new stdClass();
        $upd->cName        = $this->name;
        $upd->fRabatt      = $this->discount;
        $upd->cStandard    = $this->default;
        $upd->cShopLogin   = $this->cShopLogin;
        $upd->nNettoPreise = $this->isMerchant;

        return $this->db->update('tkundengruppe', 'kKundengruppe', $this->id, $upd);
    }

    public function delete(): int
    {
        return $this->db->delete('tkundengruppe', 'kKundengruppe', $this->id);
    }

    public function getID(): int
    {
        return $this->id;
    }

    public function setID(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function setDiscount(float|int|string $discount): self
    {
        $this->discount = (float)$discount;

        return $this;
    }

    public function getDiscount(): float
    {
        return $this->discount;
    }

    public function setDefault(string $default): self
    {
        $this->default = $default;

        return $this;
    }

    public function setShopLogin(string $cShopLogin): self
    {
        $this->cShopLogin = $cShopLogin;

        return $this;
    }

    public function setIsMerchant(int $is): self
    {
        $this->isMerchant = (int)($is > 0);

        return $this;
    }

    public function setMayViewPrices(int $n): self
    {
        $this->mayViewPrices = $n;

        return $this;
    }

    public function mayViewPrices(): bool
    {
        return $this->mayViewPrices === 1;
    }

    public function getMayViewPrices(): int
    {
        return $this->mayViewPrices;
    }

    public function setMayViewCategories(int $n): self
    {
        $this->mayViewCategories = $n;

        return $this;
    }

    public function getMayViewCategories(): int
    {
        return $this->mayViewCategories;
    }

    public function mayViewCategories(): bool
    {
        return $this->mayViewCategories === 1;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getNameLocalized(): ?string
    {
        return $this->nameLocalized;
    }

    public function setNameLocalized(?string $nameLocalized): void
    {
        $this->nameLocalized = $nameLocalized;
    }

    public function getStandard(): ?string
    {
        \trigger_error(__METHOD__ . ' is deprecated - use getDefault() instead', \E_USER_DEPRECATED);

        return $this->getIsDefault();
    }

    public function getIsDefault(): ?string
    {
        return $this->default;
    }

    public function isDefault(): bool
    {
        return $this->default === 'Y';
    }

    public function getShopLogin(): ?string
    {
        return $this->cShopLogin;
    }

    public function getIsMerchant(): int
    {
        return $this->isMerchant;
    }

    public function isMerchant(): bool
    {
        return $this->isMerchant > 0;
    }

    public function getNettoPreise(): int
    {
        \trigger_error(__METHOD__ . ' is deprecated - use getIsMerchant() instead', \E_USER_DEPRECATED);

        return $this->getIsMerchant();
    }

    /**
     * @return CustomerGroup[]
     */
    public static function getGroups(): array
    {
        $db = Shop::Container()->getDB();

        return $db->getCollection(
            'SELECT kKundengruppe AS id
                FROM tkundengruppe
                WHERE kKundengruppe > 0'
        )->map(fn(stdClass $e): self => new self((int)$e->id, $db))->toArray();
    }

    public static function getDefault(?DbInterface $db = null): ?stdClass
    {
        $res = ($db ?? Shop::Container()->getDB())->select('tkundengruppe', 'cStandard', 'Y');
        if ($res === null) {
            return null;
        }
        $res->kKundengruppe = (int)$res->kKundengruppe;
        $res->nNettoPreise  = (int)$res->nNettoPreise;

        return $res;
    }

    public function getLanguageID(): int
    {
        return $this->languageID;
    }

    public function setLanguageID(int|string $languageID): self
    {
        $this->languageID = (int)$languageID;

        return $this;
    }

    public static function getCurrent(): int
    {
        $id = 0;
        if (isset($_SESSION['Kundengruppe']->kKundengruppe)) {
            $id = $_SESSION['Kundengruppe']->getID();
        } elseif (isset($_SESSION['Kunde']->kKundengruppe)) {
            $id = (int)$_SESSION['Kunde']->kKundengruppe;
        }

        return $id;
    }

    public static function getDefaultGroupID(bool $ignoreSession = false): int
    {
        if (
            $ignoreSession === false
            && isset($_SESSION['Kundengruppe'])
            && $_SESSION['Kundengruppe'] instanceof self
            && $_SESSION['Kundengruppe']->getID() > 0
        ) {
            return $_SESSION['Kundengruppe']->getID();
        }
        $customerGroup = self::getDefault();
        if ($customerGroup !== null && $customerGroup->kKundengruppe > 0) {
            return $customerGroup->kKundengruppe;
        }

        return 0;
    }

    public static function reset(int $id): CustomerGroup
    {
        if (
            isset($_SESSION['Kundengruppe'])
            && $_SESSION['Kundengruppe'] instanceof self
            && $_SESSION['Kundengruppe']->getID() === $id
        ) {
            return $_SESSION['Kundengruppe'];
        }
        if (!$id) {
            $id = self::getDefaultGroupID();
        }
        $item = new self($id);
        if (!isset($_SESSION['Kundengruppe']) && $item->getID() > 0) {
            $item->setMayViewPrices(1)->setMayViewCategories(1);
            $conf = Settings::intValue(Globals::VISIBILITY_GUESTS);
            if ($conf === 2) {
                $item->setMayViewPrices(0);
            }
            if ($conf === 3) {
                $item->setMayViewPrices(0)->setMayViewCategories(0);
            }
            $_SESSION['Kundengruppe'] = $item;
        }

        return $item;
    }

    public static function getByID(int $id): self
    {
        $current = Frontend::getCustomerGroup();
        if ($current->getID() === $id) {
            return $current;
        }

        return new self($id);
    }

    public function initAttributes(): self
    {
        if ($this->id <= 0) {
            return $this;
        }
        $this->Attribute = [];
        $attributes      = $this->db->selectAll(
            'tkundengruppenattribut',
            'kKundengruppe',
            $this->id
        );
        foreach ($attributes as $attribute) {
            $this->Attribute[\mb_convert_case($attribute->cName, \MB_CASE_LOWER)] = $attribute->cWert;
        }

        return $this;
    }

    public function hasAttributes(): bool
    {
        return $this->Attribute !== null;
    }

    public function getAttribute(string $attributeName): mixed
    {
        return $this->Attribute[$attributeName] ?? null;
    }

    public static function getNameByID(int $id): ?string
    {
        try {
            return (new self($id))->getName();
        } catch (\Exception) {
            return null;
        }
    }
}
