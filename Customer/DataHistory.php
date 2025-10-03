<?php

declare(strict_types=1);

namespace JTL\Customer;

use Exception;
use JTL\Helpers\GeneralObject;
use JTL\MainModel;
use JTL\Shop;
use stdClass;

/**
 * Class DataHistory
 * @package JTL\Customer
 */
class DataHistory extends MainModel
{
    public int $kKundendatenHistory = 0;

    public int $kKunde = 0;

    public ?string $cJsonAlt = null;

    public ?string $cJsonNeu = null;

    public ?string $cQuelle = null;

    public string $dErstellt = '';

    public const QUELLE_MEINKONTO = 'Mein Konto';

    public const QUELLE_BESTELLUNG = 'Bestellvorgang';

    public const QUELLE_DBES = 'Wawi Abgleich';

    public function getKundendatenHistory(): int
    {
        return $this->kKundendatenHistory;
    }

    public function setKundendatenHistory(int|string $kKundendatenHistory): self
    {
        $this->kKundendatenHistory = (int)$kKundendatenHistory;

        return $this;
    }

    public function getKunde(): int
    {
        return $this->kKunde;
    }

    public function setKunde(int|string $kKunde): self
    {
        $this->kKunde = (int)$kKunde;

        return $this;
    }

    public function getJsonAlt(): ?string
    {
        return $this->cJsonAlt;
    }

    public function setJsonAlt(string $cJsonAlt): self
    {
        $this->cJsonAlt = $cJsonAlt;

        return $this;
    }

    public function getJsonNeu(): ?string
    {
        return $this->cJsonNeu;
    }

    public function setJsonNeu(string $cJsonNeu): self
    {
        $this->cJsonNeu = $cJsonNeu;

        return $this;
    }

    public function getQuelle(): ?string
    {
        return $this->cQuelle;
    }

    public function setQuelle(string $cQuelle): self
    {
        $this->cQuelle = $cQuelle;

        return $this;
    }

    public function getErstellt(): ?string
    {
        return $this->dErstellt;
    }

    public function setErstellt(string $dErstellt): self
    {
        $this->dErstellt = (\mb_convert_case($dErstellt, \MB_CASE_UPPER) === 'NOW()')
            ? \date('Y-m-d H:i:s')
            : $dErstellt;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function load(int $id, mixed $data = null, mixed $option = null): self
    {
        $history = Shop::Container()->getDB()->select('tkundendatenhistory', 'kKundendatenHistory', $id);
        if ($history !== null && $history->kKundendatenHistory > 0) {
            $this->loadObject($history);
        }

        return $this;
    }

    /**
     * @param bool $primary
     * @return ($primary is true ? int|false : bool)
     */
    public function save(bool $primary = true): bool|int
    {
        $ins = new stdClass();
        foreach (\array_keys(\get_object_vars($this)) as $member) {
            $ins->$member = $this->$member;
        }
        unset($ins->kKundendatenHistory);

        $key = Shop::Container()->getDB()->insert('tkundendatenhistory', $ins);
        if ($key < 1) {
            return false;
        }

        return $primary ? $key : true;
    }

    public function update(): int
    {
        \trigger_error(__METHOD__ . ' is deprecated.', \E_USER_DEPRECATED);
        $members = \array_keys(\get_object_vars($this));
        if (\count($members) === 0) {
            throw new Exception('ERROR: Object has no members!');
        }
        $upd = new stdClass();
        foreach ($members as $member) {
            $method = 'get' . \mb_substr($member, 1);
            if (\method_exists($this, $method)) {
                $upd->$member = $this->$method();
            }
        }

        return Shop::Container()->getDB()->updateRow(
            'tkundendatenhistory',
            'kKundendatenHistory',
            $this->getKundendatenHistory(),
            $upd
        );
    }

    public function delete(): int
    {
        return Shop::Container()->getDB()->delete(
            'tkundendatenhistory',
            'kKundendatenHistory',
            $this->getKundendatenHistory()
        );
    }

    /**
     * @throws \JsonException
     */
    public static function saveHistory(Customer $old, Customer $new, string $source): bool
    {
        if ($old->dGeburtstag === null) {
            $old->dGeburtstag = '';
        }
        if ($new->dGeburtstag === null) {
            $new->dGeburtstag = '';
        }

        $new->cPasswort = $old->cPasswort;

        if (Customer::isEqual($old, $new)) {
            return true;
        }
        $cryptoService = Shop::Container()->getCryptoService();
        $old           = GeneralObject::deepCopy($old);
        $new           = GeneralObject::deepCopy($new);
        // Encrypt Old
        $old->cNachname = $cryptoService->encryptXTEA(\trim($old->cNachname ?? ''));
        $old->cFirma    = $cryptoService->encryptXTEA(\trim($old->cFirma ?? ''));
        $old->cStrasse  = $cryptoService->encryptXTEA(\trim($old->cStrasse ?? ''));
        // Encrypt New
        $new->cNachname = $cryptoService->encryptXTEA(\trim($new->cNachname ?? ''));
        $new->cFirma    = $cryptoService->encryptXTEA(\trim($new->cFirma ?? ''));
        $new->cStrasse  = $cryptoService->encryptXTEA(\trim($new->cStrasse ?? ''));

        $history = new self();
        $history->setKunde($old->getID())
            ->setJsonAlt(\json_encode($old, \JSON_THROW_ON_ERROR) ?: '')
            ->setJsonNeu(\json_encode($new, \JSON_THROW_ON_ERROR) ?: '')
            ->setQuelle($source)
            ->setErstellt('NOW()');

        return $history->save() > 0;
    }
}
