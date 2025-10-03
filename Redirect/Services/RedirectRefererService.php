<?php

declare(strict_types=1);

namespace JTL\Redirect\Services;

use InvalidArgumentException;
use JTL\Abstracts\AbstractService;
use JTL\Redirect\DomainObjects\RedirectRefererDomainObject;
use JTL\Redirect\Repositories\RedirectRefererRepository;
use stdClass;

class RedirectRefererService extends AbstractService
{
    public function __construct(protected RedirectRefererRepository $repository = new RedirectRefererRepository())
    {
    }

    public function getRepository(): RedirectRefererRepository
    {
        return $this->repository;
    }

    public function deleteByID(int $id): bool
    {
        return $this->repository->delete($id);
    }

    public function createDO(
        int $redirectID,
        int $botID,
        string $url,
        string $ip,
        ?int $timestamp = null,
        ?int $id = null,
    ): RedirectRefererDomainObject {
        return new RedirectRefererDomainObject(
            redirectID: $redirectID,
            botID: $botID,
            url: $url,
            ip: $ip,
            timestamp: $timestamp,
            id: $id
        );
    }

    public function getByID(int $id): RedirectRefererDomainObject
    {
        $item = $this->repository->get($id);
        if ($item === null) {
            throw new InvalidArgumentException('Redirect not found');
        }

        return RedirectRefererDomainObject::fromObject($item);
    }

    public function getRefererByIPAndURL(string $ip, string $url): ?stdClass
    {
        return $this->repository->getRefererByIPAndURL($ip, $url);
    }
}
