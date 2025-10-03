<?php

declare(strict_types=1);

namespace JTL\Backend;

use InvalidArgumentException;
use JTL\DB\DbInterface;
use JTL\Shop;
use stdClass;

/**
 * Class Revision
 * @package JTL\Backend
 */
class Revision
{
    /**
     * @var array{table: string, id: string, reference?: string,
     *       reference_id?: string, reference_key?: string}[]
     */
    private static array $mapping = [
        'link'          => [
            'table'         => 'tlink',
            'id'            => 'kLink',
            'reference'     => 'tlinksprache',
            'reference_id'  => 'kLink',
            'reference_key' => 'cISOSprache'
        ],
        'export'        => [
            'table' => 'texportformat',
            'id'    => 'kExportformat'
        ],
        'mail'          => [
            'table'         => 'temailvorlage',
            'id'            => 'kEmailvorlage',
            'reference'     => 'temailvorlagesprache',
            'reference_id'  => 'kEmailvorlage',
            'reference_key' => 'kSprache'
        ],
        'opcpage'       => [
            'table' => 'topcpage',
            'id'    => 'kPage'
        ],
        'news'          => [
            'table'         => 'tnews',
            'id'            => 'kNews',
            'reference'     => 'tnewssprache',
            'reference_id'  => 'kNews',
            'reference_key' => 'languageCode'
        ],
        'box'           => [
            'table'         => 'tboxen',
            'id'            => 'kBox',
            'reference'     => 'tboxsprache',
            'reference_id'  => 'kBox',
            'reference_key' => 'cISO'
        ],
        'newsletterstd' => [
            'table'         => 'tnewslettervorlage',
            'id'            => 'kNewsletterVorlage',
            'reference'     => 'tnewslettervorlagestdvarinhalt',
            'reference_id'  => 'kNewslettervorlage',
            'reference_key' => 'kNewslettervorlageStdVar'
        ],
        'newsletter'    => [
            'table' => 'tnewslettervorlage',
            'id'    => 'kNewsletterVorlage'
        ]
    ];

    private DbInterface $db;

    public function __construct(?DbInterface $db = null)
    {
        $this->db = $db ?? Shop::Container()->getDB();
    }

    /**
     * @return array{table: string, id: string, reference?: string,
     *       reference_id?: string, reference_key?: string}|null
     */
    private function getMapping(string $type): ?array
    {
        return self::$mapping[$type] ?? null;
    }

    /**
     * @param array{table: string, id: string, reference?: string,
     *      reference_id?: string, reference_key?: string} $mapping
     */
    public function addMapping(string $name, array $mapping): self
    {
        self::$mapping[$name] = $mapping;

        return $this;
    }

    public function getRevision(int $id): ?stdClass
    {
        return $this->db->select('trevisions', 'id', $id);
    }

    public function getLatestRevision(string $type, int $key): ?stdClass
    {
        $mapping = $this->getMapping($type);
        if ($key === 0 || $mapping === null) {
            throw new InvalidArgumentException('Invalid revision type ' . $type);
        }

        return $this->db->getSingleObject(
            'SELECT *
                FROM trevisions
                WHERE type = :tp
                    AND reference_primary = :ref
                ORDER BY timestamp DESC',
            ['tp' => $type, 'ref' => $key]
        );
    }

    public function addRevision(string $type, int $key, bool $secondary = false, ?string $author = null): bool
    {
        if (\MAX_REVISIONS <= 0) {
            return false;
        }
        if (empty($key) || ($mapping = $this->getMapping($type)) === null) {
            throw new InvalidArgumentException('Invalid type/key given. Got type ' . $type . ' and key ' . $key);
        }
        if ($author === null) {
            $author = $_SESSION['AdminAccount']->cLogin ?? '?';
        }
        $field           = $mapping['id'];
        $currentRevision = $this->db->select($mapping['table'], $mapping['id'], $key);
        if ($currentRevision === null || empty($currentRevision->$field)) {
            return false;
        }
        $ok                           = true;
        $revision                     = new stdClass();
        $revision->type               = $type;
        $revision->reference_primary  = $key;
        $revision->content            = $currentRevision;
        $revision->author             = $author;
        $revision->custom_table       = $mapping['table'];
        $revision->custom_primary_key = $mapping['id'];
        if ($secondary !== false && !empty($mapping['reference']) && isset($mapping['reference_id'])) {
            $field               = $mapping['reference_key'] ?? null;
            $referencedRevisions = $this->db->selectAll(
                $mapping['reference'],
                $mapping['reference_id'],
                $key
            );
            if ($field === null || empty($referencedRevisions)) {
                return false;
            }
            $revision->content->references = [];
            foreach ($referencedRevisions as $referencedRevision) {
                $revision->content->references[$referencedRevision->$field] = $referencedRevision;
            }
            try {
                $revision->content = \json_encode($revision->content, \JSON_THROW_ON_ERROR);

                $latestRevision = $this->getLatestRevision($type, $key);
                if ($latestRevision === null || $latestRevision->content !== $revision->content) {
                    $this->storeRevision($revision);
                    $this->housekeeping($type, $key);
                }
            } catch (\JsonException) {
                $ok = false;
            }

            return $ok;
        }
        try {
            $revision->content = \json_encode($revision->content, \JSON_THROW_ON_ERROR);
            $this->storeRevision($revision);
            $this->housekeeping($type, $key);
        } catch (\JsonException) {
            $ok = false;
        }

        return $ok;
    }

    /**
     * @return stdClass[]
     */
    public function getRevisions(string $type, int $primary): array
    {
        return \array_map(
            static function (stdClass $e): stdClass {
                $e->content = \json_decode($e->content, false, 512, \JSON_THROW_ON_ERROR);

                return $e;
            },
            $this->db->selectAll(
                'trevisions',
                ['type', 'reference_primary'],
                [$type, $primary],
                '*',
                'timestamp DESC'
            )
        );
    }

    public function deleteAll(): self
    {
        $this->db->query('TRUNCATE table trevisions');

        return $this;
    }

    private function storeRevision(stdClass $revision): int
    {
        return $this->db->insert('trevisions', $revision);
    }

    public function restoreRevision(string $type, int $id, bool $secondary = false): bool
    {
        $revision = $this->getRevision($id);
        $mapping  = $this->getMapping($type); // get static mapping from build in content types
        if (
            $revision !== null
            && $mapping === null
            && !empty($revision->custom_table)
            && !empty($revision->custom_primary_key)
        ) {
            // load dynamic mapping from DB
            $mapping = ['table' => $revision->custom_table, 'id' => $revision->custom_primary_key];
        }
        if (isset($revision->id) && $mapping !== null) {
            /** @var stdClass $oldCOntent */
            $oldCOntent = \json_decode($revision->content, false, 512, \JSON_THROW_ON_ERROR);
            $primaryRow = $mapping['id'];
            $primaryKey = $oldCOntent->$primaryRow;
            $updates    = 0;
            unset($oldCOntent->$primaryRow);
            if ($secondary === false) {
                $updates = $this->db->update($mapping['table'], $primaryRow, $primaryKey, $oldCOntent);
            }
            if (
                $secondary === true
                && isset($mapping['reference'], $mapping['reference_key'], $oldCOntent->references)
            ) {
                $tableToUpdate = $mapping['reference'];
                $secondaryRow  = $mapping['reference_key']; // most likely something like "kSprache"
                foreach ($oldCOntent->references as $key => $value) {
                    // $key is the index in the reference array - which corresponds to the foreign key
                    unset($value->$primaryRow, $value->$secondaryRow);
                    $updates += $this->db->update(
                        $tableToUpdate,
                        [$primaryRow, $secondaryRow],
                        [$primaryKey, $key],
                        $value
                    );
                }
            }
            if ($updates > 0) {
                $this->db->delete('trevisions', 'id', $id);

                return true;
            }
        }

        return false;
    }

    public function deleteRevision(int $id): int
    {
        return $this->db->delete('trevisions', 'id', $id);
    }

    private function housekeeping(string $type, int $key): int
    {
        return $this->db->getAffectedRows(
            'DELETE a 
                FROM trevisions AS a 
                JOIN ( 
                    SELECT id 
                        FROM trevisions 
                        WHERE type = :type 
                            AND reference_primary = :prim
                        ORDER BY timestamp DESC 
                        LIMIT 99999 OFFSET :max) AS b
                ON a.id = b.id',
            [
                'type' => $type,
                'prim' => $key,
                'max'  => \MAX_REVISIONS
            ]
        );
    }
}
