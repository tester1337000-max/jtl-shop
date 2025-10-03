<?php

declare(strict_types=1);

namespace JTL\Checkout;

use JTL\Helpers\GeneralObject;
use JTL\Shop;

/**
 * Class ZahlungsInfo
 * @package JTL\Checkout
 */
class ZahlungsInfo
{
    public ?int $kZahlungsInfo = null;

    public ?int $kBestellung = null;

    public ?int $kKunde = null;

    public ?string $cBankName = null;

    public ?string $cBLZ = null;

    public ?string $cBIC = null;

    public ?string $cIBAN = null;

    public ?string $cKontoNr = null;

    public ?string $cKartenNr = null;

    public ?string $cGueltigkeit = null;

    public ?string $cCVV = null;

    public ?string $cKartenTyp = null;

    public ?string $cInhaber = null;

    public ?string $cVerwendungszweck = null;

    public ?string $cAbgeholt = null;

    public function __construct(int $id = 0, int $orderID = 0)
    {
        if ($id > 0 || $orderID > 0) {
            $this->loadFromDB($id, $orderID);
        }
    }

    public function loadFromDB(int $id, int $orderID): self
    {
        $obj = null;
        if ($id > 0) {
            $obj = Shop::Container()->getDB()->select('tzahlungsinfo', 'kZahlungsInfo', $id);
        } elseif ($orderID > 0) {
            $obj = Shop::Container()->getDB()->select('tzahlungsinfo', 'kBestellung', $orderID);
        }
        if ($obj === null) {
            return $this;
        }
        $obj->kZahlungsInfo = (int)$obj->kZahlungsInfo;
        $obj->kBestellung   = (int)$obj->kBestellung;
        $obj->kKunde        = (int)$obj->kKunde;
        foreach (\array_keys(\get_object_vars($obj)) as $member) {
            $this->$member = $obj->$member;
        }
        if ($this->kZahlungsInfo > 0) {
            $this->entschluesselZahlungsinfo();
        }

        return $this;
    }

    public function verschluesselZahlungsinfo(): self
    {
        $cryptoService = Shop::Container()->getCryptoService();

        $this->cBankName         = $cryptoService->encryptXTEA(\trim($this->cBankName ?? ''));
        $this->cKartenNr         = $cryptoService->encryptXTEA(\trim($this->cKartenNr ?? ''));
        $this->cCVV              = $cryptoService->encryptXTEA(\trim($this->cCVV ?? ''));
        $this->cKontoNr          = $cryptoService->encryptXTEA(\trim($this->cKontoNr ?? ''));
        $this->cBLZ              = $cryptoService->encryptXTEA(\trim($this->cBLZ ?? ''));
        $this->cIBAN             = $cryptoService->encryptXTEA(\trim($this->cIBAN ?? ''));
        $this->cBIC              = $cryptoService->encryptXTEA(\trim($this->cBIC ?? ''));
        $this->cInhaber          = $cryptoService->encryptXTEA(\trim($this->cInhaber ?? ''));
        $this->cVerwendungszweck = $cryptoService->encryptXTEA(\trim($this->cVerwendungszweck ?? ''));

        return $this;
    }

    public function entschluesselZahlungsinfo(): self
    {
        $cryptoService = Shop::Container()->getCryptoService();

        $this->cBankName         = \trim($cryptoService->decryptXTEA($this->cBankName ?? ''));
        $this->cKartenNr         = \trim($cryptoService->decryptXTEA($this->cKartenNr ?? ''));
        $this->cCVV              = \trim($cryptoService->decryptXTEA($this->cCVV ?? ''));
        $this->cKontoNr          = \trim($cryptoService->decryptXTEA($this->cKontoNr ?? ''));
        $this->cBLZ              = \trim($cryptoService->decryptXTEA($this->cBLZ ?? ''));
        $this->cIBAN             = \trim($cryptoService->decryptXTEA($this->cIBAN ?? ''));
        $this->cBIC              = \trim($cryptoService->decryptXTEA($this->cBIC ?? ''));
        $this->cInhaber          = \trim($cryptoService->decryptXTEA($this->cInhaber ?? ''));
        $this->cVerwendungszweck = \trim($cryptoService->decryptXTEA($this->cVerwendungszweck ?? ''));

        return $this;
    }

    public function insertInDB(): int
    {
        $this->cAbgeholt = 'N';
        $this->verschluesselZahlungsinfo();
        $obj = GeneralObject::copyMembers($this);
        unset($obj->kZahlungsInfo);
        $this->kZahlungsInfo = Shop::Container()->getDB()->insert('tzahlungsinfo', $obj);
        $this->entschluesselZahlungsinfo();

        return $this->kZahlungsInfo;
    }

    public function updateInDB(): int
    {
        $this->verschluesselZahlungsinfo();
        $obj = GeneralObject::copyMembers($this);
        $res = Shop::Container()->getDB()->update('tzahlungsinfo', 'kZahlungsInfo', $obj->kZahlungsInfo, $obj);
        $this->entschluesselZahlungsinfo();

        return $res;
    }
}
