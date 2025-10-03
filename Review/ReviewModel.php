<?php

declare(strict_types=1);

namespace JTL\Review;

use DateTime;
use Exception;
use Illuminate\Support\Collection;
use JTL\Model\DataAttribute;
use JTL\Model\DataModel;

/**
 * Class ReviewModel
 *
 * @package JTL\Review
 * @property int                                 $id
 * @property int                                 $productID
 * @property int                                 $customerID
 * @property int                                 $languageID
 * @property string                              $name
 * @property string                              $title
 * @property string                              $content
 * @property int                                 $helpful
 * @property int                                 $notHelpful
 * @property int                                 $stars
 * @property int                                 $active
 * @method void setActive(int $active)
 * @method void setStars(int $stars)
 * @method void setHelpful(int $count)
 * @method void setNotHelpful(int $count)
 * @method void setContent(string $content)
 * @method void setTitle(string $title)
 * @method void setName(string $name)
 * @method void setDate(string $date)
 * @method void setLanguageID(int $langID)
 * @method void setCustomerID(int $customerID)
 * @method void setProductID(int $productID)
 * @method void setId(int $id)
 * @method void setAnswer(?string $answer)
 * @method void setAnswerDate(?string $date)
 * @method int getActive()
 * @method int getStars()
 * @method int getHelpful()
 * @method int getNotHelpful()
 * @method string getContent()
 * @method string getTitle()
 * @method string getName()
 * @method string getDate()
 * @method string getAnswer()
 * @method string getAnswerDate()
 * @method int getLanguageID()
 * @method int getCustomerID()
 * @method int getProductID()
 * @method int getId()
 * @method Collection<int, ReviewHelpfulModel> getVotes()
 * @method Collection<int, ReviewBonusModel> getBonus()
 * @property DateTime|string                     $date
 * @property string                              $answer
 * @property DateTime|string                     $answerDate
 * @property Collection<int, ReviewHelpfulModel> $votes
 * @property Collection<int, ReviewBonusModel>   $bonus
 */
final class ReviewModel extends DataModel
{
    /**
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'tbewertung';
    }

    /**
     * @throws Exception
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
            $attr['id']         = DataAttribute::create('kBewertung', 'int', null, false, true);
            $attr['productID']  = DataAttribute::create('kArtikel', 'int', self::cast('0', 'int'), false);
            $attr['customerID'] = DataAttribute::create('kKunde', 'int', self::cast('0', 'int'), false);
            $attr['languageID'] = DataAttribute::create('kSprache', 'int', self::cast('0', 'int'), false);
            $attr['name']       = DataAttribute::create('cName', 'varchar', null, false);
            $attr['title']      = DataAttribute::create('cTitel', 'varchar', null, false);
            $attr['content']    = DataAttribute::create('cText', 'mediumtext', null, false);
            $attr['helpful']    = DataAttribute::create('nHilfreich', 'int', null, false);
            $attr['notHelpful'] = DataAttribute::create('nNichtHilfreich', 'int', null, false);
            $attr['stars']      = DataAttribute::create('nSterne', 'int', self::cast('0', 'int'), false);
            $attr['active']     = DataAttribute::create('nAktiv', 'int', self::cast('0', 'int'), false);
            $attr['date']       = DataAttribute::create('dDatum', 'date', null, false);
            $attr['answer']     = DataAttribute::create('cAntwort', 'mediumtext');
            $attr['answerDate'] = DataAttribute::create('dAntwortDatum', 'date');
            $attr['votes']      = DataAttribute::create(
                'votes',
                ReviewHelpfulModel::class,
                null,
                true,
                false,
                'kBewertung'
            );
            $attr['bonus']      = DataAttribute::create(
                'bonus',
                ReviewBonusModel::class,
                null,
                true,
                false,
                'kBewertung'
            );
        }

        return $attr;
    }

    /**
     * @throws Exception
     */
    public function delete(): bool
    {
        $result = parent::delete();
        \executeHook(\HOOK_REVIEWMODEL_DELETE, [
            'review' => $this,
        ]);

        return $result;
    }
}
