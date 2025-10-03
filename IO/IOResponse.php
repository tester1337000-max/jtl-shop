<?php

declare(strict_types=1);

namespace JTL\IO;

use Exception;
use JsonSerializable;

/**
 * Class IOResponse
 * @package JTL\IO
 */
class IOResponse implements JsonSerializable
{
    /**
     * @var array<\stdClass&object{target: string, attr: string, data: mixed}>
     */
    private array $domAssigns = [];

    /**
     * @var array<\stdClass&object{name: string, value: mixed}>
     */
    private array $varAssigns = [];

    /**
     * @var string[]
     * @deprecated since 5.0.0
     */
    private array $scripts = [];

    /**
     * @var array<array{0: string|string[]|null, 1: bool|string, 2: bool|string}>
     */
    private array $debugLogLines = [];

    /**
     * @var null|string
     */
    private ?string $windowLocationHref = null;

    /**
     * @var array<array<mixed>>
     */
    private array $evoProductFunctionCalls = [];

    public function assignDom(string $target, string $attr, mixed $data): self
    {
        $this->domAssigns[] = (object)[
            'target' => $target,
            'attr'   => $attr,
            'data'   => $data
        ];

        return $this;
    }

    public function assignVar(string $name, mixed $value): self
    {
        $this->varAssigns[] = (object)[
            'name'  => $name,
            'value' => $value,
        ];

        return $this;
    }

    public function setClientRedirect(string $url): self
    {
        $this->windowLocationHref = $url;

        return $this;
    }

    public function debugLog(mixed $msg, bool $groupHead = false, bool $groupEnd = false): self
    {
        $this->debugLogLines[] = [$msg, $groupHead, $groupEnd];

        return $this;
    }

    /**
     * @deprecated since 5.0.0
     * @noinspection PhpDeprecationInspection
     */
    public function script(string $js): self
    {
        $this->scripts[] = $js;

        return $this;
    }

    /**
     * @param string $name
     * @param mixed  $args
     * @return $this
     */
    public function callEvoProductFunction(string $name, ...$args): self
    {
        $this->evoProductFunctionCalls[] = [$name, $args];

        if (\defined('IO_LOG_CONSOLE') && \IO_LOG_CONSOLE === true) {
            $reset  = 'background: transparent; color: #000;';
            $orange = 'background: #e86c00; color: #fff;';
            $grey   = 'background: #e8e8e8; color: #333;';

            $this->debugLog(['%c CALL %c ' . $name, $orange, $reset]);
            $this->debugLog(['%c PARAMS %c', $grey, $reset, $args]);
            $this->debugLog(['%c TOGGLE DEBUG TRACE %c', $grey, $reset], true);

            foreach ($this->generateCallTrace() as $trace) {
                $this->debugLog(['%c TOGGLE DEBUG TRACE %c', $grey, $reset, $trace]);
            }

            $this->debugLog(null, false, true);
            $this->debugLog(null, false, true);
        }

        return $this;
    }

    /**
     * @return string[]
     */
    public function generateCallTrace(): array
    {
        $str   = (new Exception())->getTraceAsString();
        $trace = \explode("\n", $str);
        $trace = \array_reverse($trace);
        \array_shift($trace);
        \array_pop($trace);
        $result = [];
        /** @var string $t */
        foreach ($trace as $i => $t) {
            $result[] = '#' . ($i + 1) . \mb_substr($t, \mb_strpos($t, ' ') ?: 0);
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'js'                 => $this->scripts,
            'domAssigns'         => $this->domAssigns,
            'varAssigns'         => $this->varAssigns,
            'windowLocationHref' => $this->windowLocationHref,
            'debugLogLines'      => $this->debugLogLines,
            'evoProductCalls'    => $this->evoProductFunctionCalls,
        ];
    }
}
