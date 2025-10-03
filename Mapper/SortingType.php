<?php

declare(strict_types=1);

namespace JTL\Mapper;

use JTL\Filter\SortingOptions\Bestseller;
use JTL\Filter\SortingOptions\DateCreated;
use JTL\Filter\SortingOptions\DateOfIssue;
use JTL\Filter\SortingOptions\EAN;
use JTL\Filter\SortingOptions\NameASC;
use JTL\Filter\SortingOptions\NameDESC;
use JTL\Filter\SortingOptions\None;
use JTL\Filter\SortingOptions\PriceASC;
use JTL\Filter\SortingOptions\PriceDESC;
use JTL\Filter\SortingOptions\ProductNumber;
use JTL\Filter\SortingOptions\RatingDESC;
use JTL\Filter\SortingOptions\SortDefault;
use JTL\Filter\SortingOptions\SortingOptionInterface;
use JTL\Filter\SortingOptions\Weight;

/**
 * Class SortingType
 * @package JTL\Mapper
 */
class SortingType
{
    /**
     * @return class-string<SortingOptionInterface>|null
     */
    public function mapSortTypeToClassName(int $type): ?string
    {
        return match ($type) {
            \SEARCH_SORT_NONE         => None::class,
            \SEARCH_SORT_STANDARD,
            \SEARCH_SORT_AVAILABILITY => SortDefault::class,
            \SEARCH_SORT_NAME_ASC     => NameASC::class,
            \SEARCH_SORT_NAME_DESC    => NameDESC::class,
            \SEARCH_SORT_PRICE_ASC    => PriceASC::class,
            \SEARCH_SORT_PRICE_DESC   => PriceDESC::class,
            \SEARCH_SORT_EAN          => EAN::class,
            \SEARCH_SORT_NEWEST_FIRST => DateCreated::class,
            \SEARCH_SORT_PRODUCTNO    => ProductNumber::class,
            \SEARCH_SORT_WEIGHT       => Weight::class,
            \SEARCH_SORT_DATEOFISSUE  => DateOfIssue::class,
            \SEARCH_SORT_BESTSELLER   => Bestseller::class,
            \SEARCH_SORT_RATING       => RatingDESC::class,
            default                   => null,
        };
    }

    public function mapUserSorting(int|string $sort): int
    {
        if (\is_numeric($sort)) {
            return (int)$sort;
        }
        // Usersortierung ist ein String aus einem Kategorieattribut
        return match (\mb_convert_case($sort, \MB_CASE_LOWER)) {
            \SEARCH_SORT_CRITERION_NAME,
            \SEARCH_SORT_CRITERION_NAME_ASC     => \SEARCH_SORT_NAME_ASC,
            \SEARCH_SORT_CRITERION_NAME_DESC    => \SEARCH_SORT_NAME_DESC,
            \SEARCH_SORT_CRITERION_PRODUCTNO    => \SEARCH_SORT_PRODUCTNO,
            \SEARCH_SORT_CRITERION_WEIGHT       => \SEARCH_SORT_WEIGHT,
            \SEARCH_SORT_CRITERION_PRICE_ASC,
            \SEARCH_SORT_CRITERION_PRICE        => \SEARCH_SORT_PRICE_ASC,
            \SEARCH_SORT_CRITERION_PRICE_DESC   => \SEARCH_SORT_PRICE_DESC,
            \SEARCH_SORT_CRITERION_EAN          => \SEARCH_SORT_EAN,
            \SEARCH_SORT_CRITERION_NEWEST_FIRST => \SEARCH_SORT_NEWEST_FIRST,
            \SEARCH_SORT_CRITERION_DATEOFISSUE  => \SEARCH_SORT_DATEOFISSUE,
            \SEARCH_SORT_CRITERION_BESTSELLER   => \SEARCH_SORT_BESTSELLER,
            \SEARCH_SORT_CRITERION_RATING       => \SEARCH_SORT_RATING,
            default                             => \SEARCH_SORT_STANDARD,
        };
    }
}
