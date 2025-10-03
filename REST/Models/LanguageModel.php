<?php

declare(strict_types=1);

namespace JTL\REST\Models;

/**
 * Class LanguageModel
 *
 * @package JTL\Language
 * @OA\Schema(
 *     title="Language model",
 *     description="Language model",
 * )
 * @property int    $id
 * @property int    $kSprache
 * @property int    $active
 * @property string $nameEN
 * @property string $cNameEnglisch
 * @property string $nameDE
 * @property string $cNameDeutsch
 * @property string $default
 * @property string $cStandard
 * @property string $shopDefault
 * @property string $cShopStandard
 * @property string $cISO
 * @property string $iso639
 */
class LanguageModel
{
    /**
     * @OA\Property(
     *   property="id",
     *   title="id",
     *   format="int64",
     *   type="integer",
     *   example=33,
     *   description="The language ID"
     * )
     * @OA\Property(
     *   property="active",
     *   title="active",
     *   format="int64",
     *   type="integer",
     *   example=1,
     *   description="1=active, 0=inactive"
     * )
     * @OA\Property(
     *   property="nameEN",
     *   title="nameEN",
     *   type="string",
     *   example="German",
     *   description="The english translation of the language's name"
     * )
     * @OA\Property(
     *   property="nameDE",
     *   title="nameDE",
     *   type="string",
     *   example="Deutsch",
     *   description="The german translation of the language's name"
     * )
     * @OA\Property(
     *   property="default",
     *   title="default",
     *   type="string",
     *   example="Y",
     *   description="Y=default language, N=non-default language"
     * )
     * @OA\Property(
     *   property="shopDefault",
     *   title="shopDefault",
     *   type="string",
     *   example="Y",
     *   description="Y=default shop language, N=non-default shop language"
     * )
     * @OA\Property(
     *   property="iso639",
     *   title="iso639",
     *   type="string",
     *   example="ger",
     *   description="Locale code in ISO639 form"
     * )
     */
    public const TEST = 1;
}
