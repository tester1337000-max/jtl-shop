<?php

declare(strict_types=1);

namespace JTL\RateLimit;

use DateTime;
use Exception;
use JTL\Model\DataAttribute;
use JTL\Model\DataModel;
use JTL\Model\ModelHelper;

/**
 * Class Model
 *
 * @package JTL\RateLimit
 * @property int      $reference
 * @method int getReference()
 * @method void setReference(int $value)
 * @property int      $kFloodProtect
 * @method int getKFloodProtect()
 * @method void setKFloodProtect(int $value)
 * @property string   $cIP
 * @method string getCIP()
 * @method void setCIP(string $value)
 * @property string   $cTyp
 * @method string getCTyp()
 * @method void setCTyp(string $value)
 * @property DateTime $dErstellt
 * @method DateTime getDErstellt()
 * @method void setDErstellt(DateTime $value)
 */
final class Model extends DataModel
{
    /**
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'tfloodprotect';
    }

    public function getID(): int
    {
        return $this->kFloodProtect;
    }

    public function setID(int $id): void
    {
        $this->kFloodProtect = $id;
    }

    public function getIP(): string
    {
        return $this->cIP;
    }

    public function setIP(string $ip): void
    {
        $this->cIP = $ip;
    }

    public function getProtectedType(): string
    {
        return $this->cTyp;
    }

    public function setProtectedType(string $type): void
    {
        $this->cTyp = $type;
    }

    public function setTime(string $time): void
    {
        $this->dErstellt = new DateTime($time);
    }

    public function getTime(): DateTime
    {
        return $this->dErstellt;
    }

    /**
     * @inheritdoc
     */
    public function setKeyName($keyName): void
    {
        throw new Exception(__METHOD__ . ': setting of keyname is not supported', self::ERR_DATABASE);
    }

    /**
     * @inheritdoc
     */
    protected function onRegisterHandlers(): void
    {
        parent::onRegisterHandlers();
        $this->registerGetter(
            'dErstellt',
            fn($value, $default): ?DateTime => ModelHelper::fromStrToDateTime($value, $default)
        );
        $this->registerSetter('dErstellt', fn($value): ?string => ModelHelper::fromDateTimeToStr($value));
    }

    /**
     * @inheritdoc
     */
    public function getAttributes(): array
    {
        static $attributes = null;
        if ($attributes === null) {
            $attributes                  = [];
            $attributes['kFloodProtect'] = DataAttribute::create('kFloodProtect', 'int', null, false, true);
            $attributes['cIP']           = DataAttribute::create('cIP', 'varchar');
            $attributes['cTyp']          = DataAttribute::create('cTyp', 'varchar');
            $attributes['dErstellt']     = DataAttribute::create('dErstellt', 'datetime');
            $attributes['reference']     = DataAttribute::create('reference', 'int');
        }

        return $attributes;
    }
}
