<?php

declare(strict_types=1);

namespace JTL;

use ErrorException;
use Exception;
use JTL\Exceptions\FatalErrorException;
use JTL\Exceptions\FatalThrowableError;

/**
 * Class HandleExceptions
 * @package JTL
 * @deprecated since 5.3.0
 */
class HandleExceptions
{
    public function __construct()
    {
        \trigger_error(__CLASS__ . ' is deprecated and should not be used anymore.', \E_USER_DEPRECATED);
        \error_reporting(-1);
        \set_error_handler($this->handleError(...));
        \set_exception_handler($this->handleException(...));
        \register_shutdown_function($this->handleShutdown(...));
    }

    /**
     * Convert PHP errors to ErrorException instances.
     * @throws ErrorException
     * @param array<mixed> $context
     */
    public function handleError(
        int $level,
        string $message,
        string $file = '',
        int $line = 0,
        array $context = []
    ): void {
        if (\error_reporting() & $level) {
            throw new ErrorException($message, 0, $level, $file, $line);
        }
    }

    /**
     * Handle an uncaught exception from the application.
     *
     * Note: Most exceptions can be handled via the try / catch block in
     * the HTTP and Console kernels. But, fatal error exceptions must
     * be handled differently since they are not normal exceptions.
     *
     * @param \Throwable|FatalThrowableError $e
     */
    public function handleException(mixed $e): void
    {
        if (!$e instanceof Exception) {
            $e = new FatalThrowableError($e);
        }

        // report / log
        \dump($e);
    }

    /**
     * Handle the PHP shutdown event.
     */
    public function handleShutdown(): void
    {
        if (($error = \error_get_last()) !== null && $this->isFatal($error['type'])) {
            $this->handleException($this->fatalExceptionFromError($error, 0));
        }
    }

    /**
     * Create a new fatal exception instance from an error array.
     * @param array<string, mixed> $error
     */
    protected function fatalExceptionFromError(array $error, ?int $traceOffset = null): FatalErrorException
    {
        return new FatalErrorException(
            $error['message'],
            $error['type'],
            0,
            $error['file'],
            $error['line'],
            $traceOffset
        );
    }

    protected function isFatal(mixed $type): bool
    {
        return \in_array($type, [\E_COMPILE_ERROR, \E_CORE_ERROR, \E_ERROR, \E_PARSE], true);
    }
}
