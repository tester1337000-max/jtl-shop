<?php

declare(strict_types=1);

namespace JTL\Extensions\Upload;

use JTL\Cart\Cart;
use JTL\Helpers\PHPSettings;
use JTL\Nice;
use JTL\Services\JTL\LinkService;
use JTL\Shop;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\Response\RedirectResponse;
use stdClass;

/**
 * Class Upload
 * @package JTL\Extensions\Upload
 */
final class Upload
{
    public const UPLOAD_NOTNECESSARY = 0;
    public const UPLOAD_MANDATORY    = 1;
    public const UPLOAD_RECOMMENDED  = 2;

    public static function checkLicense(): bool
    {
        static $license;
        if ($license === null) {
            $license = Nice::getInstance()->checkErweiterung(\SHOP_ERWEITERUNG_UPLOADS);
        }

        return $license;
    }

    /**
     * @param int                     $productID
     * @param bool|array<int, string> $attributes
     * @return stdClass[]
     */
    public static function gibArtikelUploads(int $productID, bool|array $attributes = false): array
    {
        if (!self::checkLicense()) {
            return [];
        }
        $scheme  = new Scheme();
        $uploads = $scheme->fetchAll($productID, \UPLOAD_TYP_WARENKORBPOS);
        foreach ($uploads as $upload) {
            $upload->nEigenschaften_arr = $attributes;
            $upload->cUnique            = self::uniqueDateiname($upload);
            $upload->cDateiTyp_arr      = self::formatTypen($upload->cDateiTyp);
            $upload->cDateiListe        = \implode(';', $upload->cDateiTyp_arr);
            $upload->bVorhanden         = \is_file(\PFAD_UPLOADS . $upload->cUnique);
            $upload->prodID             = $productID;
            /** @var stdClass $file */
            $file = $_SESSION['Uploader'][$upload->cUnique] ?? null;
            if (\is_object($file)) {
                $upload->cDateiname    = $file->cName;
                $upload->cDateigroesse = self::formatGroesse($file->nBytes);
            }
        }

        return $uploads;
    }

    public static function deleteArtikelUploads(int $productID): int
    {
        if (!self::checkLicense()) {
            return 0;
        }

        $count = 0;
        foreach (self::gibArtikelUploads($productID) as $upload) {
            unset($_SESSION['Uploader'][$upload->cUnique]);
            if ($upload->bVorhanden && \unlink(\PFAD_UPLOADS . $upload->cUnique)) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * @param Cart $cart
     * @return stdClass[]
     */
    public static function gibWarenkorbUploads(Cart $cart): array
    {
        if (!self::checkLicense()) {
            return [];
        }

        $uploads = [];
        foreach ($cart->PositionenArr as $item) {
            if (
                $item->nPosTyp !== \C_WARENKORBPOS_TYP_ARTIKEL
                || empty($item->Artikel->kArtikel)
                || $item->Artikel->hasUploads === false
            ) {
                continue;
            }
            $attributes = [];
            if (!empty($item->WarenkorbPosEigenschaftArr)) {
                foreach ($item->WarenkorbPosEigenschaftArr as $attribute) {
                    $attributes[(int)$attribute->kEigenschaft] = \is_array($attribute->cEigenschaftWertName)
                        ? \reset($attribute->cEigenschaftWertName)
                        : (string)$attribute->cEigenschaftWertName;
                }
            }
            $upload         = new stdClass();
            $upload->cName  = $item->Artikel->cName;
            $upload->prodID = $item->Artikel->kArtikel;
            if (!empty($item->WarenkorbPosEigenschaftArr)) {
                $upload->WarenkorbPosEigenschaftArr = $item->WarenkorbPosEigenschaftArr;
            }
            $upload->oUpload_arr = self::gibArtikelUploads($item->Artikel->kArtikel, $attributes);
            if (\count($upload->oUpload_arr) > 0) {
                $uploads[] = $upload;
            }
        }

        return $uploads;
    }

    /**
     * @return stdClass[]
     */
    public static function gibBestellungUploads(int $orderID): array
    {
        return self::checkLicense() ? File::fetchAll($orderID, \UPLOAD_TYP_BESTELLUNG) : [];
    }

    /**
     * @deprecated since 5.3.0
     */
    public static function pruefeWarenkorbUploads(Cart $cart): bool
    {
        return self::getUploadState($cart) !== self::UPLOAD_MANDATORY;
    }

    public static function clearUploadCheckNeeded(): void
    {
        $_SESSION['Uploader']['uploadChecked'] = 1;
    }

    public static function setUploadCheckNeeded(): void
    {
        unset($_SESSION['Uploader']['uploadChecked']);
    }

    public static function isUploadCheckNeeded(): bool
    {
        return ($_SESSION['Uploader']['uploadChecked'] ?? 0) === 0;
    }

    public static function getUploadState(Cart $cart): int
    {
        if (!self::checkLicense()) {
            return self::UPLOAD_NOTNECESSARY;
        }
        $recommended = 0;
        foreach (self::gibWarenkorbUploads($cart) as $scheme) {
            foreach ($scheme->oUpload_arr as $upload) {
                if ($upload->nPflicht && !$upload->bVorhanden) {
                    return self::UPLOAD_MANDATORY;
                }

                if (!$upload->bVorhanden && self::isUploadCheckNeeded()) {
                    $recommended++;
                }
            }
        }

        return $recommended === 0 ? self::UPLOAD_NOTNECESSARY : self::UPLOAD_RECOMMENDED;
    }

    public static function redirectWarenkorb(int $errorCode): Response
    {
        return new RedirectResponse(
            LinkService::getInstance()->getStaticRoute('warenkorb.php') . '?fillOut=' . $errorCode,
            303
        );
    }

    public static function speicherUploadDateien(Cart $cart, int $orderID): void
    {
        if (self::checkLicense()) {
            foreach (self::gibWarenkorbUploads($cart) as $scheme) {
                /** @var stdClass $upload */
                foreach ($scheme->oUpload_arr as $upload) {
                    /** @var stdClass $info */
                    $info = $_SESSION['Uploader'][$upload->cUnique] ?? null;
                    if (\is_object($info)) {
                        self::setzeUploadQueue($orderID, $upload->kCustomID);
                        self::setzeUploadDatei(
                            $orderID,
                            \UPLOAD_TYP_BESTELLUNG,
                            $info->cName,
                            $upload->cUnique,
                            $info->nBytes
                        );
                    }
                    unset($_SESSION['Uploader'][$upload->cUnique]);
                }
            }
        }
        \session_regenerate_id();
        unset($_SESSION['Uploader']);
    }

    public static function setzeUploadDatei(int $customID, int $type, string $name, string $path, int $bytes): void
    {
        if (!self::checkLicense()) {
            return;
        }

        $file            = new stdClass();
        $file->kCustomID = $customID;
        $file->nTyp      = $type;
        $file->cName     = $name;
        $file->cPfad     = $path;
        $file->nBytes    = $bytes;
        $file->dErstellt = 'NOW()';

        Shop::Container()->getDB()->insert('tuploaddatei', $file);
    }

    public static function setzeUploadQueue(int $orderID, int $productID): void
    {
        if (!self::checkLicense()) {
            return;
        }

        $queue              = new stdClass();
        $queue->kBestellung = $orderID;
        $queue->kArtikel    = $productID;

        Shop::Container()->getDB()->insert('tuploadqueue', $queue);
    }

    public static function uploadMax(): int
    {
        $helper = PHPSettings::getInstance();

        return \min(
            $helper->uploadMaxFileSize(),
            $helper->postMaxSize(),
            $helper->limit()
        );
    }

    public static function formatGroesse(int|string $fileSize): string
    {
        if (!\is_numeric($fileSize)) {
            return '---';
        }
        $input    = (float)$fileSize;
        $step     = 0;
        $decr     = 1024;
        $prefixes = ['Byte', 'KB', 'MB', 'GB', 'TB', 'PB'];
        while (($input / $decr) > 0.9) {
            $input /= $decr;
            ++$step;
        }

        return \round($input, 2) . ' ' . ($prefixes[$step] ?? '');
    }

    public static function uniqueDateiname(stdClass $upload): string
    {
        $unique = $upload->kUploadSchema . $upload->kCustomID . $upload->nTyp . self::getSessionKey();
        if (!empty($upload->nEigenschaften_arr)) {
            foreach ($upload->nEigenschaften_arr as $k => $v) {
                $unique .= $k . $v;
            }
        }

        return \md5($unique);
    }

    private static function getSessionKey(): string
    {
        if (!isset($_SESSION['Uploader']['sessionKey'])) {
            $_SESSION['Uploader']['sessionKey'] = \uniqid('sk', true);
        }

        return $_SESSION['Uploader']['sessionKey'];
    }

    /**
     * @return string[]
     */
    public static function formatTypen(string $type): array
    {
        $fileTypes = \explode(',', $type);
        foreach ($fileTypes as &$fileType) {
            $fileType = '*' . $fileType;
        }

        return $fileTypes;
    }

    public static function vorschauTyp(string $name): bool
    {
        return \in_array(
            \pathinfo($name, \PATHINFO_EXTENSION),
            ['gif', 'png', 'jpg', 'jpeg', 'bmp', 'jpe'],
            true
        );
    }
}
