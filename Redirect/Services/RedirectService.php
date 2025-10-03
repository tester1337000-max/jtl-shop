<?php

declare(strict_types=1);

namespace JTL\Redirect\Services;

use InvalidArgumentException;
use JTL\Abstracts\AbstractService;
use JTL\DataObjects\AbstractDomainObject;
use JTL\Helpers\Text;
use JTL\Helpers\URL;
use JTL\Redirect\DomainObjects\RedirectDomainObject;
use JTL\Redirect\Helpers\Normalizer;
use JTL\Redirect\Repositories\RedirectRefererRepository;
use JTL\Redirect\Repositories\RedirectRepository;
use JTL\Redirect\Type;
use JTL\Redirect\URLParser;
use JTL\Shop;
use stdClass;

use function Functional\some;

class RedirectService extends AbstractService
{
    public function __construct(
        protected RedirectRepository $redirectRepository = new RedirectRepository(),
        protected RedirectRefererRepository $redirectRefererRepository = new RedirectRefererRepository(),
        private readonly Normalizer $normalizer = new Normalizer()
    ) {
    }

    public function getRepository(): RedirectRepository
    {
        return $this->redirectRepository;
    }

    public function deleteByID(int $id): bool
    {
        return $this->redirectRepository->delete($id);
    }

    public function deleteUnassigned(): int
    {
        return $this->redirectRepository->deleteUnassigned();
    }

    public function createDO(
        string $source,
        string $destination,
        int $paramHandling,
        int $type = Type::UNKNOWN,
        ?int $id = null,
    ): RedirectDomainObject {
        return new RedirectDomainObject(
            source: $source,
            destination: $destination,
            id: $id,
            paramHandling: $paramHandling,
            type: $type
        );
    }

    /**
     * @param RedirectDomainObject $insertDTO
     */
    public function save(AbstractDomainObject $insertDTO, bool $force = false, bool $overwriteExisting = false): bool
    {
        if ($insertDTO->source === $insertDTO->destination) {
            throw new InvalidArgumentException('Source and destination must not be the same');
        }
        $insertDTO->source      = $this->normalizer->normalize($insertDTO->source);
        $insertDTO->destination = $this->normalizer->normalize($insertDTO->destination, false);

        $source      = $insertDTO->source;
        $destination = $insertDTO->destination;

        $this->updateOld($source, $destination);
        if (!$this->validateData($source, $destination, $force)) {
            throw new InvalidArgumentException('Invalid source or destination');
        }
        if ($this->isDeadlock($source, $destination)) {
            $this->redirectRepository->deleteBySourceAndDestination($source, $destination);
        }
        $this->updateCircular($source, $destination, $insertDTO->paramHandling, $insertDTO->type);
        $redirect = $this->redirectRepository->getItemBySource($this->normalizer->normalize($source));
        if ($redirect === null) {
            return $this->redirectRepository->insert($insertDTO) > 0;
        }
        if (
            ($overwriteExisting || empty($redirect->cToUrl))
            && $this->normalizer->normalize($redirect->cFromUrl) === $source
        ) {
            // the redirect already exists with empty cToUrl or updateExisting is allowed => update
            $data = (object)['cToUrl' => Text::convertUTF8($destination), 'type' => $insertDTO->type];

            return $this->redirectRepository->batchUpdate('cFromUrl', $source, $data) > 0;
        }

        return false;
    }

    private function updateOld(string $source, string $destination): void
    {
        foreach ($this->redirectRepository->getItemsByDestination($source) as $oldRedirect) {
            $oldRedirect->cToUrl = $destination;
            if ($oldRedirect->cFromUrl === $destination) {
                $this->redirectRepository->delete((int)$oldRedirect->kRedirect);
            } else {
                $oldRedirect->cToUrl = $destination;
                $dto                 = RedirectDomainObject::fromObject($oldRedirect);
                $this->redirectRepository->update($dto);
            }
        }
    }

    private function updateCircular(string $source, string $destination, int $handling, int $type): void
    {
        $target = $this->redirectRepository->getItemByDestination($this->normalizer->normalize($source));
        if ($target === null) {
            return;
        }
        $dto = $this->createDO(
            source: $target->cFromUrl,
            destination: $destination,
            paramHandling: $handling,
            type: $type
        );
        $this->save($dto);
        $this->redirectRepository->updateByDestination($source, Text::convertUTF8($destination), $handling, $type);
    }

    private function isDeadlock(string $source, string $destination): bool
    {
        $path        = \parse_url(Shop::getURL(), \PHP_URL_PATH);
        $destination = $path !== null ? ($path . '/' . $destination) : $destination;
        $redirect    = $this->redirectRepository->getItemBySourceAndDestination($source, $destination);

        return $redirect !== null && (int)$redirect->kRedirect > 0;
    }

    private function validateData(string $source, string $destination, bool $force = false): bool
    {
        return $force === true
            || (\mb_strlen($source) > 1 && \mb_strlen($destination) > 1 && $this->checkAvailability($destination));
    }

    /**
     * @param string $url - one of
     *                    * full URL (must be inside the same shop) e.g. http://www.shop.com/path/to/page
     *                    * url path e.g. /path/to/page
     *                    * path relative to the shop root url
     */
    public function checkAvailability(string $url): bool
    {
        try {
            $parsed = new URLParser($url);
        } catch (InvalidArgumentException) {
            return false;
        }

        return some(
            $this->getHeaders(URL::unparseURL($parsed->toArray())) ?: [],
            static fn(string $header): int|false => \preg_match('/^HTTP\\/\\d+\\.\\d+\\s+2\\d\\d\\s+.*$/', $header)
        );
    }

    /**
     * @return string[]
     */
    public function getHeaders(string $url): array
    {
        if (!\DEFAULT_CURL_OPT_VERIFYPEER) {
            \stream_context_set_default([
                'ssl' => [
                    'verify_peer'      => false,
                    'verify_peer_name' => false,
                ],
            ]);
        }

        return \get_headers($url) ?: [];
    }

    public function getTotalCount(string $whereSQL = ''): int
    {
        return $this->redirectRepository->getTotalCount($whereSQL);
    }

    /**
     * @return stdClass[]
     */
    public function getRedirects(string $whereSQL = '', string $orderSQL = '', string $limitSQL = ''): array
    {
        $redirects = $this->redirectRepository->getRedirects($whereSQL, $orderSQL, $limitSQL);
        foreach ($redirects as $redirect) {
            $redirect->referers = $this->redirectRefererRepository->getReferers($redirect->kRedirect);
        }

        return $redirects;
    }

    public function getByID(int $id): RedirectDomainObject
    {
        $item = $this->redirectRepository->getObjectByID($id);
        if ($item === null) {
            throw new InvalidArgumentException('Redirect not found');
        }

        return $item;
    }
}
