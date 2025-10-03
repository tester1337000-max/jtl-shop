<?php

declare(strict_types=1);

namespace JTL\Extensions\Config;

use JsonSerializable;
use JTL\Helpers\Text;
use JTL\Media\Image;
use JTL\Media\MultiSizeImage;
use JTL\Nice;
use JTL\Shop;

/**
 * Class Group
 * @package JTL\Extensions\Config
 */
class Group implements JsonSerializable
{
    use MultiSizeImage;

    protected ?string $cBildPfad = null;

    protected int $nMin = 0;

    protected int $nMax = 0;

    protected int $nTyp = 0;

    public string $cKommentar = '';

    public ?GroupLocalization $oSprache = null;

    /**
     * @var Item[]
     */
    public array $oItem_arr = [];

    public bool $bAktiv = false;

    public function __construct(protected int $kKonfiggruppe = 0, int $languageID = 0)
    {
        $this->setImageType(Image::TYPE_CONFIGGROUP);
        if ($this->kKonfiggruppe > 0) {
            $this->loadFromDB($this->kKonfiggruppe, $languageID);
        }
    }

    public static function checkLicense(): bool
    {
        return Nice::getInstance()->checkErweiterung(\SHOP_ERWEITERUNG_KONFIGURATOR);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        if ($this->oSprache === null) {
            $this->oSprache = new GroupLocalization($this->kKonfiggruppe);
        }
        $override = [
            'kKonfiggruppe' => $this->kKonfiggruppe,
            'cBildPfad'     => $this->getBildPfad(),
            'nMin'          => (float)$this->nMin,
            'nMax'          => (float)$this->nMax,
            'nTyp'          => $this->nTyp,
            'fInitial'      => $this->getInitQuantity(),
            'bAnzahl'       => $this->getAnzeigeTyp() === \KONFIG_ANZEIGE_TYP_RADIO
                || $this->getAnzeigeTyp() === \KONFIG_ANZEIGE_TYP_DROPDOWN,
            'cName'         => $this->oSprache->getName(),
            'cBeschreibung' => $this->oSprache->getBeschreibung(),
            'oItem_arr'     => $this->oItem_arr
        ];
        $result   = \array_merge(\get_object_vars($this), $override);

        return Text::utf8_convert_recursive($result);
    }

    private function loadFromDB(int $id = 0, int $languageID = 0): self
    {
        $data = Shop::Container()->getDB()->select('tkonfiggruppe', 'kKonfiggruppe', $id);
        if ($data === null || !isset($data->kKonfiggruppe) || $data->kKonfiggruppe <= 0) {
            Shop::Container()->getLogService()->error('Cannot load config group with id {id}', ['id' => $id]);

            return $this;
        }
        $languageID = $languageID ?: Shop::getLanguageID();

        $this->kKonfiggruppe = (int)$data->kKonfiggruppe;
        $this->nMin          = (int)$data->nMin;
        $this->nMax          = (int)$data->nMax;
        $this->nTyp          = (int)$data->nTyp;
        $this->cKommentar    = $data->cKommentar;
        $this->cBildPfad     = $data->cBildPfad;
        $this->oSprache      = new GroupLocalization($this->kKonfiggruppe, $languageID);
        $this->oItem_arr     = Item::fetchAll($this->kKonfiggruppe, $languageID);
        $this->generateAllImageSizes(true, 1, $this->cBildPfad);
        $this->generateAllImageDimensions(1, $this->cBildPfad);

        return $this;
    }

    public function setKonfiggruppe(int $id): self
    {
        $this->kKonfiggruppe = $id;

        return $this;
    }

    public function setBildPfad(string $path): self
    {
        $this->cBildPfad = $path;

        return $this;
    }

    public function setAnzeigeTyp(int $type): self
    {
        $this->nTyp = $type;

        return $this;
    }

    public function getKonfiggruppe(): ?int
    {
        return $this->kKonfiggruppe;
    }

    public function getID(): int
    {
        return $this->kKonfiggruppe;
    }

    public function getBildPfad(): ?string
    {
        return !empty($this->cBildPfad)
            ? \PFAD_KONFIGURATOR_KLEIN . $this->cBildPfad
            : null;
    }

    public function getMin(): ?int
    {
        return $this->nMin;
    }

    public function getMax(): ?int
    {
        return $this->nMax;
    }

    public function getAuswahlTyp(): int
    {
        return 0;
    }

    public function getAnzeigeTyp(): ?int
    {
        return $this->nTyp;
    }

    public function getKommentar(): ?string
    {
        return $this->cKommentar;
    }

    public function getSprache(): ?GroupLocalization
    {
        return $this->oSprache;
    }

    public function getItemCount(): int
    {
        return Shop::Container()->getDB()->getSingleInt(
            'SELECT COUNT(*) AS cnt 
                FROM tkonfigitem 
                WHERE kKonfiggruppe = :gid',
            'cnt',
            ['gid' => $this->kKonfiggruppe]
        );
    }

    public function quantityEquals(): bool
    {
        if (\count($this->oItem_arr) === 0) {
            return false;
        }
        $equal = false;
        $item  = $this->oItem_arr[0];
        if ($item->getMin() === $item->getMax()) {
            $equal = true;
            $nKey  = $item->getMin();
            foreach ($this->oItem_arr as $item) {
                if (!($item->getMin() === $item->getMax() && $item->getMin() === $nKey)) {
                    $equal = false;
                }
            }
        }

        return $equal;
    }

    public function getInitQuantity(): float
    {
        $qty = 1.0;
        foreach ($this->oItem_arr as $item) {
            if ($item->getSelektiert()) {
                $qty = $item->getInitial();
            }
        }

        return $qty;
    }

    public function minItemsInStock(): bool
    {
        $inStockCount = 0;
        foreach ($this->oItem_arr as $item) {
            if ($item->isInStock() && ++$inStockCount >= $this->nMin) {
                return true;
            }
        }

        return false;
    }
}
