<?php

declare(strict_types=1);

namespace JTL;

use DateTime;
use InvalidArgumentException;
use JTL\Cron\Job\Statusmail as StatusCron;
use JTL\Cron\JobHydrator;
use JTL\Cron\Type;
use JTL\DB\DbInterface;
use JTL\Helpers\Request;
use JTL\Helpers\Text;
use JTL\Mail\Mail\Attachment;
use JTL\Mail\Mail\Mail;
use JTL\Optin\OptinNewsletter;
use SmartyException;
use stdClass;

use function Functional\first;
use function Functional\map;

/**
 * Class Statusmail
 * @package JTL
 */
class Statusmail
{
    private string $dateStart;

    private string $dateEnd;

    public function __construct(private readonly DbInterface $db)
    {
        Shop::Container()->getGetText()->loadAdminLocale('pages/statusemail');
    }

    public function updateConfig(): bool
    {
        $active = Request::pInt('nAktiv') === 1;
        if (
            !$active
            || (Text::filterEmailAddress($_POST['cEmail']) !== false
                && \is_array($_POST['cIntervall_arr'])
                && \count($_POST['cIntervall_arr']) > 0
                && \is_array($_POST['cInhalt_arr'])
                && \count($_POST['cInhalt_arr']) > 0)
        ) {
            $this->db->query('TRUNCATE TABLE tstatusemail');
            $this->db->query(
                "DELETE tcron, tjobqueue
                    FROM tcron
                    LEFT JOIN tjobqueue
                        ON tjobqueue.cronID = tcron.cronID
                    WHERE tcron.jobType = 'statusemail'"
            );
            foreach ($_POST['cIntervall_arr'] as $interval) {
                $interval              = (int)$interval;
                $statusMail            = new stdClass();
                $statusMail->cEmail    = $_POST['cEmail'];
                $statusMail->nInterval = $interval;
                $statusMail->cInhalt   = Text::createSSK($_POST['cInhalt_arr']);
                $statusMail->nAktiv    = Request::pInt('nAktiv');
                $statusMail->dLastSent = 'NOW()';

                $id = $this->db->insert('tstatusemail', $statusMail);
                if ($active) {
                    $this->createCronJob($id, $interval * 24);
                }
            }

            return true;
        }

        return false;
    }

    private function createCronJob(int $id, int $frequency): bool
    {
        $types     = [
            24  => ['name' => \__('intervalDay'), 'date' => 'tomorrow'],
            168 => ['name' => \__('intervalWeek'), 'date' => 'next week'],
            720 => ['name' => \__('intervalMonth'), 'date' => 'first day of next month']
        ];
        $startDate = \date('Y-m-d', \strtotime($types[$frequency]['date']));
        $d         = new DateTime($startDate);
        $d->setTime(0, 0);
        Shop::Container()->getAlertService()->addInfo(
            \sprintf(\__('nextStatusMail'), $types[$frequency]['name'], $d->format('d.m.Y')),
            'nextStatusMail' . $frequency
        );
        $job = new StatusCron(
            $this->db,
            Shop::Container()->getLogService(),
            new JobHydrator(),
            Shop::Container()->getCache()
        );
        $job->setType(Type::STATUSMAIL);
        $job->setFrequency($frequency);
        $job->setStartDate($d->format('Y-m-d H:i:s'));
        $job->setStartTime($d->format('H:i:s'));
        $job->setNextStartDate($d->format('Y-m-d H:i:s'));
        $job->setTableName('tstatusemail');
        $job->setName('statusemail');
        $job->setForeignKeyID($id);
        $job->setForeignKey('id');

        return $job->insert() > 0;
    }

    public function loadConfig(): stdClass
    {
        $data = $this->db->getObjects('SELECT * FROM tstatusemail');
        /** @var stdClass|null $first */
        $first                        = first($data);
        $conf                         = new stdClass();
        $conf->cIntervallMoeglich_arr = $this->getPossibleIntervals();
        $conf->cInhaltMoeglich_arr    = $this->getPossibleContentTypes();
        $conf->nIntervall_arr         = map($data, fn(stdClass $e): int => (int)$e->nInterval);
        $conf->nInhalt_arr            = Text::parseSSKint($first->cInhalt ?? '');
        $conf->cEmail                 = $first->cEmail ?? '';
        $conf->nAktiv                 = (int)($first->nAktiv ?? 0);

        return $conf;
    }

    /**
     * @return array<string, int>
     */
    private function getPossibleIntervals(): array
    {
        return [
            \__('intervalDay')   => 1,
            \__('intervalWeek')  => 7,
            \__('intervalMonth') => 30
        ];
    }

    /**
     * @return array<string, int>
     */
    private function getPossibleContentTypes(): array
    {
        return [
            \__('contentTypeCountItemCustomerGroup')         => 1,
            \__('contentTypeCountNewCustomer')               => 2,
            \__('contentTypeCountNewCustomerOrdered')        => 3,
            \__('contentTypeCountOrders')                    => 4,
            \__('contentTypeCountOrdersNewCustomers')        => 5,
            \__('contentTypeCountPayments')                  => 23,
            \__('contentTypeCountOrdersSent')                => 24,
            \__('contentTypeCountVisitors')                  => 6,
            \__('contentTypeCountVisitorsSearchEngine')      => 7,
            \__('contentTypeCountRatings')                   => 8,
            \__('contentTypeCountRatingsLocked')             => 9,
            \__('contentTypeCountRatingDepositPayed')        => 10,
            \__('contentTypeCountCustomerRecruited')         => 13,
            \__('contentTypeCountCustomerRecruitedOrdered')  => 14,
            \__('contentTypeCountSentWishlists')             => 15,
            \__('contentTypeCountNewsComments')              => 17,
            \__('contentTypeCountNewsCommentsLocked')        => 18,
            \__('contentTypeCountProductQuestion')           => 19,
            \__('contentTypeCountAvailabilityNotifications') => 20,
            \__('contentTypeCountProductCompare')            => 21,
            \__('contentTypeCountCouponsUsed')               => 22,
            \__('contentTypeLastErrorLog')                   => 25,
            \__('contentTypeLastNoteLog')                    => 26,
            \__('contentTypeLastDebugLog')                   => 27,
            \__('contentTypeCountNewsletterOptOut')          => 28,
            \__('contentTypeCountNewsletterOptIn')           => 29,
        ];
    }

    /**
     * @return stdClass[]
     */
    private function getProductCountPerCustomerGroup(): array
    {
        $products       = [];
        $customerGroups = $this->db->getObjects('SELECT kKundengruppe, cName FROM tkundengruppe');
        foreach ($customerGroups as $customerGroup) {
            $productCount           = $this->db->getSingleInt(
                'SELECT COUNT(*) AS cnt
                    FROM tartikel
                    LEFT JOIN tartikelsichtbarkeit
                        ON tartikelsichtbarkeit.kArtikel = tartikel.kArtikel
                        AND tartikelsichtbarkeit.kKundengruppe = :cgid
                    WHERE tartikelsichtbarkeit.kArtikel IS NULL',
                'cnt',
                ['cgid' => (int)$customerGroup->kKundengruppe]
            );
            $product                = new stdClass();
            $product->nAnzahl       = $productCount;
            $product->kKundengruppe = (int)$customerGroup->kKundengruppe;
            $product->cName         = $customerGroup->cName;

            $products[] = $product;
        }

        return $products;
    }

    private function getNewCustomersCount(): int
    {
        return $this->db->getSingleInt(
            'SELECT COUNT(*) AS cnt
                FROM tkunde
                WHERE dErstellt >= :from
                    AND dErstellt < :to
                    AND nRegistriert = 1',
            'cnt',
            [
                'from' => $this->dateStart,
                'to'   => $this->dateEnd
            ]
        );
    }

    private function getNewCustomerSalesCount(): int
    {
        return $this->db->getSingleInt(
            'SELECT COUNT(DISTINCT(tkunde.kKunde)) AS cnt
                FROM tkunde
                JOIN tbestellung
                    ON tbestellung.kKunde = tkunde.kKunde
                WHERE tbestellung.dErstellt >= :from
                    AND tbestellung.dErstellt < :to
                    AND tkunde.dErstellt >= :from
                    AND tkunde.dErstellt < :to
                    AND tkunde.nRegistriert = 1',
            'cnt',
            [
                'from' => $this->dateStart,
                'to'   => $this->dateEnd
            ]
        );
    }

    private function getOrderCount(): int
    {
        return $this->db->getSingleInt(
            'SELECT COUNT(*) AS cnt
                FROM tbestellung
                WHERE dErstellt >= :from
                    AND dErstellt < :to',
            'cnt',
            [
                'from' => $this->dateStart,
                'to'   => $this->dateEnd
            ]
        );
    }

    private function getOrderCountForNewCustomers(): int
    {
        return $this->db->getSingleInt(
            'SELECT COUNT(*) AS cnt
                FROM tbestellung
                JOIN tkunde
                    ON tkunde.kKunde = tbestellung.kKunde
                WHERE tkunde.dErstellt >= :from
                    AND tkunde.dErstellt < :to
                    AND tkunde.nRegistriert = 1
                    AND tbestellung.dErstellt >= :from
                    AND tbestellung.dErstellt < :to',
            'cnt',
            [
                'from' => $this->dateStart,
                'to'   => $this->dateEnd
            ]
        );
    }

    private function getIncomingPaymentsCount(): int
    {
        return $this->db->getSingleInt(
            'SELECT COUNT(*) AS cnt
                FROM tbestellung
                WHERE tbestellung.dErstellt >= :from
                    AND tbestellung.dErstellt < :to
                    AND tbestellung.dBezahltDatum IS NOT NULL',
            'cnt',
            [
                'from' => $this->dateStart,
                'to'   => $this->dateEnd
            ]
        );
    }

    private function getShippedOrdersCount(): int
    {
        return $this->db->getSingleInt(
            'SELECT COUNT(*) AS cnt
                FROM tbestellung
                WHERE tbestellung.dErstellt >= :from
                    AND tbestellung.dErstellt < :to
                    AND tbestellung.dVersandDatum IS NOT NULL',
            'cnt',
            [
                'from' => $this->dateStart,
                'to'   => $this->dateEnd
            ]
        );
    }

    private function getVisitorCount(): int
    {
        return $this->db->getSingleInt(
            'SELECT COUNT(*) AS cnt
                FROM tbesucherarchiv
                WHERE dZeit >= :from
                    AND dZeit < :to
                    AND kBesucherBot = 0',
            'cnt',
            [
                'from' => $this->dateStart,
                'to'   => $this->dateEnd
            ]
        );
    }

    private function getBotVisitCount(): int
    {
        return $this->db->getSingleInt(
            "SELECT COUNT(*) AS cnt
                FROM tbesucherarchiv
                WHERE dZeit >= :from
                    AND dZeit < :to
                    AND cReferer != ''",
            'cnt',
            [
                'from' => $this->dateStart,
                'to'   => $this->dateEnd
            ]
        );
    }

    private function getRatingsCount(): int
    {
        return $this->db->getSingleInt(
            'SELECT COUNT(*) AS cnt
                FROM tbewertung
                WHERE dDatum >= :from
                    AND dDatum < :to
                    AND nAktiv = 1',
            'cnt',
            [
                'from' => $this->dateStart,
                'to'   => $this->dateEnd
            ]
        );
    }

    private function getNonApprovedRatingsCount(): int
    {
        return $this->db->getSingleInt(
            'SELECT COUNT(*) AS cnt
                FROM tbewertung
                WHERE dDatum >= :from
                    AND dDatum < :to
                    AND nAktiv = 0',
            'cnt',
            [
                'from' => $this->dateStart,
                'to'   => $this->dateEnd
            ]
        );
    }

    private function getRatingCreditsCount(): stdClass
    {
        $rating = $this->db->getSingleObject(
            'SELECT COUNT(*) AS cnt, SUM(fGuthabenBonus) AS fSummeGuthaben
                FROM tbewertungguthabenbonus
                WHERE dDatum >= :from
                    AND dDatum < :to',
            [
                'from' => $this->dateStart,
                'to'   => $this->dateEnd
            ]
        );

        $res                 = new stdClass();
        $res->nAnzahl        = (int)($rating->cnt ?? 0);
        $res->fSummeGuthaben = $rating->fSummeGuthaben ?? 0;

        return $res;
    }

    private function getNewsletterOptOutCount(): int
    {
        return $this->db->getSingleInt(
            'SELECT COUNT(*) AS cnt
                FROM toptinhistory
                WHERE dDeActivated >= :from
                AND dDeActivated < :to
                AND kOptinClass = :class
                ',
            'cnt',
            [
                'class' => OptinNewsletter::class,
                'from'  => $this->dateStart,
                'to'    => $this->dateEnd
            ]
        );
    }

    private function getNewsletterOptInCount(): int
    {
        return $this->db->getSingleInt(
            'SELECT COUNT(*) AS cnt
                FROM toptin
                WHERE dActivated >= :from
                AND dActivated < :to
                AND kOptinClass = :class
                ',
            'cnt',
            [
                'class' => OptinNewsletter::class,
                'from'  => $this->dateStart,
                'to'    => $this->dateEnd
            ]
        );
    }

    private function getSentWishlistCount(): int
    {
        return $this->db->getSingleInt(
            'SELECT COUNT(*) AS cnt
                    FROM twunschlisteversand
                    WHERE dZeit >= :from
                        AND dZeit < :to',
            'cnt',
            [
                'from' => $this->dateStart,
                'to'   => $this->dateEnd
            ]
        );
    }

    private function getNewsCommentsCount(): int
    {
        return $this->db->getSingleInt(
            'SELECT COUNT(*) AS cnt
                FROM tnewskommentar
                WHERE dErstellt >= :from
                    AND dErstellt < :to
                    AND nAktiv = 1',
            'cnt',
            [
                'from' => $this->dateStart,
                'to'   => $this->dateEnd
            ]
        );
    }

    private function getNonApprovedCommentsCount(): int
    {
        return $this->db->getSingleInt(
            'SELECT COUNT(*) AS cnt
                FROM tnewskommentar
                WHERE dErstellt >= :from
                    AND dErstellt < :to
                    AND nAktiv = 0',
            'cnt',
            [
                'from' => $this->dateStart,
                'to'   => $this->dateEnd
            ]
        );
    }

    private function getAvailabilityNotificationsCount(): int
    {
        return $this->db->getSingleInt(
            'SELECT COUNT(*) AS cnt
                FROM tverfuegbarkeitsbenachrichtigung
                WHERE dErstellt >= :from
                    AND dErstellt < :to',
            'cnt',
            [
                'from' => $this->dateStart,
                'to'   => $this->dateEnd
            ]
        );
    }

    private function getProductInquriesCount(): int
    {
        return $this->db->getSingleInt(
            'SELECT COUNT(*) AS cnt
                FROM tproduktanfragehistory
                WHERE dErstellt >= :from
                    AND dErstellt < :to',
            'cnt',
            [
                'from' => $this->dateStart,
                'to'   => $this->dateEnd
            ]
        );
    }

    private function getComparisonsCount(): int
    {
        return $this->db->getSingleInt(
            'SELECT COUNT(*) AS cnt
                FROM tvergleichsliste
                WHERE dDate >= :from
                    AND dDate < :to',
            'cnt',
            [
                'from' => $this->dateStart,
                'to'   => $this->dateEnd
            ]
        );
    }

    private function getCouponUsageCount(): int
    {
        return $this->db->getSingleInt(
            'SELECT COUNT(*) AS cnt
                FROM tkuponkunde
                WHERE dErstellt >= :from
                    AND dErstellt < :to',
            'cnt',
            [
                'from' => $this->dateStart,
                'to'   => $this->dateEnd
            ]
        );
    }

    /**
     * @param string[]|int[] $logLevels
     * @return stdClass[]
     */
    public function getLogEntries(array $logLevels): array
    {
        return map(
            $this->db->getObjects(
                'SELECT *
                    FROM tjtllog
                    WHERE dErstellt >= :from
                        AND dErstellt < :to
                        AND nLevel IN (' . \implode(',', \array_map('\intval', $logLevels)) . ')
                    ORDER BY dErstellt DESC',
                [
                    'from' => $this->dateStart,
                    'to'   => $this->dateEnd
                ]
            ),
            static function (stdClass $e): stdClass {
                $e->kLog   = (int)$e->kLog;
                $e->nLevel = (int)$e->nLevel;
                $e->kKey   = (int)$e->kKey;

                return $e;
            }
        );
    }

    /**
     * @throws SmartyException
     */
    public function generate(stdClass $statusMail, string $dateStart, string $dateEnd): false|stdClass
    {
        $this->dateStart = $dateStart;
        $this->dateEnd   = $dateEnd;
        if (!$this->validateMail($statusMail)) {
            return false;
        }
        $mail                                           = new stdClass();
        $mail->mail                                     = new stdClass();
        $mail->oAnzahlArtikelProKundengruppe            = -1;
        $mail->nAnzahlNeukunden                         = -1;
        $mail->nAnzahlNeukundenGekauft                  = -1;
        $mail->nAnzahlBestellungen                      = -1;
        $mail->nAnzahlBestellungenNeukunden             = -1;
        $mail->nAnzahlBesucher                          = -1;
        $mail->nAnzahlBesucherSuchmaschine              = -1;
        $mail->nAnzahlBewertungen                       = -1;
        $mail->nAnzahlBewertungenNichtFreigeschaltet    = -1;
        $mail->oAnzahlGezahltesGuthaben                 = -1;
        $mail->nAnzahlTags                              = -1;
        $mail->nAnzahlGeworbenerKunden                  = -1;
        $mail->nAnzahlErfolgreichGeworbenerKunden       = -1;
        $mail->nAnzahlVersendeterWunschlisten           = -1;
        $mail->nAnzahlNewskommentare                    = -1;
        $mail->nAnzahlNewskommentareNichtFreigeschaltet = -1;
        $mail->nAnzahlProduktanfrageArtikel             = -1;
        $mail->nAnzahlProduktanfrageVerfuegbarkeit      = -1;
        $mail->nAnzahlVergleiche                        = -1;
        $mail->nAnzahlGenutzteKupons                    = -1;
        $mail->nAnzahlZahlungseingaengeVonBestellungen  = -1;
        $mail->nAnzahlVersendeterBestellungen           = -1;
        $mail->nAnzahlNewsletterAbmeldungen             = -1;
        $mail->nAnzahlNewsletterAnmeldungen             = -1;
        $mail->dVon                                     = $dateStart;
        $mail->dBis                                     = $dateEnd;
        $mail->oLogEntry_arr                            = [];
        $logLevels                                      = [];
        foreach ($statusMail->nInhalt_arr as $nInhalt) {
            switch ($nInhalt) {
                case 1:
                    $mail->oAnzahlArtikelProKundengruppe = $this->getProductCountPerCustomerGroup();
                    break;
                case 2:
                    $mail->nAnzahlNeukunden = $this->getNewCustomersCount();
                    break;
                case 3:
                    $mail->nAnzahlNeukundenGekauft = $this->getNewCustomerSalesCount();
                    break;
                case 4:
                    $mail->nAnzahlBestellungen = $this->getOrderCount();
                    break;
                case 5:
                    $mail->nAnzahlBestellungenNeukunden = $this->getOrderCountForNewCustomers();
                    break;
                case 6:
                    $mail->nAnzahlBesucher = $this->getVisitorCount();
                    break;
                case 7:
                    $mail->nAnzahlBesucherSuchmaschine = $this->getBotVisitCount();
                    break;
                case 8:
                    $mail->nAnzahlBewertungen = $this->getRatingsCount();
                    break;
                case 9:
                    $mail->nAnzahlBewertungenNichtFreigeschaltet = $this->getNonApprovedRatingsCount();
                    break;
                case 10:
                    $mail->oAnzahlGezahltesGuthaben = $this->getRatingCreditsCount();
                    break;
                case 15:
                    $mail->nAnzahlVersendeterWunschlisten = $this->getSentWishlistCount();
                    break;
                case 17:
                    $mail->nAnzahlNewskommentare = $this->getNewsCommentsCount();
                    break;
                case 18:
                    $mail->nAnzahlNewskommentareNichtFreigeschaltet = $this->getNonApprovedCommentsCount();
                    break;
                case 19:
                    $mail->nAnzahlProduktanfrageArtikel = $this->getProductInquriesCount();
                    break;
                case 20:
                    $mail->nAnzahlProduktanfrageVerfuegbarkeit = $this->getAvailabilityNotificationsCount();
                    break;
                case 21:
                    $mail->nAnzahlVergleiche = $this->getComparisonsCount();
                    break;
                case 22:
                    $mail->nAnzahlGenutzteKupons = $this->getCouponUsageCount();
                    break;
                case 23:
                    $mail->nAnzahlZahlungseingaengeVonBestellungen = $this->getIncomingPaymentsCount();
                    break;
                case 24:
                    $mail->nAnzahlVersendeterBestellungen = $this->getShippedOrdersCount();
                    break;
                case 25:
                    $logLevels[] = \JTLLOG_LEVEL_ERROR;
                    $logLevels[] = \JTLLOG_LEVEL_CRITICAL;
                    $logLevels[] = \JTLLOG_LEVEL_ALERT;
                    $logLevels[] = \JTLLOG_LEVEL_EMERGENCY;
                    break;
                case 26:
                    $logLevels[] = \JTLLOG_LEVEL_NOTICE;
                    break;
                case 27:
                    $logLevels[] = \JTLLOG_LEVEL_DEBUG;
                    break;
                case 28:
                    $mail->nAnzahlNewsletterAbmeldungen = $this->getNewsletterOptOutCount();
                    break;
                case 29:
                    $mail->nAnzahlNewsletterAnmeldungen = $this->getNewsletterOptInCount();
                    break;
            }
        }
        $mail = $this->addLogs($mail, $logLevels);
        if ($mail === false) {
            return false;
        }
        $mail->mail->toEmail = $statusMail->cEmail;

        return $mail;
    }

    public function sendAllActiveStatusMails(): bool
    {
        $ok = true;
        foreach ($this->db->selectAll('tstatusemail', 'nAktiv', 1) as $statusMail) {
            $ok = $ok && $this->send($statusMail);
        }

        return $ok;
    }

    public function send(stdClass $statusMail): bool
    {
        $sent                    = false;
        $statusMail->nInhalt_arr = Text::parseSSKint($statusMail->cInhalt);
        $interval                = (int)$statusMail->nInterval;
        switch ($interval) {
            case 1:
                $startDate   = \date('Y-m-d', \strtotime('yesterday'));
                $endDate     = \date('Y-m-d', \strtotime('today'));
                $intervalLoc = \__('intervalDay');
                break;
            case 7:
                $startDate   = \date('Y-m-d', \strtotime('last week monday'));
                $endDate     = \date('Y-m-d', \strtotime('last week sunday'));
                $intervalLoc = \__('intervalWeek');
                break;
            case 30:
                $startDate   = \date('Y-m-d', \strtotime('first day of previous month'));
                $endDate     = \date('Y-m-d', \strtotime('last day of previous month'));
                $intervalLoc = \__('intervalMonth');
                break;
            default:
                throw new InvalidArgumentException('Invalid interval type: ' . $interval);
        }
        $data = $this->generate($statusMail, $startDate, $endDate);
        if ($data !== false) {
            $data->interval   = $interval;
            $data->cIntervall = $intervalLoc;

            $mailer = Shop::Container()->getMailer();
            $mail   = new Mail();
            $mail   = $mail->createFromTemplateID(\MAILTEMPLATE_STATUSEMAIL, $data);
            $mail->setToMail($statusMail->cEmail);
            if (!empty($data->mail->attachment)) {
                $mail->setAttachments([$data->mail->attachment]);
            }
            $sent = $mailer->send($mail);
        }

        return $sent;
    }

    private function validateMail(stdClass $statusMail): bool
    {
        return \is_array($statusMail->nInhalt_arr)
            && !empty($this->dateStart)
            && !empty($this->dateEnd)
            && !empty($statusMail->nAktiv)
            && \count($statusMail->nInhalt_arr) > 0;
    }

    /**
     * @param stdClass $mail
     * @param int[]    $logLevels
     * @return false|stdClass
     * @throws SmartyException
     */
    private function addLogs(stdClass $mail, array $logLevels): bool|stdClass
    {
        if (\count($logLevels) === 0) {
            return $mail;
        }
        $mailType            = $this->db->select(
            'temailvorlage',
            'cModulId',
            \MAILTEMPLATE_STATUSEMAIL,
            null,
            null,
            null,
            null,
            false,
            'cMailTyp'
        )->cMailTyp ?? 'text';
        $mail->oLogEntry_arr = $this->getLogEntries($logLevels);
        $logfile             = \tempnam(\sys_get_temp_dir(), 'jtl');
        if ($logfile === false) {
            return false;
        }
        $info       = \pathinfo($logfile);
        $fileStream = \fopen($logfile, 'wb');
        if ($fileStream === false || !isset($info['filename'], $info['dirname'])) {
            return false;
        }
        $attachment = new Attachment();
        $attachment->setFileName($info['filename']);
        $attachment->setDir($info['dirname'] . '/');
        $smarty = Shop::Smarty()->assign('oMailObjekt', $mail);
        if ($mailType === 'text') {
            \fwrite(
                $fileStream,
                $smarty->fetch(\PFAD_ROOT . \PFAD_EMAILVORLAGEN . 'ger/email_bericht_plain_log.tpl')
            );
            $attachment->setName('jtl-log-digest.txt');
        } else {
            \fwrite(
                $fileStream,
                $smarty->fetch(\PFAD_ROOT . \PFAD_EMAILVORLAGEN . 'ger/email_bericht_html_log.tpl')
            );
            $attachment->setName('jtl-log-digest.html');
        }

        \fclose($fileStream);
        $mail->mail->attachment = $attachment;

        return $mail;
    }
}
