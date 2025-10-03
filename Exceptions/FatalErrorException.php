<?php

declare(strict_types=1);

namespace JTL\Exceptions;

/**
 * Class FatalErrorException
 * @package JTL\Exceptions
 * @author Konstanton Myakshin <koc-dp@yandex.ru>
 */
class FatalErrorException extends \ErrorException
{
    /**
     * @param string            $message
     * @param int               $code
     * @param int               $severity
     * @param string            $filename
     * @param int               $lineno
     * @param int|null          $traceOffset
     * @param bool              $traceArgs
     * @param array<mixed>|null $trace
     * @param \Throwable|null   $previous
     */
    public function __construct(
        string $message,
        int $code,
        int $severity,
        string $filename,
        int $lineno,
        ?int $traceOffset = null,
        bool $traceArgs = true,
        ?array $trace = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $severity, $filename, $lineno, $previous);

        if ($trace !== null) {
            if (!$traceArgs) {
                /** @var array<string, mixed> $frame */
                foreach ($trace as &$frame) {
                    unset($frame['args'], $frame['this']);
                }
                unset($frame);
            }

            $this->setTrace($trace);
        } elseif ($traceOffset !== null) {
            if (\function_exists('xdebug_get_function_stack')) {
                $trace = \xdebug_get_function_stack();
                if (0 < $traceOffset) {
                    \array_splice($trace, -$traceOffset);
                }
                /** @var array<string, mixed> $frame */
                foreach ($trace as &$frame) {
                    if (!isset($frame['type'])) {
                        // XDebug pre 2.1.1 doesn't currently set the call type key
                        // @see http://bugs.xdebug.org/view.php?id=695
                        if (isset($frame['class'])) {
                            $frame['type'] = '::';
                        }
                    } elseif ($frame['type'] === 'dynamic') {
                        $frame['type'] = '->';
                    } elseif ($frame['type'] === 'static') {
                        $frame['type'] = '::';
                    }

                    // XDebug also has a different name for the parameters array
                    if (!$traceArgs) {
                        unset($frame['params'], $frame['args']);
                    } elseif (isset($frame['params']) && !isset($frame['args'])) {
                        $frame['args'] = $frame['params'];
                        unset($frame['params']);
                    }
                }

                unset($frame);
                $trace = \array_reverse($trace);
            } else {
                $trace = [];
            }

            $this->setTrace($trace);
        }
    }

    /**
     * @param array<mixed> $trace
     */
    protected function setTrace(array $trace): void
    {
        $traceReflector = new \ReflectionProperty('Exception', 'trace');
        $traceReflector->setAccessible(true);
        $traceReflector->setValue($this, $trace);
    }
}
