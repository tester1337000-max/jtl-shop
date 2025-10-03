<?php

declare(strict_types=1);

namespace JTL\Session\Handler;

use JTL\DB\DbInterface;
use stdClass;

/**
 * Class DB
 * @package JTL\Session\Handler
 */
class DB extends JTLDefault
{
    protected int $lifeTime;

    public function __construct(protected DbInterface $db, protected string $tableName = 'tsession')
    {
        $this->lifeTime = (int)\get_cfg_var('session.gc_maxlifetime');
    }

    /**
     * @inheritdoc
     */
    public function open(string $path, string $name): bool
    {
        return $this->db->isConnected();
    }

    /**
     * @inheritdoc
     */
    public function close(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function read(string $id): string|false
    {
        $res = $this->db->getSingleObject(
            'SELECT cSessionData FROM ' . $this->tableName . '
                WHERE cSessionId = :id
                AND nSessionExpires > :time',
            [
                'id'   => $id,
                'time' => \time()
            ]
        );

        return $res->cSessionData ?? '';
    }

    /**
     * @inheritdoc
     */
    public function write(string $id, string $data): bool
    {
        // set new session expiration
        $newExp = \time() + $this->lifeTime;
        // is a session with this id already in the database?
        $res = $this->db->select($this->tableName, 'cSessionId', $id);
        // if yes,
        if ($res !== null) {
            //...update session data
            $update                  = new stdClass();
            $update->nSessionExpires = $newExp;
            $update->cSessionData    = $data;
            // if something happened, return true
            if ($this->db->update($this->tableName, 'cSessionId', $id, $update) > 0) {
                return true;
            }
        } else {
            // if no session was found, create a new row
            $session                  = new stdClass();
            $session->cSessionId      = $id;
            $session->nSessionExpires = $newExp;
            $session->cSessionData    = $data;

            return $this->db->insert($this->tableName, $session) > 0;
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function destroy(string $id): bool
    {
        // if session was deleted, return true,
        return $this->db->delete($this->tableName, 'cSessionId', $id) > 0;
    }

    /**
     * @inheritdoc
     */
    public function gc(int $max_lifetime): int|false
    {
        return $this->db->getAffectedRows(
            'DELETE FROM ' . $this->tableName . ' WHERE nSessionExpires < ' . \time()
        );
    }
}
