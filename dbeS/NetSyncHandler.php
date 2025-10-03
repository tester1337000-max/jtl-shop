<?php

declare(strict_types=1);

namespace JTL\dbeS;

use Exception;
use JTL\DB\DbInterface;
use JTL\Helpers\Text;
use Psr\Log\LoggerInterface;
use stdClass;
use Throwable;

/**
 * Class NetSyncHandler
 * @package JTL\dbeS
 */
class NetSyncHandler
{
    protected static ?NetSyncHandler $instance = null;

    /**
     * @throws Exception
     */
    public function __construct(protected DbInterface $db, protected LoggerInterface $logger)
    {
        self::$instance = $this;
        if (!$this->isAuthenticated()) {
            static::throwResponse(NetSyncResponse::ERRORLOGIN);
        }
        $this->request((int)$_REQUEST['e']);
    }

    protected function isAuthenticated(): bool
    {
        // by token
        if (isset($_REQUEST['t'])) {
            \session_id($_REQUEST['t']);
            \session_start();

            return $_SESSION['bAuthed'];
        }
        // by syncdata
        $name     = $_REQUEST['uid'];
        $pass     = $_REQUEST['upwd'];
        $nameUtf8 = Text::convertUTF8($name);
        $passUtf8 = Text::convertUTF8($pass);
        if (
            \strlen($nameUtf8) > 0
            && \strlen($passUtf8) > 0
            && (new Synclogin($this->db, $this->logger))->checkLogin($nameUtf8, $passUtf8)
        ) {
            \session_start();
            $_SESSION['bAuthed'] = true;
            return true;
        }
        $nameDecoded = Text::convertUTF8(\urldecode($name));
        $passDecoded = Text::convertUTF8(\urldecode($pass));
        if (
            \strlen($nameDecoded) > 0
            && \strlen($passDecoded) > 0
            && (new Synclogin($this->db, $this->logger))->checkLogin($nameDecoded, $passDecoded)
        ) {
            \session_start();
            $_SESSION['bAuthed'] = true;
            return true;
        }

        return false;
    }

    protected static function throwResponse(int $code, mixed $data = null): never
    {
        $response         = new stdClass();
        $response->nCode  = $code;
        $response->cToken = '';
        $response->oData  = null;
        if ($code === 0) {
            $response->cToken = \session_id();
            $response->oData  = $data;
        }
        echo \json_encode($response, \JSON_THROW_ON_ERROR);
        exit;
    }

    /**
     * @param int $request
     */
    protected function request(int $request): void
    {
    }

    public static function exception(Throwable $exception): void
    {
        // will be used in self::create as exception handler
    }

    /**
     * @param class-string<NetSyncHandler> $class
     * @param DbInterface                  $db
     * @param LoggerInterface              $logger
     */
    public static function create(string $class, DbInterface $db, LoggerInterface $logger): void
    {
        if (self::$instance === null && \class_exists($class)) {
            $instance = new $class($db, $logger);
            \set_exception_handler($instance->exception(...));
        }
    }

    public function streamFile(string $filename, string $mimetype, string $outname = ''): never
    {
        $browser = $this->getBrowser($_SERVER['HTTP_USER_AGENT'] ?? '');
        if (($mimetype === 'application/octet-stream') || ($mimetype === 'application/octetstream')) {
            $mimetype = 'application/octet-stream';
            if (($browser === 'ie') || ($browser === 'opera')) {
                $mimetype = 'application/octetstream';
            }
        }

        @\ob_end_clean();
        @\ini_set('zlib.output_compression', 'Off');

        \header('Pragma: public');
        \header('Content-Transfer-Encoding: none');

        if ($outname === '') {
            $outname = \basename($filename);
        }
        if ($browser === 'ie') {
            \header('Content-Type: ' . $mimetype);
            \header('Content-Disposition: inline; filename="' . $outname . '"');
        } else {
            \header('Content-Type: ' . $mimetype . '; name="' . $outname . '"');
            \header('Content-Disposition: attachment; filename=' . $outname);
        }
        $size = @\filesize($filename);
        if ($size) {
            \header('Content-length: ' . $size);
        }
        \readfile($filename);
        \unlink($filename);
        exit;
    }

    private function getBrowser(string $userAgent): string
    {
        $browser = 'other';
        if (\preg_match('/^Opera(\/| )(\d.\d{1,2})/', $userAgent) === 1) {
            $browser = 'opera';
        } elseif (\preg_match('/^MSIE (\d.\d{1,2})/', $userAgent) === 1) {
            $browser = 'ie';
        }

        return $browser;
    }

    /**
     * @return SystemFolder[]
     */
    protected function getFolderStruct(string $baseDir): array
    {
        $folders = [];
        $baseDir = \realpath($baseDir);
        if ($baseDir === false) {
            return $folders;
        }
        foreach (\scandir($baseDir, \SCANDIR_SORT_ASCENDING) ?: [] as $folder) {
            if ($folder === '.' || $folder === '..' || $folder[0] === '.') {
                continue;
            }
            $pathName = $baseDir . \DIRECTORY_SEPARATOR . $folder;
            if (\is_dir($pathName)) {
                $systemFolder              = new SystemFolder($folder, $pathName);
                $systemFolder->oSubFolders = $this->getFolderStruct($pathName);
                $folders[]                 = $systemFolder;
            }
        }

        return $folders;
    }

    /**
     * @return SystemFile[]
     */
    protected function getFilesStruct(string $baseDir): array
    {
        $index   = 0;
        $files   = [];
        $baseDir = \realpath($baseDir);
        if ($baseDir === false) {
            return $files;
        }
        foreach (\scandir($baseDir, \SCANDIR_SORT_ASCENDING) ?: [] as $file) {
            if ($file === '.' || $file === '..' || $file[0] === '.') {
                continue;
            }
            $pathName = $baseDir . \DIRECTORY_SEPARATOR . $file;
            if (!\is_file($pathName)) {
                continue;
            }
            $pathinfo = \pathinfo($pathName);
            $files[]  = new SystemFile(
                $index++,
                $pathName,
                \str_replace([\PFAD_DOWNLOADS_PREVIEW, \PFAD_DOWNLOADS], '', $pathName),
                $pathinfo['filename'],
                $pathinfo['dirname'],
                $pathinfo['extension'] ?? '',
                \filemtime($pathName) ?: 0,
                \filesize($pathName) ?: 0
            );
        }

        return $files;
    }
}
