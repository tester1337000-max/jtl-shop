<?php

declare(strict_types=1);

namespace JTL\Helpers;

use Illuminate\Support\Collection;
use JTL\Campaign;
use JTL\Cart\CartHelper;
use JTL\Catalog\Product\Artikel;
use JTL\Catalog\Product\Preise;
use JTL\Catalog\Product\Variation;
use JTL\Catalog\Product\VariationValue;
use JTL\Catalog\UnitsOfMeasure;
use JTL\CheckBox;
use JTL\Customer\CustomerGroup;
use JTL\DB\DbInterface;
use JTL\DB\SqlObject;
use JTL\Extensions\Config\Configurator;
use JTL\Extensions\Config\Group;
use JTL\Extensions\Config\Item;
use JTL\Language\LanguageHelper;
use JTL\Language\Texts;
use JTL\Mail\Mail\Mail;
use JTL\Optin\Optin;
use JTL\Optin\OptinAvailAgain;
use JTL\Optin\OptinRefData;
use JTL\RateLimit\AvailabilityMessage as Limiter;
use JTL\Session\Frontend;
use JTL\Shop;
use JTL\SimpleMail;
use JTL\Smarty\JTLSmarty;
use stdClass;

use function Functional\group;

/**
 * Class Product
 * @package JTL\Helpers
 */
class Product
{
    public static function isVariChild(int $productID): bool
    {
        if ($productID <= 0) {
            return false;
        }
        $id = Shop::Container()->getDB()->getSingleInt(
            'SELECT kEigenschaftKombi
                FROM tartikel
                WHERE kArtikel = :pid',
            'kEigenschaftKombi',
            ['pid' => $productID]
        );

        return $id > 0;
    }

    public static function getParent(int $productID): int
    {
        if ($productID <= 0) {
            return 0;
        }

        return Shop::Container()->getDB()->getSingleInt(
            'SELECT kVaterArtikel
                FROM tartikel
                WHERE kArtikel = :pid',
            'kVaterArtikel',
            ['pid' => $productID]
        );
    }

    public static function isVariCombiChild(int $productID): bool
    {
        return self::getParent($productID) > 0;
    }

    /**
     * Holt fuer einen kVaterArtikel + gesetzte Eigenschaften, den kArtikel vom Variationskombikind
     */
    public static function getArticleForParent(int $productID): int
    {
        $customerGroupID = Frontend::getCustomerGroup()->getID();
        $properties      = self::getChildPropertiesForParent($productID, $customerGroupID);
        $combinations    = [];
        $valid           = true;
        foreach ($properties as $i => $kAlleEigenschaftWerteProEigenschaft) {
            if (!self::hasSelectedVariationValue($i)) {
                $valid = false;
                break;
            }
            $combinations[$i] = self::getSelectedVariationValue($i);
        }
        if (!$valid) {
            return 0;
        }
        $attributes      = [];
        $attributeValues = [];
        if (\count($combinations) > 0) {
            foreach ($combinations as $i => $kVariationKombi) {
                $attributes[]      = $i;
                $attributeValues[] = (int)$kVariationKombi;
            }
            $product = Shop::Container()->getDB()->getSingleObject(
                'SELECT tartikel.kArtikel
                    FROM teigenschaftkombiwert
                    JOIN tartikel
                        ON tartikel.kEigenschaftKombi = teigenschaftkombiwert.kEigenschaftKombi
                    LEFT JOIN tartikelsichtbarkeit
                        ON tartikel.kArtikel = tartikelsichtbarkeit.kArtikel
                        AND tartikelsichtbarkeit.kKundengruppe = :cgid
                    WHERE teigenschaftkombiwert.kEigenschaft IN (' . \implode(',', $attributes) . ')
                        AND teigenschaftkombiwert.kEigenschaftWert IN (' . \implode(',', $attributeValues) . ')
                        AND tartikelsichtbarkeit.kArtikel IS NULL
                        AND tartikel.kVaterArtikel = :pid
                    GROUP BY tartikel.kArtikel
                    HAVING COUNT(*) = ' . \count($combinations),
                ['cgid' => $customerGroupID, 'pid' => $productID]
            );
            if ($product !== null && $product->kArtikel > 0) {
                return (int)$product->kArtikel;
            }
        }
        if (!isset($_SESSION['variBoxAnzahl_arr'])) {
            \header(
                'Location: ' . Shop::getURL() .
                '/?a=' . $productID .
                '&n=' . $_POST['anzahl'] .
                '&r=' . \R_VARWAEHLEN,
                true,
                302
            );
            exit;
        }

        return 0;
    }

    /**
     * Holt fuer einen kVaterArtikel alle Eigenschaften und Eigenschaftswert Assoc als Array
     * z.b. $properties[kEigenschaft] = EigenschaftWert
     *
     * @former: gibAlleKindEigenschaftenZuVater()
     * @return array<int, int[]>
     */
    public static function getChildPropertiesForParent(int $productID, int $customerGroupID): array
    {
        $properties = [];
        foreach (self::getPossibleVariationCombinations($productID, $customerGroupID) as $comb) {
            /** @var int $idx */
            $idx = $comb->kEigenschaft;
            if (!isset($properties[$idx]) || !\is_array($properties[$idx])) {
                $properties[$idx] = [];
            }
            if (
                !isset($comb->kEigenschaftWert)
                || !\in_array($comb->kEigenschaftWert, $properties[$idx], true)
            ) {
                $properties[$idx][] = $comb->kEigenschaftWert;
            }
        }

        return $properties;
    }

    /**
     * @return stdClass[]
     */
    public static function getPossibleVariationCombinations(
        int $parentID,
        int $customerGroupID = 0,
        bool $group = false
    ): array {
        if (!$customerGroupID) {
            $customerGroupID = CustomerGroup::getDefaultGroupID();
        }
        $groupBy = $group ? 'GROUP BY teigenschaftkombiwert.kEigenschaftWert ' : '';

        return \array_map(
            static function (stdClass $e): stdClass {
                $e->kEigenschaft      = (int)$e->kEigenschaft;
                $e->kEigenschaftKombi = (int)$e->kEigenschaftKombi;
                $e->kEigenschaftWert  = (int)$e->kEigenschaftWert;

                return $e;
            },
            Shop::Container()->getDB()->getObjects(
                'SELECT teigenschaftkombiwert.*
                    FROM teigenschaftkombiwert
                    JOIN tartikel
                        ON tartikel.kVaterArtikel = :pid
                        AND tartikel.kEigenschaftKombi = teigenschaftkombiwert.kEigenschaftKombi
                    LEFT JOIN tartikelsichtbarkeit
                        ON tartikel.kArtikel = tartikelsichtbarkeit.kArtikel
                        AND tartikelsichtbarkeit.kKundengruppe = :cgid
                    WHERE tartikelsichtbarkeit.kArtikel IS NULL ' . $groupBy .
                'ORDER BY teigenschaftkombiwert.kEigenschaftWert',
                ['pid' => $parentID, 'cgid' => $customerGroupID]
            )
        );
    }

    /**
     * @return array{int, int}|array{}
     */
    public static function getPropertiesForVarCombiArticle(int $productID, int &$parentID): array
    {
        $result   = [];
        $parentID = 0;
        // Hole EigenschaftWerte zur gewaehlten VariationKombi
        $children = Shop::Container()->getDB()->getObjects(
            'SELECT teigenschaftkombiwert.kEigenschaftWert, teigenschaftkombiwert.kEigenschaft, tartikel.kVaterArtikel
                FROM teigenschaftkombiwert
                JOIN tartikel
                    ON tartikel.kEigenschaftKombi = teigenschaftkombiwert.kEigenschaftKombi
                    AND tartikel.kArtikel = :productID
                LEFT JOIN tartikelsichtbarkeit
                    ON tartikel.kArtikel = tartikelsichtbarkeit.kArtikel
                    AND tartikelsichtbarkeit.kKundengruppe = :customerGroup
                WHERE tartikelsichtbarkeit.kArtikel IS NULL
                ORDER BY tartikel.kArtikel',
            [
                'productID'     => $productID,
                'customerGroup' => Frontend::getCustomerGroup()->getID(),
            ]
        );
        if (\count($children) === 0) {
            return [];
        }

        foreach ($children as $child) {
            $result[(int)$child->kEigenschaft] = (int)$child->kEigenschaftWert;
        }
        $parentID = (int)$children[0]->kVaterArtikel;

        return $result;
    }

    /**
     * @former gibGewaehlteEigenschaftenZuVariKombiArtikel()
     * @return stdClass[]
     */
    public static function getSelectedPropertiesForVarCombiArticle(int $productID, int $getVariations = 0): array
    {
        if ($productID <= 0) {
            return [];
        }
        $parentID       = 0;
        $customerGroup  = Frontend::getCustomerGroup()->getID();
        $db             = Shop::Container()->getDB();
        $properties     = [];
        $propertyValues = self::getPropertiesForVarCombiArticle($productID, $parentID);
        $exists         = true;
        if (\count($propertyValues) === 0) {
            return [];
        }

        $attributes      = [];
        $attributeValues = [];
        $langID          = Shop::getLanguageID();
        $attr            = new SqlObject();
        $attrVal         = new SqlObject();
        foreach ($propertyValues as $i => $value) {
            $attributes[]      = $i;
            $attributeValues[] = $value;
        }
        if ($langID > 0 && !LanguageHelper::isDefaultLanguageActive(languageID: $langID)) {
            $attr->setSelect('teigenschaftsprache.cName AS cName_teigenschaftsprache, ');
            $attr->setJoin(
                'LEFT JOIN teigenschaftsprache
                    ON teigenschaftsprache.kEigenschaft = teigenschaft.kEigenschaft
                    AND teigenschaftsprache.kSprache = :lid'
            );
            $attr->addParam(':lid', $langID);

            $attrVal->setSelect('teigenschaftwertsprache.cName AS cName_teigenschaftwertsprache, ');
            $attrVal->setJoin(
                'LEFT JOIN teigenschaftwertsprache
                    ON teigenschaftwertsprache.kEigenschaftWert = teigenschaftwert.kEigenschaftWert
                    AND teigenschaftwertsprache.kSprache = :lid'
            );
            $attrVal->addParam('lid', $langID);
        }

        $attrs = $db->getObjects(
            'SELECT teigenschaftwert.kEigenschaftWert, teigenschaftwert.cName, ' . $attrVal->getSelect() . '
                teigenschaftwertsichtbarkeit.kKundengruppe, teigenschaftwert.kEigenschaft, teigenschaft.cTyp, '
            . $attr->getSelect() . ' teigenschaft.cName AS cNameEigenschaft, teigenschaft.kArtikel
                FROM teigenschaftwert
                LEFT JOIN teigenschaftwertsichtbarkeit
                    ON teigenschaftwertsichtbarkeit.kEigenschaftWert = teigenschaftwert.kEigenschaftWert
                    AND teigenschaftwertsichtbarkeit.kKundengruppe = :cgid
                JOIN teigenschaft ON teigenschaft.kEigenschaft = teigenschaftwert.kEigenschaft
                LEFT JOIN teigenschaftsichtbarkeit ON teigenschaft.kEigenschaft = teigenschaftsichtbarkeit.kEigenschaft
                    AND teigenschaftsichtbarkeit.kKundengruppe = :cgid
                ' . $attr->getJoin() . '
                ' . $attrVal->getJoin() . '
                WHERE teigenschaftwertsichtbarkeit.kEigenschaftWert IS NULL
                    AND teigenschaftsichtbarkeit.kEigenschaft IS NULL
                    AND teigenschaftwert.kEigenschaft IN (' . \implode(',', $attributes) . ')
                    AND teigenschaftwert.kEigenschaftWert IN (' . \implode(',', $attributeValues) . ')',
            \array_merge(['cgid' => $customerGroup], $attr->getParams(), $attrVal->getParams())
        );

        $tmpAttr = $db->getObjects(
            "SELECT teigenschaft.kEigenschaft, teigenschaft.cName, teigenschaft.cTyp, 
                teigenschaft.kArtikel, 0 AS kEigenschaftWert
                FROM teigenschaft
                LEFT JOIN teigenschaftsichtbarkeit
                    ON teigenschaft.kEigenschaft = teigenschaftsichtbarkeit.kEigenschaft
                    AND teigenschaftsichtbarkeit.kKundengruppe = :cgid
                WHERE (teigenschaft.kArtikel = :ppid
                    OR teigenschaft.kArtikel = :pid)
                    AND teigenschaftsichtbarkeit.kEigenschaft IS NULL
                    AND (teigenschaft.cTyp = 'FREIFELD'
                    OR teigenschaft.cTyp = 'PFLICHT-FREIFELD')",
            ['pid' => $productID, 'ppid' => $parentID, 'cgid' => $customerGroup]
        );

        $attrs = \array_merge($attrs, $tmpAttr);
        foreach ($attrs as $attr2) {
            $attr2->kEigenschaftWert = (int)$attr2->kEigenschaftWert;
            $attr2->kEigenschaft     = (int)$attr2->kEigenschaft;
            $attr2->kArtikel         = (int)$attr2->kArtikel;
            if ($attr2->cTyp !== 'FREIFELD' && $attr2->cTyp !== 'PFLICHT-FREIFELD') {
                // Ist kEigenschaft zu eigenschaftwert vorhanden
                if (self::hasSelectedVariationValue($attr2->kEigenschaft)) {
                    $valueExists = $db->getSingleObject(
                        'SELECT teigenschaftwert.kEigenschaftWert
                            FROM teigenschaftwert
                            LEFT JOIN teigenschaftwertsichtbarkeit
                                ON teigenschaftwertsichtbarkeit.kEigenschaftWert = teigenschaftwert.kEigenschaftWert
                                AND teigenschaftwertsichtbarkeit.kKundengruppe = :cgid
                            WHERE teigenschaftwert.kEigenschaftWert = :avid
                                AND teigenschaftwertsichtbarkeit.kEigenschaftWert IS NULL
                                AND teigenschaftwert.kEigenschaft = :aid',
                        ['cgid' => $customerGroup, 'aid' => $attr2->kEigenschaft, 'avid' => $attr2->kEigenschaftWert]
                    );

                    if ($valueExists !== null && $valueExists->kEigenschaftWert) {
                        unset($propValue);
                        $propValue                   = new stdClass();
                        $propValue->kEigenschaftWert = $attr2->kEigenschaftWert;
                        $propValue->kEigenschaft     = $attr2->kEigenschaft;
                        $propValue->cTyp             = $attr2->cTyp;

                        if ($langID > 0 && !LanguageHelper::isDefaultLanguageActive(languageID: $langID)) {
                            $propValue->cEigenschaftName     = $attr2->cName_teigenschaftsprache;
                            $propValue->cEigenschaftWertName = $attr2->cName_teigenschaftwertsprache;
                        } else {
                            $propValue->cEigenschaftName     = $attr2->cNameEigenschaft;
                            $propValue->cEigenschaftWertName = $attr2->cName;
                        }
                        $properties[] = $propValue;
                    } else {
                        $exists = false;
                        break;
                    }
                } elseif (!isset($_SESSION['variBoxAnzahl_arr'])) {
                    \header(
                        'Location: ' . Shop::getURL()
                        . '/?a=' . $productID
                        . '&n=' . Request::pInt('anzahl')
                        . '&r=' . \R_VARWAEHLEN,
                        true,
                        302
                    );
                    exit;
                }
            } else {
                unset($propValue);
                if (
                    $attr2->cTyp === 'PFLICHT-FREIFELD'
                    && self::hasSelectedVariationValue($attr2->kEigenschaft)
                    && \mb_strlen(self::getSelectedVariationValue($attr2->kEigenschaft) ?: '') === 0
                ) {
                    \header(
                        'Location: ' . Shop::getURL()
                        . '/?a=' . $productID
                        . '&n=' . Request::pInt('anzahl')
                        . '&r=' . \R_VARWAEHLEN,
                        true,
                        302
                    );
                    exit;
                }
                $propValue                = new stdClass();
                $propValue->cFreifeldWert = Text::filterXSS(self::getSelectedVariationValue($attr2->kEigenschaft));
                $propValue->kEigenschaft  = $attr2->kEigenschaft;
                $propValue->cTyp          = $attr2->cTyp;
                $properties[]             = $propValue;
            }
        }

        if (!$exists && !isset($_SESSION['variBoxAnzahl_arr'])) {
            \header(
                'Location: ' . Shop::getURL()
                . '/?a=' . $productID
                . '&n=' . Request::pInt('anzahl')
                . '&r=' . \R_VARWAEHLEN,
                true,
                301
            );
            exit;
        }
        if ($getVariations > 0) {
            $variations = [];
            foreach ($properties as $i => $propValue) {
                $oEigenschaftWert                   = new stdClass();
                $oEigenschaftWert->kEigenschaftWert = $propValue->kEigenschaftWert;
                $oEigenschaftWert->kEigenschaft     = $propValue->kEigenschaft;
                $oEigenschaftWert->cName            = $propValue->cEigenschaftWertName;
                if ($propValue->cTyp === 'PFLICHT-FREIFELD' || $propValue->cTyp === 'FREIFELD') {
                    $oEigenschaftWert->cFreifeldWert    = $propValue->cFreifeldWert;
                    $oEigenschaftWert->kEigenschaftWert = 0;
                }
                $variations[$i]               = new stdClass();
                $variations[$i]->kEigenschaft = $propValue->kEigenschaft;
                $variations[$i]->kArtikel     = $productID;
                $variations[$i]->cWaehlbar    = 'Y';
                $variations[$i]->cTyp         = $propValue->cTyp;
                $variations[$i]->cName        = $propValue->cEigenschaftName ?? null;
                $variations[$i]->Werte        = [];
                $variations[$i]->Werte[]      = $oEigenschaftWert;
            }

            return $variations;
        }

        return $properties;
    }

    /**
     * @param int  $productID
     * @param bool $redirect
     * @return array<mixed>
     * @former gibGewaehlteEigenschaftenZuArtikel()
     * @since 5.0.0
     */
    public static function getSelectedPropertiesForArticle(int $productID, bool $redirect = true): array
    {
        $db              = Shop::Container()->getDB();
        $customerGroupID = Frontend::getCustomerGroup()->getID();
        $propData        = $db->getObjects(
            'SELECT teigenschaft.kEigenschaft,teigenschaft.cName,teigenschaft.cTyp
                FROM teigenschaft
                LEFT JOIN teigenschaftsichtbarkeit
                    ON teigenschaft.kEigenschaft = teigenschaftsichtbarkeit.kEigenschaft
                    AND teigenschaftsichtbarkeit.kKundengruppe = :cgroupid
                WHERE teigenschaft.kArtikel = :productID
                    AND teigenschaftsichtbarkeit.kEigenschaft IS NULL',
            ['cgroupid' => $customerGroupID, 'productID' => $productID]
        );
        $properties      = [];
        $exists          = true;
        if (\count($propData) === 0) {
            return [];
        }
        foreach ($propData as $prop) {
            $prop->kEigenschaft = (int)$prop->kEigenschaft;
            if ($prop->cTyp !== 'FREIFELD' && $prop->cTyp !== 'PFLICHT-FREIFELD') {
                if (self::hasSelectedVariationValue($prop->kEigenschaft)) {
                    $propExists = $db->getSingleObject(
                        'SELECT teigenschaftwert.kEigenschaftWert, teigenschaftwert.cName,
                            teigenschaftwertsichtbarkeit.kKundengruppe
                            FROM teigenschaftwert
                            LEFT JOIN teigenschaftwertsichtbarkeit
                                ON teigenschaftwertsichtbarkeit.kEigenschaftWert = teigenschaftwert.kEigenschaftWert
                                AND teigenschaftwertsichtbarkeit.kKundengruppe = :cgroupid
                            WHERE teigenschaftwert.kEigenschaftWert = :attribvalueid
                                AND teigenschaftwertsichtbarkeit.kEigenschaftWert IS NULL
                                AND teigenschaftwert.kEigenschaft = :attribid',
                        [
                            'cgroupid'      => $customerGroupID,
                            'attribvalueid' => self::getSelectedVariationValue($prop->kEigenschaft),
                            'attribid'      => $prop->kEigenschaft
                        ]
                    );

                    if ($propExists !== null) {
                        $val                       = new stdClass();
                        $val->kEigenschaftWert     = (int)self::getSelectedVariationValue($prop->kEigenschaft);
                        $val->kEigenschaft         = $prop->kEigenschaft;
                        $val->cEigenschaftName     = $prop->cName;
                        $val->cEigenschaftWertName = $propExists->cName;
                        $val->cTyp                 = $prop->cTyp;
                        $properties[]              = $val;
                    } else {
                        $exists = false;
                        break;
                    }
                } elseif (!isset($_SESSION['variBoxAnzahl_arr']) && $redirect) {
                    \header(
                        'Location: ' . Shop::getURL()
                        . '/?a=' . $productID
                        . '&n=' . Request::pInt('anzahl')
                        . '&r=' . \R_VARWAEHLEN,
                        true,
                        302
                    );
                    exit;
                }
            } else {
                if (
                    $prop->cTyp === 'PFLICHT-FREIFELD'
                    && $redirect
                    && self::hasSelectedVariationValue($prop->kEigenschaft)
                    && \mb_strlen(self::getSelectedVariationValue($prop->kEigenschaft) ?: '') === 0
                ) {
                    \header(
                        'Location: ' . Shop::getURL()
                        . '/?a=' . $productID
                        . '&n=' . Request::pInt('anzahl')
                        . '&r=' . \R_VARWAEHLEN,
                        true,
                        302
                    );
                    exit;
                }
                $val                   = new stdClass();
                $val->cFreifeldWert    = $db->escape(
                    Text::filterXSS(self::getSelectedVariationValue($prop->kEigenschaft))
                );
                $val->kEigenschaft     = $prop->kEigenschaft;
                $val->kEigenschaftWert = null;
                $val->cTyp             = $prop->cTyp;
                $properties[]          = $val;
            }
        }

        if (!$exists && $redirect && !isset($_SESSION['variBoxAnzahl_arr'])) {
            \header(
                'Location: ' . Shop::getURL()
                . '/?a=' . $productID
                . '&n=' . Request::pInt('anzahl')
                . '&r=' . \R_VARWAEHLEN,
                true,
                302
            );
            exit;
        }

        return $properties;
    }

    /**
     * @former holeKinderzuVater()
     * @return stdClass[]
     */
    public static function getChildren(int $parentID): array
    {
        return $parentID > 0
            ? Shop::Container()->getDB()->selectAll(
                'tartikel',
                'kVaterArtikel',
                $parentID,
                'kArtikel, kEigenschaftKombi'
            )
            : [];
    }

    /**
     * @former pruefeIstVaterArtikel()
     */
    public static function isParent(int $productID): bool
    {
        $isParent = Shop::Container()->getDB()->getSingleInt(
            'SELECT nIstVater
                FROM tartikel
                WHERE kArtikel = :pid',
            'nIstVater',
            ['pid' => $productID]
        );

        return $isParent > 0;
    }

    /**
     * @param int  $productID
     * @param bool $info
     * @return ($info is true ? stdClass|false : bool)
     */
    public static function isStuecklisteKomponente(int $productID, bool $info = false): bool|stdClass
    {
        if ($productID > 0) {
            $data = Shop::Container()->getDB()->select('tstueckliste', 'kArtikel', $productID);
            if ($data !== null && $data->kStueckliste > 0) {
                return $info ? $data : true;
            }
        }

        return false;
    }

    /**
     * Fallback für alte Formular-Struktur
     *
     * alt: eigenschaftwert_{kEigenschaft}
     * neu: eigenschaftwert[{kEigenschaft}]
     */
    protected static function getSelectedVariationValue(int $groupID): false|string
    {
        $idx = 'eigenschaftwert_' . $groupID;
        $res = $_POST[$idx] ?? $_POST['eigenschaftwert'][$groupID] ?? false;

        return $res === false ? false : (string)$res;
    }

    protected static function hasSelectedVariationValue(int $groupID): bool
    {
        return self::getSelectedVariationValue($groupID) !== false;
    }

    /**
     * @param Artikel    $product
     * @param stdClass[] $variationImages
     * @deprecated since 5.5.0.
     */
    public static function addVariationPictures(Artikel $product, array $variationImages): void
    {
        \trigger_error(__METHOD__ . ' is deprecated and should not be used anymore.', \E_USER_DEPRECATED);
        if (\count($variationImages) === 0) {
            return;
        }
        $product->Bilder = \array_filter($product->Bilder, static function ($item): bool {
            return !(isset($item->isVariation) && $item->isVariation);
        });
        if (\count($variationImages) === 1) {
            \array_unshift($product->Bilder, $variationImages[0]);
        } else {
            $product->Bilder = \array_merge($product->Bilder, $variationImages);
        }

        $nNr = 1;
        foreach (\array_keys($product->Bilder) as $key) {
            $product->Bilder[$key]->nNr = $nNr++;
        }

        $product->cVorschaubild = $product->Bilder[0]->cURLKlein;
    }

    public static function getBasePriceUnit(Artikel $product, ?float $price, ?int $amount): stdClass
    {
        $unitMappings = [
            'mg'  => 'kg',
            'g'   => 'kg',
            'mL'  => 'L',
            'cm3' => 'L',
            'cL'  => 'L',
            'dL'  => 'L',
        ];

        $result = (object)[
            'fGrundpreisMenge' => $product->fGrundpreisMenge,
            'fMassMenge'       => $product->fMassMenge * $amount,
            'fBasePreis'       => $price / $product->fVPEWert,
            'fVPEWert'         => (float)$product->fVPEWert,
            'cVPEEinheit'      => $product->cVPEEinheit,
        ];

        $gpUnit   = UnitsOfMeasure::getUnit($product->kGrundpreisEinheit ?? 0);
        $massUnit = UnitsOfMeasure::getUnit($product->kMassEinheit ?? 0);

        if (isset($gpUnit, $massUnit, $unitMappings[$gpUnit->cCode], $unitMappings[$massUnit->cCode])) {
            $factor     = UnitsOfMeasure::getConversionFaktor($unitMappings[$massUnit->cCode], $massUnit->cCode);
            $threshold  = 250 * $factor / 1000;
            $nAmount    = 1;
            $mappedCode = $unitMappings[$massUnit->cCode];

            if ($threshold > 0 && $result->fMassMenge > $threshold) {
                $result->fGrundpreisMenge = $nAmount;
                $result->fMassMenge       /= $factor;
                $result->fVPEWert         = $result->fMassMenge / $amount / $result->fGrundpreisMenge;
                $result->fBasePreis       = $price / $result->fVPEWert;
                $result->cVPEEinheit      = $result->fGrundpreisMenge . ' ' .
                    UnitsOfMeasure::getPrintAbbreviation($mappedCode);
            }
        }

        return $result;
    }

    /**
     * @param string|string[]     $attribute
     * @param string|int|string[] $value
     * @param callable|null       $callback
     * @return ($callback is null ? stdClass|null : mixed)
     * @since 5.0
     */
    public static function getDataByAttribute(string|array $attribute, mixed $value, ?callable $callback = null): mixed
    {
        $res = Shop::Container()->getDB()->select('tartikel', $attribute, $value);

        return \is_callable($callback)
            ? $callback($res)
            : $res;
    }

    /**
     * @param string|string[]     $attribute
     * @param string|int|string[] $value
     * @param callable|null       $callback
     * @return ($callback is null ? Artikel|null : mixed)
     * @since 5.0.0
     */
    public static function getProductByAttribute(
        string|array $attribute,
        mixed $value,
        ?callable $callback = null
    ): mixed {
        $art = ($res = self::getDataByAttribute($attribute, $value)) !== null
            ? (new Artikel())->fuelleArtikel($res->kArtikel, Artikel::getDefaultOptions())
            : null;

        return \is_callable($callback)
            ? $callback($art)
            : $art;
    }

    /**
     * Gibt den kArtikel von einem Varikombi Kind zurück und braucht dafür Eigenschaften und EigenschaftsWerte
     * Klappt nur bei max. 2 Dimensionen
     * @since 5.0.0
     * @former findeKindArtikelZuEigenschaft()
     */
    public static function getChildProductIDByAttribute(
        int $productID,
        int $es0,
        int $esWert0,
        int $es1 = 0,
        int $esWert1 = 0
    ): int {
        if ($es0 > 0 && $esWert0 > 0) {
            $join   = ' JOIN teigenschaftkombiwert
                          ON teigenschaftkombiwert.kEigenschaftKombi = tartikel.kEigenschaftKombi
                          AND teigenschaftkombiwert.kEigenschaft = ' . $es0 . '
                          AND teigenschaftkombiwert.kEigenschaftWert = ' . $esWert0;
            $having = '';
            if ($es1 > 0 && $esWert1 > 0) {
                $join = ' JOIN teigenschaftkombiwert
                              ON teigenschaftkombiwert.kEigenschaftKombi = tartikel.kEigenschaftKombi
                              AND teigenschaftkombiwert.kEigenschaft IN(' . $es0 . ', ' . $es1 . ')
                              AND teigenschaftkombiwert.kEigenschaftWert IN(' . $esWert0 . ', ' . $esWert1 . ')';

                $having = ' HAVING COUNT(*) = 2';
            }
            $product = Shop::Container()->getDB()->getSingleInt(
                'SELECT kArtikel
                    FROM tartikel' . $join . '
                    WHERE tartikel.kVaterArtikel = :pid
                    GROUP BY teigenschaftkombiwert.kEigenschaftKombi' . $having,
                'kArtikel',
                ['pid' => $productID]
            );
            if ($product > 0) {
                return $product;
            }
        }

        return 0;
    }

    /**
     * @return stdClass[]
     * @since 5.0.0
     * @former gibVarKombiEigenschaftsWerte()
     */
    public static function getVarCombiAttributeValues(int $productID, bool $visibility = true): array
    {
        $attributeValues = [];
        if ($productID <= 0 || !self::isVariChild($productID)) {
            return $attributeValues;
        }
        $product        = new Artikel();
        $productOptions = new stdClass();
        if (!$visibility) {
            $productOptions->nKeineSichtbarkeitBeachten = 1;
        }

        $product->fuelleArtikel($productID, $productOptions);

        if (GeneralObject::hasCount('oVariationenNurKind_arr', $product)) {
            foreach ($product->oVariationenNurKind_arr as $child) {
                $attributeValue                       = new stdClass();
                $attributeValue->kEigenschaftWert     = $child->Werte[0]->kEigenschaftWert;
                $attributeValue->kEigenschaft         = $child->kEigenschaft;
                $attributeValue->cEigenschaftName     = $child->cName;
                $attributeValue->cEigenschaftWertName = $child->Werte[0]->cName;

                $attributeValues[] = $attributeValue;
            }
        }

        return $attributeValues;
    }

    /**
     * @param Variation[] $variations
     * @former findeVariation()
     * @since 5.0.0
     */
    public static function findVariation(array $variations, int $propertyID, int $propertyValueID): false|VariationValue
    {
        foreach ($variations as $variation) {
            if ($variation->kEigenschaft !== $propertyID || !isset($variation->Werte)) {
                continue;
            }
            foreach ($variation->Werte as $value) {
                $value->kEigenschaftWert = (int)$value->kEigenschaftWert;
                if ($value->kEigenschaftWert === $propertyValueID) {
                    return $value;
                }
            }
        }

        return false;
    }

    public static function showAvailabilityForm(Artikel $product, string $config): int
    {
        if (
            $config !== 'N'
            && $product->cLagerBeachten === 'Y'
            && ((int)$product->inWarenkorbLegbar === \INWKNICHTLEGBAR_LAGER
                || (int)$product->inWarenkorbLegbar === \INWKNICHTLEGBAR_LAGERVAR
                || ($product->fLagerbestand <= 0 && $product->cLagerKleinerNull !== 'Y'))
        ) {
            return match ($config) {
                'Y'     => 1,
                'P'     => 2,
                default => 3,
            };
        }

        return 0;
    }

    /**
     * @param array<mixed>|null $conf
     * @former gibArtikelXSelling()
     * @since 5.0.0
     */
    public static function getXSelling(int $productID, ?bool $isParent = null, ?array $conf = null): ?stdClass
    {
        $data = self::getXSellingIDs($productID, $isParent, $conf);
        if ($data === null) {
            return null;
        }

        return self::buildXSellersFromIDs($data, $productID);
    }

    /**
     * @param array<mixed>|null $conf
     * @since 5.2.0
     */
    public static function getXSellingIDs(int $productID, ?bool $isParent = null, ?array $conf = null): ?stdClass
    {
        if ($productID <= 0) {
            return null;
        }
        $xSelling                         = new stdClass();
        $xSelling->Standard               = new stdClass();
        $xSelling->Kauf                   = new stdClass();
        $xSelling->Standard->XSellGruppen = [];
        $xSelling->Kauf->Artikel          = [];
        $xSelling->Kauf->productIDs       = [];
        $conf                             = $conf ?? Shop::getSettings([\CONF_ARTIKELDETAILS])['artikeldetails'];
        $db                               = Shop::Container()->getDB();
        self::getXSellingDefault($xSelling, $db, $productID, $conf);
        self::getXSellingPurchases($xSelling, $db, $productID, $isParent ?? self::isParent($productID), $conf);

        return $xSelling;
    }

    /**
     * @param array<mixed> $conf
     */
    private static function getXSellingDefault(stdClass $xSelling, DbInterface $db, int $productID, array $conf): void
    {
        if ($conf['artikeldetails_xselling_standard_anzeigen'] !== 'Y') {
            return;
        }
        $stockFilter = Shop::getProductFilter()->getFilterSQL()->getStockFilterSQL();
        $data        = $db->getObjects(
            'SELECT txsell.*, txsellgruppe.cName, txsellgruppe.cBeschreibung
                FROM txsell
                JOIN tartikel
                    ON txsell.kXSellArtikel = tartikel.kArtikel
                LEFT JOIN txsellgruppe
                    ON txsellgruppe.kXSellGruppe = txsell.kXSellGruppe
                    AND txsellgruppe.kSprache = :lid
                WHERE txsell.kArtikel = :aid' . $stockFilter . '
                ORDER BY tartikel.cName',
            ['lid' => Shop::getLanguageID(), 'aid' => $productID]
        );
        $groups      = group($data, fn(stdClass $e) => $e->kXSellGruppe);
        foreach ($groups as $products) {
            $group             = new stdClass();
            $group->productIDs = [];
            foreach ($products as $xs) {
                $group->Name         = $xs->cName;
                $group->Beschreibung = $xs->cBeschreibung;
                $group->productIDs[] = (int)$xs->kXSellArtikel;
            }
            $xSelling->Standard->XSellGruppen[] = $group;
        }
    }

    /**
     * @param array<mixed> $conf
     */
    private static function getXSellingPurchases(
        stdClass $xSelling,
        DbInterface $db,
        int $productID,
        bool $isParent,
        array $conf
    ): void {
        if (
            $conf['artikeldetails_xselling_kauf_anzeigen'] !== 'Y'
            || (int)$conf['artikeldetails_xselling_kauf_anzahl'] === 0
        ) {
            return;
        }

        $limit       = (int)$conf['artikeldetails_xselling_kauf_anzahl'];
        $xsellHelper = new XSelling($db);
        $xsell       = $xsellHelper->getXSellingPurchase(
            $productID,
            $isParent,
            $conf['artikeldetails_xselling_kauf_parent'] === 'Y',
            $limit
        );

        $xSelling->Kauf->productIDs = $xsell;
    }

    /**
     * @since 5.2.0
     * @param array<mixed>|stdClass|null $xSelling
     */
    public static function buildXSellersFromIDs(array|stdClass|null $xSelling, int $productID): stdClass
    {
        if ($xSelling === null) {
            return (object)[
                'kArtikelXSellerKey_arr' => [],
                'oArtikelArr'            => [],
                'Standard'               => null,
                'Kauf'                   => (object)[
                    'Artikel' => [],
                ],
            ];
        }
        $xSelling           = (object)$xSelling;
        $options            = Artikel::getDefaultOptions();
        $options->nShipping = 0;
        $db                 = Shop::Container()->getDB();
        $cache              = Shop::Container()->getCache();
        $languageID         = Shop::getLanguageID();
        $currency           = Frontend::getCurrency();
        $cgroup             = Frontend::getCustomerGroup();
        $cgroupID           = $cgroup->getID();
        foreach ($xSelling->Standard->XSellGruppen as $group) {
            $group->Artikel = [];
            foreach ($group->productIDs as $id) {
                $product = new Artikel($db, $cgroup, $currency, $cache);
                $product->fuelleArtikel($id, $options, $cgroupID, $languageID);
                if ($product->kArtikel > 0 && $product->aufLagerSichtbarkeit()) {
                    $group->Artikel[] = $product;
                }
            }
            $group->Artikel = self::separateByAvailability($group->Artikel);
            unset($group->productIDs);
        }
        foreach ($xSelling->Kauf->productIDs as $id) {
            $product = new Artikel($db, $cgroup, $currency, $cache);
            $product->fuelleArtikel($id, $options, $cgroupID, $languageID);
            if ($product->kArtikel > 0 && $product->aufLagerSichtbarkeit()) {
                $xSelling->Kauf->Artikel[] = $product;
            }
        }
        $xSelling->Kauf->Artikel = self::separateByAvailability($xSelling->Kauf->Artikel ?? []);
        unset($xSelling->Kauf->productIDs);

        \executeHook(\HOOK_ARTIKEL_INC_XSELLING, [
            'kArtikel' => $productID,
            'xSelling' => &$xSelling
        ]);

        return $xSelling;
    }

    /**
     * @param array<mixed> $notices
     * @param array<mixed> $conf - product details config section
     * @param Artikel|null $product
     * @return string[]
     * @former bearbeiteFrageZumProdukt()
     * @since 5.0.0
     */
    public static function checkProductQuestion(array $notices, array $conf, ?Artikel $product = null): array
    {
        if ($product === null) {
            \trigger_error('Calling ' . __METHOD__ . ' without product instance is deprecated.', \E_USER_DEPRECATED);
        }
        if ($conf['artikeldetails_fragezumprodukt_anzeigen'] === 'N') {
            $notices[] = Shop::Lang()->get('productquestionPleaseLogin', 'errorMessages');

            return $notices;
        }
        $missingData = self::getMissingProductQuestionFormData($conf);
        Shop::Smarty()->assign('fehlendeAngaben_fragezumprodukt', $missingData);
        $resultCode = Form::eingabenKorrekt($missingData);

        \executeHook(\HOOK_ARTIKEL_INC_FRAGEZUMPRODUKT_PLAUSI);
        if ($resultCode) {
            $honeypotCheck = Form::honeypotWasFilledOut($_POST);
            $floodCheck    = self::checkProductQuestionFloodProtection((int)$conf['produktfrage_sperre_minuten']);
            if ($floodCheck === false && $honeypotCheck === false) {
                $checkbox        = new CheckBox();
                $customerGroupID = Frontend::getCustomerGroup()->getID();
                $inquiry         = self::getProductQuestionFormDefaults();

                \executeHook(\HOOK_ARTIKEL_INC_FRAGEZUMPRODUKT);
                if (empty($inquiry->cNachname)) {
                    $inquiry->cNachname = '';
                }
                if (empty($inquiry->cVorname)) {
                    $inquiry->cVorname = '';
                }
                $checkbox->triggerSpecialFunction(
                    \CHECKBOX_ORT_FRAGE_ZUM_PRODUKT,
                    $customerGroupID,
                    true,
                    $_POST,
                    ['oKunde' => $inquiry, 'oNachricht' => $inquiry]
                )->checkLogging(\CHECKBOX_ORT_FRAGE_ZUM_PRODUKT, $customerGroupID, $_POST, true);
                Shop::Smarty()->assign('PositiveFeedback', self::sendProductQuestion($product));
            } else {
                $notices[] = Shop::Lang()->get('questionNotPossible', 'messages');
            }
        } elseif (isset($missingData['email']) && $missingData['email'] === 3) {
            $notices[] = Shop::Lang()->get('blockedEmail');
        } else {
            $notices[] = Shop::Lang()->get('mandatoryFieldNotification', 'errorMessages');
        }

        return $notices;
    }

    /**
     * @param array<mixed> $conf - product details config section
     * @return array<string, int>
     * @former gibFehlendeEingabenProduktanfrageformular()
     * @since 5.0.0
     */
    public static function getMissingProductQuestionFormData(array $conf): array
    {
        $ret = [];
        if (!$_POST['nachricht']) {
            $ret['nachricht'] = 1;
        }
        if (SimpleMail::checkBlacklist($_POST['email'])) {
            $ret['email'] = 3;
        }
        if (Text::filterEmailAddress($_POST['email']) === false) {
            $ret['email'] = 2;
        }
        if (!$_POST['email']) {
            $ret['email'] = 1;
        }
        if ($conf['produktfrage_abfragen_vorname'] === 'Y' && !$_POST['vorname']) {
            $ret['vorname'] = 1;
        }
        if ($conf['produktfrage_abfragen_nachname'] === 'Y' && !$_POST['nachname']) {
            $ret['nachname'] = 1;
        }
        if ($conf['produktfrage_abfragen_firma'] === 'Y' && !$_POST['firma']) {
            $ret['firma'] = 1;
        }
        if ($conf['produktfrage_abfragen_fax'] === 'Y' && !$_POST['fax']) {
            $ret['fax'] = 1;
        }
        if ($conf['produktfrage_abfragen_tel'] === 'Y' && !$_POST['tel']) {
            $ret['tel'] = 1;
        }
        if ($conf['produktfrage_abfragen_mobil'] === 'Y' && !$_POST['mobil']) {
            $ret['mobil'] = 1;
        }
        if ($conf['produktfrage_abfragen_captcha'] !== 'N' && !Form::validateCaptcha($_POST)) {
            $ret['captcha'] = 2;
        }
        $checkBox = new CheckBox();

        return \array_merge(
            $ret,
            $checkBox->validateCheckBox(
                \CHECKBOX_ORT_FRAGE_ZUM_PRODUKT,
                Frontend::getCustomerGroup()->getID(),
                $_POST,
                true
            )
        );
    }

    /**
     * @former baueProduktanfrageFormularVorgaben()
     * @since 5.0.0
     */
    public static function getProductQuestionFormDefaults(): stdClass
    {
        $msg             = Form::getDefaultCustomerFormInputs();
        $msg->cNachricht = isset($_POST['nachricht']) ? Text::filterXSS($_POST['nachricht']) : null;

        return $msg;
    }

    /**
     * @former sendeProduktanfrage()
     * @since 5.0.0
     */
    public static function sendProductQuestion(?Artikel $product = null): string
    {
        if ($product === null) {
            $product = $GLOBALS['AktuellerArtikel'];
            \trigger_error('Calling ' . __METHOD__ . ' without product instance is deprecated.', \E_USER_DEPRECATED);
        }
        $conf             = Shop::getSettings([\CONF_EMAILS, \CONF_ARTIKELDETAILS, \CONF_GLOBAL]);
        $data             = new stdClass();
        $data->tartikel   = $product;
        $data->tnachricht = self::getProductQuestionFormDefaults();
        $replyToName      = '';
        if ($data->tnachricht->cVorname) {
            $replyToName = $data->tnachricht->cVorname . ' ';
        }
        if ($data->tnachricht->cNachname) {
            $replyToName .= $data->tnachricht->cNachname;
        }
        if ($data->tnachricht->cFirma) {
            if ($data->tnachricht->cNachname || $data->tnachricht->cVorname) {
                $replyToName .= ' - ';
            }
            $replyToName .= $data->tnachricht->cFirma;
        }
        $mail = new stdClass();
        if (isset($conf['artikeldetails']['artikeldetails_fragezumprodukt_email'])) {
            $mail->toEmail = $conf['artikeldetails']['artikeldetails_fragezumprodukt_email'];
        }
        if (empty($mail->toEmail)) {
            $mail->toEmail = $conf['emails']['email_master_absender'];
        }
        $mail->toName       = $conf['global']['global_shopname'];
        $mail->replyToEmail = $data->tnachricht->cMail;
        $mail->replyToName  = $replyToName;
        $data->mail         = $mail;

        $mailer = Shop::Container()->getMailer();
        $mail   = new Mail();
        $mail   = $mail->createFromTemplateID(\MAILTEMPLATE_PRODUKTANFRAGE, $data);
        if ($conf['artikeldetails']['produktfrage_kopiekunde'] === 'Y') {
            $mail->addCopyRecipient($data->tnachricht->cMail);
        }
        $mailer->send($mail);

        $history             = new stdClass();
        $history->kSprache   = Shop::getLanguageID();
        $history->kArtikel   = Shop::$kArtikel;
        $history->cAnrede    = $data->tnachricht->cAnrede;
        $history->cVorname   = $data->tnachricht->cVorname;
        $history->cNachname  = $data->tnachricht->cNachname;
        $history->cFirma     = $data->tnachricht->cFirma;
        $history->cTel       = $data->tnachricht->cTel;
        $history->cMobil     = $data->tnachricht->cMobil;
        $history->cFax       = $data->tnachricht->cFax;
        $history->cMail      = $data->tnachricht->cMail;
        $history->cNachricht = $data->tnachricht->cNachricht;
        $history->cIP        = Request::getRealIP();
        $history->dErstellt  = 'NOW()';

        $inquiryID = Shop::Container()->getDB()->insert('tproduktanfragehistory', $history);
        Shop::Container()->getAlertService()->addSuccess(
            Shop::Lang()->get('thankYouForQuestion', 'messages'),
            'thankYouForQuestion'
        );
        Campaign::setCampaignAction(\KAMPAGNE_DEF_FRAGEZUMPRODUKT, $inquiryID, 1.0);

        return Shop::Lang()->get('thankYouForQuestion', 'messages');
    }

    /**
     * @former floodSchutzProduktanfrage()
     * @since 5.0.0
     */
    public static function checkProductQuestionFloodProtection(int $min = 0): bool
    {
        if ($min <= 0) {
            return false;
        }
        $id = Shop::Container()->getDB()->getSingleInt(
            'SELECT kProduktanfrageHistory
                FROM tproduktanfragehistory
                WHERE cIP = :ip
                    AND DATE_SUB(NOW(), INTERVAL :min MINUTE) < dErstellt',
            'kProduktanfrageHistory',
            ['ip' => Request::getRealIP(), 'min' => $min]
        );

        return $id > 0;
    }

    /**
     * @param string[]          $notices
     * @param array<mixed>|null $conf - product details config section
     * @return string[]
     * @former bearbeiteBenachrichtigung()
     * @since 5.0.0
     */
    public static function checkAvailabilityMessage(array $notices, ?array $conf = null): array
    {
        $conf = $conf ?? Shop::getSettings([\CONF_ARTIKELDETAILS])['artikeldetails'];
        if (
            !isset($_POST['a'], $conf['benachrichtigung_nutzen'])
            || (int)$_POST['a'] <= 0
            || $conf['benachrichtigung_nutzen'] === 'N'
        ) {
            return $notices;
        }
        $missingData = self::getMissingAvailibilityFormData($conf);
        Shop::Smarty()->assign('fehlendeAngaben_benachrichtigung', $missingData);
        $resultCode = Form::eingabenKorrekt($missingData);

        \executeHook(\HOOK_ARTIKEL_INC_BENACHRICHTIGUNG_PLAUSI);
        if ($resultCode) {
            $floodCheck    = self::checkAvailibityFormRateLimit($conf['benachrichtigung_sperre_minuten']);
            $honeypotCheck = Form::honeypotWasFilledOut($_POST);
            if ($floodCheck === false && $honeypotCheck === false) {
                $dbHandler = Shop::Container()->getDB();
                $refData   = (new OptinRefData())
                    ->setSalutation('')
                    ->setFirstName(Text::filterXSS($dbHandler->escape(\strip_tags($_POST['vorname'] ?? ''))) ?: '')
                    ->setLastName(Text::filterXSS($dbHandler->escape(\strip_tags($_POST['nachname'] ?? ''))) ?: '')
                    ->setProductId(Request::pInt('a'))
                    ->setEmail(Text::filterXSS($dbHandler->escape(\strip_tags($_POST['email']))) ?: '')
                    ->setLanguageID(Shop::getLanguageID())
                    ->setCustomerGroupID(Frontend::getCustomer()->getGroupID())
                    ->setRealIP(Request::getRealIP());

                $inquiry            = self::getAvailabilityFormDefaults();
                $inquiry->kSprache  = Shop::getLanguage();
                $inquiry->kArtikel  = Request::pInt('a');
                $inquiry->cIP       = Request::getRealIP();
                $inquiry->dErstellt = 'NOW()';
                $inquiry->nStatus   = 0;
                $inquiry->cNachname = $inquiry->cNachname ?? '';
                $inquiry->cVorname  = $inquiry->cVorname ?? '';
                $checkBox           = new CheckBox(0, $dbHandler);
                $customerGroupID    = Frontend::getCustomerGroup()->getID();
                $checkBox->triggerSpecialFunction(
                    \CHECKBOX_ORT_FRAGE_VERFUEGBARKEIT,
                    $customerGroupID,
                    true,
                    $_POST,
                    ['oKunde' => $inquiry, 'oNachricht' => $inquiry]
                )->checkLogging(\CHECKBOX_ORT_FRAGE_VERFUEGBARKEIT, $customerGroupID, $_POST, true);

                try {
                    (new Optin(OptinAvailAgain::class))
                        ->getOptinInstance()
                        ->createOptin($refData)
                        ->sendActivationMail();
                } catch (\Exception) {
                }
            } else {
                $notices[] = Shop::Lang()->get('notificationNotPossible', 'messages');
            }
        } elseif (isset($missingData['email']) && $missingData['email'] === 3) {
            $notices[] = Shop::Lang()->get('blockedEmail');
        } else {
            $notices[] = Shop::Lang()->get('mandatoryFieldNotification', 'errorMessages');
        }

        return $notices;
    }

    /**
     * @param array<mixed>|null $conf - product details config section
     * @return array<string, int>
     * @former gibFehlendeEingabenBenachrichtigungsformular()
     * @since 5.0.0
     */
    public static function getMissingAvailibilityFormData(?array $conf = null): array
    {
        $ret  = [];
        $conf = $conf ?? Shop::getSettings([\CONF_ARTIKELDETAILS])['artikeldetails'];
        if (!$_POST['email']) {
            $ret['email'] = 1;
        } elseif (Text::filterEmailAddress($_POST['email']) === false) {
            $ret['email'] = 2;
        }
        if (SimpleMail::checkBlacklist($_POST['email'])) {
            $ret['email'] = 3;
        }
        if (empty($_POST['vorname']) && $conf['benachrichtigung_abfragen_vorname'] === 'Y') {
            $ret['vorname'] = 1;
        }
        if (empty($_POST['nachname']) && $conf['benachrichtigung_abfragen_nachname'] === 'Y') {
            $ret['nachname'] = 1;
        }
        if ($conf['benachrichtigung_abfragen_captcha'] !== 'N' && !Form::validateCaptcha($_POST)) {
            $ret['captcha'] = 2;
        }
        // CheckBox Plausi
        $checkbox        = new CheckBox();
        $customerGroupID = Frontend::getCustomerGroup()->getID();

        return \array_merge(
            $ret,
            $checkbox->validateCheckBox(\CHECKBOX_ORT_FRAGE_VERFUEGBARKEIT, $customerGroupID, $_POST, true)
        );
    }

    /**
     * @former baueFormularVorgabenBenachrichtigung()
     * @since 5.0.0
     */
    public static function getAvailabilityFormDefaults(): stdClass
    {
        return Form::getDefaultCustomerFormInputs();
    }

    /**
     * checkAvailibityFormFloodProtection is public and therefore cannot be dismissed
     * This method uses RateLimiter to check for multiple requests from the specified IP
     * @former floodSchutzBenachrichtigung()
     * @since 5.2.3
     */
    public static function checkAvailibityFormRateLimit(int $min): bool
    {
        if (!$min) {
            return false;
        }
        $limiter = new Limiter(Shop::Container()->getDB());
        $limiter->init(Request::getRealIP());
        $limiter->setLimit(1);
        $limiter->setFloodMinutes($min);
        $limiter->setCleanupMinutes($min + 5);
        if ($limiter->check() !== true) {
            return true;
        }
        $limiter->persist();
        $limiter->cleanup();

        return false;
    }

    /**
     * @former gibNaviBlaettern()
     * @since 5.0.0
     */
    public static function getProductNavigation(int $productID, int $categoryID): stdClass
    {
        $nav                = new stdClass();
        $currency           = Frontend::getCurrency();
        $customerGroup      = Frontend::getCustomerGroup();
        $languageID         = Shop::getLanguageID();
        $customerGroupID    = $customerGroup->getID();
        $db                 = Shop::Container()->getDB();
        $cache              = Shop::Container()->getCache();
        $options            = Artikel::getDefaultOptions();
        $options->nShipping = 0;
        // Wurde der Artikel von der Artikelübersicht aus angeklickt?
        if (
            $productID > 0
            && isset($_SESSION['oArtikelUebersichtKey_arr'])
            && \count($_SESSION['oArtikelUebersichtKey_arr']) > 0
        ) {
            $collection = $_SESSION['oArtikelUebersichtKey_arr'];
            if (!($collection instanceof Collection)) {
                $collection = \collect($collection);
            }
            // Such die Position des aktuellen Artikels im Array der Artikelübersicht
            $prevID = 0;
            $nextID = 0;
            $index  = $collection->search($productID, true);
            if ($index === 0) {
                // Artikel ist an der ersten Position => es gibt nur einen nächsten Artikel (oder keinen :))
                $nextID = $collection[$index + 1] ?? 0;
            } elseif ($index === ($collection->count() - 1)) {
                // Artikel ist an der letzten Position => es gibt nur einen voherigen Artikel
                $prevID = $collection[$index - 1] ?? 0;
            } elseif ($index !== false) {
                $nextID = $collection[$index + 1] ?? 0;
                $prevID = $collection[$index - 1] ?? 0;
            }
            if ($nextID > 0) {
                $nav->naechsterArtikel = (new Artikel($db, $customerGroup, $currency, $cache))
                    ->fuelleArtikel($nextID, $options, $customerGroupID, $languageID);
                if ($nav->naechsterArtikel === null) {
                    unset($nav->naechsterArtikel);
                }
            }
            if ($prevID > 0) {
                $nav->vorherigerArtikel = (new Artikel($db, $customerGroup, $currency, $cache))
                    ->fuelleArtikel($prevID, $options, $customerGroupID, $languageID);
                if ($nav->vorherigerArtikel === null) {
                    unset($nav->vorherigerArtikel);
                }
            }
        }
        // Ist der Besucher nicht von der Artikelübersicht gekommen?
        if ($categoryID > 0 && (!isset($nav->vorherigerArtikel) && !isset($nav->naechsterArtikel))) {
            $stockFilter = Shop::getProductFilter()->getFilterSQL()->getStockFilterSQL();
            $prev        = $db->getSingleObject(
                'SELECT tartikel.kArtikel
                    FROM tkategorieartikel, tartikel
                    LEFT JOIN tartikelsichtbarkeit
                        ON tartikel.kArtikel = tartikelsichtbarkeit.kArtikel
                        AND tartikelsichtbarkeit.kKundengruppe = :cgid
                    WHERE tartikelsichtbarkeit.kArtikel IS NULL
                        AND tartikel.kArtikel = tkategorieartikel.kArtikel
                        AND tartikel.kVaterArtikel = 0
                        AND tkategorieartikel.kKategorie = :cid
                        AND tartikel.kArtikel < :pid ' . $stockFilter . '
                    ORDER BY tartikel.kArtikel DESC
                    LIMIT 1',
                ['cgid' => $customerGroupID, 'pid' => $productID, 'cid' => $categoryID]
            );
            $next        = $db->getSingleObject(
                'SELECT tartikel.kArtikel
                    FROM tkategorieartikel, tartikel
                    LEFT JOIN tartikelsichtbarkeit
                        ON tartikel.kArtikel = tartikelsichtbarkeit.kArtikel
                        AND tartikelsichtbarkeit.kKundengruppe = :cgid
                    WHERE tartikelsichtbarkeit.kArtikel IS NULL
                        AND tartikel.kArtikel = tkategorieartikel.kArtikel
                        AND tartikel.kVaterArtikel = 0
                        AND tkategorieartikel.kKategorie = :cid
                        AND tartikel.kArtikel > :pid ' . $stockFilter . '
                    ORDER BY tartikel.kArtikel
                    LIMIT 1',
                ['cgid' => $customerGroupID, 'pid' => $productID, 'cid' => $categoryID]
            );
            if ($prev !== null && !empty($prev->kArtikel)) {
                $nav->vorherigerArtikel = (new Artikel($db, $customerGroup, $currency, $cache))
                    ->fuelleArtikel((int)$prev->kArtikel, $options, $customerGroupID, $languageID);
            }
            if ($next !== null && !empty($next->kArtikel)) {
                $nav->naechsterArtikel = (new Artikel($db, $customerGroup, $currency, $cache))
                    ->fuelleArtikel((int)$next->kArtikel, $options, $customerGroupID, $languageID);
            }
        }

        return $nav;
    }

    /**
     * @param int $attributeValue
     * @return stdClass[]
     * @former gibNichtErlaubteEigenschaftswerte()
     * @since 5.0.0
     */
    public static function getNonAllowedAttributeValues(int $attributeValue): array
    {
        $nonAllowed  = Shop::Container()->getDB()->selectAll(
            'teigenschaftwertabhaengigkeit',
            'kEigenschaftWert',
            $attributeValue,
            'kEigenschaftWertZiel AS EigenschaftWert'
        );
        $nonAllowed2 = Shop::Container()->getDB()->selectAll(
            'teigenschaftwertabhaengigkeit',
            'kEigenschaftWertZiel',
            $attributeValue,
            'kEigenschaftWert AS EigenschaftWert'
        );

        return \array_merge($nonAllowed, $nonAllowed2);
    }

    /**
     * @param int[]|string|null     $redirectParam
     * @param bool                  $renew
     * @param null|Artikel          $product
     * @param float|int|string|null $amount
     * @param int                   $configItemID
     * @param array<mixed>          $notices
     * @return array<mixed>
     * @former baueArtikelhinweise()
     * @since 5.0.0
     */
    public static function getProductMessages(
        array|string|null $redirectParam = null,
        bool $renew = false,
        ?Artikel $product = null,
        float|int|string|null $amount = null,
        int $configItemID = 0,
        array $notices = []
    ): array {
        if ($redirectParam === null && isset($_GET['r'])) {
            $redirectParam = $_GET['r'];
        }
        if ($renew) {
            $notices = [];
        }
        if ($redirectParam) {
            $messages = \is_array($redirectParam) ? $redirectParam : \explode(',', $redirectParam);
            foreach (\array_unique($messages) as $message) {
                switch ($message) {
                    case \R_LAGERVAR:
                        $notices[] = Shop::Lang()->get('quantityNotAvailableVar', 'messages');
                        break;
                    case \R_VARWAEHLEN:
                        $notices[] = Shop::Lang()->get('chooseVariations', 'messages');
                        break;
                    case \R_VORBESTELLUNG:
                        $notices[] = Shop::Lang()->get('preorderNotPossible', 'messages');
                        break;
                    case \R_LOGIN:
                        $notices[] = Shop::Lang()->get('pleaseLogin', 'messages');
                        break;
                    case \R_LAGER:
                        $notices[] = Shop::Lang()->get('quantityNotAvailable', 'messages');
                        break;
                    case \R_MINDESTMENGE:
                        if ($product === null) {
                            if (Request::gInt('child') > 0) {
                                $product = new Artikel();
                                $product->fuelleArtikel(Request::gInt('child'));
                            } elseif (Request::gInt('a') > 0) {
                                $product = new Artikel();
                                $product->fuelleArtikel(Request::gInt('a'));
                            }
                        }
                        $notices[] = Texts::minOrderQTY(
                            $product ?? $GLOBALS['AktuellerArtikel'],
                            $amount ?? $_GET['n'] ?? 0,
                            $configItemID
                        );
                        break;
                    case \R_LOGIN_WUNSCHLISTE:
                        $notices[] = Shop::Lang()->get('loginWishlist', 'messages');
                        break;
                    case \R_MAXBESTELLMENGE:
                        $notices[] = Shop::Lang()->get('wkMaxorderlimit', 'messages');
                        break;
                    case \R_ARTIKELABNAHMEINTERVALL:
                        $notices[] = Shop::Lang()->get('wkPurchaseintervall', 'messages');
                        break;
                    case \R_UNVERKAEUFLICH:
                        $notices[] = Shop::Lang()->get('wkUnsalable', 'messages');
                        break;
                    case \R_AUFANFRAGE:
                        $notices[] = Shop::Lang()->get('wkOnrequest', 'messages');
                        break;
                    case \R_EMPTY_TAG:
                        $notices[] = Shop::Lang()->get('tagArtikelEmpty', 'messages');
                        break;
                    case \R_EMPTY_VARIBOX:
                        $notices[] = Shop::Lang()->get('artikelVariBoxEmpty', 'messages');
                        break;
                    case \R_MISSING_TOKEN:
                        $notices[] = Shop::Lang()->get('missingToken', 'messages');
                        break;
                }
                \executeHook(\HOOK_ARTIKEL_INC_ARTIKELHINWEISSWITCH, [
                    'message' => $message,
                    'notices' => &$notices
                ]);
                if (\count($notices) === 0) {
                    $notices[] = Shop::Lang()->get('unknownError', 'messages');
                }
            }
        }

        return $notices;
    }

    /**
     * Baue Blätter Navi - Dient für die Blätternavigation unter Bewertungen in der Artikelübersicht
     * @former baueBewertungNavi()
     * @since 5.0.0
     */
    public static function getRatingNavigation(
        int $ratingPage,
        int $ratingStars,
        int $ratingCount,
        int $pageCount = 0
    ): stdClass {
        $navigation         = new stdClass();
        $navigation->nAktiv = 0;
        if (!$pageCount) {
            $pageCount = 10;
        }
        // Ist die Anzahl der Bewertungen für einen bestimmten Artikel, in einer bestimmten Sprache größer als
        // die im Backend eingestellte maximale Anzahl an Bewertungen für eine Seite?
        if ($ratingCount > $pageCount) {
            $counts = [];
            // Anzahl an Seiten
            $pages = \ceil($ratingCount / $pageCount);
            $max   = 5; // Zeige in der Navigation nur maximal X Seiten an
            $start = 0; // Wenn die aktuelle Seite - $nMaxAnzeige größer 0 ist, wird nAnfang gesetzt
            $end   = 0; // Wenn die aktuelle Seite + $nMaxAnzeige <= $nSeitenist, wird nEnde gesetzt
            $prev  = $ratingPage - 1; // Zum zurück blättern in der Navigation
            if ($prev === 0) {
                $prev = 1;
            }
            $next = $ratingPage + 1; // Zum vorwärts blättern in der Navigation
            if ($next >= $pages) {
                $next = $pages;
            }
            // Ist die maximale Anzahl an Seiten > als die Anzahl erlaubter Seiten in der Navigation?
            if ($pages > $max) {
                // Diese Variablen ermitteln die aktuellen Seiten in der Navigation, die angezeigt werden sollen.
                // Begrenzt durch $nMaxAnzeige.
                // Ist die aktuelle Seite nach dem abzug der Begrenzung größer oder gleich 1?
                if (($ratingPage - $max) >= 1) {
                    $start = 1;
                    $nVon  = ($ratingPage - $max) + 1;
                } else {
                    $nVon = 1;
                }
                // Ist die aktuelle Seite nach dem addieren der Begrenzung kleiner als die maximale Anzahl der Seiten
                if (($ratingPage + $max) < $pages) {
                    $end  = $pages;
                    $nBis = ($ratingPage + $max) - 1;
                } else {
                    $nBis = $pages;
                }
                // Baue die Seiten für die Navigation
                for ($i = $nVon; $i <= $nBis; $i++) {
                    $counts[] = $i;
                }
            } else {
                // Baue die Seiten für die Navigation
                for ($i = 1; $i <= $pages; $i++) {
                    $counts[] = $i;
                }
            }
            // Blaetter Objekt um später in Smarty damit zu arbeiten
            $navigation->nSeiten             = $pages;
            $navigation->nVoherige           = $prev;
            $navigation->nNaechste           = $next;
            $navigation->nAnfang             = $start;
            $navigation->nEnde               = $end;
            $navigation->nBlaetterAnzahl_arr = $counts;
            $navigation->nAktiv              = 1;
        }

        $navigation->nSterne        = $ratingStars;
        $navigation->nAktuelleSeite = $ratingPage;
        $navigation->nVon           = (($navigation->nAktuelleSeite - 1) * $pageCount) + 1;
        $navigation->nBis           = $navigation->nAktuelleSeite * $pageCount;

        if ($navigation->nBis > $ratingCount) {
            --$navigation->nBis;
        }

        return $navigation;
    }

    /**
     * Mappt den Fehlercode für Bewertungen
     *
     * @former mappingFehlerCode()
     * @since 5.0.0
     */
    public static function mapErrorCode(string $code, float|int|string $credit = 0.0): string
    {
        $error = match ($code) {
            'f01'   => Shop::Lang()->get('mandatoryFieldNotification', 'errorMessages'),
            'f02'   => Shop::Lang()->get('bewertungBewexist', 'errorMessages'),
            'f03'   => Shop::Lang()->get('bewertungBewnotbought', 'errorMessages'),
            'f04'   => Shop::Lang()->get('loginFirst', 'product rating'),
            'f05'   => Shop::Lang()->get('ratingRange', 'errorMessages'),
            'h01'   => Shop::Lang()->get('bewertungBewadd', 'messages'),
            'h02'   => Shop::Lang()->get('bewertungHilfadd', 'messages'),
            'h03'   => Shop::Lang()->get('bewertungHilfchange', 'messages'),
            'h04'   => \sprintf(Shop::Lang()->get('bewertungBewaddCredits', 'messages'), (string)$credit),
            'h05'   => Shop::Lang()->get('bewertungBewaddacitvate', 'messages'),
            default => '',
        };
        \executeHook(\HOOK_ARTIKEL_INC_BEWERTUNGHINWEISSWITCH, ['error' => $error]);

        return $error;
    }

    /**
     * @param Artikel $parent
     * @param Artikel $child
     * @return Artikel
     * @former fasseVariVaterUndKindZusammen()
     * @since 5.0.0
     */
    public static function combineParentAndChild(Artikel $parent, Artikel $child): Artikel
    {
        $product                              = $child;
        $variChildID                          = (int)$child->kArtikel;
        $product->kArtikel                    = $parent->kArtikel;
        $product->kVariKindArtikel            = $variChildID;
        $product->nIstVater                   = 1;
        $product->kVaterArtikel               = $parent->kArtikel;
        $product->kEigenschaftKombi           = $parent->kEigenschaftKombi;
        $product->kEigenschaftKombi_arr       = $parent->kEigenschaftKombi_arr;
        $product->fDurchschnittsBewertung     = $parent->fDurchschnittsBewertung;
        $product->Bewertungen                 = $parent->Bewertungen ?? null;
        $product->HilfreichsteBewertung       = $parent->HilfreichsteBewertung ?? null;
        $product->oVariationKombiVorschau_arr = $parent->oVariationKombiVorschau_arr ?? [];
        $product->oVariationDetailPreis_arr   = $parent->oVariationDetailPreis_arr;
        $product->cVaterURL                   = $parent->cURL;
        $product->cVaterURLFull               = $parent->cURLFull;
        $product->VaterFunktionsAttribute     = $parent->FunktionsAttribute;

        \executeHook(\HOOK_ARTIKEL_INC_FASSEVARIVATERUNDKINDZUSAMMEN, ['article' => $product]);

        return $product;
    }

    /**
     * @return Artikel[]
     * @former holeAehnlicheArtikel()
     * @since 5.0.0
     */
    public static function getSimilarProductsByID(int $productID): array
    {
        $products        = [];
        $limit           = ' LIMIT 3';
        $conf            = Shop::getSettings([\CONF_ARTIKELDETAILS])['artikeldetails'];
        $xSeller         = self::getXSelling($productID, null, $conf);
        $xsellProductIDs = [];
        if ($xSeller !== null && GeneralObject::hasCount('XSellGruppen', $xSeller->Standard)) {
            foreach ($xSeller->Standard->XSellGruppen as $xSeller) {
                if (!GeneralObject::hasCount('Artikel', $xSeller)) {
                    continue;
                }
                foreach ($xSeller->Artikel as $product) {
                    $product->kArtikel = (int)$product->kArtikel;
                    if (!\in_array($product->kArtikel, $xsellProductIDs, true)) {
                        $xsellProductIDs[] = $product->kArtikel;
                    }
                }
            }
        }
        if (isset($xSeller->Kauf) && GeneralObject::hasCount('XSellGruppen', $xSeller->Kauf)) {
            foreach ($xSeller->Kauf->XSellGruppen as $xSeller) {
                if (!GeneralObject::hasCount('Artikel', $xSeller)) {
                    continue;
                }
                foreach ($xSeller->Artikel as $product) {
                    $product->kArtikel = (int)$product->kArtikel;
                    if (!\in_array($product->kArtikel, $xsellProductIDs, true)) {
                        $xsellProductIDs[] = $product->kArtikel;
                    }
                }
            }
        }

        $xsellSQL = \count($xsellProductIDs) > 0
            ? ' AND tartikel.kArtikel NOT IN (' . \implode(',', $xsellProductIDs) . ') '
            : '';

        if ($productID > 0) {
            if ((int)$conf['artikeldetails_aehnlicheartikel_anzahl'] > 0) {
                $limit = ' LIMIT ' . (int)$conf['artikeldetails_aehnlicheartikel_anzahl'];
            }
            $db                = Shop::Container()->getDB();
            $cache             = Shop::Container()->getCache();
            $stockFilterSQL    = Shop::getProductFilter()->getFilterSQL()->getStockFilterSQL();
            $currency          = Frontend::getCurrency();
            $customerGroup     = Frontend::getCustomerGroup();
            $customerGroupID   = $customerGroup->getID();
            $productAttributes = $db->getObjects(
                'SELECT tartikelmerkmal.kArtikel, tartikel.kVaterArtikel
                    FROM tartikelmerkmal
                        JOIN tartikel ON tartikel.kArtikel = tartikelmerkmal.kArtikel
                            AND tartikel.kVaterArtikel != :kArtikel
                            AND (tartikel.nIstVater = 1 OR tartikel.kEigenschaftKombi = 0)
                        JOIN tartikelmerkmal similarMerkmal ON similarMerkmal.kArtikel = :kArtikel
                            AND similarMerkmal.kMerkmal = tartikelmerkmal.kMerkmal
                            AND similarMerkmal.kMerkmalWert = tartikelmerkmal.kMerkmalWert
                        LEFT JOIN tartikelsichtbarkeit ON tartikelsichtbarkeit.kArtikel = tartikel.kArtikel
                            AND tartikelsichtbarkeit.kKundengruppe = :customerGroupID
                    WHERE tartikelsichtbarkeit.kArtikel IS NULL
                        AND tartikelmerkmal.kArtikel != :kArtikel ' . $stockFilterSQL . ' ' . $xsellSQL . '
                    GROUP BY tartikelmerkmal.kArtikel
                    ORDER BY COUNT(tartikelmerkmal.kMerkmal) DESC
                    ' . $limit,
                [
                    'kArtikel'        => $productID,
                    'customerGroupID' => $customerGroupID
                ]
            );
            if (\count($productAttributes) > 0) {
                $defaultOptions = Artikel::getDefaultOptions();
                foreach ($productAttributes as $productAttribute) {
                    $product = new Artikel($db, $customerGroup, $currency, $cache);
                    $id      = $productAttribute->kVaterArtikel > 0
                        ? $productAttribute->kVaterArtikel
                        : $productAttribute->kArtikel;
                    $product->fuelleArtikel($id, $defaultOptions, $customerGroupID);
                    if ($product->kArtikel > 0) {
                        $products[] = $product;
                    }
                }
            } else { // Falls es keine Merkmale gibt, in tsuchcachetreffer suchen
                $searchCacheHits = $db->getObjects(
                    'SELECT tsuchcachetreffer.kArtikel, tartikel.kVaterArtikel
                        FROM
                        (
                            SELECT kSuchCache
                            FROM tsuchcachetreffer
                            WHERE kArtikel = :pid
                            AND nSort <= 10
                        ) AS ssSuchCache
                        JOIN tsuchcachetreffer
                            ON tsuchcachetreffer.kSuchCache = ssSuchCache.kSuchCache
                            AND tsuchcachetreffer.kArtikel != :pid
                        LEFT JOIN tartikelsichtbarkeit
                            ON tsuchcachetreffer.kArtikel = tartikelsichtbarkeit.kArtikel
                            AND tartikelsichtbarkeit.kKundengruppe = :cgid
                        JOIN tartikel
                            ON tartikel.kArtikel = tsuchcachetreffer.kArtikel
                            AND tartikel.kVaterArtikel != :pid
                        WHERE tartikelsichtbarkeit.kArtikel IS NULL ' . $stockFilterSQL . ' ' . $xsellSQL . '
                        GROUP BY tsuchcachetreffer.kArtikel
                        ORDER BY COUNT(*) DESC' . $limit,
                    ['pid' => $productID, 'cgid' => $customerGroupID]
                );
                if (\count($searchCacheHits) > 0) {
                    $defaultOptions = Artikel::getDefaultOptions();
                    foreach ($searchCacheHits as $hit) {
                        $product = new Artikel($db, $customerGroup, $currency, $cache);
                        $id      = ($hit->kVaterArtikel > 0)
                            ? $hit->kVaterArtikel
                            : $hit->kArtikel;
                        $product->fuelleArtikel($id, $defaultOptions, $customerGroupID);
                        if ($product->kArtikel > 0) {
                            $products[] = $product;
                        }
                    }
                }
            }
        }
        \executeHook(\HOOK_ARTIKEL_INC_AEHNLICHEARTIKEL, ['oArtikel_arr' => &$products]);

        foreach ($products as $i => $product) {
            foreach ($xsellProductIDs as $xsellProductID) {
                if ($product->kArtikel === $xsellProductID) {
                    unset($products[$i]);
                }
            }
        }

        return $products;
    }

    /**
     * @former ProductBundleWK()
     * @since 5.0.0
     */
    public static function addProductBundleToCart(int $productID): bool
    {
        if ($productID <= 0) {
            return false;
        }
        $options                             = Artikel::getDefaultOptions();
        $options->nKeineSichtbarkeitBeachten = 1;

        return CartHelper::addProductIDToCart($productID, 1, [], 0, false, 0, $options);
    }

    /**
     * @param int              $productID
     * @param float|int|string $amount
     * @param array<mixed>     $variations
     * @param array<mixed>     $configGroups
     * @param array<mixed>     $configGroupAmounts
     * @param array<mixed>     $configItemAmounts
     * @param bool             $singleProductOutput
     * @return stdClass|null
     * @since 5.0.0
     */
    public static function buildConfig(
        int $productID,
        float|int|string $amount,
        array $variations,
        array $configGroups,
        array $configGroupAmounts,
        array $configItemAmounts,
        bool $singleProductOutput = false
    ): ?stdClass {
        $config                  = new stdClass();
        $config->fAnzahl         = $amount;
        $config->fGesamtpreis    = [0.0, 0.0];
        $config->cPreisLocalized = [];
        $config->cPreisString    = Shop::Lang()->get('priceAsConfigured', 'productDetails');

        if (!Configurator::checkLicense()) {
            return null;
        }
        foreach ($variations as $i => $nVariation) {
            $_POST['eigenschaftwert_' . $i] = $nVariation;
        }
        if (self::isParent($productID)) {
            $productID          = self::getArticleForParent($productID);
            $selectedProperties = self::getSelectedPropertiesForVarCombiArticle($productID);
        } else {
            $selectedProperties = self::getSelectedPropertiesForArticle($productID, false);
        }
        $product = new Artikel();
        $product->fuelleArtikel($productID, Artikel::getDefaultOptions());

        $config->nMinDeliveryDays      = $product->nMinDeliveryDays;
        $config->nMaxDeliveryDays      = $product->nMaxDeliveryDays;
        $config->cEstimatedDelivery    = $product->cEstimatedDelivery;
        $config->Lageranzeige          = new stdClass();
        $config->Lageranzeige->nStatus = $product->Lageranzeige?->nStatus;

        $amount = \max($amount, 1);
        if ($product->cTeilbar !== 'Y') {
            $amount = (int)$amount;
        } else {
            $amount = (float)$amount;
        }

        $_amount              = $singleProductOutput ? 1 : $amount;
        $config->fGesamtpreis = [
            Tax::getGross(
                $product->gibPreis($amount, $selectedProperties),
                Tax::getSalesTax($product->kSteuerklasse ?? 0)
            ) * $_amount,
            $product->gibPreis($amount, $selectedProperties) * $_amount
        ];

        $config->oKonfig_arr = $product->oKonfig_arr;

        foreach ($configGroups as $i => $data) {
            $configGroups[$i] = (array)$data;
        }
        /** @var Group $configGroup */
        foreach ($config->oKonfig_arr ?? [] as $configGroup) {
            $configGroup->bAktiv = false;
            $configGroupID       = $configGroup->getKonfiggruppe();
            $configItems         = \array_map('\intval', $configGroups[$configGroupID] ?? []);
            foreach ($configGroup->oItem_arr as $configItem) {
                $configItemID        = $configItem->getKonfigitem();
                $configItem->fAnzahl = (float)(
                    $configGroupAmounts[$configItem->getKonfiggruppe()] ?? $configItem->getInitial()
                );
                if ($configItem->fAnzahl > $configItem->getMax() || $configItem->fAnzahl < $configItem->getMin()) {
                    $configItem->fAnzahl = $configItem->getInitial();
                }
                if ($configItemAmounts && isset($configItemAmounts[$configItem->getKonfigitem()])) {
                    $configItem->fAnzahl = (float)$configItemAmounts[$configItem->getKonfigitem()];
                }
                if ($configItem->fAnzahl <= 0) {
                    $configItem->fAnzahl = 1.0;
                }
                $configItem->fAnzahlWK = $configItem->fAnzahl;
                if (!$singleProductOutput && !$configItem->ignoreMultiplier()) {
                    $configItem->fAnzahlWK *= $amount;
                }
                $configItem->bAktiv = \in_array($configItemID, $configItems, true);

                if ($configItem->bAktiv) {
                    $config->fGesamtpreis[0] += $configItem->getPreis() * $configItem->fAnzahlWK;
                    $config->fGesamtpreis[1] += $configItem->getPreis(true) * $configItem->fAnzahlWK;
                    $configGroup->bAktiv     = true;
                    if (
                        $configItem->getArtikel() !== null
                        && $configItem->getArtikel()->cLagerBeachten === 'Y'
                        && $config->nMinDeliveryDays < $configItem->getArtikel()->nMinDeliveryDays
                    ) {
                        $config->nMinDeliveryDays      = $configItem->getArtikel()->nMinDeliveryDays;
                        $config->nMaxDeliveryDays      = $configItem->getArtikel()->nMaxDeliveryDays;
                        $config->cEstimatedDelivery    = $configItem->getArtikel()->cEstimatedDelivery;
                        $config->Lageranzeige->nStatus = $configItem->getArtikel()->Lageranzeige->nStatus;
                    }
                }
            }
            $configGroup->oItem_arr = \array_values($configGroup->oItem_arr);
        }
        if (Frontend::getCustomerGroup()->mayViewPrices()) {
            $config->cPreisLocalized = [
                Preise::getLocalizedPriceString($config->fGesamtpreis[0]),
                Preise::getLocalizedPriceString($config->fGesamtpreis[1])
            ];
        } else {
            $config->cPreisLocalized = [Shop::Lang()->get('priceHidden')];
        }
        $config->nNettoPreise = Frontend::getCustomerGroup()->getIsMerchant();

        return $config;
    }

    /**
     * @former holeKonfigBearbeitenModus()
     * @since  5.0.0
     */
    public static function getEditConfigMode(int $configID, JTLSmarty $smarty): void
    {
        $cart = Frontend::getCart();
        if (
            !isset($cart->PositionenArr[$configID]->Artikel->FunktionsAttribute['jtl_voucher'])
            && !isset($cart->PositionenArr[$configID]->Artikel->FunktionsAttribute['jtl_voucher_flex'])
            && (!isset($cart->PositionenArr[$configID]) || !Item::checkLicense())
        ) {
            return;
        }
        $baseItem = $cart->PositionenArr[$configID];
        if ($baseItem->istKonfigVater()) {
            $customerGroupID    = Frontend::getCustomerGroup()->getID();
            $languageID         = Shop::getLanguageID();
            $configItems        = [];
            $configItemAmounts  = [];
            $configGroupAmounts = [];
            foreach ($cart->PositionenArr as $item) {
                if ($item->cUnique !== $baseItem->cUnique || !$item->istKonfigKind()) {
                    continue;
                }
                $configItem = new Item((int)$item->kKonfigitem, $languageID, $customerGroupID);

                $configItems[]                                   = $configItem->getKonfigitem();
                $configItemAmounts[$configItem->getKonfigitem()] = $item->nAnzahl / $baseItem->nAnzahl;
                if ($configItem->ignoreMultiplier()) {
                    $configGroupAmounts[$configItem->getKonfiggruppe()] = $item->nAnzahl;
                } else {
                    $configGroupAmounts[$configItem->getKonfiggruppe()] = $item->nAnzahl / $baseItem->nAnzahl;
                }
            }
            $smarty->assign('fAnzahl', $baseItem->nAnzahl)
                ->assign('kEditKonfig', $configID)
                ->assign('nKonfigitem_arr', $configItems)
                ->assign('nKonfigitemAnzahl_arr', $configItemAmounts)
                ->assign('nKonfiggruppeAnzahl_arr', $configGroupAmounts);
        }
        $attrValues = [];
        foreach ($baseItem->WarenkorbPosEigenschaftArr as $attr) {
            $attrValues[$attr->kEigenschaft] = (object)[
                'kEigenschaft'                  => $attr->kEigenschaft,
                'kEigenschaftWert'              => $attr->kEigenschaftWert,
                'cEigenschaftWertNameLocalized' => $attr->cEigenschaftWertName[$_SESSION['cISOSprache']],
            ];
        }
        if (\count($attrValues) > 0) {
            $smarty->assign('oEigenschaftWertEdit_arr', $attrValues);
        }
    }

    public static function getRatedByCurrentCustomer(int $productID, int $parentProductID = 0): bool
    {
        $customerID = Frontend::getCustomer()->getID();
        $productID  = !empty($parentProductID) ? $parentProductID : $productID;
        if ($customerID <= 0) {
            return false;
        }
        $ratings = Shop::Container()->getDB()->select(
            'tbewertung',
            ['kKunde', 'kArtikel', 'kSprache'],
            [$customerID, $productID, Shop::getLanguageID()]
        );

        return $ratings !== null && !empty($ratings->kBewertung);
    }

    /**
     * @param Artikel[] $items
     * @param bool      $shuffle
     * @return Artikel[]
     */
    public static function separateByAvailability(array $items, bool $shuffle = false): array
    {
        $available  = [];
        $outOfStock = [];
        foreach ($items as $item) {
            if ($item->kArtikel === null) {
                continue;
            }
            if ($item->inWarenkorbLegbar === 1) {
                $available[] = $item;
            } else {
                $outOfStock[] = $item;
            }
        }
        if ($shuffle) {
            \shuffle($available);
            \shuffle($outOfStock);
        }

        return \array_merge($available, $outOfStock);
    }

    public static function checkProductVisibility(int $productID, int $customerGroupID, ?DbInterface $db = null): bool
    {
        $cacheID = 'visibilityMustBeChecked' . $customerGroupID;
        if (!Shop::has($cacheID)) {
            $db = $db ?? Shop::Container()->getDB();
            Shop::set(
                $cacheID,
                $db->getSingleInt(
                    'SELECT COUNT(*) AS cnt 
                        FROM tartikelsichtbarkeit
                        WHERE kKundengruppe = :cgid',
                    'cnt',
                    ['cgid' => $customerGroupID]
                ) > 0
            );
        }
        if (Shop::get($cacheID) === false) {
            return true;
        }
        $db = $db ?? Shop::Container()->getDB();
        $id = $db->getSingleInt(
            'SELECT kArtikel
                FROM tartikelsichtbarkeit
                WHERE kArtikel = :pid
                    AND kKundengruppe = :cgid',
            'kArtikel',
            ['pid' => $productID, 'cgid' => $customerGroupID]
        );

        return $id < 1;
    }
}
