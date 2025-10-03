<?php

declare(strict_types=1);

namespace JTL\Checkout;

use JTL\Helpers\Text;
use JTL\Language\LanguageHelper;
use JTL\Shop;
use stdClass;

/**
 * Class Adresse
 * @package JTL
 */
class Adresse
{
    public ?string $cAnrede = null;

    public ?string $cVorname = null;

    public ?string $cNachname = null;

    public ?string $cTitel = null;

    public ?string $cFirma = null;

    public ?string $cStrasse = null;

    public ?string $cAdressZusatz = null;

    public ?string $cPLZ = null;

    public ?string $cOrt = null;

    public ?string $cBundesland = null;

    public ?string $cLand = null;

    public ?string $cTel = null;

    public ?string $cMobil = null;

    public ?string $cFax = null;

    public ?string $cMail = null;

    public ?string $cHausnummer = null;

    public ?string $cZusatz = null;

    /**
     * @var string[]
     */
    protected static array $encodedProperties = [
        'cNachname',
        'cFirma',
        'cZusatz',
        'cStrasse'
    ];

    public function __construct()
    {
    }

    public function encrypt(): self
    {
        $cyptoService = Shop::Container()->getCryptoService();
        foreach (self::$encodedProperties as $property) {
            $this->$property = $cyptoService->encryptXTEA(\trim((string)($this->$property ?? '')));
        }

        return $this;
    }

    public function decrypt(): self
    {
        $cryptoService = Shop::Container()->getCryptoService();
        foreach (self::$encodedProperties as $property) {
            if ($this->$property !== null) {
                $this->$property = \trim($cryptoService->decryptXTEA($this->$property));
                // Workaround: nur nach Update relevant (SHOP-5956)
                // verschlüsselte Shop4-Daten sind noch Latin1 kodiert und müssen nach UTF-8 konvertiert werden
                if (!Text::is_utf8($this->$property)) {
                    $this->$property = Text::convertUTF8($this->$property);
                }
            }
        }

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return \get_object_vars($this);
    }

    public function toObject(): stdClass
    {
        return (object)$this->toArray();
    }

    /**
     * @param array<mixed> $array
     */
    public function fromArray(array $array): self
    {
        foreach ($array as $key => $value) {
            if (\property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }

        return $this;
    }

    public function fromObject(object $object): self
    {
        return $this->fromArray((array)$object);
    }

    public function mappeAnrede(?string $anrede): string
    {
        return match (\mb_convert_case($anrede ?? '', \MB_CASE_LOWER)) {
            'm'     => Shop::Lang()->get('salutationM'),
            'w'     => Shop::Lang()->get('salutationW'),
            default => '',
        };
    }

    public static function checkISOCountryCode(string $iso): string
    {
        \preg_match('/[a-zA-Z]{2}/', $iso, $matches);
        if (\mb_strlen($matches[0]) !== \mb_strlen($iso)) {
            $o = LanguageHelper::getIsoCodeByCountryName($iso);
            if ($o !== 'noISO' && $o !== '') {
                $iso = $o;
            }
        }

        return $iso;
    }
}
