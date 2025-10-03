<?php

declare(strict_types=1);

namespace JTL;

/**
 * Class ShopBC
 * @package JTL
 */
class ShopBC
{
    public static int $kKonfigPos = 0;

    public static int $kKategorie = 0;

    public static int $kArtikel = 0;

    public static int $kVariKindArtikel = 0;

    public static int $kSeite = 0;

    public static int $kLink = 0;

    public static int $nLinkart = 0;

    public static int $kHersteller = 0;

    public static int $kSuchanfrage = 0;

    public static int $kMerkmalWert = 0;

    public static int $kSuchspecial = 0;

    public static int $kNews = 0;

    public static int $kNewsMonatsUebersicht = 0;

    public static int $kNewsKategorie = 0;

    public static int $nBewertungSterneFilter = 0;

    public static string $cPreisspannenFilter = '';

    public static int $kHerstellerFilter = 0;

    /**
     * @var int[]
     */
    public static array $manufacturerFilterIDs = [];

    /**
     * @var int[]
     */
    public static array $categoryFilterIDs = [];

    public static int $kKategorieFilter = 0;

    public static int $kSuchspecialFilter = 0;

    /**
     * @var int[]
     */
    public static array $searchSpecialFilterIDs = [];

    public static int $kSuchFilter = 0;

    public static int $nDarstellung = 0;

    public static int $nSortierung = 0;

    public static int $nSort = 0;

    public static int $show = 0;

    public static int $vergleichsliste = 0;

    public static bool $bFileNotFound = false;

    public static string $cCanonicalURL = '';

    public static bool $is404 = false;

    /**
     * @var int[]
     */
    public static array $MerkmalFilter = [];

    /**
     * @var int[]
     */
    public static array $SuchFilter = [];

    public static int $kWunschliste = 0;

    public static bool $bSEOMerkmalNotFound = false;

    public static bool $bKatFilterNotFound = false;

    public static bool $bHerstellerFilterNotFound = false;

    public static ?string $fileName = null;

    public static string $AktuelleSeite;

    public static int $pageType = \PAGE_UNBEKANNT;

    public static bool $directEntry = true;

    public static bool $bSeo = false;

    public static bool $isInitialized = false;

    public static int $nArtikelProSeite = 0;

    public static string $cSuche = '';

    public static int $seite = 0;

    public static int $nSterne = 0;

    public static int $nNewsKat = 0;

    public static string $cDatum = '';

    public static int $nAnzahl = 0;

    /**
     * @var array<string, int>
     */
    public static array $customFilters = [];

    protected static string $optinCode = '';
}
