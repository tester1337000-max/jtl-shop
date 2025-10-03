<?php

declare(strict_types=1);

namespace JTL;

use stdClass;

/**
 * Class Linechart
 * @package JTL
 */
class Linechart extends Chartdata
{
    public function addAxis(string $label): self
    {
        if ($this->xAxis === null) {
            $this->xAxis             = new stdClass();
            $this->xAxis->categories = [];
        }
        $this->xAxis->labels               = new stdClass();
        $this->xAxis->labels->style        = new stdClass();
        $this->xAxis->labels->style->color = '#5cbcf6';
        $this->xAxis->categories[]         = $label;

        return $this;
    }

    /**
     * @param array<mixed> $data
     */
    public function addSerie(
        string $name,
        array $data,
        string $linecolor = '#5cbcf6',
        string $areacolor = '#5cbcf6',
        string $pointcolor = '#5cbcf6'
    ): self {
        if ($this->series === null) {
            $this->series = [];
        }
        $serie                            = new stdClass();
        $serie->name                      = $name;
        $serie->data                      = $data;
        $serie->lineColor                 = $linecolor;
        $serie->color                     = $areacolor;
        $serie->marker                    = new stdClass();
        $serie->marker->lineColor         = $pointcolor;
        $serie->fillColor                 = new stdClass();
        $serie->fillColor->linearGradient = [0, 0, 0, 300];
        $serie->fillColor->stops          = [
            [0, $this->hex2rgba($areacolor, 0.9)],
            [0.7, $this->hex2rgba($areacolor, 0.0)]
        ];
        $this->series[]                   = $serie;

        return $this;
    }

    private function hex2rgba(string $color, float|bool|string $opacity = false): string
    {
        $default = 'rgb(0,0,0)';
        // Return default if no color provided
        if (empty($color)) {
            return $default;
        }
        // Sanitize $color if "#" is provided
        if (\str_starts_with($color, '#')) {
            $color = \substr($color, 1);
        }
        // Check if color has 6 or 3 characters and get values
        if (\strlen($color) === 6) {
            $hex = [$color[0] . $color[1], $color[2] . $color[3], $color[4] . $color[5]];
        } elseif (\strlen($color) === 3) {
            $hex = [$color[0] . $color[0], $color[1] . $color[1], $color[2] . $color[2]];
        } else {
            return $default;
        }
        // Convert hexadec to rgb
        $rgb = \array_map('\hexdec', $hex);
        // Check if opacity is set(rgba or rgb)
        if ($opacity !== false) {
            if (\abs((float)$opacity) > 1) {
                $opacity = 1.0;
            }
            $output = 'rgba(' . \implode(',', $rgb) . ',' . $opacity . ')';
        } else {
            $output = 'rgb(' . \implode(',', $rgb) . ')';
        }

        return $output;
    }
}
