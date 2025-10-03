<?php

declare(strict_types=1);

namespace JTL\REST\Models;

use Exception;
use JTL\Model\DataAttribute;
use JTL\Model\DataModel;

/**
 * Class ProductDownloadModel
 * @OA\Schema(
 *     title="Product download model",
 *     description="Product download model",
 * )
 * @property int $kArtikel
 * @property int $productID
 * @property int $kDownload
 * @property int $downloadID
 */
final class ProductDownloadModel extends DataModel
{
    /**
     * @OA\Property(
     *   property="productID",
     *   type="integer",
     *   example=99,
     *   description="The product's ID"
     * )
     * @OA\Property(
     *   property="downloadID",
     *   type="integer",
     *   example=2,
     *   description="The download's ID"
     * )
     *
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'tartikeldownload';
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
        static $attributes = null;

        if ($attributes === null) {
            $attributes               = [];
            $attributes['productID']  = DataAttribute::create('kArtikel', 'int', null, false, true);
            $attributes['downloadID'] = DataAttribute::create('kDownload', 'int', null, false, true);
        }

        return $attributes;
    }
}
