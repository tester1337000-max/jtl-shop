<?php

declare(strict_types=1);

namespace JTL\IO;

use Exception;
use JsonException;
use JTL\Helpers\Request;
use JTL\Helpers\Text;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use ReflectionFunction;
use ReflectionMethod;

/**
 * Class IO
 * @package JTL\IO
 */
class IO
{
    /**
     * @var static|null
     */
    protected static ?IO $instance = null;

    /**
     * @var array<string, array<mixed>>
     */
    protected array $functions = [];

    final private function __construct()
    {
    }

    private function __clone()
    {
    }

    /**
     * @return static
     */
    public static function getInstance(): IO
    {
        return static::$instance ?? (static::$instance = new static());
    }

    /**
     * Registers a PHP function or method.
     * This makes the function available for XMLHTTPRequest requests.
     *
     * @param string                 $name - name under which this function is callable
     * @param callable|\Closure|null $function - target function name, method-tuple or closure
     * @param string|null            $include - file where this function is defined in
     * @return $this
     * @throws Exception
     */
    public function register(string $name, array|callable|null $function = null, ?string $include = null): self
    {
        if ($this->exists($name)) {
            throw new Exception('Function already registered');
        }
        if ($function === null) {
            $function = $name;
        }
        $this->functions[$name] = [$function, $include];

        return $this;
    }

    /**
     * @param string $reqString
     * @return IOError|mixed
     * @throws Exception
     */
    public function handleRequest(string $reqString): mixed
    {
        try {
            /** @var array{name?: string, params?: array<mixed>} $request */
            $request = \json_decode($reqString, true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            return new IOError('Error while decoding data: ' . $e->getMessage());
        }

        if (!isset($request['name'], $request['params'])) {
            return new IOError('Missing request property');
        }

        \ob_start();
        \set_time_limit(0);

        $result = $this->execute($request['name'], $request['params']);

        if (\ob_get_level() > 0) {
            \ob_end_clean();
        }

        return $result;
    }

    /**
     * @throws Exception
     */
    public function respondAndExit(mixed $data): never
    {
        // respond with an error?
        if (\is_object($data)) {
            if ($data instanceof IOError) {
                $code = empty($data->code) ? 500 : $data->code;
                \header(Request::makeHTTPHeader($code), true, $code);
            } elseif ($data instanceof IOFile) {
                $this->pushFile($data->filename, $data->mimetype);
            }
        }

        \header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        \header('Last-Modified: ' . \gmdate('D, d M Y H:i:s') . ' GMT');
        \header('Cache-Control: no-cache, must-revalidate');
        \header('Pragma: no-cache');
        \header('Content-type: application/json');

        die(Text::json_safe_encode($data));
    }

    /**
     * @throws Exception
     */
    public function getResponse(mixed $data): ResponseInterface
    {
        $code = 200;
        if (\is_object($data)) {
            if ($data instanceof IOError) {
                $code = empty($data->code) ? 500 : $data->code;
            } elseif ($data instanceof IOFile) {
                $this->pushFile($data->filename, $data->mimetype);
            }
        }
        $response = (new Response())->withStatus($code)
            ->withAddedHeader('Expires', 'Mon, 26 Jul 1997 05:00:00 GMT')
            ->withAddedHeader('Last-Modified', \gmdate('D, d M Y H:i:s') . ' GMT')
            ->withAddedHeader('Cache-Control', 'no-cache, must-revalidate')
            ->withAddedHeader('Pragma', 'no-cache')
            ->withAddedHeader('Content-type', 'application/json');
        $response->getBody()->write(Text::json_safe_encode($data));

        return $response;
    }

    /**
     * Check if function exists
     */
    public function exists(string $name): bool
    {
        return isset($this->functions[$name]);
    }

    /**
     * Executes a registered function
     * @throws Exception
     */
    public function execute(string $name, mixed $params): mixed
    {
        if (!$this->exists($name)) {
            return new IOError('Function not registered');
        }

        $function = $this->functions[$name][0];
        $include  = $this->functions[$name][1];

        if ($include !== null) {
            require_once $include;
        }

        if (\is_array($function)) {
            $ref = new ReflectionMethod($function[0], $function[1]);
        } else {
            $ref = new ReflectionFunction($function);
        }

        if ($ref->getNumberOfRequiredParameters() > \count($params)) {
            return new IOError('Wrong required parameter count');
        }

        try {
            return \call_user_func_array($function, $params);
        } catch (Exception $e) {
            return new IOError($e->getMessage(), $e->getCode());
        }
    }

    protected function pushFile(string $filename, string $mimetype): never
    {
        $userAgent    = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $browserAgent = '';
        if (\preg_match('/Opera\/(\d.\d{1,2})/', $userAgent, $m)) {
            $browserAgent = 'opera';
        } elseif (\preg_match('/MSIE (\d.\d{1,2})/', $userAgent, $m)) {
            $browserAgent = 'ie';
        }

        if (($mimetype === 'application/octet-stream') || ($mimetype === 'application/octetstream')) {
            $mimetype = ($browserAgent === 'ie' || $browserAgent === 'opera')
                ? 'application/octetstream'
                : 'application/octet-stream';
        }

        @\ob_end_clean();
        @\ini_set('zlib.output_compression', 'Off');

        \header('Pragma: public');
        \header('Content-Transfer-Encoding: none');

        if ($browserAgent === 'ie') {
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
