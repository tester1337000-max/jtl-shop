<?php

declare(strict_types=1);

namespace JTL;

/**
 * Class LessParser
 * @package JTL
 */
class LessParser
{
    /**
     * @var array<mixed>
     */
    private array $stack = [];

    public function read(string $file): self
    {
        $lines = \file($file, \FILE_SKIP_EMPTY_LINES);
        foreach ($lines ?: [] as $line) {
            if (\preg_match('/@([\w\-]+)\s*:\s*([^;]+)/', $line, $matches)) {
                [, $key, $value] = $matches;

                $this->stack[$key] = $value;
            }
        }

        return $this;
    }

    public function write(string $file): false|int
    {
        $content = '';
        foreach ($this->stack as $key => $value) {
            $content .= '@' . $key . ': ' . $value . ";\r\n";
        }

        return \file_put_contents($file, $content);
    }

    /**
     * @return array<mixed>
     */
    public function getStack(): array
    {
        return $this->stack;
    }

    /**
     * @return array<string, string>
     */
    public function getColors(): array
    {
        $colors = [];
        foreach ($this->stack as $key => $value) {
            $color = $this->getAs($value, 'color');
            if ($color) {
                $colors[$key] = $color;
            }
        }

        return $colors;
    }

    public function set(string $key, mixed $value): self
    {
        $this->stack[$key] = $value;

        return $this;
    }

    public function get(string $key, ?string $type = null): mixed
    {
        $value = $this->stack[$key] ?? null;
        if ($value !== null && !$type !== null) {
            $typedValue = $this->getAs($value, $type);
            if ($typedValue !== false) {
                return $typedValue;
            }
        }

        return $value;
    }

    /**
     * @return bool|string|float
     */
    protected function getAs(string $value, string $type): mixed
    {
        $matches = [];

        switch (\mb_convert_case($type, \MB_CASE_LOWER)) {
            case 'color':
                // rgb(255,255,255)
                if (\preg_match('/rgb(\s*)\(([\d\s]+),([\d\s]+),([\d\s]+)\)/', $value, $matches)) {
                    return $this->rgb2html((int)$matches[2], (int)$matches[3], (int)$matches[4]);
                } // #fff or #ffffff
                if (\preg_match('/#([\w]+)/', $value, $matches)) {
                    return \trim($matches[0]);
                }
                break;

            case 'size':
                // 1.2em 15% '12 px'
                if (\preg_match('/([\d\.]+)(.*)/', $value, $matches)) {
                    $pair = [
                        'numeric' => (float)$matches[1],
                        'unit'    => \trim($matches[2])
                    ];

                    return $pair['numeric'];
                }
                break;

            default:
                break;
        }

        return false;
    }

    protected function rgb2html(int $r, int $g, int $b): string
    {
        $red   = \dechex($r < 0 ? 0 : (\min($r, 255)));
        $green = \dechex($g < 0 ? 0 : (\min($g, 255)));
        $blue  = \dechex($b < 0 ? 0 : (\min($b, 255)));

        $color = (\mb_strlen($red) < 2 ? '0' : '') . $red;
        $color .= (\mb_strlen($green) < 2 ? '0' : '') . $green;
        $color .= (\mb_strlen($blue) < 2 ? '0' : '') . $blue;

        return '#' . $color;
    }
}
