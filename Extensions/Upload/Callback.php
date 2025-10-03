<?php

declare(strict_types=1);

namespace JTL\Extensions\Upload;

use JTL\Catalog\Product\Artikel;
use JTL\Helpers\Form;
use JTL\Helpers\Seo;
use JTL\Nice;
use JTL\Session\Frontend;
use JTL\Shop;
use stdClass;

class Callback
{
    /**
     * @throws \JsonException
     */
    private function retCode(bool $ok, int $responseCode = 200, string $message = 'error'): never
    {
        \http_response_code($responseCode);
        die(\json_encode(['status' => $ok ? 'ok' : $message], \JSON_THROW_ON_ERROR));
    }

    public function getResponse(): void
    {
        Frontend::getInstance();
        if (!Form::validateToken() || !Nice::getInstance()->checkErweiterung(\SHOP_ERWEITERUNG_UPLOADS)) {
            $this->retCode(false, 403);
        }
        if (Form::reachedUploadLimitPerHour((int)Shop::getSettingValue(\CONF_ARTIKELDETAILS, 'upload_modul_limit'))) {
            $this->retCode(false, 403, 'reached_limit_per_hour');
        }
        if (!empty($_FILES)) {
            $this->handleFiles();
        }
        if (!empty($_REQUEST['action'])) {
            $this->handleAction();
        }
        $this->retCode(false, 400);
    }

    private function handleAction(): void
    {
        switch ($_REQUEST['action']) {
            case 'remove':
                $this->remove();
                break;
            case 'exists':
                $this->exists();
                break;
            case 'preview':
                $this->getPreview();
            // no break since getPreview is return: never-return
            default:
                break;
        }
    }

    private function remove(): void
    {
        $unique     = $_REQUEST['uniquename'];
        $filePath   = \PFAD_UPLOADS . $unique;
        $targetInfo = \pathinfo($filePath);
        $realPath   = \str_replace('\\', '/', (\realpath($targetInfo['dirname'] . \DIRECTORY_SEPARATOR) ?: ''));
        if (
            !isset($targetInfo['extension'])
            && isset($_SESSION['Uploader'][$unique])
            && \str_starts_with($realPath, \realpath(\PFAD_UPLOADS) ?: 'invalid')
        ) {
            unset($_SESSION['Uploader'][$unique]);
            if (\file_exists($filePath)) {
                $this->retCode(@\unlink($filePath));
            }
        } else {
            $this->retCode(false);
        }
    }

    private function exists(): void
    {
        $filePath = \PFAD_UPLOADS . $_REQUEST['uniquename'];
        $info     = \pathinfo($filePath);
        $realPath = \realpath($info['dirname']);
        if (
            $realPath !== false
            && !\str_starts_with(
                $realPath . \DIRECTORY_SEPARATOR,
                \realpath(\PFAD_UPLOADS) ?: 'invalid'
            )
        ) {
            $this->retCode(false, 403, 'forbidden');
        }
        $this->retCode(!isset($info['extension']) && \file_exists(\realpath($filePath) ?: ''));
    }

    private function getPreview(): never
    {
        $uploadFile = new File();
        $customerID = Frontend::getCustomer()->getID();
        $filePath   = \PFAD_ROOT . \BILD_UPLOAD_ZUGRIFF_VERWEIGERT;
        $uploadID   = (int)Shop::Container()->getCryptoService()->decryptXTEA(
            \rawurldecode($_REQUEST['secret'])
        );
        if ($uploadID > 0 && $customerID > 0 && $uploadFile->loadFromDB($uploadID)) {
            $tmpFilePath = \PFAD_UPLOADS . $uploadFile->cPfad;
            if (\file_exists($tmpFilePath)) {
                $filePath = $tmpFilePath;
            }
        }
        \header('Cache-Control: max-age=3600, public');
        \header('Content-type: Image');
        \readfile($filePath);
        exit;
    }

    private function handleFiles(): void
    {
        $blacklist         = [
            'application/x-httpd-php-source',
            'application/x-httpd-php',
            'application/x-php',
            'application/php',
            'text/x-php',
            'text/php',
            'application/x-sh',
            'application/x-csh',
            'application/x-httpd-cgi',
            'application/x-httpd-perl',
            'application/octet-stream',
            'application/sql',
            'text/x-sql',
            'text/sql',
        ];
        $fileData          = isset($_FILES['Filedata']['tmp_name'])
            ? $_FILES['Filedata']
            : $_FILES['file_data'];
        $extension         = \pathinfo($fileData['name'], PATHINFO_EXTENSION);
        $mime              = \mime_content_type($fileData['tmp_name']);
        $allowedExtensions = [];
        $productID         = (int)$_REQUEST['prodID'];
        foreach (Upload::gibArtikelUploads($productID) as $scheme) {
            if ((int)$scheme->kUploadSchema === (int)$_REQUEST['kUploadSchema']) {
                $allowedExtensions = $scheme->cDateiTyp_arr;
            }
        }
        if (!isset($_REQUEST['uniquename'], $_REQUEST['cname'])) {
            $this->retCode(false);
        }
        if (empty($allowedExtensions) || !\in_array('*.' . \strtolower($extension), $allowedExtensions, true)) {
            $this->retCode(false, 400, 'extension_not_listed');
        }
        if (\in_array($mime, $blacklist, true)) {
            $this->retCode(false, 403, 'filetype_forbidden');
        }
        $this->handleUpload($fileData, $extension);
    }

    /**
     * @param array{'error'?: string, 'name'?: string, 'tmp_name': string, 'size': int} $fileData
     * @throws \JsonException
     */
    private function handleUpload(array $fileData, string $extension): void
    {
        $unique     = $_REQUEST['uniquename'];
        $targetFile = \PFAD_UPLOADS . $unique;
        $targetInfo = \pathinfo($targetFile);
        $productID  = (int)$_REQUEST['prodID'];
        // legitimate uploads do not have an extension for the destination
        // file name - but for the originally uploaded file
        if ($extension === '' || isset($targetInfo['extension'])) {
            $this->retCode(false);
        }
        $product = (new Artikel())->fuelleArtikel($productID);
        if ($product === null) {
            $this->retCode(false);
        }
        if (!$this->validateUpload($fileData, $targetInfo, $targetFile)) {
            $this->retCode(false);
        }
        $productName = $_REQUEST['cname'] ?? $product->cName;
        $preName     = $productID
            . '_' . $product->cArtNr
            . '_' . Seo::sanitizeSeoSlug(Seo::getFlatSeoPath($productName));
        if (empty($_REQUEST['variation'])) {
            $postName = '_' . $unique . '.' . $extension;
        } else {
            $postName = '_' . Seo::sanitizeSeoSlug(Seo::getFlatSeoPath($_REQUEST['variation']))
                . '_' . $unique . '.' . $extension;
        }
        $file         = new stdClass();
        $file->cName  = \mb_substr($preName, 0, 200 - \mb_strlen($postName)) . $postName;
        $file->nBytes = $fileData['size'];
        $file->cKB    = \round($fileData['size'] / 1024, 2);

        if (!isset($_SESSION['Uploader'])) {
            $_SESSION['Uploader'] = [];
        }
        $_SESSION['Uploader'][$unique] = $file;
        if (isset($_REQUEST['uploader'])) {
            die(\json_encode($file, \JSON_THROW_ON_ERROR));
        }
        $this->retCode(true);
    }

    /**
     * @param array{'error'?: string, 'name'?: string, 'tmp_name': string}     $fileData
     * @param array{'dirname': string, 'basename': string, 'filename': string} $targetInfo
     */
    private function validateUpload(array $fileData, array $targetInfo, string $targetFile): bool
    {
        $realPath = \str_replace('\\', '/', (\realpath($targetInfo['dirname']) ?: '') . \DIRECTORY_SEPARATOR);

        return isset($fileData['error'], $fileData['name'])
            && (int)$fileData['error'] === \UPLOAD_ERR_OK
            && \str_starts_with($realPath, \realpath(\PFAD_UPLOADS) ?: 'invalid')
            && \move_uploaded_file($fileData['tmp_name'], $targetFile);
    }
}
