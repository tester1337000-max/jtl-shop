<?php

declare(strict_types=1);

namespace JTL\Export;

use DateTime;
use JTL\Customer\CustomerGroup;
use JTL\DB\DbInterface;
use JTL\Helpers\Text;
use JTL\Helpers\URL;
use JTL\Language\LanguageHelper;
use JTL\Language\LanguageModel;
use JTL\Shop;
use Psr\Log\LoggerInterface;
use stdClass;

/**
 * Class RSS
 * @package JTL\Export
 */
class RSS
{
    private string $shopURL;

    /**
     * @var array<string, string>
     */
    private array $conf;

    private int $days;

    private string $locale;

    private LanguageModel $language;

    public function __construct(private readonly DbInterface $db, private readonly LoggerInterface $logger)
    {
        $this->shopURL = Shop::getURL();
        $this->conf    = Shop::getSettingSection(\CONF_RSS);
        $days          = (int)$this->conf['rss_alterTage'];
        if (!$days) {
            $days = 14;
        }
        $this->days     = $days;
        $this->language = LanguageHelper::getDefaultLanguage();
        $this->locale   = Text::convertISO2ISO639($this->language->getCode());
    }

    public function generateXML(): bool
    {
        if ($this->conf['rss_nutzen'] !== 'Y') {
            return false;
        }
        if (!\is_writable(\PFAD_ROOT . \FILE_RSS_FEED)) {
            $this->logger->error('RSS Verzeichnis {dir} nicht beschreibbar!', ['dir' => \PFAD_ROOT . \FILE_RSS_FEED]);

            return false;
        }
        $this->logger->debug('RSS wird erstellt');
        $oldLanguageID   = (int)($_SESSION['kSprache'] ?? '0');
        $oldLanguageCode = $_SESSION['cISOSprache'] ?? '';

        $_SESSION['kSprache']    = $this->language->getId();
        $_SESSION['cISOSprache'] = $this->language->getCode();
        // ISO-8859-1
        $xml = $this->getXmlHead($this->locale);
        $xml .= $this->getProductXML();
        $xml .= $this->getNewsXML();
        $xml .= $this->getReviewXML();
        $xml .= <<<FOOTER

            </channel>
        </rss>
        FOOTER;

        $file = \fopen(\PFAD_ROOT . \FILE_RSS_FEED, 'wb+');
        if ($file === false) {
            return false;
        }
        \fwrite($file, $xml);
        \fclose($file);
        $_SESSION['kSprache']    = $oldLanguageID;
        $_SESSION['cISOSprache'] = $oldLanguageCode;

        return true;
    }

    public function asRFC2822(string $dateString): false|string
    {
        return \mb_strlen($dateString) > 0
            ? (new DateTime($dateString))->format(\DATE_RSS)
            : false;
    }

    /**
     * @former wandelXMLEntitiesUm()
     */
    public function asEntity(string $text): string
    {
        return \mb_strlen($text) > 0
            ? '<![CDATA[ ' . Text::htmlentitydecode($text) . ' ]]>'
            : '';
    }

    private function getProductXML(): string
    {
        if ($this->conf['rss_artikel_beachten'] !== 'Y') {
            return '';
        }
        $customerGroup = CustomerGroup::getDefault($this->db);
        $xml           = '';
        foreach ($this->getProductData($customerGroup->kKundengruppe ?? 0) as $product) {
            $url = URL::buildURL($product, \URLART_ARTIKEL, true, $this->shopURL . '/', $this->locale);
            $xml .= <<<ITEM

                    <item>
                        <title>{$this->asEntity($product->cName)}</title>
                        <description>{$this->asEntity($product->cKurzBeschreibung)}</description>
                        <link>$url</link>
                        <guid>$url</guid>
                        <pubDate>{$this->asRFC2822($product->dLetzteAktualisierung)}</pubDate>
                    </item>
            ITEM;
        }

        return $xml;
    }

    private function getReviewXML(): string
    {
        if ($this->conf['rss_bewertungen_beachten'] !== 'Y') {
            return '';
        }
        $xml = '';
        foreach ($this->getReviews() as $review) {
            $url = URL::buildURL($review, \URLART_ARTIKEL, true, $this->shopURL . '/', $this->locale);
            $xml .= <<<ITEM

                    <item>
                        <title>{$this->asEntity('Bewertung ' . $review->cTitel . ' von ' . $review->cName)}</title>
                        <description>{$this->asEntity($review->cText)}</description>
                        <link>$url</link>
                        <guid>$url</guid>
                        <pubDate>{$this->asRFC2822($review->dDatum)}</pubDate>
                    </item>
            ITEM;
        }

        return $xml;
    }

    /**
     * @return stdClass[]
     */
    private function getProductData(int $customerGroupID): array
    {
        return $this->db->getObjects(
            "SELECT tartikel.kArtikel, tartikel.cName, tartikel.cKurzBeschreibung, tseo.cSeo, 
                tartikel.dLetzteAktualisierung, tartikel.dErstellt, tseo.kSprache AS langID,
                DATE_FORMAT(tartikel.dErstellt, '%a, %d %b %Y %H:%i:%s UTC') AS erstellt
                    FROM tartikel
                    LEFT JOIN tartikelsichtbarkeit 
                        ON tartikel.kArtikel = tartikelsichtbarkeit.kArtikel
                        AND tartikelsichtbarkeit.kKundengruppe = :cgid
                    LEFT JOIN tseo 
                        ON tseo.cKey = 'kArtikel'
                        AND tseo.kKey = tartikel.kArtikel
                        AND tseo.kSprache = :lid
                    WHERE tartikelsichtbarkeit.kArtikel IS NULL
                        AND tartikel.cNeu = 'Y' " . Shop::getProductFilter()->getFilterSQL()->getStockFilterSQL() . "
                        AND cNeu = 'Y' 
                        AND DATE_SUB(now(), INTERVAL :ds DAY) < dErstellt
                    ORDER BY dLetzteAktualisierung DESC",
            [
                'lid'  => $this->language->getId(),
                'cgid' => $customerGroupID,
                'ds'   => $this->days
            ]
        );
    }

    /**
     * @return stdClass[]
     */
    private function getNewsData(): array
    {
        return $this->db->getObjects(
            "SELECT tnews.*, t.title, t.preview, tseo.cSeo, tseo.kSprache AS langID,
                DATE_FORMAT(dGueltigVon, '%a, %d %b %Y %H:%i:%s UTC') AS dErstellt_RSS
                    FROM tnews
                    JOIN tnewssprache t 
                        ON tnews.kNews = t.kNews
                    JOIN tseo 
                        ON tseo.cKey = 'kNews'
                        AND tseo.kKey = tnews.kNews
                        AND tseo.kSprache = t.languageID
                    WHERE DATE_SUB(now(), INTERVAL :ds DAY) < dGueltigVon
                        AND nAktiv = 1
                        AND dGueltigVon <= NOW()
                    ORDER BY dGueltigVon DESC",
            ['ds' => $this->days]
        );
    }

    /**
     * @return stdClass[]
     */
    private function getReviews(): array
    {
        return $this->db->getObjects(
            "SELECT tbewertung.*, DATE_FORMAT(dDatum, '%a, %d %b %y %h:%i:%s +0100') AS dErstellt_RSS,
                tseo.kSprache AS langID, tseo.cSeo
                FROM tbewertung
                JOIN tartikel
                    ON tartikel.kArtikel = tbewertung.kArtikel
                JOIN tseo
                    ON tseo.cKey = 'kArtikel'
                    AND tseo.kKey = tbewertung.kArtikel
                    AND tseo.kSprache = :lid
                WHERE DATE_SUB(NOW(), INTERVAL :ds DAY) < dDatum
                    AND nAktiv = 1",
            ['lid' => $this->language->getId(), 'ds' => $this->days]
        );
    }

    private function getXmlHead(string $language): string
    {
        $date    = \date('r');
        $charset = \JTL_CHARSET;

        return
            <<<HEADER
            <?xml version="1.0" encoding="$charset"?>
            <rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
                <channel>
                    <title>{$this->conf['rss_titel']}</title>
                    <link>$this->shopURL</link>
                    <description>{$this->conf['rss_description']}</description>
                    <language>$language</language>
                    <copyright>{$this->conf['rss_copyright']}</copyright>
                    <pubDate>$date</pubDate>
                    <atom:link href="$this->shopURL/rss.xml" rel="self" type="application/rss+xml" />
                    <image>
                        <url>{$this->conf['rss_logoURL']}</url>
                        <title>{$this->conf['rss_titel']}</title>
                        <link>$this->shopURL</link>
                    </image>
            HEADER;
    }

    public function getNewsXML(): string
    {
        if ($this->conf['rss_news_beachten'] !== 'Y') {
            return '';
        }
        $xml = '';
        foreach ($this->getNewsData() as $item) {
            $url = URL::buildURL($item, \URLART_NEWS, true, $this->shopURL . '/', $this->locale);
            $xml .= <<<ITEM
                
                        <item>
                            <title>{$this->asEntity($item->title)}</title>
                            <description>{$this->asEntity($item->preview)}</description>
                            <link>$url</link>
                            <guid>$url</guid>
                            <pubDate>{$this->asRFC2822($item->dGueltigVon)}</pubDate>
                        </item>
                ITEM;
        }

        return $xml;
    }
}
