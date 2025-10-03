<?php

declare(strict_types=1);

namespace JTL;

use stdClass;

/**
 * Class Piechart
 * @package JTL
 */
class Piechart extends Chartdata
{
    /**
     * @param array<mixed> $data
     */
    public function addSerie(string $name, array $data): self
    {
        if ($this->series === null) {
            $this->series = [];
        }
        $serie          = new stdClass();
        $serie->type    = 'pie';
        $serie->name    = $name;
        $serie->data    = $data;
        $this->series[] = $serie;

        return $this;
    }
}
