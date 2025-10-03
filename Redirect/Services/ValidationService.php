<?php

declare(strict_types=1);

namespace JTL\Redirect\Services;

use JTL\Helpers\Request;
use JTL\Redirect\DomainObjects\RedirectDomainObject;
use JTL\Redirect\Helpers\Normalizer;
use JTL\Redirect\Type;
use JTL\Session\Frontend;
use JTL\Settings\Option\Globals;
use JTL\Settings\Settings;
use stdClass;

readonly class ValidationService
{
    public function __construct(
        private RedirectService $redirectService = new RedirectService(),
        private Normalizer $normalizer = new Normalizer(),
        private RedirectRefererService $refererService = new RedirectRefererService()
    ) {
    }

    public function test(string $urlPath): string|false
    {
        $urlPath = $this->normalizer->normalize($urlPath);
        if (\mb_strlen($urlPath) === 0 || !$this->isValid($urlPath)) {
            return false;
        }
        $redirectUrl            = false;
        $foundRedirectWithQuery = false;
        $queryString            = $this->getQueryString($urlPath);
        $urlPath                = $this->getPath($urlPath);
        $item                   = empty($queryString)
            ? $this->redirectService->getRepository()->getItemBySource($urlPath)
            : $this->getRedirectDataWithQueryString($queryString, $urlPath, $foundRedirectWithQuery);
        if ($item === null) {
            $item = $this->add404Redirect($urlPath, $queryString);
        } elseif (\mb_strlen($item->cToUrl) > 0) {
            $redirectUrl = $item->cToUrl;
            $redirectUrl .= $queryString !== null && !$foundRedirectWithQuery
                ? '?' . $queryString
                : '';
        }
        $this->addReferer($urlPath, $item);

        return $redirectUrl;
    }

    private function addReferer(string $url, ?stdClass $item): void
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        if (\mb_strlen($referer) > 0) {
            $referer = $this->normalizer->normalize($referer);
        }
        $ip = Request::getRealIP();
        // Eintrag für diese IP bereits vorhanden?
        $entry = $this->refererService->getRefererByIPAndURL($ip, $url);
        if ($entry !== null && (int)$entry->nCount !== 0) {
            return;
        }
        $do = $this->refererService->createDO(
            (int)($item->kRedirect ?? '0'),
            (int)(Frontend::getVisitor()->kBesucherBot ?? '0'),
            \is_string($referer) ? $referer : '',
            $ip
        );
        $this->refererService->insert($do);
        // this counts only how many different referers are hitting that url
        if ($item !== null) {
            ++$item->nCount;
            $do = RedirectDomainObject::fromObject($item);
            $this->redirectService->update($do);
        }
    }

    private function getQueryString(string $url): ?string
    {
        return \parse_url($url)['query'] ?? null;
    }

    private function getPath(string $url): string
    {
        $parsedUrl = \parse_url($url);
        if (isset($parsedUrl['query'], $parsedUrl['path'])) {
            return $parsedUrl['path'];
        }

        return $url;
    }

    private function add404Redirect(string $url, ?string $queryString): ?stdClass
    {
        if (isset($_GET['notrack']) || Settings::boolValue(Globals::REDIRECTS_404) === false) {
            return null;
        }
        $dto            = $this->redirectService->createDO(
            $url . (!empty($queryString) ? '?' . $queryString : ''),
            '',
            0,
            Type::NOTFOUND
        );
        $dto->count     = 0;
        $dto->available = '';
        $id             = $this->redirectService->insert($dto);
        $dto->id        = $id;

        return $dto->toObject();
    }

    private function getRedirectDataWithQueryString(
        string $queryString,
        string &$url,
        bool &$foundRedirectWithQuery
    ): ?stdClass {
        $item = $this->redirectService->getRepository()->getItemBySource($url . '?' . $queryString);
        if ($item !== null) {
            $url                    .= '?' . $queryString;
            $foundRedirectWithQuery = true;

            return $item;
        }
        $item = $this->redirectService->getRepository()->getItemBySource($url);
        if ($item === null || $item->paramHandling === 0) {
            return null;
        }
        $foundRedirectWithQuery = $item->paramHandling === 1;

        return $item;
    }

    public function isValid(string $url): bool
    {
        $extension = \pathinfo($url, \PATHINFO_EXTENSION);
        if (\mb_strlen($extension) === 0) {
            return true;
        }
        $invalidExtensions = [
            'jpg',
            'jpeg',
            'gif',
            'bmp',
            'xml',
            'ico',
            'txt',
            'png'
        ];

        return !\in_array(\mb_convert_case($extension, \MB_CASE_LOWER), $invalidExtensions, true);
    }
}
