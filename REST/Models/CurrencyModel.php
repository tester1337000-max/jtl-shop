<?php

declare(strict_types=1);

namespace JTL\REST\Models;

use Exception;
use JTL\Model\DataAttribute;
use JTL\Model\DataModel;

/**
 * Class CurrencyModel
 * @OA\Schema(
 *     title="Currency model",
 *     description="Currency model"
 * )
 * @package JTL\REST\Models;
 * @property int    $kWaehrung
 * @method int getKWaehrung()
 * @method void setKWaehrung(int $value)
 * @property string $cISO
 * @method string getCISO()
 * @method void setCISO(string $value)
 * @property string $cName
 * @method string getCName()
 * @method void setCName(string $value)
 * @property string $cNameHTML
 * @method string getCNameHTML()
 * @method void setCNameHTML(string $value)
 * @property float  $fFaktor
 * @method float getFFaktor()
 * @method void setFFaktor(float $value)
 * @property string $cStandard
 * @method string getCStandard()
 * @method void setCStandard(string $value)
 * @property string $cVorBetrag
 * @method string getCVorBetrag()
 * @method void setCVorBetrag(string $value)
 * @property string $cTrennzeichenCent
 * @method string getCTrennzeichenCent()
 * @method void setCTrennzeichenCent(string $value)
 * @property string $cTrennzeichenTausend
 * @method string getCTrennzeichenTausend()
 * @method void setCTrennzeichenTausend(string $value)
 */
final class CurrencyModel extends DataModel
{
    /**
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'twaehrung';
    }

    /**
     * Setting of keyname is not supported!
     * Call will always throw an Exception with code ERR_DATABASE!
     * @inheritdoc
     */
    public function setKeyName($keyName): void
    {
        throw new Exception(__METHOD__ . ': setting of keyname is not supported', self::ERR_DATABASE);
    }

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function getAttributes(): array
    {
        $attributes                   = [];
        $attributes['id']             = DataAttribute::create('kWaehrung', 'int', self::cast('0', 'int'), false, true);
        $attributes['code']           = DataAttribute::create('cISO', 'varchar', null, false);
        $attributes['name']           = DataAttribute::create('cName', 'varchar');
        $attributes['nameHTML']       = DataAttribute::create('cNameHTML', 'varchar');
        $attributes['factor']         = DataAttribute::create('fFaktor', 'double');
        $attributes['default']        = DataAttribute::create('cStandard', 'char', self::cast('N', 'char'));
        $attributes['positionBefore'] = DataAttribute::create('cVorBetrag', 'char', self::cast('N', 'char'));

        $attributes['dividerDecimal']   = DataAttribute::create(
            'cTrennzeichenCent',
            'char',
            self::cast(',', 'char'),
            false
        );
        $attributes['dividerThousands'] = DataAttribute::create(
            'cTrennzeichenTausend',
            'char',
            self::cast('.', 'char'),
            false
        );

        return $attributes;
    }
}
