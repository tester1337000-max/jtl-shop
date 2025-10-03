<?php

declare(strict_types=1);

namespace JTL\Console\Command;

use JTL\Cache\JTLCacheInterface;
use JTL\Console\Application;
use JTL\Console\ConsoleIO;
use JTL\DB\DbInterface;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class Command
 * @package JTL\Console\Command
 */
class Command extends BaseCommand
{
    protected DbInterface $db;

    protected JTLCacheInterface $cache;

    public function getDB(): DbInterface
    {
        return $this->db;
    }

    public function setDB(DbInterface $db): void
    {
        $this->db = $db;
    }

    public function getCache(): JTLCacheInterface
    {
        return $this->cache;
    }

    public function setCache(JTLCacheInterface $cache): void
    {
        $this->cache = $cache;
    }

    public function getApp(): Application
    {
        return $this->getApplication() ?? throw new \RuntimeException('Application not set');
    }

    public function getIO(): ConsoleIO
    {
        return $this->getApp()->getIO();
    }

    public function getArgumentDefinition(string $name): InputArgument
    {
        return $this->getDefinition()->getArgument($name);
    }

    public function hasMissingOption(string $name): bool
    {
        $option = $this->getDefinition()->getOption($name);
        $value  = \trim($this->getIO()->getInput()->getOption($name) ?? '');

        return $option->isValueRequired() && $option->acceptValue() && empty($value);
    }

    public function getOptionDefinition(string $name): InputOption
    {
        return $this->getDefinition()->getOption($name);
    }

    public function getOption(string $name): mixed
    {
        $value = $this->getIO()->getInput()->getOption($name);

        return \is_string($value) ? \trim($value) : $value;
    }

    /**
     * @return array<string|bool|int|float|array<mixed>|null>
     */
    public function getOptions(): array
    {
        return $this->getIO()->getInput()->getOptions();
    }

    public function hasOption(string $name): bool
    {
        return $this->getIO()->getInput()->hasOption($name);
    }
}
