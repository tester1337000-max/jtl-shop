<?php

declare(strict_types=1);

namespace JTL\Cron\Job;

use DateInterval;
use DateTime;
use JTL\Campaign;
use JTL\Cron\Job;
use JTL\Cron\JobInterface;
use JTL\Cron\QueueEntry;
use JTL\Customer\Customer;
use JTL\Settings\Option\Newsletter as NewsletterConfig;
use JTL\Settings\Settings;
use JTL\Shop;
use stdClass;

/**
 * Class Newsletter
 * @package JTL\Cron\Job
 */
final class Newsletter extends Job
{
    /**
     * @inheritdoc
     */
    public function hydrate(object $data): self
    {
        parent::hydrate($data);
        if (\JOBQUEUE_LIMIT_M_NEWSLETTER > 0) {
            $this->setLimit((int)\JOBQUEUE_LIMIT_M_NEWSLETTER);
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function start(QueueEntry $queueEntry): JobInterface
    {
        parent::start($queueEntry);
        $id = $this->getForeignKeyID();
        if ($id === null || $this->checkLastSentDate($id) === false || ($jobData = $this->getJobData()) === null) {
            return $this;
        }
        $instance = new \JTL\Newsletter\Newsletter($this->db, Shop::getSettings([\CONF_NEWSLETTER]));
        $instance->initSmarty();
        $productIDs      = $instance->getKeys($jobData->cArtikel, true);
        $manufacturerIDs = $instance->getKeys($jobData->cHersteller);
        $categoryIDs     = $instance->getKeys($jobData->cKategorie);
        $customerGroups  = $instance->getKeys($jobData->cKundengruppe);
        $campaign        = new Campaign((int)$jobData->kKampagne, $this->db);
        if (\count($customerGroups) === 0) {
            $this->setFinished(true);

            return $this;
        }
        $products   = [];
        $categories = [];
        foreach ($customerGroups as $groupID) {
            $products[$groupID]   = $instance->getProducts($productIDs, $campaign, $groupID, (int)$jobData->kSprache);
            $categories[$groupID] = $instance->getCategories($categoryIDs, $campaign);
        }
        $manufacturers = $instance->getManufacturers($manufacturerIDs, $campaign, (int)$jobData->kSprache);
        $recipients    = $this->getRecipients($jobData, $queueEntry, $customerGroups);
        if (\count($recipients) === 0) {
            $this->setFinished(true);
            $this->db->delete('tcron', 'cronID', $this->getCronID());

            return $this;
        }
        $service = Shop::Container()->getPasswordService();
        $shopURL = Shop::getURL();
        foreach ($recipients as $recipient) {
            $recipient->cLoeschURL = $shopURL . '/?' . \QUERY_PARAM_OPTIN_CODE . '=' . $recipient->cLoeschCode;
            $cgID                  = \max(0, $recipient->kKundengruppe);
            $instance->send(
                $jobData,
                $recipient,
                $products[$cgID],
                $manufacturers,
                $categories[$cgID],
                $campaign,
                $recipient->kKunde > 0 ? new Customer($recipient->kKunde, $service, $this->db) : null
            );
            $this->db->update(
                'tnewsletterempfaenger',
                'kNewsletterEmpfaenger',
                $recipient->kNewsletterEmpfaenger,
                (object)['dLetzterNewsletter' => \date('Y-m-d H:i:s')]
            );
            ++$queueEntry->tasksExecuted;
        }
        $rowUpdate                = new stdClass();
        $rowUpdate->dLastSendings = (new DateTime())->format('Y-m-d H:i:s');
        $this->db->update('tnewsletter', 'kNewsletter', $id, $rowUpdate);
        $this->setFinished(false);

        return $this;
    }

    /**
     * @param stdClass   $jobData
     * @param QueueEntry $queueEntry
     * @param int[]      $customerGroups
     * @return stdClass[]
     */
    private function getRecipients(stdClass $jobData, QueueEntry $queueEntry, array $customerGroups): array
    {
        $cgSQL = 'AND (tkunde.kKundengruppe IN (' . \implode(',', $customerGroups) . ') ';
        if (\in_array(0, $customerGroups, true)) {
            $cgSQL .= ' OR tkunde.kKundengruppe IS NULL';
        }
        $cgSQL .= ')';

        return $this->db->getCollection(
            'SELECT tkunde.kKundengruppe, tkunde.kKunde, tsprache.cISO, tnewsletterempfaenger.kNewsletterEmpfaenger,
            tnewsletterempfaenger.cAnrede, tnewsletterempfaenger.cVorname, tnewsletterempfaenger.cNachname,
            tnewsletterempfaenger.cEmail, tnewsletterempfaenger.cLoeschCode
                FROM tnewsletterempfaenger
                LEFT JOIN tsprache
                    ON tsprache.kSprache = tnewsletterempfaenger.kSprache
                LEFT JOIN tkunde
                    ON tkunde.kKunde = tnewsletterempfaenger.kKunde
                WHERE tnewsletterempfaenger.kSprache = :lid
                    AND tnewsletterempfaenger.nAktiv = 1 ' . $cgSQL . '
                ORDER BY tnewsletterempfaenger.kKunde
                LIMIT :lmts, :lmte',
            [
                'lid'  => $jobData->kSprache,
                'lmts' => $queueEntry->tasksExecuted,
                'lmte' => $queueEntry->taskLimit
            ]
        )->map(static function (stdClass $recipient): stdClass {
            $recipient->kKundengruppe         = (int)$recipient->kKundengruppe;
            $recipient->kKunde                = (int)$recipient->kKunde;
            $recipient->kNewsletterEmpfaenger = (int)$recipient->kNewsletterEmpfaenger;

            return $recipient;
        })->toArray();
    }

    private function checkLastSentDate(int $id): bool
    {
        $delay       = Settings::intValue(NewsletterConfig::SEND_DELAY_HOURS);
        $lastSending = $this->db->select(
            'tnewsletter',
            'kNewsletter',
            $id,
            null,
            null,
            null,
            null,
            false,
            'dLastSendings'
        )?->dLastSendings;
        $interval    = DateInterval::createFromDateString('-' . $delay + 1 . ' hour');
        $now         = new DateTime();
        if ($interval !== false) {
            $now->add($interval);
        }
        // the first call always sends mails. each following call only sends mails depending on the lastSending-time
        $lastSending = empty($lastSending)
            ? $now
            : DateTime::createFromFormat('Y-m-d H:i:s', $lastSending);
        $date        = (new DateTime())->sub(new DateInterval('PT' . $delay . 'H'));

        return $date >= $lastSending;
    }
}
