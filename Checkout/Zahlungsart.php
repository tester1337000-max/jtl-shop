<?php

declare(strict_types=1);

namespace JTL\Checkout;

use JTL\MainModel;
use JTL\Shop;

/**
 * Class Zahlungsart
 * @package JTL\Checkout
 */
class Zahlungsart extends MainModel
{
    public ?int $kZahlungsart = null;

    public ?string $cName = null;

    public ?string $cModulId = null;

    public ?string $cKundengruppen = null;

    public ?string $cZusatzschrittTemplate = null;

    public ?string $cPluginTemplate = null;

    public ?string $cBild = null;

    public int $nSort = 0;

    public int $nMailSenden = 0;

    public int $nActive = 0;

    public ?string $cAnbieter = null;

    public ?string $cTSCode = null;

    public int $nWaehrendBestellung = 0;

    public int $nCURL = 0;

    public int $nSOAP = 0;

    public int $nSOCKETS = 0;

    public int $nNutzbar = 0;

    public ?string $cHinweisText = null;

    public ?string $cHinweisTextShop = null;

    public ?string $cGebuehrname = null;

    /**
     * @var array<mixed>|null
     */
    public ?array $einstellungen = null;

    public bool $bPayAgain = false;

    public function getZahlungsart(): ?int
    {
        return $this->kZahlungsart;
    }

    public function setZahlungsart(int|string $kZahlungsart): self
    {
        $this->kZahlungsart = (int)$kZahlungsart;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->cName;
    }

    public function setName(string $name): self
    {
        $this->cName = $name;

        return $this;
    }

    public function getModulId(): ?string
    {
        return $this->cModulId;
    }

    public function setModulId(string $cModulId): self
    {
        $this->cModulId = $cModulId;

        return $this;
    }

    public function getKundengruppen(): ?string
    {
        return $this->cKundengruppen;
    }

    public function setKundengruppen(string $cKundengruppen): self
    {
        $this->cKundengruppen = $cKundengruppen;

        return $this;
    }

    public function getZusatzschrittTemplate(): ?string
    {
        return $this->cZusatzschrittTemplate;
    }

    public function setZusatzschrittTemplate(?string $cZusatzschrittTemplate): self
    {
        $this->cZusatzschrittTemplate = $cZusatzschrittTemplate;

        return $this;
    }

    public function getPluginTemplate(): ?string
    {
        return $this->cPluginTemplate;
    }

    public function setPluginTemplate(?string $cPluginTemplate): self
    {
        $this->cPluginTemplate = $cPluginTemplate;

        return $this;
    }

    public function getBild(): ?string
    {
        return $this->cBild;
    }

    public function setBild(string $cBild): self
    {
        $this->cBild = $cBild;

        return $this;
    }

    public function getSort(): int
    {
        return $this->nSort;
    }

    public function setSort(int|string $sort): self
    {
        $this->nSort = (int)$sort;

        return $this;
    }

    public function getMailSenden(): int
    {
        return $this->nMailSenden;
    }

    public function setMailSenden(int|string $nMailSenden): self
    {
        $this->nMailSenden = (int)$nMailSenden;

        return $this;
    }

    public function getActive(): int
    {
        return $this->nActive;
    }

    public function setActive(int|string $nActive): self
    {
        $this->nActive = (int)$nActive;

        return $this;
    }

    public function getAnbieter(): ?string
    {
        return $this->cAnbieter;
    }

    public function setAnbieter(string $cAnbieter): self
    {
        $this->cAnbieter = $cAnbieter;

        return $this;
    }

    public function getTSCode(): ?string
    {
        return $this->cTSCode;
    }

    public function setTSCode(string $cTSCode): self
    {
        $this->cTSCode = $cTSCode;

        return $this;
    }

    public function getWaehrendBestellung(): int
    {
        return $this->nWaehrendBestellung;
    }

    public function setWaehrendBestellung(int|string $nWaehrendBestellung): self
    {
        $this->nWaehrendBestellung = (int)$nWaehrendBestellung;

        return $this;
    }

    public function getCURL(): int
    {
        return $this->nCURL;
    }

    public function setCURL(int|string $nCURL): self
    {
        $this->nCURL = (int)$nCURL;

        return $this;
    }

    public function getSOAP(): ?int
    {
        return $this->nSOAP;
    }

    public function setSOAP(int|string $nSOAP): self
    {
        $this->nSOAP = (int)$nSOAP;

        return $this;
    }

    public function getSOCKETS(): ?int
    {
        return $this->nSOCKETS;
    }

    public function setSOCKETS(int|string $nSOCKETS): self
    {
        $this->nSOCKETS = (int)$nSOCKETS;

        return $this;
    }

    public function getNutzbar(): int
    {
        return $this->nNutzbar;
    }

    public function setNutzbar(int|string $nNutzbar): self
    {
        $this->nNutzbar = (int)$nNutzbar;

        return $this;
    }

    public function getHinweisText(): ?string
    {
        return $this->cHinweisText;
    }

    public function setHinweisText(?string $cHinweisText): self
    {
        $this->cHinweisText = $cHinweisText ?? '';

        return $this;
    }

    public function getHinweisTextShop(): ?string
    {
        return $this->cHinweisTextShop;
    }

    public function setHinweisTextShop(?string $cHinweisTextShop): self
    {
        $this->cHinweisTextShop = $cHinweisTextShop ?? '';

        return $this;
    }

    public function getGebuehrname(): ?string
    {
        return $this->cGebuehrname;
    }

    public function setGebuehrname(?string $cGebuehrname): self
    {
        $this->cGebuehrname = $cGebuehrname ?? '';

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function load(int $id, mixed $data = null, mixed $option = null): self
    {
        if ($id <= 0) {
            return $this;
        }
        $iso  = $option['iso'] ?? Shop::getLanguageCode();
        $item = Shop::Container()->getDB()->getSingleObject(
            'SELECT z.kZahlungsart, COALESCE(s.cName, z.cName) AS cName, z.cModulId, z.cKundengruppen,
                    z.cZusatzschrittTemplate, z.cPluginTemplate, z.cBild, z.nSort, z.nMailSenden, z.nActive,
                    z.cAnbieter, z.cTSCode, z.nWaehrendBestellung, z.nCURL, z.nSOAP, z.nSOCKETS, z.nNutzbar,
                    s.cISOSprache, s.cGebuehrname, s.cHinweisText, s.cHinweisTextShop
                FROM tzahlungsart AS z
                LEFT JOIN tzahlungsartsprache AS s 
                    ON s.kZahlungsart = z.kZahlungsart
                    AND s.cISOSprache = :iso
                WHERE z.kZahlungsart = :pmID
                LIMIT 1',
            [
                'iso'  => $iso,
                'pmID' => $id
            ]
        );
        if ($item !== null) {
            $this->loadObject($item);
        }

        return $this;
    }

    /**
     * @return Zahlungsart[]
     */
    public static function loadAll(bool $active = true, ?string $iso = null): array
    {
        $payments = [];
        $where    = $active ? ' WHERE z.nActive = 1' : '';
        $iso      = $iso ?? Shop::getLanguageCode();
        $data     = Shop::Container()->getDB()->getObjects(
            'SELECT *
                FROM tzahlungsart AS z
                LEFT JOIN tzahlungsartsprache AS s 
                    ON s.kZahlungsart = z.kZahlungsart
                    AND s.cISOSprache = :iso' . $where,
            ['iso' => $iso]
        );
        foreach ($data as $obj) {
            $payments[] = new self(null, $obj);
        }

        return $payments;
    }
}
