<?php

declare(strict_types=1);

namespace JTL\Newsletter;

use DateTime;
use JTL\Shop;
use stdClass;

/**
 * Class NewsletterCronDAO
 * reflects all columns of the table `tcron`, except the auto_increment column
 * @package JTL\Newsletter
 */
class NewsletterCronDAO
{
    private int $foreignKeyID = 0;

    private string $foreignKey = 'kNewsletter';

    private string $tableName = 'tnewsletter';

    private string $name = 'Newsletter';

    private string $jobType = 'newsletter';

    private int $frequency;

    private string $startDate;

    private string $startTime;

    private string $lastStart = '_DBNULL_';

    private string $lastFinish = '_DBNULL_';

    public function __construct()
    {
        $this->startDate = (new DateTime())->format('Y-m-d H:i:s');
        $this->startTime = (new DateTime())->format('H:i:s');
        $this->frequency = Shop::getSettingValue(\CONF_NEWSLETTER, 'newsletter_send_delay');
    }

    public function getForeignKeyID(): int
    {
        return $this->foreignKeyID;
    }

    public function setForeignKeyID(int $foreignKeyID): self
    {
        $this->foreignKeyID = $foreignKeyID;

        return $this;
    }

    public function getForeignKey(): string
    {
        return $this->foreignKey;
    }

    public function setForeignKey(string $foreignKey): self
    {
        $this->foreignKey = $foreignKey;

        return $this;
    }

    public function getTableName(): string
    {
        return $this->tableName;
    }

    public function setTableName(string $tableName): self
    {
        $this->tableName = $tableName;

        return $this;
    }

    public function getFrequency(): int
    {
        return $this->frequency;
    }

    public function setFrequency(int $frequency): self
    {
        $this->frequency = $frequency;

        return $this;
    }

    public function getStartDate(): string
    {
        return $this->startDate;
    }

    public function setStartDate(string $startDate): self
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getStartTime(): string
    {
        return $this->startTime;
    }

    public function setStartTime(string $startTime): self
    {
        $this->startTime = $startTime;

        return $this;
    }

    public function getLastStart(): string
    {
        return $this->lastStart;
    }

    public function setLastStart(string $lastStart): self
    {
        $this->lastStart = $lastStart;

        return $this;
    }

    public function getLastFinish(): string
    {
        return $this->lastFinish;
    }

    public function setLastFinish(string $lastFinish): self
    {
        $this->lastFinish = $lastFinish;

        return $this;
    }

    public function getData(): stdClass
    {
        $res = new stdClass();
        foreach (\get_object_vars($this) as $k => $v) {
            $res->$k = $v;
        }

        return $res;
    }
}
