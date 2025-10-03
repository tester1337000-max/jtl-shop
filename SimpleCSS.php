<?php

/**
 *  SimpleCSS Parser
 *  (c) 2010 Andreas Juetten <andreasjuetten@gmx.de>
 * @noinspection PhpDeprecationInspection
 */

declare(strict_types=1);

namespace JTL;

/**
 * Class SimpleCSS
 * @package JTL
 * @deprecated since 5.4.0
 */
class SimpleCSS
{
    protected const LF = "\n\n";

    /**
     * @var array<mixed>
     */
    public array $cCSS_arr = [];

    public function __construct()
    {
        \trigger_error(__CLASS__ . ' is deprecated and should not be used anymore.', \E_USER_DEPRECATED);
    }

    public function addCSS(string $selector, mixed $attribute, mixed $value): self
    {
        if (isset($this->cCSS_arr[$selector])) {
            $this->cCSS_arr[$selector] = \array_merge($this->cCSS_arr[$selector], [$attribute => $value]);
        } else {
            $this->cCSS_arr[$selector] = [$attribute => $value];
        }

        return $this;
    }

    public function addFile(string $filename): bool
    {
        if (!\file_exists($filename)) {
            return false;
        }
        $data = \file_get_contents($filename);
        if ($data === false) {
            return false;
        }
        $data              = \preg_replace('!/\*.*?\*/!s', '', $data) ?? $data;
        $split             = \preg_split('/\{|\}/', $data) ?: [];
        $dataCount         = \count($split);
        $selectors         = [];
        $cssBaseAttributes = [];
        for ($i = 0; $i < $dataCount; $i++) {
            if ($i % 2 === 0) {
                $cssBaseAttributes = [];
                $selectors         = \explode(',', $split[$i]);
            } else {
                $attributes = \explode(';', $split[$i]);
                $attributes = $this->trimCSSData($attributes);
                foreach ($attributes as $attribute) {
                    $tmp = \explode(':', $attribute);
                    if (\count($tmp) === 2) {
                        $name                     = \trim($tmp[0]);
                        $cssBaseAttributes[$name] = \trim($tmp[1]);
                    }
                }

                foreach ($selectors as $selector) {
                    $selector = \trim($selector);
                    $selector = \preg_replace('#\s+#', ' ', $selector);
                    if (isset($this->cCSS_arr[$selector])) {
                        $this->cCSS_arr[$selector] = \array_merge($this->cCSS_arr[$selector], $cssBaseAttributes);
                    } else {
                        $this->cCSS_arr[$selector] = $cssBaseAttributes;
                    }
                }
            }
        }

        return \count($this->cCSS_arr) > 0;
    }

    /**
     * @param string[] $data
     * @return string[]
     */
    public function trimCSSData(array $data): array
    {
        return \array_filter(\array_map('\trim', $data), static fn(string $val): bool => $val !== '');
    }

    /**
     * @return mixed|false
     */
    public function getSelector(string $selector): mixed
    {
        return $this->cCSS_arr[$selector] ?? false;
    }

    /**
     * @return mixed|false
     */
    public function getAttribute(string $selector, string $key): mixed
    {
        $item = $this->getSelector($selector);
        if (\is_array($item) && \count($item)) {
            foreach ($item as $attrKey => $value) {
                if (\strcasecmp($attrKey, $key) === 0) {
                    return $value;
                }
            }
        }

        return false;
    }

    /**
     * @return array<mixed>
     */
    public function getCSS(): array
    {
        return $this->cCSS_arr;
    }

    public function renderCSS(): string
    {
        $ret = '';
        if (\count($this->cCSS_arr)) {
            foreach ($this->cCSS_arr as $selector => $attribute) {
                $ret .= $selector . ' {' . self::LF;
                foreach ($attribute as $cKey => $cValue) {
                    if (\mb_strlen($cKey) && \mb_strlen($cValue)) {
                        $ret .= '   ' . $cKey . ': ' . $cValue . ';' . self::LF;
                    }
                }
                $ret .= '}' . self::LF;
            }
        }

        return $ret;
    }

    public function getTemplatePath(string $dir): string
    {
        return \realpath(\PFAD_ROOT . \PFAD_TEMPLATES . \basename($dir)) . '/';
    }

    public function getCustomCSSFile(string $dir): string
    {
        return $this->getTemplatePath($dir) . 'themes/custom.css';
    }

    /**
     * @return false|array<mixed>|string
     */
    public function getAttrAs(string $value, string $type): false|array|string
    {
        $matches = [];
        switch ($type) {
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
                    return ['numeric' => (float)$matches[1], 'unit' => \trim($matches[2])];
                }
                break;

            default:
                break;
        }

        return false;
    }

    public function rgb2html(int $r, int $g, int $b): string
    {
        $red   = \dechex($r < 0 ? 0 : (\min($r, 255)));
        $green = \dechex($g < 0 ? 0 : (\min($g, 255)));
        $blue  = \dechex($b < 0 ? 0 : (\min($b, 255)));

        $color = (\mb_strlen($red) < 2 ? '0' : '') . $red;
        $color .= (\mb_strlen($green) < 2 ? '0' : '') . $green;
        $color .= (\mb_strlen($blue) < 2 ? '0' : '') . $blue;

        return '#' . $color;
    }

    /**
     * @return false|int[]
     */
    public function html2rgb(string $color): false|array
    {
        if (\str_starts_with($color, '#')) {
            $color = \mb_substr($color, 1);
        }
        if (\mb_strlen($color) === 6) {
            [$r, $g, $b] = [
                $color[0] . $color[1],
                $color[2] . $color[3],
                $color[4] . $color[5]
            ];
        } elseif (\mb_strlen($color) === 3) {
            [$r, $g, $b] = [$color[0] . $color[0], $color[1] . $color[1], $color[2] . $color[2]];
        } else {
            return false;
        }
        $r = \hexdec($r);
        $g = \hexdec($g);
        $b = \hexdec($b);

        return [(int)$r, (int)$g, (int)$b];
    }

    /**
     * @return string[]
     */
    public function getUnits(): array
    {
        return ['em', 'px', '%'];
    }
}
