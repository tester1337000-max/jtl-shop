<?php

/** @noinspection PhpDeprecationInspection */

declare(strict_types=1);

namespace JTL;

use JTL\DB\DbInterface;
use JTL\Helpers\Text;
use JTL\Helpers\URL;
use JTL\Redirect\Helpers\Normalizer;
use JTL\Redirect\Services\RedirectService;
use JTL\Redirect\Services\ValidationService;
use stdClass;

/**
 * Class Redirect
 * @package JTL
 * @deprecated since 5.4.1
 */
class Redirect
{
    public ?int $kRedirect = null;

    public ?string $cFromUrl = null;

    public ?string $cToUrl = null;

    public ?string $cAvailable = null;

    public int $type = self::TYPE_UNKNOWN;

    public int $nCount = 0;

    public int $paramHandling = 0;

    protected DbInterface $db;

    /**
     * @deprecated since 5.4.1
     */
    public const TYPE_UNKNOWN = 0;

    /**
     * @deprecated since 5.4.1
     */
    public const TYPE_WAWI = 1;

    /**
     * @deprecated since 5.4.1
     */
    public const TYPE_IMPORT = 2;

    /**
     * @deprecated since 5.4.1
     */
    public const TYPE_MANUAL = 3;

    /**
     * @deprecated since 5.4.1
     */
    public const TYPE_404 = 4;

    /**
     * @deprecated since 5.4.1
     */
    public function __construct(int $id = 0, ?DbInterface $db = null)
    {
        $this->db = $db ?? Shop::Container()->getDB();
        if ($id > 0) {
            $this->loadFromDB($id);
        }
    }

    /**
     * @deprecated since 5.4.1
     */
    public function loadFromDB(int $id): self
    {
        $obj = $this->db->select('tredirect', 'kRedirect', $id);
        if ($obj === null || $obj->kRedirect < 1) {
            return $this;
        }
        $this->kRedirect     = (int)$obj->kRedirect;
        $this->nCount        = (int)$obj->nCount;
        $this->paramHandling = (int)$obj->paramHandling;
        $this->cFromUrl      = $obj->cFromUrl;
        $this->cToUrl        = $obj->cToUrl;
        $this->cAvailable    = $obj->cAvailable;
        $this->type          = (int)($obj->type ?? self::TYPE_UNKNOWN);

        return $this;
    }

    /**
     * @deprecated since 5.4.1
     */
    public function find(string $url): ?stdClass
    {
        return (new RedirectService())->getRepository()->getItemBySource((new Normalizer())->normalize($url));
    }

    /**
     * @deprecated since 5.4.1
     */
    public function getRedirectByTarget(string $targetURL): ?stdClass
    {
        return (new RedirectService())->getRepository()
            ->getItemByDestination((new Normalizer())->normalize($targetURL));
    }

    /**
     * @deprecated since 5.4.1
     */
    public function isDeadlock(string $source, string $destination): bool
    {
        $path        = \parse_url(Shop::getURL(), \PHP_URL_PATH);
        $destination = $path !== null ? ($path . '/' . $destination) : $destination;
        $redirect    = $this->db->select('tredirect', 'cFromUrl', $destination, 'cToUrl', $source);

        return $redirect !== null && (int)$redirect->kRedirect > 0;
    }

    /**
     * @deprecated since 5.4.1
     */
    public function saveExt(
        string $source,
        string $destination,
        bool $force = false,
        int $handling = 0,
        bool $overwriteExisting = false,
        int $type = self::TYPE_UNKNOWN
    ): bool {
        $normalizer = new Normalizer();
        if (\mb_strlen($source) > 0) {
            $source = $normalizer->normalize($source);
        }
        if (\mb_strlen($destination) > 0) {
            $destination = $normalizer->normalize($destination, false);
        }
        if ($source === $destination) {
            return false;
        }
        $this->updateOld($source, $destination);
        if ($force || $this->dataIsValid($source, $destination)) {
            if ($this->isDeadlock($source, $destination)) {
                $this->db->delete('tredirect', ['cToUrl', 'cFromUrl'], [$source, $destination]);
            }
            $this->updateCircular($source, $destination, $handling, $type);
            $redirect = $this->find($source);
            if ($redirect === null) {
                return $this->create($source, $destination, $handling, $type);
            }
            if (
                ($overwriteExisting || empty($redirect->cToUrl))
                && $normalizer->normalize($redirect->cFromUrl) === $source
            ) {
                // the redirect already exists with empty cToUrl or updateExisting is allowed => update
                $update = $this->db->update(
                    'tredirect',
                    'cFromUrl',
                    $source,
                    (object)['cToUrl' => Text::convertUTF8($destination), 'type' => $type]
                );

                return $update > 0;
            }
        }

        return false;
    }

    /**
     * @deprecated since 5.4.1
     */
    public function test(string $url): false|string
    {
        return (new ValidationService())->test($url);
    }

    /**
     * @deprecated since 5.4.1
     */
    public function isValid(string $url): bool
    {
        return (new ValidationService())->isValid($url);
    }

    /**
     * @deprecated since 5.4.1
     */
    public function normalize(string $path, bool $trailingSlash = true): string
    {
        $url = new URL();
        $url->setUrl($path);

        return '/' . ($trailingSlash ? \trim($url->normalize(), '\\/') : \ltrim($url->normalize(), '\\/'));
    }

    /**
     * @return stdClass[]
     * @deprecated since 5.4.1
     */
    public static function getRedirects(string $whereSQL = '', string $orderSQL = '', string $limitSQL = ''): array
    {
        return (new RedirectService())->getRedirects($whereSQL, $orderSQL, $limitSQL);
    }

    /**
     * @deprecated since 5.4.1
     */
    public static function getRedirectCount(string $whereSQL = ''): int
    {
        return Shop::Container()->getDB()->getSingleInt(
            'SELECT COUNT(kRedirect) AS cnt
                FROM tredirect' .
            ($whereSQL !== '' ? ' WHERE ' . $whereSQL : ''),
            'cnt'
        );
    }

    /**
     * @return stdClass[]
     * @deprecated since 5.4.1
     */
    public static function getReferers(int $redirectID, int $limit = 100): array
    {
        return Shop::Container()->getDB()->getObjects(
            'SELECT tredirectreferer.*, tbesucherbot.cName AS cBesucherBotName,
                    tbesucherbot.cUserAgent AS cBesucherBotAgent
                FROM tredirectreferer
                LEFT JOIN tbesucherbot
                    ON tredirectreferer.kBesucherBot = tbesucherbot.kBesucherBot
                    WHERE kRedirect = :kr
                ORDER BY dDate ASC
                LIMIT :lmt',
            ['kr' => $redirectID, 'lmt' => $limit]
        );
    }

    /**
     * @deprecated since 5.4.1
     */
    public static function getTotalRedirectCount(): int
    {
        return (new RedirectService())->getTotalCount();
    }

    /**
     * @param string $url - one of
     *                    * full URL (must be inside the same shop) e.g. http://www.shop.com/path/to/page
     *                    * url path e.g. /path/to/page
     *                    * path relative to the shop root url
     * @return bool
     * @deprecated since 5.4.1
     */
    public static function checkAvailability(string $url): bool
    {
        return (new RedirectService())->checkAvailability($url);
    }

    /**
     * @deprecated since 5.4.1
     */
    public static function deleteRedirect(int $kRedirect): void
    {
        Shop::Container()->getDB()->delete('tredirect', 'kRedirect', $kRedirect);
        Shop::Container()->getDB()->delete('tredirectreferer', 'kRedirect', $kRedirect);
    }

    /**
     * @deprecated since 5.4.1
     */
    public static function deleteUnassigned(): int
    {
        return (new RedirectService())->deleteUnassigned();
    }

    /**
     * @param array<string, mixed>|null $hookInfos
     * @param bool                      $forceExit
     * @return array<string, mixed>
     * @deprecated since 5.4.1
     */
    public static function urlNotFoundRedirect(?array $hookInfos = null, bool $forceExit = false): array
    {
        $shopSubPath = \parse_url(Shop::getURL(), \PHP_URL_PATH) ?: '';
        $url         = \preg_replace('/^' . \preg_quote($shopSubPath, '/') . '/', '', $_SERVER['REQUEST_URI'] ?? '', 1);
        $redirect    = new self();
        $redirectUrl = $redirect->test($url);
        if ($redirectUrl !== false && $redirectUrl !== $url && '/' . $redirectUrl !== $url) {
            $parsed = \parse_url($redirectUrl);
            if (!isset($parsed['scheme'])) {
                $redirectUrl = \str_starts_with($redirectUrl, '/')
                    ? Shop::getURL() . $redirectUrl
                    : Shop::getURL() . '/' . $redirectUrl;
            }
            \http_response_code(301);
            \header('Location: ' . $redirectUrl);
            exit;
        }
        \http_response_code(404);

        if ($forceExit || !$redirect->isValid($url)) {
            exit;
        }
        $isFileNotFound = true;
        \executeHook(\HOOK_PAGE_NOT_FOUND_PRE_INCLUDE, [
            'isFileNotFound'  => &$isFileNotFound,
            $hookInfos['key'] => &$hookInfos['value']
        ]);
        $hookInfos['isFileNotFound'] = $isFileNotFound;

        return $hookInfos;
    }

    private function create(string $source, string $destination, int $handling, int $type): bool
    {
        $ins                = new stdClass();
        $ins->cFromUrl      = Text::convertUTF8($source);
        $ins->cToUrl        = Text::convertUTF8($destination);
        $ins->cAvailable    = 'y';
        $ins->paramHandling = $handling;
        $ins->type          = $type;

        return $this->db->insert('tredirect', $ins) > 0;
    }

    private function updateOld(string $source, string $destination): void
    {
        $oldRedirects = $this->db->getObjects(
            'SELECT * FROM tredirect WHERE cToUrl = :source',
            ['source' => $source]
        );
        foreach ($oldRedirects as $oldRedirect) {
            $oldRedirect->cToUrl = $destination;
            if ($oldRedirect->cFromUrl === $destination) {
                $this->db->delete('tredirect', 'kRedirect', (int)$oldRedirect->kRedirect);
            } else {
                $this->db->updateRow('tredirect', 'kRedirect', (int)$oldRedirect->kRedirect, $oldRedirect);
            }
        }
    }

    private function updateCircular(string $source, string $destination, int $handling, int $type): void
    {
        $target = $this->getRedirectByTarget($source);
        if ($target === null) {
            return;
        }
        $this->saveExt($target->cFromUrl, $destination, false, $handling, false, $type);
        $upd                = new stdClass();
        $upd->cToUrl        = Text::convertUTF8($destination);
        $upd->cAvailable    = 'y';
        $upd->paramHandling = $handling;
        $upd->type          = $type;
        $this->db->update('tredirect', 'cToUrl', $source, $upd);
    }

    private function dataIsValid(string $source, string $destination): bool
    {
        return self::checkAvailability($destination) && \mb_strlen($source) > 1 && \mb_strlen($destination) > 1;
    }
}
