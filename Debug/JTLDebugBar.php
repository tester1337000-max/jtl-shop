<?php

declare(strict_types=1);

namespace JTL\Debug;

use DebugBar\DataCollector\ConfigCollector;
use DebugBar\DataCollector\ExceptionsCollector;
use DebugBar\DataCollector\MemoryCollector;
use DebugBar\DataCollector\MessagesCollector;
use DebugBar\DataCollector\PDO\PDOCollector;
use DebugBar\DataCollector\PDO\TraceablePDO;
use DebugBar\DataCollector\PhpInfoCollector;
use DebugBar\DataCollector\RequestDataCollector;
use DebugBar\DataCollector\TimeDataCollector;
use DebugBar\DebugBar;
use DebugBar\DebugBarException;
use DebugBar\JavascriptRenderer;
use JTL\Debug\DataCollector\DummyTimeDataCollector;
use JTL\Debug\DataCollector\Errors;
use JTL\Debug\DataCollector\JTLCache;

/**
 * Class JTLDebugBar
 * @package JTL\Debug
 */
class JTLDebugBar extends DebugBar
{
    private TimeDataCollector $timer;

    /**
     * @param \PDO                                $pdo
     * @param array<string, array<string, mixed>> $config
     * @throws DebugBarException
     */
    public function __construct(\PDO $pdo, array $config)
    {
        if (\SHOW_DEBUG_BAR === true) {
            $this->initDefault($pdo, $config);
        } else {
            $this->initDummy();
        }
    }

    public function getTimer(): TimeDataCollector
    {
        return $this->timer;
    }

    /**
     * @param \PDO                                $pdo
     * @param array<string, array<string, mixed>> $config
     * @throws DebugBarException
     */
    private function initDefault(\PDO $pdo, array $config): void
    {
        $this->timer      = new TimeDataCollector();
        $this->jsRenderer = new JavascriptRenderer($this);
        $this->timer->startMeasure('init', 'Shop start to end');
        $this->jsRenderer->setBaseUrl(\URL_SHOP . '/' . \rtrim(\PFAD_INCLUDES, '/') . $this->jsRenderer->getBaseUrl());
        $this->addCollector($this->timer);
        $this->addCollector(new PDOCollector(new TraceablePDO($pdo)));
        $this->addCollector(new ConfigCollector($config));
        $this->addCollector(new PhpInfoCollector());
        $this->addCollector(new MessagesCollector());
        $this->addCollector(new RequestDataCollector());
        $this->addCollector(new MemoryCollector());
        $this->addCollector(new ExceptionsCollector());
        $this->addCollector(new Errors());
        $this->addCollector(new JTLCache());
    }

    private function initDummy(): void
    {
        $this->timer      = new DummyTimeDataCollector();
        $this->jsRenderer = new DummyRenderer($this);
        $this->jsRenderer->setBaseUrl(\URL_SHOP . '/' . \rtrim(\PFAD_INCLUDES, '/') . $this->jsRenderer->getBaseUrl());
    }
}
