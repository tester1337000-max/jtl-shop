<?php

declare(strict_types=1);

namespace JTL\Redirect\Repositories;

use JTL\Abstracts\AbstractDBRepository;
use JTL\DataObjects\DomainObjectInterface;
use JTL\Helpers\Text;
use JTL\Redirect\DomainObjects\RedirectDomainObject;
use stdClass;

use function Functional\map;

class RedirectRepository extends AbstractDBRepository
{
    /**
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'tredirect';
    }

    /**
     * @inheritdoc
     */
    public function getKeyName(): string
    {
        return 'kRedirect';
    }

    /**
     * @inheritdoc
     */
    public function getKeyValue(DomainObjectInterface $domainObject): ?int
    {
        return $domainObject->id ?? null;
    }

    public function deleteUnassigned(): int
    {
        return $this->db->getAffectedRows(
            "DELETE tredirect, tredirectreferer
                FROM tredirect
                LEFT JOIN tredirectreferer
                    ON tredirect.kRedirect = tredirectreferer.kRedirect
                WHERE tredirect.cToUrl = ''"
        );
    }

    /**
     * @return stdClass[]
     */
    public function getItemsBySource(string $source): array
    {
        return map(
            $this->db->getObjects(
                'SELECT * FROM tredirect WHERE cFromUrl = :source',
                ['source' => $source]
            ),
            $this->sanitizeRedirectData(...)
        );
    }

    /**
     * @return stdClass[]
     */
    public function getItemsByDestination(string $destination): array
    {
        return map(
            $this->db->getObjects(
                'SELECT * FROM tredirect WHERE cToUrl = :source',
                ['source' => $destination]
            ),
            $this->sanitizeRedirectData(...)
        );
    }

    public function getItemBySource(string $source): ?stdClass
    {
        $item = $this->db->select(
            'tredirect',
            'cFromUrl',
            \mb_substr($source, 0, 255)
        );
        if ($item === null) {
            return null;
        }

        return $this->sanitizeRedirectData($item);
    }

    public function getItemByDestination(string $destination): ?stdClass
    {
        $item = $this->db->select('tredirect', 'cToUrl', $destination);
        if ($item === null) {
            return null;
        }

        return $this->sanitizeRedirectData($item);
    }

    public function updateByDestination(string $oldDestination, string $destination, int $handling, int $type): int
    {
        $upd                = new stdClass();
        $upd->cToUrl        = $destination;
        $upd->cAvailable    = 'y';
        $upd->paramHandling = $handling;
        $upd->type          = $type;

        return $this->db->update($this->getTableName(), 'cToUrl', $oldDestination, $upd);
    }

    public function getItemBySourceAndDestination(string $source, string $destination): ?stdClass
    {
        $item = $this->db->select($this->getTableName(), 'cFromUrl', $destination, 'cToUrl', $source);
        if ($item === null) {
            return null;
        }

        return $this->sanitizeRedirectData($item);
    }

    public function deleteBySourceAndDestination(string $source, string $destination): int
    {
        return $this->db->delete('tredirect', ['cToUrl', 'cFromUrl'], [$source, $destination]);
    }

    public function batchUpdate(string $column, string|int $value, stdClass $data): int
    {
        return $this->db->update($this->getTableName(), $column, $value, $data);
    }

    public function getTotalCount(string $whereSQL = ''): int
    {
        return $this->db->getSingleInt(
            'SELECT COUNT(kRedirect) AS cnt
                FROM tredirect' .
            ($whereSQL !== '' ? ' WHERE ' . $whereSQL : ''),
            'cnt'
        );
    }

    /**
     * @return stdClass[]
     */
    public function getRedirects(string $whereSQL = '', string $orderSQL = '', string $limitSQL = ''): array
    {
        $redirects = $this->getDB()->getObjects(
            'SELECT *
                FROM tredirect' .
            ($whereSQL !== '' ? ' WHERE ' . $whereSQL : '') .
            ($orderSQL !== '' ? ' ORDER BY ' . $orderSQL : '') .
            ($limitSQL !== '' ? ' LIMIT ' . $limitSQL : '')
        );
        foreach ($redirects as $redirect) {
            $redirect           = $this->sanitizeRedirectData($redirect);
            $redirect->cFromUrl = Text::filterXSS($redirect->cFromUrl);
        }

        return $redirects;
    }

    public function getObjectByID(int $id): ?RedirectDomainObject
    {
        $item = $this->get($id);
        if ($item === null) {
            return null;
        }

        return RedirectDomainObject::fromObject($item);
    }

    private function sanitizeRedirectData(stdClass $item): stdClass
    {
        $item->kRedirect     = (int)$item->kRedirect;
        $item->nCount        = (int)$item->nCount;
        $item->paramHandling = (int)$item->paramHandling;
        $item->type          = (int)$item->type;

        return $item;
    }
}
