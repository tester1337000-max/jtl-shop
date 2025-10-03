<?php

declare(strict_types=1);

namespace JTL\Review;

use Exception;
use JTL\Model\DataAttribute;
use JTL\Model\DataModel;

/**
 * Class ReviewHelpfulModel
 * @package JTL\Review
 * @property int $id
 * @property int $reviewID
 * @property int $customerID
 * @property int $rating
 * @method int getRating()
 * @method int getCustomerID()
 * @method int getReviewID()
 * @method int|null getId()
 * @method void setRating(int $rating)
 * @method void setCustomerID(int $customerID)
 * @method void setReviewID(int $reviewID)
 * @method void setId(int $id)
 */
final class ReviewHelpfulModel extends DataModel
{
    /**
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'tbewertunghilfreich';
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
            $attr['id']         = DataAttribute::create('kBewertungHilfreich', 'int', null, false, true);
            $attr['reviewID']   = DataAttribute::create('kBewertung', 'int', self::cast('0', 'int'), false);
            $attr['customerID'] = DataAttribute::create('kKunde', 'int', self::cast('0', 'int'), false);
            $attr['rating']     = DataAttribute::create('nBewertung', 'int', null, false);
        }

        return $attr;
    }
}
