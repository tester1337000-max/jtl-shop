<?php

declare(strict_types=1);

namespace JTL\Backend\StatusCheck;

use DateTime;

class MySqlPhpTime extends AbstractStatusCheck
{
    protected bool $includeInServiceReport = true;

    /**
     * @var array{db: string, php: string, diff: int}
     */
    private array $diffs = [
        'db'   => '0',
        'php'  => '0',
        'diff' => 0
    ];

    public function isOK(?string &$hash = null): bool
    {
        try {
            $dbTime  = new DateTime($this->db->getSingleObject('SELECT NOW() AS time')->time ?? 'now()');
            $phpTime = new DateTime();

            $this->diffs = [
                'db'   => $dbTime->format('Y-m-d H:i:s'),
                'php'  => $phpTime->format('Y-m-d H:i:s'),
                'diff' => \abs($dbTime->getTimestamp() - $phpTime->getTimestamp())
            ];

            return $this->diffs['diff'] <= 1;
        } catch (\Exception) {
            return true;
        }
    }

    public function getTitle(): string
    {
        return \__('mysqlTimeErrorTitle');
    }

    public function generateMessage(): void
    {
        $this->addNotification(\sprintf(\__('mysqlTimeErrorMessage'), $this->diffs['db'], $this->diffs['php']));
    }
}
