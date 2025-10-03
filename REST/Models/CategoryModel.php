<?php

declare(strict_types=1);

namespace JTL\REST\Models;

use DateTime;
use Exception;
use Illuminate\Support\Collection;
use JTL\Model\DataAttribute;
use JTL\Model\DataModel;
use JTL\Model\DataModelInterface;
use JTL\Model\ModelHelper;

/**
 * Class CategoryModel
 * @OA\Schema(
 *     title="Category model",
 *     description="Category model",
 * )
 *
 * @package JAPI\Models
 * @property int                                                                    $kKategorie
 * @property int                                                                    $id
 * @property string                                                                 $cSeo
 * @property string                                                                 $slug
 * @property string                                                                 $cName
 * @property string                                                                 $name
 * @property string                                                                 $cBeschreibung
 * @property string                                                                 $description
 * @property int                                                                    $kOberKategorie
 * @property int                                                                    $parentID
 * @property int                                                                    $nSort
 * @property int                                                                    $sort
 * @property DateTime                                                               $dLetzteAktualisierung
 * @property DateTime                                                               $lastModified
 * @property int                                                                    $lft
 * @property int                                                                    $rght
 * @property int                                                                    $level
 * @property int                                                                    $nLevel
 * @property Collection<int, CategoryLocalizationModel>|CategoryLocalizationModel[] $localization
 * @property Collection<int, CategoryImageModel>|CategoryImageModel[]               $images
 * @property Collection<int, CategoryAttributeModel>|CategoryAttributeModel[]       $attributes
 * @property Collection<int, CategoryVisibilityModel>|CategoryVisibilityModel[]     $visibility
 * @method Collection<int, CategoryLocalizationModel>|CategoryLocalizationModel[]   getLocalization()
 * @method string getSlug()
 * @method int getId()
 * @method int getParentID()
 * @method int getSort()
 * @method int getLft()
 * @method int getRght()
 * @method int getLevel()
 * @method string getName()
 * @method string getDescription()
 */
final class CategoryModel extends DataModel
{
    /**
     * @OA\Property(
     *   property="id",
     *   type="integer",
     *   example=123,
     *   description="The category id"
     * )
     * @OA\Property(
     *   property="slug",
     *   type="string",
     *   example="example-category",
     *   description="The category url slug"
     * )
     * @OA\Property(
     *   property="name",
     *   type="string",
     *   example="Example category",
     *   description="The category name"
     * )
     * @OA\Property(
     *   property="description",
     *   type="string",
     *   example="Example description",
     *   description="The category description"
     * )
     * @OA\Property(
     *   property="parentID",
     *   type="integer",
     *   example=0,
     *   description="The category's parent ID (0 if none)"
     * )
     * @OA\Property(
     *   property="sort",
     *   type="integer",
     *   example=0,
     *   description="The sort index"
     * )
     * @OA\Property(
     *     property="lastModified",
     *     example="2022-09-22",
     *     format="datetime",
     *     description="Date of last modification",
     *     title="Modification date",
     *     type="string"
     * )
     * @OA\Property(
     *   property="lft",
     *   type="integer",
     *   example=0,
     *   description="Nested set model left value"
     * )
     * @OA\Property(
     *   property="rght",
     *   type="integer",
     *   example=0,
     *   description="Nested set model right value"
     * )
     * @OA\Property(
     *   property="level",
     *   type="integer",
     *   example=1,
     *   description="Nested set model level"
     * )
     * @OA\Property(
     *   property="localization",
     *   type="array",
     *   description="List of CategoryLocalizationModel objects",
     *   @OA\Items(ref="#/components/schemas/CategoryLocalizationModel")
     * )
     * @OA\Property(
     *   property="images",
     *   type="array",
     *   description="List of CategoryImageModel objects",
     *   @OA\Items(ref="#/components/schemas/CategoryImageModel")
     * )
     * @OA\Property(
     *   property="attributes",
     *   type="array",
     *   description="List of CategoryAttributeModel objects",
     *   @OA\Items(ref="#/components/schemas/CategoryAttributeModel")
     * )
     * @OA\Property(
     *   property="visibility",
     *   type="array",
     *   description="List of CategoryVisibilityModel objects",
     *   @OA\Items(ref="#/components/schemas/CategoryVisibilityModel")
     * )
     * @OA\Property(
     *   property="categories",
     *   type="array",
     *   description="List of ProductCategoriesModel objects",
     *   @OA\Items(ref="#/components/schemas/ProductCategoriesModel")
     * )
     *
     * pseudo auto increment for ProductCategories model
     */
    protected int $lastAttributeID = -1;

    /**
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'tkategorie';
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
        $this->registerGetter('dLetzteAktualisierung', static function ($value, $default) {
            return ModelHelper::fromStrToDate($value, $default);
        });
        $this->registerSetter('dLetzteAktualisierung', static function ($value) {
            return ModelHelper::fromDateToStr($value);
        });
        $this->registerSetter('localization', function ($value, $model) {
            if ($value === null) {
                return null;
            }
            if (\is_a($value, Collection::class)) {
                return $value;
            }
            if (!\is_array($value)) {
                $value = [$value];
            }
            $res = $model->localization ?? new Collection();
            foreach ($value as $data) {
                $data = (array)$data;
                if (!isset($data['categoryID'])) {
                    $data['categoryID'] = $model->id;
                }
                try {
                    $loc = CategoryLocalizationModel::loadByAttributes($data, $this->getDB(), self::ON_NOTEXISTS_NEW);
                } catch (Exception) {
                    continue;
                }
                $existing = $res->first(static function ($e) use ($loc): bool {
                    return $e->categoryID === $loc->categoryID && $e->languageID === $loc->languageID;
                });
                /** @var DataModelInterface|null $existing */
                if ($existing === null) {
                    $res->push($loc);
                } else {
                    foreach ($loc->getAttributes() as $attribute => $v) {
                        $existing->setAttribValue($attribute, $loc->getAttribValue($attribute));
                    }
                }
            }

            return $res;
        });
        $this->registerSetter('attributes', function ($value, $model) {
            if ($value === null) {
                return null;
            }
            if (\is_a($value, Collection::class)) {
                return $value;
            }
            if (!\is_array($value)) {
                $value = [$value];
            }
            $res = $model->attributes ?? new Collection();
            foreach ($value as $data) {
                $data = (array)$data;
                if (!isset($data['categoryID'])) {
                    $data['categoryID'] = $model->id;
                }
                // tkategorieattribut has no auto increment ID
                // and depends on cName and kKategorie - so it has to be provided
                if (!isset($data['id']) && $this->lastAttributeID === -1) {
                    throw new \InvalidArgumentException('Attribute ID is missing', 400);
                }
                try {
                    $item = CategoryAttributeModel::loadByAttributes($data, $this->getDB(), self::ON_NOTEXISTS_NEW);
                } catch (Exception) {
                    continue;
                }
                $existing = $res->first(static function ($e) use ($item): bool {
                    return $e->id === $item->id && $e->categoryID === $item->categoryID;
                });
                /** @var DataModelInterface|null $existing */
                if ($existing === null) {
                    $res->push($item);
                } else {
                    foreach ($item->getAttributes() as $attribute => $v) {
                        $existing->setAttribValue($attribute, $item->getAttribValue($attribute));
                    }
                }
            }

            return $res;
        });
        $this->registerSetter('images', function ($value, $model) {
            if ($value === null) {
                return null;
            }
            if (\is_a($value, Collection::class)) {
                return $value;
            }
            if (!\is_array($value)) {
                $value = [$value];
            }
            $res = $model->images ?? new Collection();
            foreach ($value as $data) {
                $data = (array)$data;
                if (!isset($data['categoryID'])) {
                    $data['categoryID'] = $model->id;
                }
                try {
                    $img = CategoryImageModel::loadByAttributes($data, $this->getDB(), self::ON_NOTEXISTS_NEW);
                } catch (Exception) {
                    continue;
                }
                $existing = $res->first(static function ($e) use ($img): bool {
                    return $e->categoryID === $img->categoryID;
                });
                /** @var DataModelInterface|null $existing */
                if ($existing === null) {
                    $res->push($img);
                } else {
                    foreach ($img->getAttributes() as $attribute => $v) {
                        $existing->setAttribValue($attribute, $img->getAttribValue($attribute));
                    }
                }
            }

            return $res;
        });
    }

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function getAttributes(): array
    {
        static $attributes = null;

        if ($attributes !== null) {
            return $attributes;
        }
        $attributes                 = [];
        $attributes['id']           = DataAttribute::create('kKategorie', 'int', self::cast('0', 'int'), false, true);
        $attributes['slug']         = DataAttribute::create('cSeo', 'varchar', self::cast('', 'varchar'), false);
        $attributes['name']         = DataAttribute::create('cName', 'varchar');
        $attributes['description']  = DataAttribute::create('cBeschreibung', 'mediumtext');
        $attributes['parentID']     = DataAttribute::create('kOberKategorie', 'int', self::cast('0', 'int'));
        $attributes['sort']         = DataAttribute::create('nSort', 'int', self::cast('0', 'int'));
        $attributes['lastModified'] = DataAttribute::create('dLetzteAktualisierung', 'date');
        $attributes['lft']          = DataAttribute::create('lft', 'int', self::cast('0', 'int'), false);
        $attributes['rght']         = DataAttribute::create('rght', 'int', self::cast('0', 'int'), false);
        $attributes['level']        = DataAttribute::create('nLevel', 'int', self::cast('1', 'int'), false);

        $attributes['localization'] = DataAttribute::create(
            'localization',
            CategoryLocalizationModel::class,
            null,
            true,
            false,
            'kKategorie'
        );
        $attributes['images']       = DataAttribute::create(
            'images',
            CategoryImageModel::class,
            null,
            true,
            false,
            'kKategorie'
        );
        $attributes['attributes']   = DataAttribute::create(
            'attributes',
            CategoryAttributeModel::class,
            null,
            true,
            false,
            'kKategorie'
        );
        $attributes['visibility']   = DataAttribute::create(
            'visibility',
            CategoryVisibilityModel::class,
            null,
            true,
            false,
            'kKategorie'
        );
        $attributes['discount']     = DataAttribute::create(
            'discount',
            ProductCategoryDiscountModel::class,
            null,
            true,
            false,
            'kKategorie'
        );
        $attributes['categories']   = DataAttribute::create(
            'categories',
            ProductCategoriesModel::class,
            null,
            true,
            false,
            'kKategorie'
        );

        return $attributes;
    }
}
