<?php

declare(strict_types=1);

namespace JTL\Cron\Starter;

/**
 * Class Curl
 * @package JTL\Cron\Starter
 */
class Curl extends AbstractStarter
{
    private int $frequency = 1;

    public function getFrequency(): int
    {
        return $this->frequency;
    }

    public function setFrequency(int $frequency): void
    {
        $this->frequency = $frequency;
    }

    /**
     * @inheritdoc
     */
    public function start(): bool
    {
        if (\random_int(1, $this->frequency) !== 1) {
            return false;
        }
        $curl = \curl_init();
        \curl_setopt($curl, \CURLOPT_URL, $this->getURL());
        \curl_setopt($curl, \CURLOPT_POST, true);
        \curl_setopt($curl, \CURLOPT_NOSIGNAL, true);
        \curl_setopt($curl, \CURLOPT_POSTFIELDS, ['runCron' => 1]);
        \curl_setopt($curl, \CURLOPT_USERAGENT, 'jtl-shop-cron');
        \curl_setopt($curl, \CURLOPT_TIMEOUT_MS, $this->getTimeout());
        \curl_setopt($curl, \CURLOPT_HEADER, false);
        \curl_setopt($curl, \CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($curl, \CURLOPT_FORBID_REUSE, true);
        \curl_setopt($curl, \CURLOPT_CONNECTTIMEOUT_MS, $this->getTimeout());
        \curl_setopt($curl, \CURLOPT_DNS_CACHE_TIMEOUT, 1);
        \curl_setopt($curl, \CURLOPT_FRESH_CONNECT, true);
        \curl_setopt($curl, \CURLOPT_SSL_VERIFYPEER, \DEFAULT_CURL_OPT_VERIFYPEER);
        \curl_setopt($curl, \CURLOPT_SSL_VERIFYHOST, \DEFAULT_CURL_OPT_VERIFYHOST);
        \curl_exec($curl);
        \curl_close($curl);

        return true;
    }
}
