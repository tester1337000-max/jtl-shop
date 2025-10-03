<?php

declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
use JTL\Path;

class PathTest extends UnitTestCase
{
    public function testCombine(): void
    {
        $this->assertEquals('/var/www/html', Path::combine('/var/www', 'html'));
        $this->assertEquals('/var/www/html', Path::combine('/var', 'www', 'html'));
        $this->assertEquals('/var/www/html', Path::combine('/var/', '/www/', '/html'));

        $this->expectException(InvalidArgumentException::class);
        Path::combine();
    }

    public function testGetDirectoryName(): void
    {
        $this->assertEquals('/var/www', Path::getDirectoryName('/var/www/html', false));
        $this->assertEquals('/var', Path::getDirectoryName('/var/www', false));
    }

    public function testGetFileName(): void
    {
        $this->assertEquals('file.txt', Path::getFileName('/var/www/file.txt'));
        $this->assertEquals('file', Path::getFileName('/var/www/file'));
    }

    public function testGetFileNameWithoutExtension(): void
    {
        $this->assertEquals('file', Path::getFileNameWithoutExtension('/var/www/file.txt'));
        $this->assertEquals('file', Path::getFileNameWithoutExtension('/var/www/file'));
    }

    public function testGetExtension(): void
    {
        $this->assertEquals('txt', Path::getExtension('/var/www/file.txt'));
        $this->assertEquals('', Path::getExtension('/var/www/file'));
    }

    public function testHasExtension(): void
    {
        $this->assertTrue(Path::hasExtension('/var/www/file.txt'));
        $this->assertFalse(Path::hasExtension('/var/www/file'));
    }

    public function testAddTrailingSlash(): void
    {
        $this->assertEquals('/var/www/', Path::addTrailingSlash('/var/www'));
        $this->assertEquals('/var/www/', Path::addTrailingSlash('/var/www/'));
    }

    public function testRemoveTrailingSlash(): void
    {
        $this->assertEquals('/var/www', Path::removeTrailingSlash('/var/www/'));
        $this->assertEquals('/var/www', Path::removeTrailingSlash('/var/www'));
    }

    public function testClean(): void
    {
        $this->assertEquals('/var/www', Path::clean('/var/www/../www'));
        $this->assertEquals('/var/www/', Path::clean('/var/www/../www', true));
        $this->assertEquals('http://example.com/test', Path::clean('http://example.com/path/../test'));
        $this->assertEquals('/var/test', Path::clean('/var/www/../test'));
        $this->assertEquals('C:/test', Path::clean('C:\\path\\..\\test'));
    }
}
