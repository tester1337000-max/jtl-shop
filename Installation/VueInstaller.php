<?php

declare(strict_types=1);

namespace JTL\Installation;

use Exception;
use JTL\DB\NiceDB;
use JTL\DB\ReturnType;
use JTL\Exceptions\InvalidEntityNameException;
use stdClass;
use Systemcheck\Environment;
use Systemcheck\Platform\Filesystem;
use Systemcheck\Platform\PDOConnection;
use Systemcheck\Tests\Shop5\PhpPdoMysqlSupport;

/**
 * Class VueInstaller
 * @package JTL\Installation
 */
class VueInstaller
{
    private ?NiceDB $db = null;

    private bool $responseStatus = true;

    /**
     * @var string[]
     */
    private array $responseMessage = [];

    /**
     * @var array <mixed>
     */
    private array $payload = [];

    /**
     * @param array<mixed>|null $post
     */
    public function __construct(
        private readonly string $task,
        private readonly ?array $post = null,
        private readonly bool $cli = false
    ) {
    }

    /**
     * @return array<mixed>|null
     */
    public function run(): ?array
    {
        match ($this->task) {
            'installedcheck'   => $this->getIsInstalled(),
            'systemcheck'      => $this->getSystemCheck(),
            'dircheck'         => $this->getDirectoryCheck(),
            'credentialscheck' => $this->getDBCredentialsCheck(),
            'doinstall'        => $this->doInstall(),
            'installdemodata'  => $this->installDemoData(),
            default            => null,
        };

        return $this->output();
    }

    /**
     * @return array<mixed>|no-return
     * @throws \JsonException
     */
    private function output()
    {
        if (!$this->cli) {
            echo \json_encode($this->payload, \JSON_THROW_ON_ERROR);
            exit(0);
        }

        return $this->payload;
    }

    /**
     * @throws \JsonException
     */
    private function sendResponse(): never
    {
        if ($this->responseStatus === true && empty($this->responseMessage)) {
            $this->responseMessage[] = 'executeSuccess';
        }
        echo \json_encode([
            'ok'      => $this->responseStatus,
            'payload' => $this->payload,
            'msg'     => \implode('<br>', $this->responseMessage)
        ], \JSON_THROW_ON_ERROR);
        exit(0);
    }

    /**
     * @param array<mixed> $credentials
     */
    private function initNiceDB(array $credentials): false|NiceDB
    {
        if (!isset($credentials['host'], $credentials['user'], $credentials['pass'], $credentials['name'])) {
            return false;
        }
        try {
            if (!empty($credentials['socket'])) {
                \define('DB_SOCKET', $credentials['socket']);
            }
            \ifndef('DB_HOST', $credentials['host']);
            \ifndef('DB_USER', $credentials['user']);
            \ifndef('DB_PASS', $credentials['pass']);
            \ifndef('DB_NAME', $credentials['name']);
            $db = new NiceDB(
                $credentials['host'],
                $credentials['user'],
                $credentials['pass'],
                $credentials['name']
            );
        } catch (Exception $e) {
            $this->responseMessage[] = $e->getMessage();
            $this->responseStatus    = false;

            return false;
        }
        $this->db = $db;

        return $db;
    }

    /**
     * @throws InvalidEntityNameException
     */
    private function doInstall(): self
    {
        $schema = \PFAD_ROOT . 'install/initial_schema.sql';
        if (!\file_exists($schema)) {
            $this->responseStatus    = false;
            $this->responseMessage[] = 'File does not exists: ' . $schema;
        } elseif (($db = $this->initNiceDB($this->post['db'] ?? [])) !== false) {
            $db->query('SET FOREIGN_KEY_CHECKS=0');
            $this->parseMysqlDump($schema);
            $this->insertUsers();
            $blowfishKey = $this->getUID(30);
            $this->writeConfigFile($this->post['db'] ?? [], $blowfishKey);
            $this->payload['secretKey'] = $blowfishKey;
            $db->query('SET FOREIGN_KEY_CHECKS=1');
        }
        if ($this->cli) {
            $this->payload['error'] = !$this->responseStatus;
            $this->payload['msg']   = $this->responseStatus === true && empty($this->responseMessage)
                ? 'executeSuccess'
                : $this->responseMessage;
        } else {
            $this->sendResponse();
        }

        return $this;
    }

    private function installDemoData(): self
    {
        $db = $this->initNiceDB($this->post['db'] ?? []);
        if ($db !== false) {
            $this->db = $db;
            $config   = [];
            if (isset($this->post['demoProducts'])) {
                $config['products'] = $this->post['demoProducts'];
            }
            if (isset($this->post['demoCategories'])) {
                $config['categories'] = $this->post['demoCategories'];
            }
            if (isset($this->post['demoManufacturers'])) {
                $config['manufacturers'] = $this->post['demoManufacturers'];
            }
            $demoData = new DemoDataInstaller($this->db, $config);
            $demoData->run();
            $this->responseStatus = true;
        }
        if ($this->cli) {
            $this->payload['error'] = !$this->responseStatus;
        } else {
            $this->sendResponse();
        }

        return $this;
    }

    /**
     * @param array<mixed> $credentials
     */
    private function writeConfigFile(array $credentials, string $blowfishKey): bool
    {
        if (!isset($credentials['host'], $credentials['user'], $credentials['pass'], $credentials['name'])) {
            return false;
        }
        $socket = '';
        if (!empty($credentials['socket'])) {
            $socket = "\ndefine('DB_SOCKET', '" . $credentials['host'] . "');";
        }
        $rootPath = \PFAD_ROOT;
        if (\str_contains(\PFAD_ROOT, '\\')) {
            $rootPath = \str_replace('\\', '\\\\', $rootPath);
        }
        $config = "<?php
define('PFAD_ROOT', '" . $rootPath . "');
define('URL_SHOP', '" . \rtrim(URL_SHOP, '/') . "');" .
            $socket . "
define('DB_HOST','" . $credentials['host'] . "');
define('DB_NAME','" . \addcslashes($credentials['name'], "'") . "');
define('DB_USER','" . \addcslashes($credentials['user'], "'") . "');
define('DB_PASS','" . \addcslashes($credentials['pass'], "'") . "');

define('BLOWFISH_KEY', '" . $blowfishKey . "');
// enables printing of warnings/infos/errors for the shop frontend
define('SHOP_LOG_LEVEL', E_ALL);
// enables printing of warnings/infos/errors for the dbeS sync
define('SYNC_LOG_LEVEL', E_ALL ^ E_NOTICE ^ E_DEPRECATED ^ E_WARNING);
// enables printing of warnings/infos/errors for the admin backend
define('ADMIN_LOG_LEVEL', E_ALL);
// enables printing of warnings/infos/errors for the smarty templates
define('SMARTY_LOG_LEVEL', E_ALL);
// excplicitly show/hide errors
ini_set('display_errors', 0);" . "\n";
        $file   = \fopen(\PFAD_ROOT . \PFAD_INCLUDES . 'config.JTL-Shop.ini.php', 'wb');
        if ($file === false) {
            return false;
        }
        \fwrite($file, $config);
        \fclose($file);

        return true;
    }

    private function parseMysqlDump(string $url): string
    {
        if ($this->db === null) {
            return 'noNiceDB';
        }
        $content = \file($url) ?: [];
        $errors  = '';
        $query   = '';
        foreach ($content as $i => $line) {
            if (!$this->isValidLine($line)) {
                continue;
            }
            $query .= $line;
            if (\preg_match('/;\s*$/', $line)) {
                $result = $this->db->query($query, ReturnType::QUERY_OK);
                if (!$result) {
                    $this->responseStatus    = false;
                    $this->responseMessage[] = $this->db->getErrorMessage() .
                        ' Nr: ' . $this->db->getErrorCode() . ' in Zeile ' . $i . '<br>' . $query . '<br>';
                }
                $query = '';
            }
        }

        return $errors;
    }

    private function isValidLine(string $line): bool
    {
        $tsl = \trim($line);

        return $tsl !== ''
            && !\str_starts_with($tsl, '/*')
            && !\str_starts_with($tsl, '--')
            && !\str_starts_with($tsl, '#');
    }

    /**
     * @throws InvalidEntityNameException
     */
    private function insertUsers(): self
    {
        if (!isset($this->post['admin']['name'], $this->post['admin']['pass'])) {
            return $this;
        }
        $adminLogin                    = new stdClass();
        $adminLogin->cLogin            = $this->post['admin']['name'];
        $adminLogin->cPass             = \password_hash($this->post['admin']['pass'], \PASSWORD_DEFAULT);
        $adminLogin->cName             = 'Admin';
        $adminLogin->cMail             = '';
        $adminLogin->kAdminlogingruppe = 1;
        $adminLogin->nLoginVersuch     = 0;
        $adminLogin->bAktiv            = 1;
        if (isset($this->post['admin']['locale']) && $this->post['admin']['locale'] === 'en') {
            $adminLogin->language = 'en-GB';
        }
        if ($this->db === null) {
            return $this;
        }
        if (!$this->db->insertRow('tadminlogin', $adminLogin)) {
            $this->responseMessage[] = 'Error code: ' . $this->db->getErrorCode();
            $this->responseStatus    = false;
        }

        $syncLogin        = new stdClass();
        $syncLogin->cMail = '';
        $syncLogin->cName = $this->post['wawi']['name'];
        $syncLogin->cPass = \password_hash($this->post['wawi']['pass'], \PASSWORD_DEFAULT);

        if (!$this->db->insertRow('tsynclogin', $syncLogin)) {
            $this->responseMessage[] = 'Error code: ' . $this->db->getErrorCode();
            $this->responseStatus    = false;
        }

        return $this;
    }

    private function getDBCredentialsCheck(): self
    {
        $res        = new stdClass();
        $res->error = false;
        $res->msg   = 'connectionSuccess';

        $pdoTest = new PhpPdoMysqlSupport();
        if ($pdoTest->execute() === false) {
            $this->payload['msg']   = \strip_tags($pdoTest->getDescription());
            $this->payload['error'] = true;

            return $this;
        }

        if (isset($this->post['host'], $this->post['user'], $this->post['pass'], $this->post['name'])) {
            if (!empty($this->post['socket'])) {
                \define('DB_SOCKET', $this->post['socket']);
            }
            try {
                $db = new NiceDB($this->post['host'], $this->post['user'], $this->post['pass'], $this->post['name']);
                if (!$db->isConnected()) {
                    $res->error = true;
                    $res->msg   = 'cannotConnect';
                }
                $obj = $db->query("SHOW TABLES LIKE 'tsynclogin'", 1);
                if ($obj !== false) {
                    $res->error = true;
                    $res->msg   = 'shopExists';
                }
                $mysqlVersion = $db->getSingleObject(
                    "SHOW VARIABLES LIKE 'innodb_version'"
                )->Value ?? '';
                if ($mysqlVersion !== '' && \version_compare($mysqlVersion, '5.7', '<')) {
                    $res->error = true;
                    $res->msg   = 'minMySQLVersion';
                }
            } catch (Exception $e) {
                $res->error = true;
                $res->msg   = $e->getMessage();
            }
        } else {
            $res->msg   = 'noCredentials';
            $res->error = true;
        }
        $this->payload['msg']   = $res->msg;
        $this->payload['error'] = $res->error;

        return $this;
    }

    private function getIsInstalled(): self
    {
        $res = false;
        if (\file_exists(\PFAD_ROOT . \PFAD_INCLUDES . 'config.JTL-Shop.ini.php')) {
            // use buffer to avoid redeclaring constants errors
            \ob_start();
            require_once \PFAD_ROOT . \PFAD_INCLUDES . 'config.JTL-Shop.ini.php';
            \ob_end_clean();

            $res = \defined('BLOWFISH_KEY');
        }
        $this->payload['shopURL']   = URL_SHOP;
        $this->payload['installed'] = $res;

        return $this;
    }

    public function getSystemCheck(): self
    {
        $environment = new Environment();
        $db          = $this->initNiceDB($this->post['db'] ?? []);
        if ($db !== false) {
            $this->db = $db;
            PDOConnection::getInstance()->setConnection($this->db->getPDO());
        }
        $this->payload['testresults'] = $environment->executeTestGroup('Shop5');

        return $this;
    }

    public function getDirectoryCheck(): self
    {
        $fsCheck                      = new Filesystem(\PFAD_ROOT);
        $this->payload['testresults'] = $fsCheck->getFoldersChecked();

        return $this;
    }

    private function getUID(int $length = 40): string
    {
        $salt     = '';
        $saltBase = 'aAbBcCdDeEfFgGhHiIjJkKlLmMnNoOpPqQrRsStTuUvVwWxXyYzZ0123456789';
        for ($j = 0; $j < 30; $j++) {
            $salt .= $saltBase[\random_int(0, \strlen($saltBase) - 1)];
        }
        $salt = \md5($salt);
        \mt_srand();
        $uid = $this->cryptPasswort(\md5(\M_PI . $salt . \md5((string)(\time() - \mt_rand()))));

        return $length > 0 ? \substr($uid, 0, $length) : $uid;
    }

    private function cryptPasswort(string $pass): string
    {
        $passLen = \strlen($pass);
        $salt    = \sha1(\uniqid((string)\random_int(\PHP_INT_MIN, \PHP_INT_MAX), true));
        $length  = \strlen($salt);
        $length  = \max($length >> 3, ($length >> 2) - $passLen);
        $salt    = \strrev(\substr($salt, 0, $length));
        $hash    = \sha1($pass);
        $hash    = \sha1(\substr($hash, 0, $passLen) . $salt . \substr($hash, $passLen));
        $hash    = \substr($hash, $length);

        return \substr($hash, 0, $passLen) . $salt . \substr($hash, $passLen);
    }
}
