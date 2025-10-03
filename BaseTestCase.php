<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use ReflectionObject;

use function str_starts_with;

/**
 * Class BaseTestCase
 * @package Tests
 */
abstract class BaseTestCase extends TestCase
{
    /**
     * @inheritdoc
     */
    protected function tearDown(): void
    {
        $refl = new ReflectionObject($this);

        foreach ($refl->getProperties() as $prop) {
            if (!$prop->isStatic() && !str_starts_with($prop->getDeclaringClass()->getName(), 'PHPUnit_')) {
                $prop->setAccessible(true);
                $prop->setValue($this, null);
            }
        }
    }
}
