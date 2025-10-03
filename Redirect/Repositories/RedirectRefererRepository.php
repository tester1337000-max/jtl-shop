<?php

declare(strict_types=1);

namespace JTL\Redirect\Repositories;

use JTL\Abstracts\AbstractDBRepository;
use JTL\DataObjects\DomainObjectInterface;
use JTL\Helpers\Text;
use stdClass;

use function Functional\map;

class RedirectRefererRepository extends AbstractDBRepository
{
    /**
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'tredirectreferer';
    }

    /**
     * @inheritdoc
     */
    public function getKeyName(): string
    {
        return 'kRedirectReferer';
    }

    /**
     * @inheritdoc
     */
    public function getKeyValue(DomainObjectInterface $domainObject): ?int
    {
        return $domainObject->id ?? null;
    }

    /**
     * @return stdClass[]
     */
    public function getReferers(int $redirectID, int $limit = 100): array
    {
        return map(
            $this->db->getObjects(
                'SELECT tredirectreferer.*, tbesucherbot.cName AS cBesucherBotName,
                        tbesucherbot.cUserAgent AS cBesucherBotAgent
                    FROM tredirectreferer
                    LEFT JOIN tbesucherbot
                        ON tredirectreferer.kBesucherBot = tbesucherbot.kBesucherBot
                        WHERE kRedirect = :kr
                    ORDER BY dDate ASC
                    LIMIT :lmt',
                ['kr' => $redirectID, 'lmt' => $limit]
            ),
            static function (stdClass $item): stdClass {
                $item->kRedirectReferer = (int)$item->kRedirectReferer;
                $item->kRedirect        = (int)$item->kRedirect;
                $item->kBesucherBot     = (int)$item->kBesucherBot;
                $item->cRefererUrl      = Text::filterXSS($item->cRefererUrl);

                return $item;
            }
        );
    }

    public function getRefererByIPAndURL(string $ip, string $url): ?stdClass
    {
        return $this->db->getSingleObject(
            'SELECT *
                FROM tredirectreferer tr
                LEFT JOIN tredirect t
                    ON t.kRedirect = tr.kRedirect
                WHERE tr.cIP = :ip
                AND t.cFromUrl = :frm LIMIT 1',
            ['ip' => $ip, 'frm' => $url]
        );
    }
}
