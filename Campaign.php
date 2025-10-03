<?php

declare(strict_types=1);

namespace JTL;

use DateTime;
use JTL\Customer\Visitor;
use JTL\DB\DbInterface;
use JTL\Helpers\Request;
use JTL\Helpers\Text;
use JTL\Session\Frontend;
use stdClass;

/**
 * Class Campaign
 * @package JTL
 */
class Campaign
{
    public int $kKampagne = 0;

    public string $cName = '';

    public string $cParameter = '';

    public string $cWert = '';

    public int $nDynamisch = 0;

    public int $nAktiv = 0;

    public string $dErstellt = '';

    public string $dErstellt_DE = '';

    public int $nInternal = 1;

    private DbInterface $db;

    public function __construct(int $id = 0, ?DbInterface $db = null)
    {
        $this->db = $db ?? Shop::Container()->getDB();
        if ($id > 0) {
            $this->loadFromDB($id);
        }
    }

    public function loadFromDB(int $id): self
    {
        $data = $this->db->getSingleObject(
            "SELECT tkampagne.*, DATE_FORMAT(tkampagne.dErstellt, '%d.%m.%Y %H:%i:%s') AS dErstellt_DE
                FROM tkampagne
                WHERE tkampagne.kKampagne = :cid",
            ['cid' => $id]
        );

        if ($data !== null && $data->kKampagne > 0) {
            $this->kKampagne    = (int)$data->kKampagne;
            $this->cName        = $data->cName;
            $this->cParameter   = $data->cParameter;
            $this->cWert        = $data->cWert;
            $this->nDynamisch   = (int)$data->nDynamisch;
            $this->nAktiv       = (int)$data->nAktiv;
            $this->dErstellt    = $data->dErstellt;
            $this->dErstellt_DE = $data->dErstellt_DE;
            $this->nInternal    = (int)$data->nInternal;
        }

        return $this;
    }

    public function insertInDB(): int
    {
        $obj             = new stdClass();
        $obj->cName      = Text::filterXSS($this->cName);
        $obj->cParameter = Text::filterXSS($this->cParameter);
        $obj->cWert      = Text::filterXSS($this->cWert);
        $obj->nDynamisch = $this->nDynamisch;
        $obj->nAktiv     = $this->nAktiv;
        $obj->dErstellt  = $this->dErstellt;
        $this->kKampagne = $this->db->insert('tkampagne', $obj);
        if (\mb_convert_case($this->dErstellt, \MB_CASE_LOWER) === 'now()') {
            $this->dErstellt = (new DateTime())->format('Y-m-d H:i:s');
        }
        $this->dErstellt_DE = (new DateTime($this->dErstellt))->format('d.m.Y H:i:s');

        return $this->kKampagne;
    }

    public function updateInDB(): int
    {
        $obj             = new stdClass();
        $obj->cName      = Text::filterXSS($this->cName);
        $obj->cParameter = Text::filterXSS($this->cParameter);
        $obj->cWert      = Text::filterXSS($this->cWert);
        $obj->nDynamisch = $this->nDynamisch;
        $obj->nAktiv     = $this->nAktiv;
        $obj->dErstellt  = $this->dErstellt;
        $obj->kKampagne  = $this->kKampagne;

        $res = $this->db->update('tkampagne', 'kKampagne', $obj->kKampagne, $obj);
        if (\mb_convert_case($this->dErstellt, \MB_CASE_LOWER) === 'now()') {
            $this->dErstellt = (new DateTime())->format('Y-m-d H:i:s');
        }
        $this->dErstellt_DE = (new DateTime($this->dErstellt))->format('d.m.Y H:i:s');

        return $res;
    }

    public function deleteInDB(): bool
    {
        if ($this->kKampagne <= 0) {
            return false;
        }
        // only external campaigns are deletable
        $this->db->queryPrepared(
            'DELETE tkampagne, tkampagnevorgang
                FROM tkampagne
                LEFT JOIN tkampagnevorgang 
                    ON tkampagnevorgang.kKampagne = tkampagne.kKampagne
                WHERE tkampagne.kKampagne = :cid AND tkampagne.nInternal = 0',
            ['cid' => $this->kKampagne]
        );

        return true;
    }

    /**
     * @return self[]
     */
    public static function getAvailable(): array
    {
        $cacheID = 'jtl_cmpgns';
        /** @var self[]|false $campaigns */
        $campaigns = Shop::Container()->getCache()->get($cacheID);
        if ($campaigns !== false) {
            return $campaigns;
        }
        $campaigns = [];
        $data      = Shop::Container()->getDB()->selectAll(
            'tkampagne',
            'nAktiv',
            1,
            '*, DATE_FORMAT(dErstellt, \'%d.%m.%Y %H:%i:%s\') AS dErstellt_DE'
        );
        foreach ($data as $item) {
            $campaign               = new self();
            $campaign->kKampagne    = (int)$item->kKampagne;
            $campaign->nDynamisch   = (int)$item->nDynamisch;
            $campaign->nAktiv       = (int)$item->nAktiv;
            $campaign->nInternal    = (int)$item->nInternal;
            $campaign->cWert        = $item->cWert;
            $campaign->cParameter   = $item->cParameter;
            $campaign->cName        = $item->cName;
            $campaign->dErstellt    = $item->dErstellt;
            $campaign->dErstellt_DE = (new DateTime($item->dErstellt))->format('d.m.Y H:i:s');
            $campaigns[]            = $campaign;
        }
        Shop::Container()->getCache()->set($cacheID, $campaigns, [\CACHING_GROUP_CORE]);

        return $campaigns;
    }

    private static function paramMatches(string $given, string $campaignValue): bool
    {
        return \mb_convert_case($campaignValue, \MB_CASE_LOWER) === \mb_convert_case($given, \MB_CASE_LOWER);
    }

    /**
     * @former pruefeKampagnenParameter()
     */
    public static function checkCampaignParameters(): void
    {
        $visitorID = Frontend::get('oBesucher')->kBesucher ?? 0;
        if ($visitorID <= 0) {
            return;
        }
        $campaigns = self::getAvailable();
        if (\count($campaigns) === 0) {
            return;
        }
        $db       = Shop::Container()->getDB();
        $hit      = false;
        $referrer = Visitor::getReferer();
        foreach ($campaigns as $campaign) {
            $campaign->setDB($db);
            if (!$campaign->isValid()) {
                continue;
            }
            $hit = true;
            // wurde der HIT für diesen Besucher schon gezaehlt?
            $event = $db->select(
                'tkampagnevorgang',
                ['kKampagneDef', 'kKampagne', 'kKey', 'cCustomData'],
                [
                    \KAMPAGNE_DEF_HIT,
                    $campaign->kKampagne,
                    $visitorID,
                    Text::filterXSS((string)$_SERVER['REQUEST_URI']) . ';' . $referrer
                ]
            );
            if ($event !== null) {
                continue;
            }
            $campaign->trackHit($visitorID, $referrer);
        }
        if (!$hit) {
            self::checkGoogleCampaignHit($visitorID, $db);
        }
    }

    private static function checkGoogleCampaignHit(int $visitorID, DbInterface $db): void
    {
        if (!\str_contains($_SERVER['HTTP_REFERER'] ?? '', '.google.')) {
            return;
        }
        // Besucher kommt von Google und hat vorher keine Kampagne getroffen
        $event = $db->select(
            'tkampagnevorgang',
            ['kKampagneDef', 'kKampagne', 'kKey'],
            [\KAMPAGNE_DEF_HIT, \KAMPAGNE_INTERN_GOOGLE, $visitorID]
        );
        if ($event !== null) {
            return;
        }
        $campaign = new self(\KAMPAGNE_INTERN_GOOGLE, $db);
        if ($campaign->nAktiv === 0) {
            return;
        }
        $campaign->trackHit($visitorID, Visitor::getReferer());
    }

    /**
     * @former setzeKampagnenVorgang()
     */
    public static function setCampaignAction(
        int $id,
        int $kKey,
        float|int|string $value,
        ?string $customData = null
    ): int {
        if ($id <= 0 || $kKey <= 0 || $value <= 0 || (($campaigns = Frontend::get('Kampagnenbesucher')) === null)) {
            return 0;
        }
        $events = [];
        if (!\is_array($campaigns)) {
            $campaigns = $campaigns instanceof self
                ? [$campaigns->kKampagne => $campaigns]
                : [];
            Frontend::set('Kampagnenbesucher', $campaigns);
        }
        foreach ($campaigns as $campaign) {
            $event               = new stdClass();
            $event->kKampagne    = $campaign->kKampagne;
            $event->kKampagneDef = $id;
            $event->kKey         = $kKey;
            $event->fWert        = $value;
            $event->cParamWert   = $campaign->cWert;
            $event->dErstellt    = 'NOW()';

            if ($customData !== null) {
                $event->cCustomData = \mb_substr($customData, 0, 255);
            }
            $events[] = $event;
        }

        return Shop::Container()->getDB()->insertBatch('tkampagnevorgang', $events);
    }

    private function validateStaticParams(): bool
    {
        $full = Shop::getURL() . '/?' . $this->cParameter . '=' . $this->cWert;
        \parse_str(\parse_url($full, \PHP_URL_QUERY) ?: '', $params);
        $ok = \count($params) > 0;
        foreach ($params as $param => $value) {
            if (\is_array($value)) {
                continue;
            }
            if (!self::paramMatches(Request::verifyGPDataString((string)$param), (string)$value)) {
                $ok = false;
                break;
            }
        }

        return $ok;
    }

    public function isValid(): bool
    {
        $given = Request::verifyGPDataString($this->cParameter);

        return $given !== '' && ($this->nDynamisch === 1 || $this->validateStaticParams());
    }

    public function trackHit(int $visitorID, string $referrer = ''): void
    {
        $requestURL          = $_SERVER['REQUEST_URI'] ?? '';
        $event               = new stdClass();
        $event->kKampagne    = $this->kKampagne;
        $event->kKampagneDef = \KAMPAGNE_DEF_HIT;
        $event->kKey         = $visitorID;
        $event->fWert        = 1.0;
        $event->cParamWert   = $this->nDynamisch === 0
            ? $this->cWert
            : Request::verifyGPDataString($this->cParameter);
        if ($referrer !== '' || $requestURL !== '') {
            $event->cCustomData = Text::filterXSS((string)$requestURL) . ';' . $referrer;
        }
        $event->dErstellt = 'NOW()';
        $this->cWert      = $event->cParamWert;
        $this->db->insert('tkampagnevorgang', $event);
        $_SESSION['Kampagnenbesucher'][$this->kKampagne] = $this;
    }

    public function setDB(DbInterface $db): void
    {
        $this->db = $db;
    }

    public function getName(): string
    {
        return $this->cParameter === 'jtl'
            ? \__($this->cName)
            : $this->cName;
    }
}
