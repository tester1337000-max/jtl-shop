<?php

declare(strict_types=1);

namespace JTL\Sitemap\Factories;

use Generator;
use JTL\Language\LanguageHelper;
use JTL\Language\LanguageModel;
use JTL\Sitemap\Items\Product as Item;

use function Functional\first;
use function Functional\map;

/**
 * Class Product
 * @package JTL\Sitemap\Factories
 */
final class Product extends AbstractFactory
{
    /**
     * @inheritdoc
     */
    public function getCollection(array $languages, array $customerGroups): Generator
    {
        $defaultCustomerGroupID = first($customerGroups);
        $defaultLang            = LanguageHelper::getDefaultLanguage();
        $defaultLangID          = $defaultLang->getId();
        $andWhere               = '';
        $filterConf             = (int)$this->config['global']['artikel_artikelanzeigefilter'];
        $languageIDs            = map($languages, fn(LanguageModel $e): int => $e->getId());

        $_SESSION['kSprache'] = $defaultLangID;

        $_SESSION['cISOSprache'] = $defaultLang->getCode();

        if ($this->config['sitemap']['sitemap_varkombi_children_export'] !== 'Y') {
            $andWhere .= ' AND tartikel.kVaterArtikel = 0';
        }
        if ($filterConf === \EINSTELLUNGEN_ARTIKELANZEIGEFILTER_LAGER) {
            $andWhere .= " AND (tartikel.cLagerBeachten = 'N' OR tartikel.fLagerbestand > 0)";
        } elseif ($filterConf === \EINSTELLUNGEN_ARTIKELANZEIGEFILTER_LAGERNULL) {
            $andWhere .= " AND (tartikel.cLagerBeachten = 'N' 
                               OR tartikel.cLagerKleinerNull = 'Y' 
                               OR tartikel.fLagerbestand > 0)";
        }
        $res = $this->db->getPDOStatement(
            "SELECT tartikel.kArtikel, tartikel.dLetzteAktualisierung AS dlm, 
            tseo.cSeo, tseo.kSprache AS langID
                FROM tartikel
                JOIN tseo 
                    ON tseo.cKey = 'kArtikel'
                    AND tseo.kKey = tartikel.kArtikel
                    AND tseo.kSprache IN (" . \implode(',', $languageIDs) . ')
                LEFT JOIN tartikelsichtbarkeit 
                    ON tartikel.kArtikel = tartikelsichtbarkeit.kArtikel
                    AND tartikelsichtbarkeit.kKundengruppe = :cgid
                WHERE tartikelsichtbarkeit.kArtikel IS NULL' . $andWhere . "
                    AND tartikel.kArtikel NOT IN (
                        SELECT tartikelattribut.kArtikel
                        FROM tartikelattribut
                        WHERE tartikelattribut.kArtikel = tartikel.kArtikel AND tartikelattribut.cName = 'noindex'
                    )
                ORDER BY tartikel.kArtikel",
            ['cgid' => $defaultCustomerGroupID]
        );
        while (($product = $res->fetchObject()) !== false) {
            $item = new Item($this->config, $this->baseURL, $this->baseImageURL);
            $item->generateData($product, $languages);
            yield $item;
        }
    }
}
