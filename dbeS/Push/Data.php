<?php

declare(strict_types=1);

namespace JTL\dbeS\Push;

/**
 * Class Data
 * @package JTL\dbeS\Push
 */
final class Data extends AbstractPush
{
    private const LIMIT_UPLOADQUEUE = 100;

    private const LIMIT_AVAILABILITY_MSGS = 100;

    /**
     * @return array<mixed>
     */
    public function getData(): array
    {
        $current = $this->db->getArrays(
            "SELECT *
                FROM tverfuegbarkeitsbenachrichtigung
                WHERE cAbgeholt = 'N'
                LIMIT :lmt",
            ['lmt' => self::LIMIT_AVAILABILITY_MSGS]
        );
        $xml     = [];
        $count   = \count($current);
        if ($count > 0) {
            $xml['tverfuegbarkeitsbenachrichtigung attr']['anzahl'] = $count;
            for ($i = 0; $i < $xml['tverfuegbarkeitsbenachrichtigung attr']['anzahl']; $i++) {
                $current[$i . ' attr'] = $this->buildAttributes($current[$i]);
                $this->db->queryPrepared(
                    "UPDATE tverfuegbarkeitsbenachrichtigung
                        SET cAbgeholt = 'Y'
                        WHERE kVerfuegbarkeitsbenachrichtigung = :mid",
                    ['mid' => (int)$current[$i . ' attr']['kVerfuegbarkeitsbenachrichtigung']]
                );
            }
            $xml['queueddata']['verfuegbarkeitsbenachrichtigungen']['tverfuegbarkeitsbenachrichtigung'] = $current;
        }
        $queueData = $this->db->getArrays(
            'SELECT *
                FROM tuploadqueue
                LIMIT :lmt',
            ['lmt' => self::LIMIT_UPLOADQUEUE]
        );
        $count     = \count($queueData);
        if ($count > 0) {
            $xml['queueddata']['uploadqueue']['tuploadqueue'] = $queueData;
            $xml['tuploadqueue attr']['anzahl']               = $count;
            foreach ($queueData as $i => $item) {
                $xml['queueddata']['uploadqueue']['tuploadqueue'][$i . ' attr'] = $this->buildAttributes($item);
            }
        }

        return $xml;
    }
}
