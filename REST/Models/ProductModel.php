<?php

declare(strict_types=1);

namespace JTL\REST\Models;

use DateTime;
use Exception;
use Illuminate\Support\Collection;
use JTL\Model\DataAttribute;
use JTL\Model\DataModel;
use JTL\Model\DataModelInterface;

/**
 * Class ProductModel
 * @OA\Schema(
 *     title="Product model",
 *     description="Product model",
 * )
 *
 * @property int                                                                      $id
 * @property int                                                                      $manufacturerID
 * @property int                                                                      $deliveryStatus
 * @property int                                                                      $taxClassID
 * @property int                                                                      $unitID
 * @property int                                                                      $shippingClassID
 * @property int                                                                      $propertyCombinationID
 * @property int                                                                      $parentID
 * @property int                                                                      $partlistID
 * @property int                                                                      $commodityGroupID
 * @property int                                                                      $packagingUnitID
 * @property int                                                                      $measurementUnitID
 * @property int                                                                      $basePriceUnitID
 * @property string                                                                   $slug
 * @property string                                                                   $sku
 * @property string                                                                   $name
 * @property string                                                                   $description
 * @property string                                                                   $comment
 * @property float                                                                    $stockQty
 * @property float                                                                    $standardPriceNet
 * @property float                                                                    $taxRate
 * @property float                                                                    $minOrderQty
 * @property float                                                                    $supplierStocks
 * @property float                                                                    $deliveryTime
 * @property string                                                                   $barcode
 * @property string                                                                   $topProduct
 * @property float                                                                    $weight
 * @property float                                                                    $productWeight
 * @property float                                                                    $measurementAmount
 * @property float                                                                    $basePriceAmount
 * @property float                                                                    $width
 * @property float                                                                    $height
 * @property float                                                                    $length
 * @property string                                                                   $new
 * @property string                                                                   $shortdescription
 * @property float                                                                    $msrp
 * @property string                                                                   $trackStock
 * @property string                                                                   $stockMayBeSmallerThanZero
 * @property string                                                                   $trackStocksOfVariations
 * @property string                                                                   $divisible
 * @property float                                                                    $packagingUnit
 * @property float                                                                    $permissibleOrderQty
 * @property float                                                                    $awaitedDelivery
 * @property string                                                                   $hasPackagingUnit
 * @property float                                                                    $packagingUnitAmount
 * @property string                                                                   $packagingUnitName
 * @property string                                                                   $searchTerms
 * @property int                                                                      $sort
 * @property DateTime                                                                 $release
 * @property DateTime                                                                 $created
 * @property DateTime                                                                 $lastModified
 * @property DateTime                                                                 $dateOfAwaitedDelivery
 * @property DateTime                                                                 $shelflifeExpirationDate
 * @property string                                                                   $series
 * @property string                                                                   $isbn
 * @property string                                                                   $asin
 * @property string                                                                   $han
 * @property string                                                                   $unNumber
 * @property string                                                                   $hazardIdentificationNumber
 * @property string                                                                   $taric
 * @property string                                                                   $upc
 * @property string                                                                   $originCountry
 * @property string                                                                   $epid
 * @property int                                                                      $isParent
 * @property int                                                                      $deliveryDaysWhenSoldOut
 * @property int                                                                      $autoDeliveryCalculation
 * @property int                                                                      $handlingTime
 * @property Collection<int, ProductLocalizationModel>|ProductLocalizationModel[]     $localization
 * @property Collection<int, ProductCharacteristicModel>|ProductCharacteristicModel[] $characteristics
 * @property Collection<int, ProductAttributeModel>|ProductAttributeModel[]           $functionalAttributes
 * @property Collection<int, AttributeModel>|AttributeModel[]                         $attributes
 * @property Collection<int, ProductVisibilityModel>|ProductVisibilityModel[]         $visibility
 * @property Collection<int, ProductDownloadModel>|ProductDownloadModel[]             $downloads
 * @property Collection<int, ProductCategoriesModel>|ProductCategoriesModel[]         $categories
 *
 * @method string getSlug()
 * @method Collection<int, ProductLocalizationModel> getLocalization()
 * @method int getId()
 * @method int getManufacturerID()
 * @method int getDeliveryStatus()
 * @method int getTaxClassID()
 * @method int getUnitID()
 * @method int getShippingClassID()
 * @method int getPropertyCombinationID()
 * @method int getParentID()
 */
final class ProductModel extends DataModel
{
    /**
     * @OA\Property(
     *   property="id",
     *   type="integer",
     *   example=99,
     *   description="The product id"
     * )
     * @OA\Property(
     *   property="manufacturerID",
     *   type="integer",
     *   example=3,
     *   description="The manufacturer's id"
     * )
     * @OA\Property(
     *   property="deliveryStatus",
     *   example=1,
     *   type="integer",
     *   description="The deliery status"
     * )
     * @OA\Property(
     *   property="taxClassID",
     *   example=1,
     *   type="integer"
     * )
     * @OA\Property(
     *   property="unitID",
     *   example=0,
     *   type="integer"
     * )
     * @OA\Property(
     *   property="shippingClassID",
     *   example=1,
     *   type="integer"
     * )
     * @OA\Property(
     *   property="propertyCombinationID",
     *   example=1,
     *   type="integer"
     * )
     * @OA\Property(
     *   property="parentID",
     *   type="integer",
     *   example=0,
     *   description="ID of parent product, 0 if none"
     * )
     * @OA\Property(
     *   property="partlistID",
     *   type="integer",
     *   example=0,
     *   description="ID of part list, 0 if none"
     * )
     * @OA\Property(
     *   property="commodityGroupID",
     *   type="integer",
     *   example=0,
     *   description=""
     * )
     * @OA\Property(
     *   property="packagingUnitID",
     *   type="integer",
     *   example=0,
     *   description=""
     * )
     * @OA\Property(
     *   property="measurementUnitID",
     *   type="integer",
     *   example=0,
     *   description=""
     * )
     * @OA\Property(
     *   property="basePriceUnitID",
     *   type="integer",
     *   example=0,
     *   description=""
     * )
     * @OA\Property(
     *   property="slug",
     *   type="string",
     *   description="The url slug"
     * )
     * @OA\Property(
     *   property="sku",
     *   type="string",
     *   description="product SKU"
     * )
     * @OA\Property(
     *   property="name",
     *   type="string",
     *   example="Example product",
     *   description="The product name"
     * )
     * @OA\Property(
     *   property="description",
     *   type="string",
     *   description="The description"
     * )
     * @OA\Property(
     *   property="comment",
     *   type="string",
     *   description=""
     * )
     * @OA\Property(
     *   property="stockQty",
     *   type="number",
     *   format="float",
     *   description=""
     * )
     * @OA\Property(
     *   property="standardPriceNet",
     *   type="number",
     *   format="float",
     *   description=""
     * )
     * @OA\Property(
     *   property="taxRate",
     *   type="number",
     *   format="float",
     *   description=""
     * )
     * @OA\Property(
     *   property="minOrderQty",
     *   type="number",
     *   format="float",
     *   description=""
     * )
     * @OA\Property(
     *   property="supplierStocks",
     *   type="number",
     *   format="float",
     *   description=""
     * )
     * @OA\Property(
     *   property="deliveryTime",
     *   type="number",
     *   format="float",
     *   description=""
     * )
     * @OA\Property(
     *   property="barcode",
     *   type="string",
     *   description=""
     * )
     * @OA\Property(
     *   property="topProduct",
     *   type="string",
     *   description="Is this a top product?"
     * )
     * @OA\Property(
     *   property="weight",
     *   type="number",
     *   format="float",
     *   description=""
     * )
     * @OA\Property(
     *   property="productWeight",
     *   type="number",
     *   format="float",
     *   description=""
     * )
     * @OA\Property(
     *   property="measurementAmount",
     *   type="number",
     *   format="float",
     *   description=""
     * )
     * @OA\Property(
     *   property="basePriceAmount",
     *   type="number",
     *   format="float",
     *   description=""
     * )
     * @OA\Property(
     *   property="width",
     *   type="number",
     *   format="float",
     *   description=""
     * )
     * @OA\Property(
     *   property="height",
     *   type="number",
     *   format="float",
     *   description=""
     * )
     * @OA\Property(
     *   property="length",
     *   type="number",
     *   format="float",
     *   description=""
     * )
     * @OA\Property(
     *   property="new",
     *   type="string",
     *   description="Is this a top product?"
     * )
     * @OA\Property(
     *   property="shortdescription",
     *   type="string",
     *   description="The short description"
     * )
     * @OA\Property(
     *   property="msrp",
     *   type="number",
     *   format="float",
     *   description=""
     * )
     * @OA\Property(
     *   property="trackStock",
     *   type="string",
     *   description=""
     * )
     * @OA\Property(
     *   property="stockMayBeSmallerThanZero",
     *   type="string",
     *   description=""
     * )
     * @OA\Property(
     *   property="trackStocksOfVariations",
     *   type="string",
     *   description=""
     * )
     * @OA\Property(
     *   property="divisible",
     *   type="string",
     *   description=""
     * )
     * @OA\Property(
     *   property="packagingUnit",
     *   type="number",
     *   format="float",
     *   description=""
     * )
     * @OA\Property(
     *   property="permissibleOrderQty",
     *   type="number",
     *   format="float",
     *   description=""
     * )
     * @OA\Property(
     *   property="awaitedDelivery",
     *   type="number",
     *   format="float",
     *   description=""
     * )
     * @OA\Property(
     *   property="hasPackagingUnit",
     *   type="string",
     *   description=""
     * )
     * @OA\Property(
     *   property="packagingUnitAmount",
     *   type="number",
     *   format="float",
     *   description=""
     * )
     * @OA\Property(
     *   property="packagingUnitName",
     *   type="string",
     *   description=""
     * )
     * @OA\Property(
     *   property="searchTerms",
     *   type="string",
     *   description=""
     * )
     * @OA\Property(
     *   property="sort",
     *   type="integer",
     *   description=""
     * )
     * @OA\Property(
     *     property="release",
     *     example="2022-09-22 18:31:45",
     *     format="datetime",
     *     description="Release date",
     *     title="Release date",
     *     type="string"
     * )
     * @OA\Property(
     *     property="created",
     *     example="2022-09-22 18:31:45",
     *     format="datetime",
     *     description="Created date",
     *     title="Created date",
     *     type="string"
     * )
     * @OA\Property(
     *     property="lastModified",
     *     example="2022-09-22 18:31:45",
     *     format="datetime",
     *     description="Last modified date",
     *     title="Last modified date",
     *     type="string"
     * )
     * @OA\Property(
     *     property="dateOfAwaitedDelivery",
     *     example="2022-09-22 18:31:45",
     *     format="datetime",
     *     description="Date of awaited delivery",
     *     title="Date of awaited delivery",
     *     type="string"
     * )
     * @OA\Property(
     *     property="shelflifeExpirationDate",
     *     example="2022-09-22 18:31:45",
     *     format="datetime",
     *     description="Shelf life expiration date",
     *     title="Shelf life expiration date",
     *     type="string"
     * )
     * @OA\Property(
     *   property="series",
     *   type="string",
     *   description=""
     * )
     * @OA\Property(
     *   property="isbn",
     *   type="string",
     *   example="978-0241506455",
     *   description="ISBN"
     * )
     * @OA\Property(
     *   property="asin",
     *   type="string",
     *   example="352770986X",
     *   description="ASIN"
     * )
     * @OA\Property(
     *   property="han",
     *   type="string",
     *   example="abcde12345",
     *   description="Manufacturer part number"
     * )
     * @OA\Property(
     *   property="unNumber",
     *   type="string",
     *   description="UN Number"
     * )
     * @OA\Property(
     *   property="hazardIdentificationNumber",
     *   type="string",
     *   example="X323",
     *   description="Hazard identification number"
     * )
     * @OA\Property(
     *   property="taric",
     *   type="string",
     *   example="8500000000",
     *   description="TARIC code"
     * )
     * @OA\Property(
     *   property="upc",
     *   type="string",
     *   example="123456789104",
     *   description="Universal product code"
     * )
     * @OA\Property(
     *   property="originCountry",
     *   type="string",
     *   example="Germany",
     *   description="Country of origin"
     * )
     * @OA\Property(
     *   property="epid",
     *   type="string",
     *   example="123456",
     *   description="eBay product ID"
     * )
     * @OA\Property(
     *   property="isParent",
     *   type="integer",
     *   example=0,
     *   description="Is parent product?"
     * )
     * @OA\Property(
     *   property="deliveryDaysWhenSoldOut",
     *   type="integer",
     *   example=3,
     *   description=""
     * )
     * @OA\Property(
     *   property="autoDeliveryCalculation",
     *   type="integer",
     *   description=""
     * )
     * @OA\Property(
     *   property="handlingTime",
     *   type="integer",
     *   example="3",
     *   description="The product's handling time in days"
     * )
     * @OA\Property(
     *   property="localization",
     *   type="array",
     *   description="List of ProductLocalizationModel objects",
     *   @OA\Items(ref="#/components/schemas/ProductLocalizationModel")
     * )
     * @OA\Property(
     *   property="characteristics",
     *   type="array",
     *   description="List of ProductCharacteristicModel objects",
     *   @OA\Items(ref="#/components/schemas/ProductCharacteristicModel")
     * )
     * @OA\Property(
     *   property="functionalAttributes",
     *   type="array",
     *   description="List of ProductAttributeModel objects",
     *   @OA\Items(ref="#/components/schemas/ProductAttributeModel")
     * )
     * @OA\Property(
     *   property="attributes",
     *   type="array",
     *   description="List of AttributeModel objects",
     *   @OA\Items(ref="#/components/schemas/AttributeModel")
     * )
     * @OA\Property(
     *   property="visibility",
     *   type="array",
     *   description="List of ProductVisibilityModel objects",
     *   @OA\Items(ref="#/components/schemas/ProductVisibilityModel")
     * )
     * @OA\Property(
     *   property="downloads",
     *   type="array",
     *   description="List of ProductDownloadModel objects",
     *   @OA\Items(ref="#/components/schemas/ProductDownloadModel")
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
    protected int $lastProductCategoryID = -1;

    /**
     * pseudo auto increment for ProductAttributes model
     */
    protected int $lastProductAttributeID = -1;

    /**
     * pseudo auto increment for Attribute model
     */
    protected int $lastAttributeID = -1;

    public bool $full = false;

    /**
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'tartikel';
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
    public function onRegisterHandlers(): void
    {
        parent::onRegisterHandlers();

        $this->registerSetter('characteristics', function ($value, $model) {
            if (\is_a($value, Collection::class)) {
                return $value;
            }
            if (!\is_array($value)) {
                $value = [$value];
            }
            $res = $model->characteristics ?? new Collection();
            foreach (\array_filter($value) as $data) {
                if (\is_object($data)) {
                    $data = (array)$data;
                }
                if (!\is_array($data)) {
                    // support adding product characteristics by a simple array of characteristic value IDs
                    $id = (int)$data;
                    if ($id <= 0) {
                        continue;
                    }
                    $data = ['valueID' => $id];
                    try {
                        $char = CharacteristicValueModel::loadByAttributes(
                            ['id' => $id],
                            $this->getDB()
                        );
                    } catch (Exception) {
                        continue;
                    }
                    $data['id'] = $char->characteristicID;
                }
                if (!isset($data['productID'])) {
                    $data['productID'] = $model->id;
                }
                try {
                    $item = ProductCharacteristicModel::loadByAttributes(
                        $data,
                        $this->getDB(),
                        self::ON_NOTEXISTS_NEW
                    );
                } catch (Exception) {
                    continue;
                }
                $res->push($item);
//                /** @var DataModelInterface|null $existing */
//                $existing = $res->first(static function ($e) use ($item): bool {
//                    return $e->productID === $item->productID && $e->valueID === $item->valueID;
//                });
//                if ($existing === null) {
//                    $res->push($item);
//                } else {
//                    foreach ($item->getAttributes() as $attribute => $v) {
//                        if (\array_key_exists($attribute, $data)) {
//                            $existing->setAttribValue($attribute, $item->getAttribValue($attribute));
//                        }
//                    }
//                }
            }

            return $res;
        });

        $this->registerSetter('localization', function ($value, $model) {
            if (\is_a($value, Collection::class)) {
                return $value;
            }
            if (!\is_array($value)) {
                $value = [$value];
            }
            $res = $model->localization ?? new Collection();
            foreach (\array_filter($value) as $data) {
                $data = (array)$data;
                if (!isset($data['productID'])) {
                    $data['productID'] = $model->id;
                }
                try {
                    $item = ProductLocalizationModel::loadByAttributes(
                        $data,
                        $this->getDB(),
                        self::ON_NOTEXISTS_NEW
                    );
                } catch (Exception) {
                    continue;
                }
                /** @var DataModelInterface|null $existing */
                $existing = $res->first(static function ($e) use ($item): bool {
                    return $e->productID === $item->productID && $e->languageID === $item->languageID;
                });
                if ($existing === null) {
                    $res->push($item);
                } else {
                    foreach ($item->getAttributes() as $attribute => $v) {
                        if (\array_key_exists($attribute, $data)) {
                            $existing->setAttribValue($attribute, $item->getAttribValue($attribute));
                        }
                    }
                }
            }

            return $res;
        });

        $this->registerSetter('categories', function ($value, $model) {
            if (\is_a($value, Collection::class)) {
                return $value;
            }
            if (!\is_array($value)) {
                $value = [$value];
            }
            $res = $model->categories ?? new Collection();
            foreach (\array_filter($value) as $data) {
                $data = (array)$data;
                if (!isset($data['productID'])) {
                    $data['productID'] = $model->id;
                }
                if (!isset($data['id'])) {
                    // tkategorieartikel has no auto increment ID...
                    if ($this->lastProductCategoryID === -1) {
                        $this->lastProductCategoryID = $this->getDB()->getSingleInt(
                            'SELECT MAX(kKategorieArtikel) AS newID FROM tkategorieartikel',
                            'newID'
                        );
                    }
                    $data['id'] = ++$this->lastProductCategoryID;
                }
                try {
                    $item = ProductCategoriesModel::loadByAttributes(
                        $data,
                        $this->getDB(),
                        self::ON_NOTEXISTS_NEW
                    );
                } catch (Exception) {
                    continue;
                }
                /** @var DataModelInterface|null $existing */
                $existing = $res->first(static function ($e) use ($item): bool {
                    return $e->id === $item->id && $e->id > 0;
                });
                if ($existing === null) {
                    $res->push($item);
                } else {
                    foreach ($item->getAttributes() as $attribute => $v) {
                        if (\array_key_exists($attribute, $data)) {
                            $existing->setAttribValue($attribute, $item->getAttribValue($attribute));
                        }
                    }
                }
            }

            return $res;
        });

        $this->registerSetter('functionalAttributes', function ($value, $model) {
            if (\is_a($value, Collection::class)) {
                return $value;
            }
            if (!\is_array($value)) {
                $value = [$value];
            }
            $res = $model->functionalAttributes ?? new Collection();
            foreach (\array_filter($value) as $data) {
                $data = (array)$data;
                if (!isset($data['productID'])) {
                    $data['productID'] = $model->id;
                }
                if (!isset($data['id'])) {
                    // tartikelattribut has no auto increment ID...
                    if ($this->lastProductAttributeID === -1) {
                        $this->lastProductAttributeID = $this->getDB()->getSingleInt(
                            'SELECT MAX(kArtikelAttribut) AS newID FROM tartikelattribut',
                            'newID'
                        );
                    }
                    $data['id'] = ++$this->lastProductCategoryID;
                }
                try {
                    $item = ProductAttributeModel::loadByAttributes(
                        $data,
                        $this->getDB(),
                        self::ON_NOTEXISTS_NEW
                    );
                } catch (Exception) {
                    continue;
                }
                /** @var DataModelInterface|null $existing */
                $existing = $res->first(static fn($e): bool => $e->id === $item->id && $e->id > 0);
                if ($existing === null) {
                    $res->push($item);
                } else {
                    foreach ($item->getAttributes() as $attribute => $v) {
                        if (\array_key_exists($attribute, $data)) {
                            $existing->setAttribValue($attribute, $item->getAttribValue($attribute));
                        }
                    }
                }
            }

            return $res;
        });

        $this->registerSetter('attributes', function ($value, $model) {
            if (\is_a($value, Collection::class)) {
                return $value;
            }
            if (!\is_array($value)) {
                $value = [$value];
            }
            $res = $model->attributes ?? new Collection();
            foreach (\array_filter($value) as $data) {
                $data = (array)$data;
                if (!isset($data['productID'])) {
                    $data['productID'] = $model->id;
                }
                if (!isset($data['id'])) {
                    // tattribut has no auto increment ID...
                    if ($this->lastAttributeID === -1) {
                        $this->lastAttributeID = $this->getDB()->getSingleInt(
                            'SELECT MAX(kAttribut) AS newID FROM tattribut',
                            'newID'
                        );
                    }
                    $data['id'] = ++$this->lastAttributeID;
                }
                try {
                    $item = AttributeModel::loadByAttributes(
                        $data,
                        $this->getDB(),
                        self::ON_NOTEXISTS_NEW
                    );
                } catch (Exception) {
                    continue;
                }
                /** @var DataModelInterface|null $existing */
                $existing = $res->first(static fn($e): bool => $e->id === $item->id && $e->id > 0);
                if ($existing === null) {
                    $res->push($item);
                } else {
                    foreach ($item->getAttributes() as $attribute => $v) {
                        if (\array_key_exists($attribute, $data)) {
                            $existing->setAttribValue($attribute, $item->getAttribValue($attribute));
                        }
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
                if (!isset($data['productID'])) {
                    $data['productID'] = $model->id;
                }
                try {
                    $img = ProductImageModel::loadByAttributes($data, $this->getDB(), self::ON_NOTEXISTS_NEW);
                } catch (Exception) {
                    continue;
                }
                /** @var DataModelInterface|null $exists */
                $exists = $res->first(static fn($e): bool => $e->productID === $img->productID && $e->id === $img->id);
                if ($exists === null) {
                    $res->push($img);
                } else {
                    foreach ($img->getAttributes() as $attribute => $v) {
                        $exists->setAttribValue($attribute, $img->getAttribValue($attribute));
                    }
                }
            }

            return $res;
        });

        $this->registerSetter('prices', function ($value, $model) {
            if ($value === null) {
                return null;
            }
            if (\is_a($value, Collection::class)) {
                return $value;
            }
            if (!\is_array($value)) {
                $value = [$value];
            }
            $res = $model->prices ?? new Collection();
            foreach ($value as $data) {
                $data = (array)$data;
                if (!isset($data['productID'])) {
                    $data['productID'] = $model->id;
                }
                try {
                    $price = PriceModel::loadByAttributes($data, $this->getDB(), self::ON_NOTEXISTS_NEW);
                } catch (Exception) {
                    continue;
                }
                /** @var DataModelInterface|null $existing */
                $existing = $res->first(static function ($e) use ($price): bool {
                    return $e->kPreis === $price->kPreis
                        && $e->customerGroupID === $price->customerGroupID
                        && $e->customerID === $price->customerID;
                });
                if ($existing === null) {
                    $res->push($price);
                } else {
                    foreach ($price->getAttributes() as $attribute => $v) {
                        $existing->setAttribValue($attribute, $price->getAttribValue($attribute));
                    }
                }
            }

            return $res;
        });

        $this->registerSetter('minimumOrderQuantities', function ($value, $model) {
            if ($value === null) {
                return null;
            }
            if (\is_a($value, Collection::class)) {
                return $value;
            }
            if (!\is_array($value)) {
                $value = [$value];
            }
            $res = $model->minimumOrderQuantities ?? new Collection();
            foreach ($value as $data) {
                $data = (array)$data;
                if (!isset($data['productID'])) {
                    $data['productID'] = $model->id;
                }
                try {
                    $minQty = MinimumPurchaseQuantityModel::loadByAttributes(
                        $data,
                        $this->getDB(),
                        self::ON_NOTEXISTS_NEW
                    );
                } catch (Exception) {
                    continue;
                }
                /** @var DataModelInterface|null $existing */
                $existing = $res->first(static function ($e) use ($minQty): bool {
                    return $e->productID === $minQty->productID;
                });
                if ($existing === null) {
                    $res->push($minQty);
                } else {
                    foreach ($minQty->getAttributes() as $attribute => $v) {
                        $existing->setAttribValue($attribute, $minQty->getAttribValue($attribute));
                    }
                }
            }

            return $res;
        });

        $this->registerSetter('categoryDiscounts', function ($value, $model) {
            if ($value === null) {
                return null;
            }
            if (\is_a($value, Collection::class)) {
                return $value;
            }
            if (!\is_array($value)) {
                $value = [$value];
            }
            $res = $model->categoryDiscounts ?? new Collection();
            foreach ($value as $data) {
                $data = (array)$data;
                if (!isset($data['productID'])) {
                    $data['productID'] = $model->id;
                }
                try {
                    $discounts = ProductCategoryDiscountModel::loadByAttributes(
                        $data,
                        $this->getDB(),
                        self::ON_NOTEXISTS_NEW
                    );
                } catch (Exception) {
                    continue;
                }
                /** @var DataModelInterface|null $existing */
                $existing = $res->first(static fn($e): bool => $e->productID === $discounts->productID);
                if ($existing === null) {
                    $res->push($discounts);
                } else {
                    foreach ($discounts->getAttributes() as $attribute => $v) {
                        $existing->setAttribValue($attribute, $discounts->getAttribValue($attribute));
                    }
                }
            }

            return $res;
        });
    }

    protected function onInstanciation(): void
    {
        if ($this->id <= 0 || $this->full === false) {
            return;
        }
        $db = $this->getDB();
        if ($this->characteristics instanceof Collection && $this->characteristics->count() > 0) {
            $characteristics = new Collection();
            foreach ($this->characteristics as $data) {
                $item = CharacteristicValueModel::loadByAttributes(
                    ['characteristicID' => $data->id, 'id' => $data->valueID],
                    $db
                );
                $characteristics->push($item);
            }
            $this->characteristics = $characteristics;
        }
        if ($this->categories instanceof Collection && $this->categories->count() > 0) {
            $categories = new Collection();
            foreach ($this->categories as $data) {
                try {
                    $item = CategoryModel::loadByAttributes(['id' => $data->categoryID], $db);
                } catch (Exception) {
                    continue;
                }
                $categories->push($item);
            }
            $this->categories = $categories;
        }
    }

    /**
     * @inheritdoc
     */
    public function getAttributes(): array
    {
        static $attributes = null;

        if ($attributes !== null) {
            return $attributes;
        }
        $attributes                            = [];
        $attributes['id']                      = DataAttribute::create(
            'kArtikel',
            'int',
            self::cast('0', 'int'),
            false,
            true
        );
        $attributes['manufacturerID']          = DataAttribute::create(
            'kHersteller',
            'int',
            self::cast('0', 'int'),
            false
        );
        $attributes['taxClassID']              = DataAttribute::create(
            'kSteuerklasse',
            'int',
            self::cast('0', 'int'),
            false
        );
        $attributes['unitID']                  = DataAttribute::create(
            'kEinheit',
            'int',
            self::cast('0', 'int'),
            false
        );
        $attributes['shippingClassID']         = DataAttribute::create(
            'kVersandklasse',
            'int',
            self::cast('0', 'int'),
            false
        );
        $attributes['parentID']                = DataAttribute::create(
            'kVaterArtikel',
            'bigint',
            self::cast('0', 'int'),
            false
        );
        $attributes['partlistID']              = DataAttribute::create(
            'kStueckliste',
            'int',
            self::cast('0', 'int'),
            false
        );
        $attributes['slug']                    = DataAttribute::create(
            'cSeo',
            'varchar',
            self::cast('', 'varchar'),
            false
        );
        $attributes['sku']                     = DataAttribute::create('cArtNr', 'varchar');
        $attributes['name']                    = DataAttribute::create('cName', 'varchar');
        $attributes['description']             = DataAttribute::create('cBeschreibung', 'mediumtext');
        $attributes['comment']                 = DataAttribute::create('cAnmerkung', 'mediumtext');
        $attributes['stockQty']                = DataAttribute::create(
            'fLagerbestand',
            'double',
            self::cast('0', 'double')
        );
        $attributes['taxRate']                 = DataAttribute::create('fMwSt', 'float');
        $attributes['minOrderQty']             = DataAttribute::create(
            'fMindestbestellmenge',
            'double',
            self::cast('0', 'double')
        );
        $attributes['topProduct']              = DataAttribute::create(
            'cTopArtikel',
            'yesno',
            self::cast('N', 'yesno')
        );
        $attributes['weight']                  = DataAttribute::create(
            'fGewicht',
            'double',
            self::cast('0', 'double'),
            false
        );
        $attributes['productWeight']           = DataAttribute::create(
            'fArtikelgewicht',
            'double',
            self::cast('0', 'double'),
            false
        );
        $attributes['width']                   = DataAttribute::create('fBreite', 'double', self::cast('0', 'double'));
        $attributes['height']                  = DataAttribute::create('fHoehe', 'double', self::cast('0', 'double'));
        $attributes['length']                  = DataAttribute::create('fLaenge', 'double', self::cast('0', 'double'));
        $attributes['new']                     = DataAttribute::create('cNeu', 'yesno', self::cast('N', 'yesno'));
        $attributes['shortDescription']        = DataAttribute::create('cKurzBeschreibung', 'mediumtext');
        $attributes['msrp']                    = DataAttribute::create('fUVP', 'float', self::cast('0', 'double'));
        $attributes['divisible']               = DataAttribute::create('cTeilbar', 'yesno', self::cast('N', 'yesno'));
        $attributes['searchTerms']             = DataAttribute::create('cSuchbegriffe', 'varchar');
        $attributes['sort']                    = DataAttribute::create('nSort', 'int', self::cast('0', 'int'), false);
        $attributes['release']                 = DataAttribute::create('dErscheinungsdatum', 'date');
        $attributes['created']                 = DataAttribute::create('dErstellt', 'date', 'now()');
        $attributes['lastModified']            = DataAttribute::create('dLetzteAktualisierung', 'datetime');
        $attributes['series']                  = DataAttribute::create('cSerie', 'varchar');
        $attributes['isbn']                    = DataAttribute::create('cISBN', 'varchar');
        $attributes['asin']                    = DataAttribute::create('cASIN', 'varchar');
        $attributes['han']                     = DataAttribute::create('cHAN', 'varchar');
        $attributes['originCountry']           = DataAttribute::create('cHerkunftsland', 'varchar');
        $attributes['epid']                    = DataAttribute::create('cEPID', 'varchar');
        $attributes['isParent']                = DataAttribute::create(
            'nIstVater',
            'tinyint',
            self::cast('0', 'tinyint'),
            false
        );
        $attributes['autoDeliveryCalculation'] = DataAttribute::create('nAutomatischeLiefertageberechnung', 'int');
        $attributes['deliveryStatus']          = DataAttribute::create(
            'kLieferstatus',
            'int',
            self::cast('0', 'int'),
            false
        );
        $attributes['commodityGroupID']        = DataAttribute::create(
            'kWarengruppe',
            'int',
            self::cast('0', 'int'),
            false
        );
        $attributes['basePriceUnitID']         = DataAttribute::create('kGrundPreisEinheit', 'int');
        $attributes['shelflifeExpirationDate'] = DataAttribute::create('dMHD', 'date');

        $attributes['propertyCombinationID']      = DataAttribute::create(
            'kEigenschaftKombi',
            'int',
            self::cast('0', 'int'),
            false
        );
        $attributes['packagingUnitID']            = DataAttribute::create('kVPEEinheit', 'int');
        $attributes['measurementUnitID']          = DataAttribute::create('kMassEinheit', 'int');
        $attributes['standardPriceNet']           = DataAttribute::create('fStandardpreisNetto', 'double');
        $attributes['deliveryTime']               = DataAttribute::create(
            'fLieferzeit',
            'double',
            self::cast('0', 'double'),
            false
        );
        $attributes['barcode']                    = DataAttribute::create('cBarcode', 'varchar');
        $attributes['measurementAmount']          = DataAttribute::create(
            'fMassMenge',
            'double',
            self::cast('0', 'double')
        );
        $attributes['basePriceAmount']            = DataAttribute::create(
            'fGrundpreisMenge',
            'double',
            self::cast('0', 'double')
        );
        $attributes['trackStock']                 = DataAttribute::create(
            'cLagerBeachten',
            'yesno',
            self::cast('N', 'yesno')
        );
        $attributes['stockMayBeSmallerThanZero']  = DataAttribute::create(
            'cLagerKleinerNull',
            'yesno',
            self::cast('N', 'yesno')
        );
        $attributes['trackStocksOfVariations']    = DataAttribute::create(
            'cLagerVariation',
            'yesno',
            self::cast('N', 'yesno')
        );
        $attributes['packagingUnit']              = DataAttribute::create(
            'fPackeinheit',
            'double',
            self::cast('1.0000', 'double')
        );
        $attributes['permissibleOrderQty']        = DataAttribute::create(
            'fAbnahmeintervall',
            'double',
            self::cast('0', 'double'),
            false
        );
        $attributes['awaitedDelivery']            = DataAttribute::create(
            'fZulauf',
            'double',
            self::cast('0', 'double')
        );
        $attributes['hasPackagingUnit']           = DataAttribute::create('cVPE', 'yesno', self::cast('N', 'yesno'));
        $attributes['packagingUnitAmount']        = DataAttribute::create('fVPEWert', 'double');
        $attributes['packagingUnitName']          = DataAttribute::create(
            'cVPEEinheit',
            'varchar',
            self::cast('0', 'double')
        );
        $attributes['supplierStocks']             = DataAttribute::create(
            'fLieferantenlagerbestand',
            'double',
            self::cast('0', 'float'),
            false
        );
        $attributes['deliveryDaysWhenSoldOut']    = DataAttribute::create(
            'nLiefertageWennAusverkauft',
            'int',
            self::cast('0', 'int')
        );
        $attributes['handlingTime']               = DataAttribute::create(
            'nBearbeitungszeit',
            'int',
            self::cast('0', 'int')
        );
        $attributes['unNumber']                   = DataAttribute::create('cUNNummer', 'varchar');
        $attributes['hazardIdentificationNumber'] = DataAttribute::create('cGefahrnr', 'varchar');
        $attributes['taricCode']                  = DataAttribute::create('cTaric', 'varchar');
        $attributes['upc']                        = DataAttribute::create('cUPC', 'varchar');
        $attributes['dateOfAwaitedDelivery']      = DataAttribute::create('dZulaufDatum', 'date');

        $attributes['localization']           = DataAttribute::create(
            'localization',
            ProductLocalizationModel::class,
            null,
            true,
            false,
            'kArtikel'
        );
        $attributes['characteristics']        = DataAttribute::create(
            'characteristics',
            ProductCharacteristicModel::class,
            null,
            true,
            false,
            'kArtikel'
        );
        $attributes['functionalAttributes']   = DataAttribute::create(
            'functionalAttributes',
            ProductAttributeModel::class,
            null,
            true,
            false,
            'kArtikel'
        );
        $attributes['attributes']             = DataAttribute::create(
            'attributes',
            AttributeModel::class,
            null,
            true,
            false,
            'kArtikel'
        );
        $attributes['visibility']             = DataAttribute::create(
            'visibility',
            ProductVisibilityModel::class,
            null,
            true,
            false,
            'kArtikel'
        );
        $attributes['downloads']              = DataAttribute::create(
            'downloads',
            ProductDownloadModel::class,
            null,
            true,
            false,
            'kArtikel'
        );
        $attributes['images']                 = DataAttribute::create(
            'images',
            ProductImageModel::class,
            null,
            true,
            false,
            'kArtikel'
        );
        $attributes['prices']                 = DataAttribute::create(
            'prices',
            PriceModel::class,
            null,
            true,
            false,
            'kArtikel'
        );
        $attributes['categories']             = DataAttribute::create(
            'categories',
            ProductCategoriesModel::class,
            null,
            true,
            false,
            'kArtikel'
        );
        $attributes['stock']                  = DataAttribute::create(
            'stock',
            StockModel::class,
            null,
            true,
            false,
            'kArtikel'
        );
        $attributes['minimumOrderQuantities'] = DataAttribute::create(
            'minimumOrderQuantities',
            MinimumPurchaseQuantityModel::class,
            null,
            true,
            false,
            'kArtikel'
        );
        $attributes['categoryDiscounts']      = DataAttribute::create(
            'categoryDiscounts',
            ProductCategoryDiscountModel::class,
            null,
            true,
            false,
            'kArtikel'
        );
        $attributes['properties']             = DataAttribute::create(
            'properties',
            ProductPropertyModel::class,
            null,
            true,
            false,
            'kArtikel'
        );

        return $attributes;
    }
}
