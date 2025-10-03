<?php

declare(strict_types=1);

namespace JTL\Mail\Attachments;

use JTL\Abstracts\AbstractDBRepository;
use JTL\Mail\SendMailObjects\MailDataAttachmentObject;

/**
 * Class AttachmentsRepository
 * @package JTL\Mail\Attachments
 */
class AttachmentsRepository extends AbstractDBRepository
{
    /**
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'email_attachments';
    }

    /**
     * @inheritdoc
     */
    public function getKeyName(): string
    {
        return 'id';
    }

    /**
     * @param int[] $IDs
     * @return \stdClass[]
     */
    public function getListByMailIDs(array $IDs): array
    {
        if (\count($IDs) > 0) {
            $IDs  = $this->ensureIntValuesInArray($IDs);
            $stmt = 'SELECT * FROM ' . $this->getTableName() .
                ' WHERE mailID IN(' . \implode(',', $IDs) . ')';

            return $this->db->getObjects($stmt);
        }

        return [];
    }

    public function insertMailAttachment(MailDataAttachmentObject $insertDTO): int
    {
        return $this->getDB()->insertRow($this->getTableName(), $insertDTO->toObject());
    }
}
