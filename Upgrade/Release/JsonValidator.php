<?php

declare(strict_types=1);

namespace JTL\Backend\Upgrade\Release;

use JsonException;

class JsonValidator
{
    /**
     * @var string[]
     */
    private static array $requiredKeys = [
        'id',
        'reference',
        'downloadUrl',
        'channel',
        'sha1',
        'last_modified'
    ];

    public function validate(string $data): bool
    {
        try {
            $res = (array)\json_decode($data, false, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return false;
        }
        foreach ($res as $item) {
            if (!\is_object($item)) {
                return false;
            }
            foreach (self::$requiredKeys as $key) {
                if (!isset($item->$key)) {
                    return false;
                }
            }
        }

        return true;
    }
}
