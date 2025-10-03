<?php

declare(strict_types=1);

namespace JTL\Sitemap\Factories;

use Generator;
use JTL\Language\LanguageModel;
use JTL\Link\Link;
use JTL\Link\LinkList;
use JTL\Sitemap\Items\Page as Item;
use stdClass;

use function Functional\first;
use function Functional\map;

/**
 * Class Page
 * @package JTL\Sitemap\Factories
 */
final class Page extends AbstractFactory
{
    /**
     * @inheritdoc
     */
    public function getCollection(array $languages, array $customerGroups): Generator
    {
        $customerGroup = first($customerGroups);
        $languageCodes = map($languages, fn(LanguageModel $e): string => "'" . $e->getCode() . "'");
        $linkIDs       = $this->db->getInts(
            "SELECT DISTINCT tlink.kLink AS id
                FROM tlink
                JOIN tlinkgroupassociations
                    ON tlinkgroupassociations.linkID = tlink.kLink
                JOIN tseo
                    ON tseo.cKey = 'kLink'
                    AND tseo.kKey = tlink.kLink
                JOIN tlinkgruppe 
                    ON tlinkgroupassociations.linkGroupID = tlinkgruppe.kLinkgruppe
                JOIN tlinksprache
                    ON tlinksprache.kLink = tlink.kLink
                WHERE tlink.cSichtbarNachLogin = 'N'
                    AND tlink.cNoFollow = 'N'
                    AND tlink.bIsActive = 1
                    AND tlink.nLinkart != " . \LINKTYP_EXTERNE_URL . "
                    AND tlinkgruppe.cName != 'hidden'
                    AND tlinkgruppe.cTemplatename != 'hidden'
                    AND tlinksprache.cISOSprache IN (" . \implode(',', $languageCodes) . ")
                    AND (tlink.cKundengruppen IS NULL
                        OR tlink.cKundengruppen = 'NULL'
                        OR FIND_IN_SET(:cGrpID, REPLACE(tlink.cKundengruppen, ';', ',')) > 0)
                ORDER BY tlinksprache.kLink",
            'id',
            ['cGrpID' => $customerGroup]
        );
        $linkList      = new LinkList($this->db);
        $linkList->createLinks($linkIDs, true);
        /** @var Link $link */
        foreach ($linkList->getLinks()->all() as $link) {
            $linkType = $link->getLinkType();
            foreach ($link->getURLPaths() as $i => $path) {
                $data           = new stdClass();
                $data->kLink    = $link->getID();
                $data->cSEO     = $path;
                $data->nLinkart = $linkType;
                $data->langID   = $link->getLanguageID($i);
                $data->langCode = $link->getLanguageCode($i);
                $item           = new Item($this->config, $this->baseURL, $this->baseImageURL);
                $item->generateData($data, $languages);
                if ($item->getLanguageID() === null) {
                    continue;
                }
                yield $item;
            }
        }
    }
}
