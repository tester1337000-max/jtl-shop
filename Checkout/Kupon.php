<?php

declare(strict_types=1);

namespace JTL\Checkout;

use DateTime;
use JTL\Cart\CartHelper;
use JTL\Catalog\Category\Kategorie;
use JTL\Catalog\Hersteller;
use JTL\Catalog\Product\Artikel;
use JTL\Catalog\Product\Preise;
use JTL\Customer\CustomerGroup;
use JTL\DB\DbInterface;
use JTL\Helpers\GeneralObject;
use JTL\Helpers\Product;
use JTL\Helpers\Text;
use JTL\Language\LanguageHelper;
use JTL\Session\Frontend;
use JTL\Shop;
use Monolog\Logger;
use stdClass;

use function Functional\select;

/**
 * Class Kupon
 * @package JTL\Checkout
 */
class Kupon
{
    public ?int $kKupon = null;

    public ?int $kKundengruppe = null;

    public ?int $kSteuerklasse = null;

    public ?string $cName = null;

    /**
     * @var numeric-string|float|null
     */
    public string|null|float $fWert = null;

    public ?string $cWertTyp = null;

    public ?string $dGueltigAb = null;

    public ?string $dGueltigBis = null;

    public string|null|float $fMindestbestellwert = null;

    public ?string $cCode = null;

    public ?int $nVerwendungen = null;

    public ?int $nVerwendungenBisher = null;

    public ?int $nVerwendungenProKunde = null;

    public ?string $cArtikel = null;

    public ?string $cHersteller = null;

    public ?string $cKategorien = null;

    public ?string $cKunden = null;

    public ?string $cKuponTyp = null;

    public ?string $cLieferlaender = null;

    public ?string $cZusatzgebuehren = null;

    public ?string $cAktiv = null;

    public ?string $dErstellt = null;

    public int $nGanzenWKRabattieren = 0;

    /**
     * @var array<string, string>
     */
    public ?array $translationList = null;

    public ?stdClass $massCreationCoupon = null;

    public ?string $cLocalizedWert = null;

    public ?string $cLocalizedMBW = null;

    /**
     * @usedby template file
     */
    public ?string $AngezeigterName = null;

    public ?string $cGueltigAbShort = null;

    public ?string $cGueltigAbLong = null;

    public ?string $cGueltigBisShort = null;

    public ?string $cKundengruppe = null;

    public ?string $cArtikelInfo = null;

    public ?string $cHerstellerInfo = null;

    public ?string $cKategorieInfo = null;

    public ?string $cKundenInfo = null;

    public ?DateTime $dLastUse = null;

    public ?string $cGueltigBisLong;

    public bool $bOpenEnd = false;

    /**
     * @var Kategorie[]
     */
    public array $Kategorien = [];

    /**
     * @var Artikel[]
     */
    public array $Artikel = [];

    /**
     * @var Hersteller[]
     */
    public array $Hersteller = [];

    private DbInterface $db;

    public const TYPE_STANDARD    = 'standard';
    public const TYPE_SHIPPING    = 'versandkupon';
    public const TYPE_NEWCUSTOMER = 'neukundenkupon';

    public function __get(string $name): mixed
    {
        if ($name === 'cLocalizedMbw') {
            return $this->cLocalizedMBW;
        }
        if ($name === 'cLocalizedValue') {
            return $this->cLocalizedWert;
        }

        return null;
    }

    public function __set(string $name, mixed $value): void
    {
        if ($name === 'cLocalizedMbw') {
            $this->cLocalizedMBW = $value;
        }
        if ($name === 'cLocalizedValue') {
            $this->cLocalizedWert = $value;
        }
    }

    /**
     * @return string[]
     */
    public function __sleep(): array
    {
        return select(\array_keys(\get_object_vars($this)), fn(string $e): bool => $e !== 'db');
    }

    public function __wakeup(): void
    {
        $this->db = Shop::Container()->getDB();
    }

    public function __construct(int $id = 0, ?DbInterface $db = null)
    {
        $this->db = $db ?? Shop::Container()->getDB();
        if ($id > 0) {
            $this->loadFromDB($id);
        }
    }

    /**
     * @return false|self
     */
    private function loadFromDB(int $id = 0): Kupon|bool
    {
        $item = $this->db->select('tkupon', 'kKupon', $id);
        if ($item === null || $item->kKupon <= 0) {
            return false;
        }
        $this->translationList       = $this->getTranslation((int)$item->kKupon);
        $this->kKupon                = (int)$item->kKupon;
        $this->kKundengruppe         = (int)$item->kKundengruppe;
        $this->kSteuerklasse         = (int)$item->kSteuerklasse;
        $this->cName                 = $item->cName;
        $this->fWert                 = $item->fWert;
        $this->cWertTyp              = $item->cWertTyp;
        $this->dGueltigAb            = $item->dGueltigAb;
        $this->dGueltigBis           = $item->dGueltigBis;
        $this->fMindestbestellwert   = $item->fMindestbestellwert;
        $this->cCode                 = $item->cCode;
        $this->nVerwendungen         = (int)$item->nVerwendungen;
        $this->nVerwendungenBisher   = (int)$item->nVerwendungenBisher;
        $this->nVerwendungenProKunde = (int)$item->nVerwendungenProKunde;
        $this->cArtikel              = $item->cArtikel;
        $this->cHersteller           = $item->cHersteller;
        $this->cKategorien           = $item->cKategorien;
        $this->cKunden               = $item->cKunden;
        $this->cKuponTyp             = $item->cKuponTyp;
        $this->cLieferlaender        = $item->cLieferlaender;
        $this->cZusatzgebuehren      = $item->cZusatzgebuehren;
        $this->cAktiv                = $item->cAktiv;
        $this->dErstellt             = $item->dErstellt;
        $this->nGanzenWKRabattieren  = (int)$item->nGanzenWKRabattieren;

        return $this;
    }

    /**
     * @param bool $primary
     * @return ($primary is true ? int|false : bool)
     */
    public function save(bool $primary = true): bool|int
    {
        $ins                        = new stdClass();
        $ins->kKundengruppe         = $this->kKundengruppe;
        $ins->kSteuerklasse         = $this->kSteuerklasse;
        $ins->cName                 = $this->cName;
        $ins->fWert                 = $this->fWert;
        $ins->cWertTyp              = $this->cWertTyp;
        $ins->dGueltigAb            = $this->dGueltigAb;
        $ins->dGueltigBis           = $this->dGueltigBis;
        $ins->fMindestbestellwert   = $this->fMindestbestellwert;
        $ins->cCode                 = $this->cCode;
        $ins->nVerwendungen         = $this->nVerwendungen;
        $ins->nVerwendungenBisher   = $this->nVerwendungenBisher;
        $ins->nVerwendungenProKunde = $this->nVerwendungenProKunde;
        $ins->cArtikel              = $this->cArtikel;
        $ins->cHersteller           = empty($this->cHersteller) ? '-1' : $this->cHersteller;
        $ins->cKategorien           = empty($this->cKategorien) ? '-1' : $this->cKategorien;
        $ins->cKunden               = $this->cKunden;
        $ins->cKuponTyp             = $this->cKuponTyp;
        $ins->cLieferlaender        = $this->cLieferlaender;
        $ins->cZusatzgebuehren      = $this->cZusatzgebuehren;
        $ins->cAktiv                = $this->cAktiv;
        $ins->dErstellt             = $this->dErstellt;
        $ins->nGanzenWKRabattieren  = $this->nGanzenWKRabattieren;
        if (empty($ins->dGueltigBis)) {
            $ins->dGueltigBis = '_DBNULL_';
        }
        $key = $this->db->insert('tkupon', $ins);
        if ($key < 1) {
            return false;
        }

        return $primary ? $key : true;
    }

    public function update(): int
    {
        $upd                        = new stdClass();
        $upd->kKundengruppe         = $this->kKundengruppe;
        $upd->kSteuerklasse         = $this->kSteuerklasse;
        $upd->cName                 = $this->cName;
        $upd->fWert                 = $this->fWert;
        $upd->cWertTyp              = $this->cWertTyp;
        $upd->dGueltigAb            = $this->dGueltigAb;
        $upd->dGueltigBis           = empty($this->dGueltigBis) ? '_DBNULL_' : $this->dGueltigBis;
        $upd->fMindestbestellwert   = $this->fMindestbestellwert;
        $upd->cCode                 = $this->cCode;
        $upd->nVerwendungen         = $this->nVerwendungen;
        $upd->nVerwendungenBisher   = $this->nVerwendungenBisher;
        $upd->nVerwendungenProKunde = $this->nVerwendungenProKunde;
        $upd->cArtikel              = $this->cArtikel;
        $upd->cHersteller           = empty($this->cHersteller) ? '-1' : $this->cHersteller;
        $upd->cKategorien           = empty($this->cKategorien) ? '-1' : $this->cKategorien;
        $upd->cKunden               = $this->cKunden;
        $upd->cKuponTyp             = $this->cKuponTyp;
        $upd->cLieferlaender        = $this->cLieferlaender;
        $upd->cZusatzgebuehren      = $this->cZusatzgebuehren;
        $upd->cAktiv                = $this->cAktiv;
        $upd->dErstellt             = $this->dErstellt;
        $upd->nGanzenWKRabattieren  = $this->nGanzenWKRabattieren;

        return $this->db->update('tkupon', 'kKupon', (int)$this->kKupon, $upd);
    }

    public function delete(): int
    {
        return $this->db->delete('tkupon', 'kKupon', (int)$this->kKupon);
    }

    public function augment(): void
    {
        $this->cLocalizedWert = $this->cWertTyp === 'festpreis'
            ? Preise::getLocalizedPriceString($this->fWert)
            : '';
        $this->cLocalizedMBW  = $this->fMindestbestellwert !== null
            ? Preise::getLocalizedPriceString($this->fMindestbestellwert)
            : '';
        $this->bOpenEnd       = $this->dGueltigBis === null;

        if ($this->dGueltigAb !== null && \date_create($this->dGueltigAb) !== false) {
            $this->cGueltigAbShort = (new DateTime($this->dGueltigAb))->format('d.m.Y');
            $this->cGueltigAbLong  = (new DateTime($this->dGueltigAb))->format('d.m.Y H:i');
        } else {
            $this->cGueltigAbShort = 'ungültig';
            $this->cGueltigAbLong  = 'ungültig';
        }

        if ($this->bOpenEnd) {
            $this->cGueltigBisShort = 'open-end';
            $this->cGueltigBisLong  = 'open-end';
        } elseif ($this->dGueltigBis === null || \date_create($this->dGueltigBis) === false) {
            $this->cGueltigBisShort = 'ungültig';
            $this->cGueltigBisLong  = 'ungültig';
        } elseif ($this->dGueltigBis === '') {
            $this->cGueltigBisShort = '';
            $this->cGueltigBisLong  = '';
        } else {
            $this->cGueltigBisShort = (new DateTime($this->dGueltigBis))->format('d.m.Y');
            $this->cGueltigBisLong  = (new DateTime($this->dGueltigBis))->format('d.m.Y H:i');
        }
        $this->cKundengruppe = '';
        if ($this->kKundengruppe > 0) {
            try {
                $customerGroup       = new CustomerGroup($this->kKundengruppe, $this->db);
                $this->cKundengruppe = $customerGroup->getName() ?? '';
            } catch (\Exception) {
            }
        }

        $artNos       = Text::parseSSKint($this->cArtikel);
        $manufactuers = Text::parseSSKint($this->cHersteller);
        $categories   = Text::parseSSKint($this->cKategorien);
        $customers    = Text::parseSSKint($this->cKunden);

        $this->cArtikelInfo    = ($this->cArtikel === '')
            ? ''
            : (string)\count($artNos);
        $this->cHerstellerInfo = (empty($this->cHersteller) || $this->cHersteller === '-1')
            ? ''
            : (string)\count($manufactuers);
        $this->cKategorieInfo  = (empty($this->cKategorien) || $this->cKategorien === '-1')
            ? ''
            : (string)\count($categories);
        $this->cKundenInfo     = (empty($this->cKunden) || $this->cKunden === '-1')
            ? ''
            : (string)\count($customers);

        $maxCreated     = $this->db->getSingleObject(
            'SELECT MAX(dErstellt) AS dLastUse
                FROM tkuponkunde
                WHERE kKupon = :cid',
            ['cid' => (int)$this->kKupon]
        );
        $this->dLastUse = new DateTime(
            $maxCreated !== null && \is_string($maxCreated->dLastUse)
                ? $maxCreated->dLastUse
                : ''
        );
    }

    public function setKupon(int $id): self
    {
        $this->kKupon = $id;

        return $this;
    }

    public function setKundengruppe(int $customerGroupID): self
    {
        $this->kKundengruppe = $customerGroupID;

        return $this;
    }

    public function setSteuerklasse(int $kSteuerklasse): self
    {
        $this->kSteuerklasse = $kSteuerklasse;

        return $this;
    }

    public function setName(?string $name): self
    {
        $this->cName = $name;

        return $this;
    }

    public function setWert(mixed $fWert): self
    {
        $this->fWert = (float)$fWert;

        return $this;
    }

    public function setWertTyp(?string $cWertTyp): self
    {
        $this->cWertTyp = $cWertTyp;

        return $this;
    }

    public function setGueltigAb(?string $dGueltigAb): self
    {
        $this->dGueltigAb = $dGueltigAb;

        return $this;
    }

    public function setGueltigBis(?string $dGueltigBis): self
    {
        $this->dGueltigBis = $dGueltigBis;

        return $this;
    }

    public function setMindestbestellwert(mixed $fMindestbestellwert): self
    {
        $this->fMindestbestellwert = (float)$fMindestbestellwert;

        return $this;
    }

    public function setCode(?string $code): self
    {
        $this->cCode = $code;

        return $this;
    }

    public function setVerwendungen(int $nVerwendungen): self
    {
        $this->nVerwendungen = $nVerwendungen;

        return $this;
    }

    public function setVerwendungenBisher(int $nVerwendungenBisher): self
    {
        $this->nVerwendungenBisher = $nVerwendungenBisher;

        return $this;
    }

    public function setVerwendungenProKunde(int $nVerwendungenProKunde): self
    {
        $this->nVerwendungenProKunde = $nVerwendungenProKunde;

        return $this;
    }

    public function setArtikel(string $cArtikel): self
    {
        $this->cArtikel = $cArtikel;

        return $this;
    }

    public function setHersteller(string $cHersteller): self
    {
        $this->cHersteller = $cHersteller;

        return $this;
    }

    public function setKategorien(string $cKategorien): self
    {
        $this->cKategorien = $cKategorien;

        return $this;
    }

    public function setKunden(string $cKunden): self
    {
        $this->cKunden = $cKunden;

        return $this;
    }

    public function setKuponTyp(string $cKuponTyp): self
    {
        $this->cKuponTyp = $cKuponTyp;

        return $this;
    }

    public function setLieferlaender(string $cLieferlaender): self
    {
        $this->cLieferlaender = $cLieferlaender;

        return $this;
    }

    public function setZusatzgebuehren(string $cZusatzgebuehren): self
    {
        $this->cZusatzgebuehren = $cZusatzgebuehren;

        return $this;
    }

    public function setAktiv(string $cAktiv): self
    {
        $this->cAktiv = $cAktiv;

        return $this;
    }

    public function setErstellt(string $dErstellt): self
    {
        $this->dErstellt = $dErstellt;

        return $this;
    }

    public function setGanzenWKRabattieren(int $value): self
    {
        $this->nGanzenWKRabattieren = $value;

        return $this;
    }

    public function getKupon(): ?int
    {
        return $this->kKupon;
    }

    public function getKundengruppe(): ?int
    {
        return $this->kKundengruppe;
    }

    public function getSteuerklasse(): ?int
    {
        return $this->kSteuerklasse;
    }

    public function getName(): ?string
    {
        return $this->cName;
    }

    public function getWert(): float|string|null
    {
        return $this->fWert;
    }

    public function getWertTyp(): ?string
    {
        return $this->cWertTyp;
    }

    public function getGueltigAb(): ?string
    {
        return $this->dGueltigAb;
    }

    public function getGueltigBis(): ?string
    {
        return $this->dGueltigBis;
    }

    public function getMindestbestellwert(): float|string|null
    {
        return $this->fMindestbestellwert;
    }

    public function getCode(): ?string
    {
        return $this->cCode;
    }

    public function getVerwendungen(): int
    {
        return (int)($this->nVerwendungen ?? '0');
    }

    public function getVerwendungenBisher(): int
    {
        return (int)($this->nVerwendungenBisher ?? '0');
    }

    public function getVerwendungenProKunde(): int
    {
        return (int)($this->nVerwendungenProKunde ?? '0');
    }

    public function getArtikel(): ?string
    {
        return $this->cArtikel;
    }

    public function getHersteller(): ?string
    {
        return $this->cHersteller;
    }

    public function getKategorien(): ?string
    {
        return $this->cKategorien;
    }

    public function getKunden(): ?string
    {
        return $this->cKunden;
    }

    public function getKuponTyp(): ?string
    {
        return $this->cKuponTyp;
    }

    public function getLieferlaender(): ?string
    {
        return $this->cLieferlaender;
    }

    public function getZusatzgebuehren(): ?string
    {
        return $this->cZusatzgebuehren;
    }

    public function getAktiv(): ?string
    {
        return $this->cAktiv;
    }

    public function getErstellt(): ?string
    {
        return $this->dErstellt;
    }

    public function getGanzenWKRabattieren(): int
    {
        return $this->nGanzenWKRabattieren;
    }

    public function getByCode(string $code = ''): Kupon|false
    {
        return $this->db->getCollection(
            'SELECT kKupon AS id 
                FROM tkupon
                WHERE cCode = :code
                LIMIT 1',
            ['code' => $code]
        )->map(fn(stdClass $e): self => new self((int)$e->id, $this->db))->first() ?? false;
    }

    /**
     * @return array<string, string>
     */
    public function getTranslation(int $id = 0): array
    {
        $translationList = [];
        foreach (Frontend::getLanguages() as $language) {
            $localized                             = $this->db->select(
                'tkuponsprache',
                'kKupon',
                $id,
                'cISOSprache',
                $language->getCode(),
                null,
                null,
                false,
                'cName'
            );
            $translationList[$language->getCode()] = $localized->cName ?? '';
        }

        return $translationList;
    }

    public function accept(): void
    {
        $cart   = Frontend::getCart();
        $logger = Shop::Container()->getLogService();
        if (
            (!empty($_SESSION['oVersandfreiKupon']) || !empty($_SESSION['VersandKupon']) || !empty($_SESSION['Kupon']))
            && isset($_POST['Kuponcode']) && $_POST['Kuponcode']
        ) {
            $cart->loescheSpezialPos(\C_WARENKORBPOS_TYP_KUPON);
        }
        $couponPrice = 0;
        if ($this->cWertTyp === 'festpreis') {
            $couponPrice = $this->fWert;
            if ($this->fWert > $cart->gibGesamtsummeWarenExt([\C_WARENKORBPOS_TYP_ARTIKEL], true)) {
                $couponPrice = $cart->gibGesamtsummeWarenExt([\C_WARENKORBPOS_TYP_ARTIKEL], true);
            }
            if (
                $this->nGanzenWKRabattieren === 0 && $this->fWert > CartHelper::getCouponProductsTotal(
                    $this,
                    $cart->PositionenArr
                )
            ) {
                $couponPrice = CartHelper::getCouponProductsTotal($this, $cart->PositionenArr);
            }
        } elseif ($this->cWertTyp === 'prozent') {
            // Alle Positionen prüfen ob der Kupon greift und falls ja, dann Position rabattieren
            if ($this->nGanzenWKRabattieren === 0) {
                $productNames = [];
                if (GeneralObject::hasCount('PositionenArr', $cart)) {
                    $productPrice = 0;
                    foreach ($cart->PositionenArr as $item) {
                        $tmpItem      = CartHelper::checkSetPercentCouponWKPos($item, $this);
                        $productPrice += $tmpItem->fPreis;
                        if (!empty($tmpItem->cName)) {
                            $productNames[] = $tmpItem->cName;
                        }
                    }
                    $couponPrice = ($productPrice / 100) * (float)$this->fWert;
                }
            } else { // Rabatt ermitteln für den ganzen WK
                $couponPrice = ($cart->gibGesamtsummeWarenExt([\C_WARENKORBPOS_TYP_ARTIKEL], true) / 100.0)
                    * $this->fWert;
            }
        }
        $special        = new stdClass();
        $special->cName = $this->translationList;
        $languageHelper = LanguageHelper::getInstance();
        $oldLangISO     = $languageHelper->getIso();
        foreach (Frontend::getLanguages() as $language) {
            $code = $language->getCode();
            if (
                $this->cWertTyp === 'prozent'
                && $this->nGanzenWKRabattieren === 0
                && $this->cKuponTyp !== self::TYPE_NEWCUSTOMER
            ) {
                $languageHelper->setzeSprache($code);
                $special->cName[$code]              .= ' ' . $this->fWert . '% ';
                $special->discountForArticle[$code] = $languageHelper->get('discountForArticle', 'checkout');
            } elseif ($this->cWertTyp === 'prozent') {
                $special->cName[$code] .= ' ' . $this->fWert . '%';
            }
        }
        $languageHelper->setzeSprache($oldLangISO);
        if (isset($productNames)) {
            $special->cArticleNameAffix = $productNames;
        }
        $type          = \C_WARENKORBPOS_TYP_KUPON;
        $handlesNotice = $logger instanceof Logger && $logger->isHandling(\JTLLOG_LEVEL_NOTICE);
        if ($this->cKuponTyp === self::TYPE_STANDARD) {
            $_SESSION['Kupon'] = $this;
            if ($handlesNotice === true) {
                $logger->notice('Der Standardkupon' . \print_r($this, true) . ' wurde genutzt.');
            }
        } elseif ($this->cKuponTyp === self::TYPE_NEWCUSTOMER) {
            $type = \C_WARENKORBPOS_TYP_NEUKUNDENKUPON;
            $cart->loescheSpezialPos(\C_WARENKORBPOS_TYP_NEUKUNDENKUPON);
            $_SESSION['NeukundenKupon']           = $this;
            $_SESSION['NeukundenKuponAngenommen'] = true;
            //@todo: erst loggen wenn wirklich bestellt wird. hier kann noch abgebrochen werden
            if ($handlesNotice === true) {
                $logger->notice('Der Neukundenkupon' . \print_r($this, true) . ' wurde genutzt.');
            }
        } elseif ($this->cKuponTyp === self::TYPE_SHIPPING) {
            // Darf nicht gelöscht werden sondern den Preis nur auf 0 setzen!
            $cart->setzeVersandfreiKupon();
            $_SESSION['VersandKupon'] = $this;
            $couponPrice              = 0;
            $special->cName           = $this->translationList;
            unset($_POST['Kuponcode']);
            $cart->erstelleSpezialPos(
                $special->cName,
                1,
                $couponPrice * -1,
                (int)$this->kSteuerklasse,
                $type
            );
            if ($handlesNotice === true) {
                $logger->notice('Der Versandkupon ' . \print_r($this, true) . ' wurde genutzt.');
            }
        }
        if ($this->cWertTyp === 'prozent' || $this->cWertTyp === 'festpreis') {
            unset($_POST['Kuponcode']);
            $cart->erstelleSpezialPos($special->cName, 1, $couponPrice * -1, $this->kSteuerklasse, $type);
        }
    }

    /**
     * @return Kupon[]
     */
    public function getNewCustomerCoupon(): array
    {
        $coupons            = [];
        $newCustomerCoupons = $this->db->selectAll(
            'tkupon',
            ['cKuponTyp', 'cAktiv'],
            [self::TYPE_NEWCUSTOMER, 'Y'],
            '*',
            'fWert DESC'
        );

        foreach ($newCustomerCoupons as $newCustomerCoupon) {
            if (isset($newCustomerCoupon->kKupon) && $newCustomerCoupon->kKupon > 0) {
                $coupons[] = new self((int)$newCustomerCoupon->kKupon, $this->db);
            }
        }

        return $coupons;
    }

    public function generateCode(
        int $len = 7,
        bool $lower = true,
        bool $upper = true,
        bool $numbers = true,
        string $prefix = '',
        string $suffix = ''
    ): string {
        $lowerString   = $lower ? 'abcdefghijklmnopqrstuvwxyz' : null;
        $upperString   = $upper ? 'ABCDEFGHIJKLMNOPQRSTUVWXYZ' : null;
        $numbersString = $numbers ? '0123456789' : null;
        $code          = '';
        $count         = $this->db->getSingleInt(
            'SELECT COUNT(*) AS cnt 
                FROM tkupon',
            'cnt'
        );
        while (
            empty($code) || ($count === 0
                ? empty($code)
                : $this->db->select('tkupon', 'cCode', $code))
        ) {
            $code = $prefix
                . \mb_substr(
                    \str_shuffle(\str_repeat($lowerString . $upperString . $numbersString, $len)),
                    0,
                    $len
                ) . $suffix;
        }

        return $code;
    }

    /**
     * @former altenKuponNeuBerechnen()
     * @since  5.0.0
     */
    public static function reCheck(): void
    {
        // Wenn Kupon vorhanden und prozentual auf ganzen Warenkorb, dann verwerfen und neu anlegen
        if (isset($_SESSION['Kupon']) && $_SESSION['Kupon']->cWertTyp === 'prozent') {
            $coupon = $_SESSION['Kupon'];
            unset($_SESSION['Kupon']);
            Frontend::getCart()->setzePositionsPreise();
            require_once \PFAD_ROOT . \PFAD_INCLUDES . 'bestellvorgang_inc.php';
            self::acceptCoupon($coupon);
        }
    }

    /**
     * @former kuponMoeglich()
     * @since  5.0.0
     */
    public static function couponsAvailable(?DbInterface $db = null): int
    {
        if (isset($_SESSION['NeukundenKuponAngenommen']) && $_SESSION['NeukundenKuponAngenommen']) {
            return 0;
        }
        $cart        = Frontend::getCart();
        $productQry  = '';
        $manufQry    = '';
        $categoryIDs = [];
        $productIDs  = [];
        $catQry      = '';
        $customerQry = '';
        $db          = $db ?? Shop::Container()->getDB();
        $prep        = [
            'tya'  => self::TYPE_SHIPPING,
            'tyb'  => self::TYPE_STANDARD,
            'sum'  => $cart->gibGesamtsummeWaren(true, false),
            'cgid' => Frontend::getCustomerGroup()->getID()
        ];
        foreach ($cart->PositionenArr as $key => $item) {
            if (\mb_strlen($item->Artikel->cArtNr ?? '') > 0) {
                $itemNmbrKey        = 'cArtNr' . $key;
                $prep[$itemNmbrKey] = \str_replace('%', '\%', $item->Artikel->cArtNr);
                $productQry         .= ' OR FIND_IN_SET(:' . $itemNmbrKey . ", REPLACE(cArtikel, ';', ',')) > 0";
            }
            if (\mb_strlen($item->Artikel->cHersteller ?? '') > 0) {
                $mnfKey        = 'mnf' . $key;
                $prep[$mnfKey] = $item->Artikel->kHersteller;
                $manufQry      .= ' OR FIND_IN_SET(:' . $mnfKey . ", REPLACE(cHersteller, ';', ',')) > 0";
            }
            if ($item->nPosTyp === \C_WARENKORBPOS_TYP_ARTIKEL && ($item->Artikel->kArtikel ?? 0) > 0) {
                $productID = (int)$item->Artikel->kArtikel;
                if (Product::isVariChild($productID)) {
                    $productID = Product::getParent($productID);
                }
                $productIDs[] = $productID;
            }
        }

        if (\count($productIDs) > 0) {
            $categoryIDs = $db->getInts(
                'SELECT DISTINCT kKategorie
                    FROM tkategorieartikel
                    WHERE kArtikel IN (' . \implode(',', $productIDs) . ')',
                'kKategorie'
            );
        }
        foreach ($categoryIDs as $i => $category) {
            $prep['cqid' . $i] = $category;
            $catQry            .= ' OR FIND_IN_SET(:cqid' . $i . ", REPLACE(cKategorien, ';', ',')) > 0";
        }
        if (Frontend::getCustomer()->getID() > 0) {
            $prep['cid'] = Frontend::getCustomer()->getID();
            $customerQry = " OR FIND_IN_SET(:cid, REPLACE(cKunden, ';', ',')) > 0";
        }

        $ok = $db->getSingleInt(
            "SELECT kKupon FROM tkupon
                WHERE cAktiv = 'Y'
                    AND NOW() BETWEEN dGueltigAb AND COALESCE(dGueltigBis, NOW())
                    AND fMindestbestellwert <= :sum
                    AND (cKuponTyp = :tya OR cKuponTyp = :tyb)
                    AND (kKundengruppe = -1
                        OR kKundengruppe = 0
                        OR kKundengruppe = :cgid)
                    AND (nVerwendungen = 0
                        OR nVerwendungen > nVerwendungenBisher)
                    AND (cArtikel = '' " . $productQry . ")
                    AND (cHersteller = '' OR cHersteller = '-1' " . $manufQry . ")
                    AND (cKategorien = ''
                        OR cKategorien = '-1' " . $catQry . ")
                    AND (cKunden = ''
                        OR cKunden = '-1' " . $customerQry . ') LIMIT 1',
            'kKupon',
            $prep
        );

        return (int)($ok > 0);
    }

    /**
     * @param object $coupon
     * @return array<string, int>
     * @former checkeKupon()
     * @since  5.0.0
     */
    public static function checkCoupon(object $coupon): array
    {
        if (\get_class($coupon) !== __CLASS__) {
            $coupon = new self((int)$coupon->kKupon);
        }

        return $coupon->check();
    }

    /**
     * @return array<string, int>
     * @former checkeKupon()
     * @since  5.2.0
     */
    public function check(): array
    {
        require_once \PFAD_ROOT . \PFAD_INCLUDES . 'bestellvorgang_inc.php';
        $ret = [];
        if ($this->cAktiv !== 'Y') {
            $ret['ungueltig'] = 1;
        } elseif (!empty($this->dGueltigBis) && \date_create($this->dGueltigBis) < \date_create()) {
            $ret['ungueltig'] = 2;
        } elseif ($this->dGueltigAb !== null && (new DateTime($this->dGueltigAb)) > \date_create()) {
            $ret['ungueltig'] = 3;
        } elseif (
            $this->fMindestbestellwert > Frontend::getCart()->gibGesamtsummeWarenExt(
                [\C_WARENKORBPOS_TYP_ARTIKEL],
                true
            )
            || ($this->cWertTyp === 'festpreis'
                && $this->nGanzenWKRabattieren === 0
                && $this->fMindestbestellwert > CartHelper::getCouponProductsTotal(
                    $this,
                    Frontend::getCart()->PositionenArr
                )
            )
        ) {
            //minimum order value not reached for whole cart or the products which are valid for this coupon
            $ret['ungueltig'] = 4;
        } elseif (
            $this->kKundengruppe > 0
            && (int)$this->kKundengruppe !== Frontend::getCustomerGroup()->getID()
        ) {
            $ret['ungueltig'] = 5;
        } elseif ($this->nVerwendungen > 0 && $this->nVerwendungen <= $this->nVerwendungenBisher) {
            $ret['ungueltig'] = 6;
        } elseif (!CartHelper::cartHasCouponValidProducts($this, Frontend::getCart()->PositionenArr)) {
            $ret['ungueltig'] = 7;
        } elseif (!CartHelper::cartHasCouponValidCategories($this, Frontend::getCart()->PositionenArr)) {
            $ret['ungueltig'] = 8;
        } elseif (
            $this->cKuponTyp !== self::TYPE_NEWCUSTOMER
            && (int)$this->cKunden !== -1
            && (!isset($_SESSION['Kunde']->kKunde)
                || (!empty($_SESSION['Kunde']->kKunde)
                    && !\str_contains($this->cKunden, $_SESSION['Kunde']->kKunde . ';')))
        ) {
            //invalid for account
            $ret['ungueltig'] = 9;
        } elseif (
            $this->cKuponTyp === self::TYPE_SHIPPING
            && isset($_SESSION['Lieferadresse'])
            && !\str_contains($this->cLieferlaender, $_SESSION['Lieferadresse']->cLand)
        ) {
            $ret['ungueltig'] = 10;
        } elseif (!CartHelper::cartHasCouponValidManufacturers($this, Frontend::getCart()->PositionenArr)) {
            $ret['ungueltig'] = 12;
        } elseif (!empty($_SESSION['Kunde']->cMail)) {
            if (
                $this->cKuponTyp === self::TYPE_NEWCUSTOMER
                && self::newCustomerCouponUsed($_SESSION['Kunde']->cMail)
            ) {
                $ret['ungueltig'] = 11;
            } elseif (!empty($this->nVerwendungenProKunde) && $this->nVerwendungenProKunde > 0) {
                // check if max usage of coupon is reached for cutomer
                $countCouponUsed = $this->db->getSingleObject(
                    'SELECT nVerwendungen
                         FROM tkuponkunde
                         WHERE kKupon = :coupon
                            AND cMail = :email',
                    [
                        'coupon' => (int)$this->kKupon,
                        'email'  => self::hash($_SESSION['Kunde']->cMail)
                    ]
                );
                if ($countCouponUsed !== null && $countCouponUsed->nVerwendungen >= $this->nVerwendungenProKunde) {
                    $ret['ungueltig'] = 6;
                }
            }
        }

        return $ret;
    }

    /**
     * @return string[]
     */
    public function validate(): array
    {
        $errors = [];
        if ($this->cName === '') {
            $errors[] = \__('errorCouponNameMissing');
        }
        if (
            ($this->cKuponTyp === self::TYPE_STANDARD || $this->cKuponTyp === self::TYPE_NEWCUSTOMER)
            && $this->fWert < 0
        ) {
            $errors[] = \__('errorCouponValueNegative');
        }
        if ($this->fMindestbestellwert < 0) {
            $errors[] = \__('errorCouponMinOrderValueNegative');
        }
        if ($this->cKuponTyp === self::TYPE_SHIPPING && $this->cLieferlaender === '') {
            $errors[] = \__('errorCouponISOMissing');
        }
        if (isset($this->massCreationCoupon)) {
            $codeLen = (int)$this->massCreationCoupon->hashLength
                + (int)\mb_strlen($this->massCreationCoupon->prefixHash)
                + (int)\mb_strlen($this->massCreationCoupon->suffixHash);
            if ($codeLen > 32) {
                $errors[] = \__('errorCouponCodeLong');
            }
            if ($codeLen < 2) {
                $errors[] = \__('errorCouponCodeShort');
            }
            if (
                !$this->massCreationCoupon->lowerCase
                && !$this->massCreationCoupon->upperCase
                && !$this->massCreationCoupon->numbersHash
            ) {
                $errors[] = \__('errorCouponCodeOptionSelect');
            }
        } elseif (\mb_strlen($this->cCode ?? '') > 32) {
            $errors[] = \__('errorCouponCodeLong');
        }
        if (
            $this->cCode !== ''
            && !isset($this->massCreationCoupon)
            && ($this->cKuponTyp === self::TYPE_STANDARD || $this->cKuponTyp === self::TYPE_SHIPPING)
        ) {
            $queryRes = $this->db->getSingleObject(
                'SELECT kKupon
                    FROM tkupon
                    WHERE cCode = :cCode
                        AND kKupon != :kKupon',
                ['cCode' => $this->cCode, 'kKupon' => $this->kKupon]
            );
            if ($queryRes !== null) {
                $errors[] = \__('errorCouponCodeDuplicate');
            }
        }

        $productNos = [];
        foreach (Text::parseSSK($this->cArtikel) as $productNo) {
            $res = $this->db->select('tartikel', 'cArtNr', $productNo);
            if ($res === null) {
                $errors[] = \sprintf(\__('errorProductNumberNotFound'), $productNo);
            } else {
                $productNos[] = $productNo;
            }
        }

        $this->cArtikel = Text::createSSK($productNos);
        if ($this->cKuponTyp === self::TYPE_SHIPPING) {
            $countryHelper = Shop::Container()->getCountryService();
            foreach (Text::parseSSK($this->cLieferlaender) as $isoCode) {
                if ($countryHelper->getCountry($isoCode) === null) {
                    $errors[] = \sprintf(\__('errorISOInvalid'), $isoCode);
                }
            }
        }

        $validFrom  = \date_create($this->dGueltigAb ?? '');
        $validUntil = \date_create($this->dGueltigBis ?? '');
        if ($validFrom === false) {
            $errors[] = \__('errorPeriodBeginFormat');
        }
        if ($validUntil === false) {
            $errors[] = \__('errorPeriodEndFormat');
        }
        $openEnd = $this->dGueltigBis === null;
        if ($validFrom !== false && $validUntil !== false && $validFrom > $validUntil && $openEnd === false) {
            $errors[] = \__('errorPeriodEndAfterBegin');
        }

        return $errors;
    }

    /**
     * check if a new customer coupon was already used for an email
     */
    public static function newCustomerCouponUsed(string $email): bool
    {
        $res = Shop::Container()->getDB()->getSingleObject(
            'SELECT kKuponFlag
                FROM tkuponflag
                WHERE cEmailHash = :email
                  AND cKuponTyp = :newCustomer',
            [
                'email'       => self::hash($email),
                'newCustomer' => self::TYPE_NEWCUSTOMER
            ]
        );

        return $res !== null;
    }

    /**
     * @param object $coupon
     * @former kuponAnnehmen()
     * @since  5.0.0
     */
    public static function acceptCoupon(object $coupon): void
    {
        if (\get_class($coupon) !== __CLASS__) {
            $coupon = new self((int)$coupon->kKupon);
        }
        $coupon->accept();
    }

    /**
     * @former resetNeuKundenKupon()
     * @since  5.0.0
     */
    public static function resetNewCustomerCoupon(bool $priceRecalculation = true): void
    {
        unset($_SESSION['NeukundenKupon'], $_SESSION['NeukundenKuponAngenommen']);
        $cart = Frontend::getCart();
        if ($cart->posTypEnthalten(\C_WARENKORBPOS_TYP_NEUKUNDENKUPON)) {
            $cart->loescheSpezialPos(\C_WARENKORBPOS_TYP_NEUKUNDENKUPON);
            if ($priceRecalculation) {
                $cart->setzePositionsPreise();
            }
        }
    }

    public static function hash(string $strToHash, bool $strtolower = true): string
    {
        return $strToHash === ''
            ? ''
            : \hash(
                'sha256',
                $strtolower ? \mb_convert_case($strToHash, \MB_CASE_LOWER) : $strToHash
            );
    }

    /**
     * @return array<string, string>
     */
    public static function getCouponTypes(): array
    {
        return [
            'newCustomer' => self::TYPE_NEWCUSTOMER,
            'standard'    => self::TYPE_STANDARD,
            'shipping'    => self::TYPE_SHIPPING
        ];
    }

    public static function mapCouponErrorMessage(int $errorCode, bool $createAlert = true): ?string
    {
        switch ($errorCode) {
            case 0:
                Shop::Container()->getAlertService()->addSuccess(Shop::Lang()->get('couponSuccess'), 'couponSuccess');
                return null;
            case 1:
            case 2:
            case 3:
            case 4:
            case 5:
            case 6:
            case 7:
            case 8:
            case 9:
            case 10:
            case 12:
                $errorMessage = Shop::Lang()->get('couponErr' . $errorCode);
                break;
            case 11:
                $errorMessage = Shop::Lang()->get('invalidCouponCode', 'checkout');
                break;
            default:
                $errorMessage = Shop::Lang()->get('couponErr99');
                break;
        }
        if ($createAlert) {
            Shop::Container()->getAlertService()->addDanger(
                $errorMessage,
                'couponError',
                ['saveInSession' => true]
            );
        }

        return $errorMessage;
    }
}
