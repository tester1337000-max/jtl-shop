<?php

declare(strict_types=1);

namespace JTL\News;

use JTL\DB\DbInterface;
use JTL\Shop;
use stdClass;

/**
 * Class Author
 * @package JTL\News
 */
class Author
{
    private static ?self $instance = null;

    public static function getInstance(?DbInterface $db = null): self
    {
        return self::$instance ?? new self($db ?? Shop::Container()->getDB());
    }

    public function __construct(private readonly DbInterface $db)
    {
        self::$instance = $this;
    }

    public function setAuthor(string $realm, int $contentID, ?int $authorID = null): bool|int
    {
        if ($authorID === null || $authorID === 0) {
            $account = Shop::Container()->getAdminAccount()->account();
            if ($account !== false) {
                $authorID = $account->kAdminlogin;
            }
        }
        if ($authorID > 0) {
            return $this->db->getLastInsertedID(
                'INSERT INTO tcontentauthor (cRealm, kAdminlogin, kContentId)
                    VALUES (:realm, :aid, :cid)
                    ON DUPLICATE KEY UPDATE kAdminlogin = :aid',
                ['realm' => $realm, 'aid' => $authorID, 'cid' => $contentID]
            );
        }

        return false;
    }

    public function clearAuthor(string $realm, int $contentID): void
    {
        $this->db->delete('tcontentauthor', ['cRealm', 'kContentId'], [$realm, $contentID]);
    }

    public function getAuthor(string $realm, int $contentID, bool $activeOnly = false): ?stdClass
    {
        $filter = $activeOnly
            ? ' AND tadminlogin.bAktiv = 1
                AND COALESCE(tadminlogin.dGueltigBis, NOW()) >= NOW()'
            : '';
        $author = $this->db->getSingleObject(
            'SELECT tcontentauthor.kContentAuthor, tcontentauthor.cRealm, 
                tcontentauthor.kAdminlogin, tcontentauthor.kContentId,
                tadminlogin.cName, tadminlogin.cMail
                FROM tcontentauthor
                INNER JOIN tadminlogin 
                    ON tadminlogin.kAdminlogin = tcontentauthor.kAdminlogin
                WHERE tcontentauthor.cRealm = :realm
                    AND tcontentauthor.kContentId = :contentid' . $filter,
            ['realm' => $realm, 'contentid' => $contentID]
        );
        if ($author !== null && (int)$author->kAdminlogin > 0) {
            $attribs                = $this->db->getObjects(
                'SELECT tadminloginattribut.kAttribut, tadminloginattribut.cName, 
                    tadminloginattribut.cAttribValue, tadminloginattribut.cAttribText
                    FROM tadminloginattribut
                    WHERE tadminloginattribut.kAdminlogin = :aid',
                ['aid' => (int)$author->kAdminlogin]
            );
            $author->extAttribs     = [];
            $author->kContentId     = (int)$author->kContentId;
            $author->kContentAuthor = (int)$author->kContentAuthor;
            $author->kAdminlogin    = (int)$author->kAdminlogin;
            foreach ($attribs as $attrib) {
                $attrib->kAttribut                  = (int)$attrib->kAttribut;
                $author->extAttribs[$attrib->cName] = $attrib;
            }
        }

        return $author;
    }

    /**
     * @param string[]|null $adminRights
     * @return stdClass[]
     */
    public function getPossibleAuthors(?array $adminRights = null): array
    {
        $filter = '';
        if (\is_array($adminRights)) {
            $filter = " AND (tadminlogin.kAdminlogingruppe = 1
                        OR EXISTS (
                            SELECT 1 
                            FROM tadminrechtegruppe
                            WHERE tadminrechtegruppe.kAdminlogingruppe = tadminlogin.kAdminlogingruppe
                                AND tadminrechtegruppe.cRecht IN ('" . \implode("', '", $adminRights) . "')
                        ))";
        }

        return $this->db->getObjects(
            'SELECT tadminlogin.kAdminlogin, tadminlogin.cLogin, tadminlogin.cName, tadminlogin.cMail 
                FROM tadminlogin
                WHERE tadminlogin.bAktiv = 1
                    AND COALESCE(tadminlogin.dGueltigBis, NOW()) >= NOW()' . $filter
        );
    }
}
