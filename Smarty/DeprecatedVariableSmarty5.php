<?php

declare(strict_types=1);

namespace JTL\Smarty;

use Smarty\Variable;

/**
 * Class DeprecatedVariable
 * @package \JTL\Smarty
 */
class DeprecatedVariableSmarty5 extends Variable
{
    private string $name;

    private string $version;

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function setVersion(string $version): self
    {
        $this->version = $version;

        return $this;
    }

    public function __toString(): string
    {
        return (string)$this->value;
    }

    public function __get(string $name): mixed
    {
        if ($name === 'value') {
            \trigger_error(
                'Smarty variable ' . $this->name . ' is deprecated since JTL-Shop version ' . $this->version . '.',
                \E_USER_DEPRECATED
            );

            return $this->value;
        }

        return null;
    }
}
