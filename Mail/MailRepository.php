<?php

declare(strict_types=1);

namespace JTL\Mail;

use JTL\Abstracts\AbstractDBRepository;
use JTL\DataObjects\DataTableObjectInterface;
use stdClass;

/**
 * Class MailRepository
 * @package JTL\Mail
 */
class MailRepository extends AbstractDBRepository
{
    /**
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'emails';
    }

    public function queueMailDataTableObject(DataTableObjectInterface $mailDataTableObject): int
    {
        return $this->insertMail($mailDataTableObject);
    }

    public function insertMail(DataTableObjectInterface $insertDTO): int
    {
        return $this->getDB()->insertRow($this->getTableName(), $insertDTO->toObject(true, true));
    }

    /**
     * @return array<int, array<mixed>>
     */
    public function getNextMailsFromQueue(int $chunkSize): array
    {
        $stmt = 'SELECT * FROM ' . $this->getTableName() .
            ' WHERE (isSendingNow = 0 AND sendCount < 3 AND errorCount < 3) OR reSend = 1' .
            ' ORDER BY priority, id LIMIT :chunkSize';

        return $this->getDB()->getArrays($stmt, ['chunkSize' => $chunkSize]);
    }

    /**
     * @return array<int, array<mixed>>
     */
    public function getMailByQueueId(int $mailId): array
    {
        $stmt = 'SELECT * FROM ' . $this->getTableName() .
            ' WHERE id = :mailId';

        return $this->getDB()->getArrays($stmt, ['mailId' => $mailId]);
    }

    /**
     * @param int[] $mailIds
     */
    public function setMailStatus(array $mailIds, int $isSendingNow): bool
    {
        $ids      = \implode(',', $this->ensureIntValuesInArray($mailIds));
        $stmt     = 'UPDATE ' .
            $this->getTableName() . ' SET reSend = 0, isSendingNow = :isSendingNow, sendCount = sendCount + 1 ' .
            'WHERE id IN (:mailId)';
        $affected = $this->getDB()->getAffectedRows(
            $stmt,
            [
                'isSendingNow' => $isSendingNow,
                'mailId'       => $ids
            ]
        );

        return $affected > 0;
    }

    public function setError(int $mailID, string $errorMsg): int
    {
        $stmt = 'UPDATE emails ' .
            'SET isSendingNow = 0, sendCount = sendCount + 1, errorCount = errorCount + 1, lastError = :errorMsg ' .
            'WHERE id = :mailID';

        return $this->getDB()->getAffectedRows(
            $stmt,
            [
                'errorMsg' => $errorMsg,
                'mailID'   => $mailID,
            ]
        );
    }

    public function deleteQueuedMail(int|string $value): bool
    {
        $deleted = $this->getDB()->deleteRow(
            $this->getTableName(),
            $this->getKeyName(),
            $value
        );

        return $deleted !== self::DELETE_FAILED;
    }

    /**
     * @param numeric-string[]|int[] $mailIds
     */
    public function deleteQueuedMails(array $mailIds): int
    {
        return $this->db->getAffectedRows(
            'DELETE FROM ' . $this->getTableName() . '
            WHERE id IN (' . \implode(',', \array_map('\intval', $mailIds)) . ')'
        );
    }

    /**
     * @return stdClass[]
     */
    public function getQueuedMails(string $limit): array
    {
        $stmt = 'SELECT * FROM ' . $this->getTableName() . ' WHERE errorCount = 0 LIMIT ' . $limit;

        return $this->getDB()->getObjects($stmt);
    }

    public function getQueuedMailsCount(): int
    {
        $stmt = 'SELECT COUNT(*) AS cnt FROM ' . $this->getTableName() . ' WHERE errorCount = 0';

        return $this->getDB()->getSingleInt($stmt, 'cnt');
    }

    /**
     * @return stdClass[]
     */
    public function getErroneousMails(string $limit): array
    {
        $stmt = 'SELECT * FROM ' . $this->getTableName() . ' WHERE errorCount > 0 LIMIT ' . $limit;

        return $this->getDB()->getObjects($stmt);
    }

    public function getErroneousMailsCount(): int
    {
        $stmt = 'SELECT COUNT(*) AS cnt FROM ' . $this->getTableName() . ' WHERE errorCount > 0';

        return $this->getDB()->getSingleInt($stmt, 'cnt');
    }
}
