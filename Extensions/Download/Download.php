<?php

declare(strict_types=1);

namespace JTL\Extensions\Download;

use DateTime;
use JTL\Cart\Cart;
use JTL\Checkout\Bestellung;
use JTL\MagicCompatibilityTrait;
use JTL\Nice;
use JTL\Shop;

/**
 * Class Download
 * @package JTL\Extensions\Download
 */
class Download
{
    use MagicCompatibilityTrait;

    public const ERROR_NONE = 1;

    public const ERROR_ORDER_NOT_FOUND = 2;

    public const ERROR_INVALID_CUSTOMER = 3;

    public const ERROR_PRODUCT_NOT_FOUND = 4;

    public const ERROR_DOWNLOAD_LIMIT_REACHED = 5;

    public const ERROR_DOWNLOAD_EXPIRED = 6;

    public const ERROR_MISSING_PARAMS = 7;

    public ?int $kDownload = null;

    public ?string $cID = null;

    public ?string $cPfad = null;

    public ?string $cPfadVorschau = null;

    public int $nAnzahl = 0;

    public int $nTage = 0;

    public int $nSort = 0;

    public ?string $dErstellt = null;

    public ?Localization $oDownloadSprache = null;

    public ?int $kBestellung = null;

    public ?string $dGueltigBis = null;

    /**
     * @var \stdClass[]
     */
    public array $oArtikelDownload_arr = [];

    public ?string $cLimit = null;

    /**
     * @var array<string, string>
     */
    public static array $mapping = [
        'oDownloadHistory_arr' => 'DownloadHistory'
    ];

    public function __construct(int $id = 0, int $languageID = 0, bool $info = true, int $orderID = 0)
    {
        if ($id > 0 && self::checkLicense() === true) {
            $this->loadFromDB($id, $languageID, $info, $orderID);
        }
    }

    public static function checkLicense(): bool
    {
        return Nice::getInstance()->checkErweiterung(\SHOP_ERWEITERUNG_DOWNLOADS);
    }

    private function loadFromDB(int $id, int $languageID, bool $info, int $orderID): void
    {
        $item = Shop::Container()->getDB()->select('tdownload', 'kDownload', $id);
        if ($item === null || $item->kDownload <= 0) {
            return;
        }
        $this->kDownload     = (int)$item->kDownload;
        $this->nAnzahl       = (int)$item->nAnzahl;
        $this->nTage         = (int)$item->nTage;
        $this->nSort         = (int)$item->nSort;
        $this->cID           = $item->cID;
        $this->cPfad         = $item->cPfad;
        $this->cPfadVorschau = $item->cPfadVorschau;
        $this->dErstellt     = $item->dErstellt;
        if ($info) {
            if (!$languageID) {
                $languageID = Shop::getLanguageID();
            }
            $this->oDownloadSprache = new Localization($this->kDownload, $languageID);
        }
        if ($orderID > 0) {
            $this->kBestellung = $orderID;
            $order             = Shop::Container()->getDB()->getSingleObject(
                'SELECT * FROM tbestellung
                    WHERE kBestellung = :oid',
                ['oid' => $orderID]
            );
            if (
                $order !== null
                && $order->kBestellung > 0
                && $order->dBezahltDatum !== null
                && $this->getTage() > 0
            ) {
                $paymentDate = new DateTime($order->dBezahltDatum);
                $modifyBy    = $this->getTage() + 1;
                $paymentDate->modify('+' . $modifyBy . ' day');
                $this->dGueltigBis = $paymentDate->format('d.m.Y');
            }
        }
        $this->oArtikelDownload_arr = Shop::Container()->getDB()->getObjects(
            'SELECT tartikeldownload.*
                FROM tartikeldownload
                JOIN tdownload 
                    ON tdownload.kDownload = tartikeldownload.kDownload
                WHERE tartikeldownload.kDownload = :dlid
                ORDER BY tdownload.nSort',
            ['dlid' => $this->kDownload]
        );
        foreach ($this->oArtikelDownload_arr as $dla) {
            $dla->kArtikel  = (int)$dla->kArtikel;
            $dla->kDownload = (int)$dla->kDownload;
        }
    }

    /**
     * @param array<string, int|string> $keys
     * @param int                       $languageID
     * @return array<int, Download>
     */
    public static function getDownloads(array $keys = [], int $languageID = 0): array
    {
        $productID  = (int)($keys['kArtikel'] ?? 0);
        $orderID    = (int)($keys['kBestellung'] ?? 0);
        $customerID = (int)($keys['kKunde'] ?? 0);
        $downloads  = [];
        if (($productID > 0 || $orderID > 0 || $customerID > 0) && $languageID > 0 && self::checkLicense()) {
            if ($orderID > 0) {
                $prep   = [
                    'oid' => $orderID,
                    'pos' => \C_WARENKORBPOS_TYP_ARTIKEL
                ];
                $select = 'tbestellung.kBestellung, tbestellung.kKunde, tartikeldownload.kDownload';
                $where  = 'tartikeldownload.kArtikel = twarenkorbpos.kArtikel OR
                           tartikeldownload.kArtikel IN (
                                SELECT tstueckliste.kArtikel
                                FROM tstueckliste
                                    INNER JOIN tartikel
                                        ON tstueckliste.kStueckliste = tartikel.kStueckliste
                                WHERE tartikel.kArtikel = twarenkorbpos.kArtikel)';
                $join   = 'JOIN tbestellung ON tbestellung.kBestellung = :oid
                               JOIN tdownload ON tdownload.kDownload = tartikeldownload.kDownload
                               JOIN twarenkorbpos ON twarenkorbpos.kWarenkorb = tbestellung.kWarenkorb
                                    AND twarenkorbpos.nPosTyp = :pos';
            } elseif ($customerID > 0) {
                $prep   = [
                    'cid' => $customerID,
                    'pos' => \C_WARENKORBPOS_TYP_ARTIKEL
                ];
                $select = 'MAX(tbestellung.kBestellung) AS kBestellung, tbestellung.kKunde,
                    tartikeldownload.kDownload';
                $where  = 'tartikeldownload.kArtikel = twarenkorbpos.kArtikel OR
                           tartikeldownload.kArtikel IN (
                                SELECT tstueckliste.kArtikel
                                FROM tstueckliste
                                    INNER JOIN tartikel
                                        ON tstueckliste.kStueckliste = tartikel.kStueckliste
                                WHERE tartikel.kArtikel = twarenkorbpos.kArtikel)';
                $join   = 'JOIN tbestellung ON tbestellung.kKunde = :cid
                               JOIN tdownload ON tdownload.kDownload = tartikeldownload.kDownload
                               JOIN twarenkorbpos ON twarenkorbpos.kWarenkorb = tbestellung.kWarenkorb
                                    AND twarenkorbpos.nPosTyp = :pos';
            } else {
                $prep   = ['pid' => $productID];
                $select = 'tartikeldownload.kDownload';
                $where  = 'kArtikel = :pid OR
                           tartikeldownload.kArtikel IN (
                                SELECT tstueckliste.kArtikel
                                FROM tstueckliste
                                    LEFT JOIN tartikel
                                        ON tstueckliste.kStueckliste = tartikel.kStueckliste
                                WHERE tartikel.kArtikel = :pid)';
                $join   = 'LEFT JOIN tdownload ON tartikeldownload.kDownload = tdownload.kDownload';
            }

            $items = Shop::Container()->getDB()->getObjects(
                'SELECT ' . $select . '
                    FROM tartikeldownload
                    ' . $join . '
                    WHERE ' . $where . '
                    GROUP BY tartikeldownload.kDownload
                    ORDER BY tdownload.nSort, tdownload.dErstellt DESC',
                $prep
            );
            foreach ($items as $i => $data) {
                $data->kDownload   = (int)$data->kDownload;
                $data->kBestellung = (int)($data->kBestellung ?? 0);
                $data->kKunde      = (int)($data->kKunde ?? 0);
                $download          = new self(
                    $data->kDownload,
                    $languageID,
                    true,
                    $data->kBestellung
                );
                if (($orderID > 0 || $customerID > 0) && $download->getAnzahl() > 0) {
                    $history               = History::getOrderHistory($data->kKunde, $data->kBestellung);
                    $id                    = $download->getDownload();
                    $count                 = \count($history[$id] ?? []);
                    $download->cLimit      = $count . ' / ' . $download->getAnzahl();
                    $download->kBestellung = $data->kBestellung;
                }
                $downloads[$i] = $download;
            }
        }

        return $downloads;
    }

    public static function hasDownloads(Cart $cart): bool
    {
        foreach ($cart->PositionenArr as $item) {
            if (
                ($item->nPosTyp === \C_WARENKORBPOS_TYP_ARTIKEL
                    || $item->nPosTyp === \C_WARENKORBPOS_TYP_GRATISGESCHENK)
                && isset($item->Artikel->oDownload_arr)
                && \count($item->Artikel->oDownload_arr) > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public static function getFile(int $downloadID, int $customerID, int $orderID): int
    {
        if ($downloadID <= 0 || $customerID <= 0 || $orderID <= 0) {
            return self::ERROR_MISSING_PARAMS;
        }
        $download = new self($downloadID, 0, false);
        $res      = self::checkFile($download->kDownload ?? 0, $customerID, $orderID);
        if ($res !== self::ERROR_NONE) {
            return $res;
        }
        (new History())
            ->setDownload($downloadID)
            ->setKunde($customerID)
            ->setBestellung($orderID)
            ->setErstellt('NOW()')
            ->save();

        \executeHook(\HOOK_ORDER_DOWNLOAD_FILE, [
            'download'   => $download,
            'customerID' => $customerID,
            'orderID'    => $orderID
        ]);

        self::sendFileToBrowser(
            \PFAD_DOWNLOADS . $download->getPfad(),
            'application/octet-stream'
        );
    }

    /**
     * Fehlercodes:
     * 1 = Alles O.K.
     * 2 = Bestellung nicht gefunden
     * 3 = Kunde stimmt nicht
     * 4 = Kein Artikel mit Downloads gefunden
     * 5 = Maximales Downloadlimit wurde erreicht
     * 6 = Maximales Datum wurde erreicht
     * 7 = Paramter fehlen
     * @throws \Exception
     */
    public static function checkFile(int $downloadID, int $customerID, int $orderID): int
    {
        if ($downloadID <= 0 || $customerID <= 0 || $orderID <= 0) {
            return self::ERROR_MISSING_PARAMS;
        }
        $order = new Bestellung($orderID);
        // Existiert die Bestellung und wurde Sie bezahlt?
        if ($order->kBestellung <= 0 || (empty($order->dBezahltDatum) && $order->fGesamtsumme > 0)) {
            return self::ERROR_ORDER_NOT_FOUND;
        }
        // Stimmt der Kunde?
        if ($order->kKunde !== $customerID) {
            return self::ERROR_INVALID_CUSTOMER;
        }
        $order->fuelleBestellung();
        $download = new self($downloadID, $order->kSprache ?? 0, false);
        // Gibt es einen Artikel der zum Download passt?
        if (\count($download->oArtikelDownload_arr) === 0) {
            return self::ERROR_PRODUCT_NOT_FOUND;
        }
        foreach ($order->Positionen as $item) {
            foreach ($download->oArtikelDownload_arr as $donwloadItem) {
                if ($item->kArtikel !== $donwloadItem->kArtikel) {
                    continue;
                }
                if ($download->getAnzahl() > 0) {
                    $history = History::getOrderHistory($customerID, $orderID);
                    if (\count($history[$download->kDownload] ?? []) >= $download->getAnzahl()) {
                        return self::ERROR_DOWNLOAD_LIMIT_REACHED;
                    }
                }
                $paymentDate = new DateTime($order->dBezahltDatum);
                $paymentDate->modify('+' . ($download->getTage() + 1) . ' day');
                if ($download->getTage() > 0 && $paymentDate < new DateTime()) {
                    return self::ERROR_DOWNLOAD_EXPIRED;
                }

                return self::ERROR_NONE;
            }
        }

        return self::ERROR_MISSING_PARAMS;
    }

    /**
     * Fehlercodes:
     * 2 = Bestellung nicht gefunden
     * 3 = Kunde stimmt nicht
     * 4 = Kein Artikel mit Downloads gefunden
     * 5 = Maximales Downloadlimit wurde erreicht
     * 6 = Maximales Datum wurde erreicht
     * 7 = Paramter fehlen
     */
    public static function mapGetFileErrorCode(int $errorCode): string
    {
        return match ($errorCode) {
            self::ERROR_ORDER_NOT_FOUND        => Shop::Lang()->get('dlErrorOrderNotFound'),
            self::ERROR_INVALID_CUSTOMER       => Shop::Lang()->get('dlErrorCustomerNotMatch'),
            self::ERROR_PRODUCT_NOT_FOUND      => Shop::Lang()->get('dlErrorDownloadNotFound'),
            self::ERROR_DOWNLOAD_LIMIT_REACHED => Shop::Lang()->get('dlErrorDownloadLimitReached'),
            self::ERROR_DOWNLOAD_EXPIRED       => Shop::Lang()->get('dlErrorValidityReached'),
            self::ERROR_MISSING_PARAMS         => Shop::Lang()->get('dlErrorWrongParameter'),
            default                            => '',
        };
    }

    public function setDownload(int $kDownload): self
    {
        $this->kDownload = $kDownload;

        return $this;
    }

    /**
     * @return History[]
     */
    public function getDownloadHistory(?int $kDownload = null): array
    {
        return History::getHistory($kDownload ?? (int)$this->kDownload);
    }

    public function setID(string $cID): self
    {
        $this->cID = $cID;

        return $this;
    }

    public function setPfad(string $path): self
    {
        $this->cPfad = $path;

        return $this;
    }

    public function setPfadVorschau(string $path): self
    {
        $this->cPfadVorschau = $path;

        return $this;
    }

    public function setAnzahl(int $count): self
    {
        $this->nAnzahl = $count;

        return $this;
    }

    public function setTage(int $days): self
    {
        $this->nTage = $days;

        return $this;
    }

    public function setSort(int $sort): self
    {
        $this->nSort = $sort;

        return $this;
    }

    public function setErstellt(string $date): self
    {
        $this->dErstellt = $date;

        return $this;
    }

    public function getDownload(): ?int
    {
        return $this->kDownload;
    }

    public function getID(): ?string
    {
        return $this->cID;
    }

    public function getPfad(): ?string
    {
        return $this->cPfad;
    }

    public function hasPreview(): bool
    {
        return $this->cPfadVorschau !== null && \mb_strlen($this->cPfadVorschau) > 0;
    }

    public function getExtension(): string
    {
        if ($this->cPfad !== null && \mb_strlen($this->cPfad) > 0) {
            return \mb_convert_case(\pathinfo($this->cPfad, \PATHINFO_EXTENSION), \MB_CASE_UPPER);
        }

        return '';
    }

    public function getPreviewExtension(): string
    {
        return $this->cPfadVorschau !== null && \mb_strlen($this->cPfadVorschau) > 0
            ? \mb_convert_case(\pathinfo($this->cPfadVorschau, \PATHINFO_EXTENSION), \MB_CASE_UPPER)
            : '';
    }

    public function getPreviewType(): string
    {
        return match (\strtolower($this->getPreviewExtension())) {
            'mpeg', 'mpg', 'avi', 'wmv', 'mp4'        => 'video',
            'wav', 'mp3', 'wma'                       => 'music',
            'gif', 'jpeg', 'jpg', 'png', 'jpe', 'bmp' => 'image',
            default                                   => 'misc',
        };
    }

    public function getPreview(): string
    {
        return Shop::getURL() . '/' . \PFAD_DOWNLOADS_PREVIEW_REL . $this->cPfadVorschau;
    }

    public function getAnzahl(): ?int
    {
        return $this->nAnzahl;
    }

    public function getTage(): ?int
    {
        return $this->nTage;
    }

    public function getSort(): ?int
    {
        return $this->nSort;
    }

    public function getErstellt(): ?string
    {
        return $this->dErstellt;
    }

    private static function sendFileToBrowser(string $filename, string $mimetype): never
    {
        $browser   = 'other';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (\preg_match('/Opera\/(\d.\d{1,2})/', $userAgent, $log_version)) {
            $browser = 'opera';
        } elseif (\preg_match('/MSIE (\d.\d{1,2})/', $userAgent, $log_version)) {
            $browser = 'ie';
        }
        if (($mimetype === 'application/octet-stream') || ($mimetype === 'application/octetstream')) {
            $mimetype = ($browser === 'ie' || $browser === 'opera')
                ? 'application/octetstream'
                : 'application/octet-stream';
        }

        @\ob_end_clean();
        @\ini_set('zlib.output_compression', 'Off');

        \header('Pragma: public');
        \header('Content-Transfer-Encoding: none');
        if ($browser === 'ie') {
            \header('Content-Type: ' . $mimetype);
            \header('Content-Disposition: inline; filename="' . \basename($filename) . '"');
        } else {
            \header('Content-Type: ' . $mimetype . '; name="' . \basename($filename) . '"');
            \header('Content-Disposition: attachment; filename="' . \basename($filename) . '"');
        }

        $size = @\filesize($filename);
        if ($size) {
            \header('Content-length: ' . $size);
        }

        \readfile($filename);
        exit;
    }
}
