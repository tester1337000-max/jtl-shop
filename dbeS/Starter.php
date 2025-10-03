<?php

declare(strict_types=1);

namespace JTL\dbeS;

use Generator;
use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\dbeS\Push\AbstractPush;
use JTL\dbeS\Push\Customers;
use JTL\dbeS\Push\Data as PushData;
use JTL\dbeS\Push\DeletedCustomers;
use JTL\dbeS\Push\ImageAPI;
use JTL\dbeS\Push\Invoice;
use JTL\dbeS\Push\MediaFiles;
use JTL\dbeS\Push\Orders as PushOrders;
use JTL\dbeS\Push\Payments;
use JTL\dbeS\Push\Returns as PushReturns;
use JTL\dbeS\Sync\AbstractSync;
use JTL\dbeS\Sync\Brocken;
use JTL\dbeS\Sync\Categories;
use JTL\dbeS\Sync\Characteristics;
use JTL\dbeS\Sync\ConfigGroups;
use JTL\dbeS\Sync\Customer;
use JTL\dbeS\Sync\Data;
use JTL\dbeS\Sync\DeliveryNotes;
use JTL\dbeS\Sync\Downloads;
use JTL\dbeS\Sync\Globals;
use JTL\dbeS\Sync\ImageCheck;
use JTL\dbeS\Sync\ImageLink;
use JTL\dbeS\Sync\Images;
use JTL\dbeS\Sync\ImageUpload;
use JTL\dbeS\Sync\Manufacturers;
use JTL\dbeS\Sync\Orders;
use JTL\dbeS\Sync\Products;
use JTL\dbeS\Sync\QuickSync;
use JTL\dbeS\Sync\Returns;
use JTL\Helpers\Text;
use JTL\Settings\Option\Image;
use JTL\Settings\Settings;
use JTL\Shop;
use JTL\XML;
use Psr\Log\LoggerInterface;
use SimpleXMLElement;

/**
 * Class Starter
 * @package JTL\dbeS
 */
class Starter
{
    public const ERROR_NOT_AUTHORIZED = 3;

    public const ERROR_UNZIP = 2;

    public const OK = 0;

    private const DIRECTION_PUSH = 'push';

    private const DIRECTION_PULL = 'pull';

    /**
     * @var array<string, class-string<AbstractSync>>
     */
    private static array $pullMapping = [
        'Artikel_xml'      => Products::class,
        'Bestellungen_xml' => Orders::class,
        'Returns_xml'      => Returns::class,
        'Bilder_xml'       => Images::class,
        'Brocken_xml'      => Brocken::class,
        'Data_xml'         => Data::class,
        'Download_xml'     => Downloads::class,
        'Globals_xml'      => Globals::class,
        'Hersteller_xml'   => Manufacturers::class,
        'img_check'        => ImageCheck::class,
        'img_link'         => ImageLink::class,
        'img_upload'       => ImageUpload::class,
        'Kategorien_xml'   => Categories::class,
        'Konfig_xml'       => ConfigGroups::class,
        'Kunden_xml'       => Customer::class,
        'Lieferschein_xml' => DeliveryNotes::class,
        'Merkmal_xml'      => Characteristics::class,
        'QuickSync_xml'    => QuickSync::class,
        'SetKunde_xml'     => Customer::class
    ];

    /**
     * @var array<string, class-string<AbstractPush>>
     */
    private static array $pushMapping = [
        'GetBestellungen_xml'     => PushOrders::class,
        'GetReturns_xml'          => PushReturns::class,
        'GetData_xml'             => PushData::class,
        'GetKunden_xml'           => Customers::class,
        'GetMediendateien_xml'    => MediaFiles::class,
        'GetZahlungen_xml'        => Payments::class,
        'Invoice_xml'             => Invoice::class,
        'bild'                    => ImageAPI::class,
        'GetDeletedCustomers_xml' => DeletedCustomers::class,
    ];

    /**
     * @var array<string, class-string<NetSyncHandler>>
     */
    private static array $netSyncMapping = [
        'Cronjob_xml'           => SyncCronjob::class,
        'GetDownloadStruct_xml' => ProductDownloads::class,
        'Upload_xml'            => Uploader::class
    ];

    private mixed $data = null;

    /**
     * @var array<mixed>|null
     */
    private ?array $postData = null;

    /**
     * @var string[]|null
     */
    private ?array $files = null;

    private string $unzipPath;

    private string $wawiVersion = 'unknown';

    public function __construct(
        private readonly Synclogin $auth,
        private readonly FileHandler $fileHandler,
        private readonly DbInterface $db,
        private readonly JTLCacheInterface $cache,
        private readonly LoggerInterface $logger
    ) {
        $this->checkPermissions();
    }

    private function checkPermissions(): void
    {
        $tmpDir = \PFAD_ROOT . \PFAD_DBES . \PFAD_SYNC_TMP;
        if (!\is_writable($tmpDir)) {
            \syncException(
                'Fehler beim Abgleich: Das Verzeichnis ' . $tmpDir . ' ist nicht beschreibbar!',
                \FREIDEFINIERBARER_FEHLER
            );
        }
    }

    public function getData(): mixed
    {
        return $this->data;
    }

    public function setData(mixed $data): void
    {
        $this->data = $data;
    }

    /**
     * @param string|null $index
     * @return array<mixed>|string|null
     */
    public function getPostData(?string $index = null): array|string|null
    {
        return $index === null ? $this->postData : ($this->postData[$index] ?? '');
    }

    /**
     * @param array<mixed> $postData
     */
    public function setPostData(array $postData): void
    {
        $this->postData = $postData;
    }

    public function getUnzipPath(): string
    {
        return $this->unzipPath;
    }

    public function setUnzipPath(string $unzipPath): void
    {
        $this->unzipPath = $unzipPath;
    }

    /**
     * @param array<mixed> $post
     * @return bool
     * @throws \Exception
     */
    public function checkAuth(array $post): bool
    {
        if (!isset($post['userID'], $post['userPWD'])) {
            return false;
        }
        $userID = Text::convertUTF8($post['userID']);
        $pass   = Text::convertUTF8($post['userPWD']);

        return $this->auth->checkLogin($userID, $pass) === true;
    }

    /**
     * @param array<mixed> $files
     * @return string[]|null
     */
    public function getFiles(array $files): ?array
    {
        return $this->fileHandler->getSyncFiles($files);
    }

    private function executeNetSync(string $handledFile): void
    {
        $mapping = self::$netSyncMapping[$handledFile] ?? null;
        if ($mapping === null) {
            return;
        }
        NetSyncHandler::create($mapping, $this->db, $this->logger);
        exit;
    }

    /**
     * handling of files that do not fit the general push/pull scheme
     * @param array<string, mixed> $post
     * @throws \Exception
     */
    private function handleSpecialCases(string $handledFile, array $post): void
    {
        if (!\in_array($handledFile, ['lastjobs', 'mytest', 'bild'], true)) {
            return;
        }
        $res = $this->init($post, [], false);
        switch ($handledFile) {
            case 'lastjobs':
                if ($res === self::OK) {
                    $lastjobs = new LastJob($this->db, $this->logger);
                    $lastjobs->execute();
                }
                echo $res;
                break;
            case 'mytest':
                if ($res === self::OK) {
                    $test = new Test($this->db);
                    echo $test->execute();
                } else {
                    \syncException(\APPLICATION_VERSION, $res);
                }
                break;
            case 'bild':
                $conf = Settings::stringValue(Image::EXTERNAL_INTERFACE_ENABLED);
                if ($conf === 'N' || ($conf === 'W' && $res !== self::OK)) {
                    exit; // api is wawi only
                }
                $api = new ImageAPI($this->db, $this->cache, $this->logger);
                $api->getData();
                break;
        }
        exit;
    }

    /**
     * @param string       $handledFile
     * @param array<mixed> $post
     * @param array<mixed> $files
     * @throws \Exception
     */
    public function start(string $handledFile, array $post, array $files): never
    {
        if (isset($post['uID'], $post['uPWD']) && !isset($post['userID'], $post['userPWD'])) {
            // for some reason, wawi sometimes sends uID/uPWD and sometimes userID/userPWD
            $post['userID']  = $post['uID'];
            $post['userPWD'] = $post['uPWD'];
        }
        if (Shop::getSettingValue(\CONF_GLOBAL, 'wartungsmodus_aktiviert') === 'Y') {
            echo 'Maintenance';
            exit();
        }
        $this->setVersionByUserAgent();
        $this->handleSpecialCases($handledFile, $post);
        $this->executeNetSync($handledFile);
        $direction = self::DIRECTION_PULL;
        $handler   = self::$pullMapping[$handledFile] ?? null;
        if ($handler === null) {
            $handler = self::$pushMapping[$handledFile] ?? null;
            if ($handler !== null) {
                $direction = self::DIRECTION_PUSH;
            }
        }
        if ($handler === null) {
            die();
        }
        $this->setPostData($post);
        $this->setData($files['data']['tmp_name'] ?? null);
        if ($direction === self::DIRECTION_PULL) {
            $this->pull($handler, $post, $files, $handledFile);
        } else {
            $this->push($post, $handler);
        }
        exit;
    }

    /**
     * @param array<mixed> $post
     * @param array<mixed> $files
     * @param bool         $unzip
     * @return int
     * @throws \Exception
     */
    public function init(array $post, array $files, bool $unzip = true): int
    {
        if (!$this->checkAuth($post)) {
            return self::ERROR_NOT_AUTHORIZED;
        }
        require_once \PFAD_ROOT . \PFAD_INCLUDES . 'sprachfunktionen.php';
        $this->setPostData($post);
        $this->setData($files['data']['tmp_name'] ?? null);
        if ($unzip !== true) {
            return self::OK;
        }
        $this->files     = $this->getFiles($files);
        $this->unzipPath = $this->fileHandler->getUnzipPath();

        return $this->files === null ? self::ERROR_UNZIP : self::OK;
    }

    /**
     * @param bool $string
     * @return ($string is true ? Generator<array<string, SimpleXMLElement>> : Generator<array<string, array<mixed>>>)
     */
    public function getXML(bool $string = false): Generator
    {
        /** @var string $xmlFile */
        foreach ($this->files ?? [] as $xmlFile) {
            if (!\str_contains($xmlFile, '.xml')) {
                continue;
            }
            $data = \file_get_contents($xmlFile) ?: '';
            if ($string === true) {
                $res = \simplexml_load_string($data) ?: new SimpleXMLElement('');
            } else {
                $res = XML::unserialize($data) ?? [];
            }

            yield [$xmlFile => $res];
        }
    }

    public function setVersionByUserAgent(): void
    {
        $useragent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $matches   = [];
        if ($useragent !== null) {
            \preg_match('/JTL-Wawi\/(\d+(\.\d+)+)/', $useragent, $matches);
            if (\count($matches) > 0 && isset($matches[1])) {
                $this->setWawiVersion($matches[1]);
            }
        }
    }

    public function getWawiVersion(): string
    {
        return $this->wawiVersion;
    }

    public function setWawiVersion(string $wawiVersion): void
    {
        $this->wawiVersion = $wawiVersion;
    }

    /**
     * @param array<mixed> $post
     * @param array<mixed> $files
     */
    private function pull(string $handler, array $post, array $files, string $handledFile): void
    {
        $res    = '';
        $unzip  = $handler !== Brocken::class;
        $return = $this->init($post, $files, $unzip);
        if ($return === self::OK) {
            /** @var AbstractSync $sync */
            $sync = new $handler($this->db, $this->cache, $this->logger);
            $res  = $sync->handle($this);
        }
        if ($handledFile !== 'SetKunde_xml') {
            echo $return;
            exit;
        }
        if (\is_array($res)) {
            $serializedXML = $this->getWawiVersion() === 'unknown'
                ? Text::convertISO(XML::serialize($res))
                : XML::serialize($res);
            echo $return . ";\n" . $serializedXML;
        } else {
            echo $return . ';' . $res;
        }
    }

    /**
     * @param array<mixed> $post
     */
    public function push(array $post, string $handler): void
    {
        $res = $this->init($post, [], false);
        if ($res === self::OK) {
            /** @var AbstractPush $pusher */
            $pusher = new $handler($this->db, $this->cache, $this->logger);
            $xml    = $pusher->getData();
            if (\is_array($xml) && \count($xml) > 0) {
                $pusher->zipRedirect(\time() . '.jtl', $xml, $this->getWawiVersion());
            }
        }
        echo $res;
    }
}
