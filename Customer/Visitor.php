<?php

declare(strict_types=1);

namespace JTL\Customer;

use DateTime;
use JTL\Cache\JTLCacheInterface;
use JTL\Crawler\Controller;
use JTL\DB\DbInterface;
use JTL\GeneralDataProtection\IpAnonymizer;
use JTL\Helpers\Request;
use JTL\Helpers\Text;
use JTL\Session\Frontend;
use stdClass;

/**
 * Class Visitor
 * @package JTL\Customer
 * @since 5.2.0
 */
class Visitor
{
    public const ARCHIVE_INTERVAL = 3;

    public function __construct(private readonly DbInterface $db, private readonly JTLCacheInterface $cache)
    {
    }

    public function generateData(): void
    {
        if (\TRACK_VISITORS === false) {
            return;
        }
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $botID     = $this->getSpiderID($userAgent);
        if ($botID > 0) {
            $this->db->queryPrepared(
                'UPDATE tbesucherbot SET dZeit = NOW() WHERE kBesucherBot = :bid',
                ['bid' => $botID]
            );
        }
        $this->archive();
        $visitor = $this->dbLookup($userAgent, Request::getRealIP());
        if ($visitor === null) {
            $visitor = Frontend::getVisitor();
            if ($visitor !== null) {
                $visitor = $this->updateVisitorObject($visitor, 0, $userAgent, $botID);
            } else {
                // create a new visitor-object
                $visitor = $this->createVisitorObject($userAgent, $botID);
            }
            // get back the new ID of that visitor (and write it back into the session)
            $visitor->kBesucher = $this->insert($visitor);
            // always increment the visitor-counter (if no bot)
            $this->db->query('UPDATE tbesucherzaehler SET nZaehler = nZaehler + 1');
        } else {
            $visitor->kBesucher    = (int)$visitor->kBesucher;
            $visitor->kKunde       = (int)$visitor->kKunde;
            $visitor->kBestellung  = (int)$visitor->kBestellung;
            $visitor->kBesucherBot = (int)$visitor->kBesucherBot;
            // prevent counting internal redirects by counting only the next request above 3 seconds
            $diff = (new DateTime())->getTimestamp() - (new DateTime($visitor->dLetzteAktivitaet))->getTimestamp();
            if ($diff > 2) {
                $visitor = $this->updateVisitorObject($visitor, $visitor->kBesucher, $userAgent, $botID);
                $this->update($visitor, $visitor->kBesucher);
            } else {
                // time-diff is to low! so we do nothing but update this "last-action"-time in the session
                $visitor->dLetzteAktivitaet = (new DateTime())->format('Y-m-d H:i:s');
            }
        }
        $_SESSION['oBesucher'] = $visitor;
    }

    /**
     * Besucher nach 3 Std in Besucherarchiv verschieben
     *
     * @former archiviereBesucher()
     * @since 5.2.0
     */
    public function archive(): void
    {
        $this->db->queryPrepared(
            'INSERT IGNORE INTO tbesucherarchiv
            (kBesucher, cIP, kKunde, kBestellung, cReferer, cEinstiegsseite, cBrowser,
              cAusstiegsseite, nBesuchsdauer, kBesucherBot, dZeit)
            SELECT kBesucher, cIP, kKunde, kBestellung, cReferer, cEinstiegsseite, cBrowser, cAusstiegsseite,
            (UNIX_TIMESTAMP(dLetzteAktivitaet) - UNIX_TIMESTAMP(dZeit)) AS nBesuchsdauer, kBesucherBot, dZeit
              FROM tbesucher
              WHERE dLetzteAktivitaet <= DATE_SUB(NOW(), INTERVAL :interval HOUR)',
            ['interval' => self::ARCHIVE_INTERVAL]
        );
        $this->db->queryPrepared(
            'DELETE FROM tbesucher
                WHERE dLetzteAktivitaet <= DATE_SUB(NOW(), INTERVAL :interval HOUR)',
            ['interval' => self::ARCHIVE_INTERVAL]
        );
    }

    /**
     * @former dbLookupVisitor()
     * @since  5.2.0
     */
    public function dbLookup(string $userAgent, string $ip): ?stdClass
    {
        return $this->db->select('tbesucher', 'cSessID', \session_id() ?: 'invalid')
            ?? $this->db->select('tbesucher', 'cID', \md5($userAgent . $ip));
    }

    /**
     * @since 5.2.0
     */
    public function updateVisitorObject(stdClass $vis, int $visitorID, string $userAgent, int $botID): stdClass
    {
        $vis->kBesucher         = $visitorID;
        $vis->cIP               = (new IpAnonymizer(Request::getRealIP()))->anonymize();
        $vis->cSessID           = \session_id();
        $vis->cID               = \md5($userAgent . Request::getRealIP());
        $vis->kKunde            = Frontend::getCustomer()->getID();
        $vis->kBestellung       = $vis->kKunde > 0 ? $this->refreshCustomerOrderId($vis->kKunde) : 0;
        $vis->cReferer          = self::getReferer();
        $vis->cUserAgent        = Text::filterXSS($_SERVER['HTTP_USER_AGENT'] ?? '');
        $vis->cBrowser          = $this->getBrowser();
        $vis->cAusstiegsseite   = Text::filterXSS($_SERVER['REQUEST_URI'] ?? '');
        $vis->dLetzteAktivitaet = (new DateTime())->format('Y-m-d H:i:s');
        $vis->kBesucherBot      = $botID;

        return $vis;
    }

    /**
     * @since 5.2.0
     */
    public function createVisitorObject(string $userAgent, int $botID): stdClass
    {
        $vis                    = new stdClass();
        $vis->kBesucher         = 0;
        $vis->cIP               = (new IpAnonymizer(Request::getRealIP()))->anonymize();
        $vis->cSessID           = \session_id();
        $vis->cID               = \md5($userAgent . Request::getRealIP());
        $vis->kKunde            = Frontend::getCustomer()->getID();
        $vis->kBestellung       = $vis->kKunde > 0 ? $this->refreshCustomerOrderId($vis->kKunde) : 0;
        $vis->cEinstiegsseite   = Text::filterXSS($_SERVER['REQUEST_URI'] ?? '');
        $vis->cReferer          = self::getReferer();
        $vis->cUserAgent        = Text::filterXSS($_SERVER['HTTP_USER_AGENT'] ?? '');
        $vis->cBrowser          = $this->getBrowser();
        $vis->cAusstiegsseite   = $vis->cEinstiegsseite;
        $vis->dLetzteAktivitaet = (new DateTime())->format('Y-m-d H:i:s');
        $vis->dZeit             = (new DateTime())->format('Y-m-d H:i:s');
        $vis->kBesucherBot      = $botID;

        return $vis;
    }

    /**
     * @since 5.2.0
     */
    public function insert(stdClass $visitor): int
    {
        return $this->db->insert('tbesucher', $visitor);
    }

    /**
     * @since 5.2.0
     */
    public function update(stdClass $visitor, int $visitorID): int
    {
        return $this->db->update('tbesucher', 'kBesucher', $visitorID, $visitor);
    }

    /**
     * @since 5.2.0
     */
    public function refreshCustomerOrderId(int $customerID): int
    {
        return $this->db->getSingleInt(
            'SELECT kBestellung
                FROM tbestellung
                WHERE kKunde = :cid
                ORDER BY `dErstellt` DESC LIMIT 1',
            'kBestellung',
            ['cid' => $customerID]
        );
    }

    /**
     * @former gibBrowser()
     * @since  5.2.0
     */
    public function getBrowser(): string
    {
        $agent  = \mb_convert_case($_SERVER['HTTP_USER_AGENT'] ?? '', \MB_CASE_LOWER);
        $mobile = '';
        if (
            \mb_stripos($agent, 'iphone') !== false
            || \mb_stripos($agent, 'ipad') !== false
            || \mb_stripos($agent, 'ipod') !== false
            || \mb_stripos($agent, 'android') !== false
            || \mb_stripos($agent, 'opera mobi') !== false
            || \mb_stripos($agent, 'blackberry') !== false
            || \mb_stripos($agent, 'playbook') !== false
            || \mb_stripos($agent, 'kindle') !== false
            || \mb_stripos($agent, 'windows phone') !== false
        ) {
            $mobile = '/Mobile';
        }
        if (\str_contains($agent, 'msie')) {
            return 'Internet Explorer ' . (int)\mb_substr($agent, \mb_strpos($agent, 'msie') + 4) . $mobile;
        }
        if (\str_contains($agent, 'opera') || \mb_stripos($agent, 'opr') !== false) {
            return 'Opera' . $mobile;
        }
        if (\str_contains($agent, 'vivaldi')) {
            return 'Vivaldi' . $mobile;
        }
        if (\str_contains($agent, 'safari') && !\str_contains($agent, 'chrome')) {
            return 'Safari' . $mobile;
        }
        if (\str_contains($agent, 'firefox')) {
            return 'Firefox' . $mobile;
        }
        if (\str_contains($agent, 'chrome')) {
            return 'Chrome' . $mobile;
        }

        return 'Sonstige' . $mobile;
    }

    /**
     * @fomer gibReferer()
     * @since 5.2.0
     */
    public static function getReferer(): string
    {
        if (empty($_SERVER['HTTP_REFERER'])) {
            return '';
        }
        $parts = \explode('/', $_SERVER['HTTP_REFERER']);
        if (!isset($parts[2])) {
            return '';
        }

        return Text::filterXSS(\mb_convert_case($parts[2], \MB_CASE_LOWER));
    }

    /**
     * @former gibBot()
     * @since  5.2.0
     */
    public function getBot(): string
    {
        $agent = \mb_convert_case($_SERVER['HTTP_USER_AGENT'] ?? '', \MB_CASE_LOWER);
        if (\str_contains($agent, 'googlebot')) {
            return 'Google';
        }
        if (\str_contains($agent, 'bingbot')) {
            return 'Bing';
        }
        if (\str_contains($agent, 'inktomi.com')) {
            return 'Inktomi';
        }
        if (\str_contains($agent, 'yahoo! slurp')) {
            return 'Yahoo!';
        }
        if (\str_contains($agent, 'msnbot')) {
            return 'MSN';
        }
        if (\str_contains($agent, 'teoma')) {
            return 'Teoma';
        }
        if (\str_contains($agent, 'crawler')) {
            return 'Crawler';
        }
        if (\str_contains($agent, 'scooter')) {
            return 'Scooter';
        }
        if (\str_contains($agent, 'fireball')) {
            return 'Fireball';
        }
        if (\str_contains($agent, 'ask jeeves')) {
            return 'Ask';
        }

        return '';
    }

    /**
     * @former werteRefererAus()
     * @since  5.2.0
     */
    public function analyzeReferer(int $visitorID, string $referer): void
    {
    }

    /**
     * @former istSuchmaschine()
     * @since  5.2.0
     */
    public function isSearchEngine(?string $referer): bool
    {
        if (!$referer) {
            return false;
        }
        if (
            \str_contains($referer, '.google.')
            || \str_contains($referer, '.bing.')
            || \str_contains($referer, 'suche.')
            || \str_contains($referer, 'search.')
            || \str_contains($referer, '.yahoo.')
            || \str_contains($referer, '.fireball.')
            || \str_contains($referer, '.seekport.')
            || \str_contains($referer, '.keywordspy.')
            || \str_contains($referer, '.hotfrog.')
            || \str_contains($referer, '.altavista.')
            || \str_contains($referer, '.ask.')
        ) {
            return true;
        }

        return false;
    }

    /**
     * @former istSpider()
     * @since  5.2.0
     */
    public function getSpiderID(string $userAgent): int
    {
        $bot = (new Controller($this->db, $this->cache))->getByUserAgent($userAgent);

        return (int)($bot->kBesucherBot ?? 0);
    }

    /**
     * @return stdClass[]
     */
    public function getSpiders(): array
    {
        return (new Controller($this->db, $this->cache))->getAllCrawlers();
    }

    private function isMobile(string $userAgent): bool
    {
        return
            \preg_match(
                '/android|avantgo|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile'
                . '|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker'
                . '|pocket|psp|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i',
                $userAgent,
                $matches
            )
            || \preg_match(
                '/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s-)|ai(ko|rn)'
                . '|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|-m|r |s )'
                . '|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw-(n|u)|c55\/|capi|ccwa'
                . '|cdm-|cell|chtm|cldc|cmd-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc-s|devi|dica|dmob'
                . '|do(c|p)o|ds(12|-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(-|_)'
                . '|g1 u|g560|gene|gf-5|g-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd-(m|p|t)|hei-|hi(pt|ta)'
                . '|hp( i|ip)|hs-c|ht(c(-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i-(20|go|ma)|i230|iac( |-|\/)'
                . '|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)'
                . '|klon|kpt |kwc-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|e-|e\/|-[a-w])'
                . '|libw|lynx|m1-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m-cr|me(di|rc|ri)|mi(o8|oa|ts)'
                . '|mmef|mo(01|02|bi|de|do|t(-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)'
                . '|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1'
                . '|p800|pan(a|d|t)|pdxg|pg(13|-([1-8]|c))|phil|pire|pl(ay|uc)|pn-2|po(ck|rt|se)|prox|psio'
                . '|pt-g|qa-a|qc(07|12|21|32|60|-[2-7]|i-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa'
                . '(ge|ma|mm|ms|ny|va)|sc(01|h-|oo|p-)|sdk\/|se(c(-|0|1)|47|mc|nd|ri)|sgh-|shar|sie(-|m)'
                . '|sk-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h-|v-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)'
                . '|ta(gt|lk)|tcl-|tdg-|tel(i|m)|tim-|t-mo|to(pl|sh)|ts(70|m-|m3|m5)|tx-9|up(\.b|g1|si)'
                . '|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)'
                . '|w3c(-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|xda(-|2|g)|yas-|your|zeto|zte-/i',
                \mb_substr($userAgent, 0, 4),
                $matches
            );
    }

    private function getBrowserData(stdClass $browser, string $userAgent): stdClass
    {
        if ($userAgent === '') {
            return $browser;
        }
        if (\stripos($userAgent, 'MSIE') && \stripos($userAgent, 'Opera') === false) {
            $browser->nType    = \BROWSER_MSIE;
            $browser->cName    = 'Internet Explorer';
            $browser->cBrowser = 'msie';
        } elseif (\stripos($userAgent, 'Firefox') !== false) {
            $browser->nType    = \BROWSER_FIREFOX;
            $browser->cName    = 'Mozilla Firefox';
            $browser->cBrowser = 'firefox';
        } elseif (\stripos($userAgent, 'Chrome') !== false) {
            $browser->nType    = \BROWSER_CHROME;
            $browser->cName    = 'Google Chrome';
            $browser->cBrowser = 'chrome';
        } elseif (\stripos($userAgent, 'Safari') !== false) {
            $browser->nType = \BROWSER_SAFARI;
            if (\stripos($userAgent, 'iPhone') !== false) {
                $browser->cName    = 'Apple iPhone';
                $browser->cBrowser = 'iphone';
            } elseif (\stripos($userAgent, 'iPad') !== false) {
                $browser->cName    = 'Apple iPad';
                $browser->cBrowser = 'ipad';
            } elseif (\stripos($userAgent, 'iPod') !== false) {
                $browser->cName    = 'Apple iPod';
                $browser->cBrowser = 'ipod';
            } else {
                $browser->cName    = 'Apple Safari';
                $browser->cBrowser = 'safari';
            }
        } elseif (\stripos($userAgent, 'Opera') !== false) {
            $browser->nType = \BROWSER_OPERA;
            if (\preg_match('/Opera Mini/i', $userAgent)) {
                $browser->cName    = 'Opera Mini';
                $browser->cBrowser = 'opera_mini';
            } else {
                $browser->cName    = 'Opera';
                $browser->cBrowser = 'opera';
            }
        }

        return $browser;
    }

    public function getBrowserForUserAgent(?string $userAgent = null): stdClass
    {
        $userAgent          = $userAgent ?? $_SERVER['HTTP_USER_AGENT'] ?? '';
        $browser            = new stdClass();
        $browser->nType     = 0;
        $browser->bMobile   = false;
        $browser->cName     = 'Unknown';
        $browser->cBrowser  = 'unknown';
        $browser->cPlatform = 'unknown';
        $browser->cVersion  = '0';
        $browser->cAgent    = $userAgent;
        $browser->bMobile   = $this->isMobile($browser->cAgent);
        if (\stripos($userAgent, 'linux') !== false) {
            $browser->cPlatform = 'linux';
        } elseif (\preg_match('/macintosh|mac os x/i', $userAgent)) {
            $browser->cPlatform = 'mac';
        } elseif (\preg_match('/windows|win32/i', $userAgent)) {
            $browser->cPlatform = \preg_match('/windows mobile|wce/i', $userAgent)
                ? 'mobile'
                : 'windows';
        }
        $browser = $this->getBrowserData($browser, $userAgent);
        $known   = ['version', 'other', 'mobile', $browser->cBrowser];
        $pattern = '/(?<browser>' . \implode('|', $known) . ')[\/ ]+(?<version>[0-9.|a-zA-Z.]*)/i';
        \preg_match_all($pattern, $userAgent, $browserMatches);
        if (\count($browserMatches['browser']) !== 1) {
            $browser->cVersion = '0';
            if (
                isset($browserMatches['version'][0])
                && \mb_strripos($userAgent, 'Version') < \mb_strripos($userAgent, $browser->cBrowser)
            ) {
                $browser->cVersion = $browserMatches['version'][0];
            } elseif (isset($browserMatches['version'][1])) {
                $browser->cVersion = $browserMatches['version'][1];
            }
        } else {
            $browser->cVersion = $browserMatches['version'][0];
        }
        if (\mb_strlen($browser->cVersion) === 0) {
            $browser->cVersion = '0';
        }

        return $browser;
    }
}
