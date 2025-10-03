<?php

declare(strict_types=1);

namespace JTL\Review;

use DateTime;
use Exception;
use JTL\Model\DataAttribute;
use JTL\Model\DataModel;

/**
 * Class ReviewBonusModel
 *
 * @property int      $id
 * @property int      $reviewID
 * @property int      $customerID
 * @property float    $bonus
 * @property DateTime $date
 * @method int getId()
 * @method int getReviewID()
 * @method int getCustomerID()
 * @method float getBonus()
 * @method string getDate()
 * @method void setId(int $id)
 * @method void setReviewID(int $reviewID)
 * @method void setCustomerID(int $customerID)
 * @method void setBonus(float $bonus)
 * @method void setDate(string $date)
 */
final class ReviewBonusModel extends DataModel
{
    /**
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'tbewertungguthabenbonus';
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
    public function getAttributes(): array
    {
        static $attr = null;

        if ($attr === null) {
            $attr               = [];
            $attr['id']         = DataAttribute::create('kBewertungGuthabenBonus', 'int', null, false, true);
            $attr['reviewID']   = DataAttribute::create('kBewertung', 'int', null, false);
            $attr['customerID'] = DataAttribute::create('kKunde', 'int', null, false);
            $attr['bonus']      = DataAttribute::create('fGuthabenBonus', 'double', null, false);
            $attr['date']       = DataAttribute::create('dDatum', 'datetime', null, false);
        }

        return $attr;
    }
}
