<?php

declare(strict_types=1);

namespace JTL\Events;

use JTL\SingletonTrait;
use stdClass;

use function Functional\pluck;

/**
 * Class Dispatcher
 * @package JTL\Events
 */
final class Dispatcher
{
    use SingletonTrait;

    /**
     * The registered event listeners.
     *
     * @var array<string, array<object{listener: callable, priority: int}&stdClass>>
     */
    private array $listeners = [];

    /**
     * The wildcard listeners.
     *
     * @var array<string, array<object{listener: callable, priority: int}&stdClass>>
     */
    private array $wildcards = [];

    public function hasListeners(string $eventName): bool
    {
        return isset($this->listeners[$eventName]) || isset($this->wildcards[$eventName]);
    }

    /**
     * Register an event listener with the dispatcher.
     *
     * @param string[]|string $eventNames
     * @param callable        $listener
     * @param int             $priority
     */
    public function listen(array|string $eventNames, callable $listener, int $priority = 5): void
    {
        foreach ((array)$eventNames as $event) {
            $item = (object)['listener' => $listener, 'priority' => $priority];
            if (\str_contains($event, '*')) {
                $this->wildcards[$event][] = $item;
            } else {
                $this->listeners[$event][] = $item;
            }
        }
    }

    /**
     * @since 5.2.0
     */
    public function hookInto(int $hookID, callable $listener, int $priority = 5): void
    {
        $this->listeners['shop.hook.' . $hookID][] = (object)['listener' => $listener, 'priority' => $priority];
    }

    /**
     * Fire an event and call the listeners.
     */
    public function fire(string $eventName, mixed $arguments = []): void
    {
        foreach ($this->getListeners($eventName) as $listener) {
            $listener($arguments);
        }
    }

    /**
     * @param int          $hookID
     * @param mixed        $result
     * @param array<mixed> ...$arguments
     * @return mixed
     */
    public function getData(int $hookID, mixed $result, ...$arguments): mixed
    {
        foreach ($this->getListeners('shop.hook.' . $hookID) as $listener) {
            $result = $listener($result, ...$arguments);
        }

        return $result;
    }

    public function forget(string $eventName): void
    {
        if (\str_contains($eventName, '*')) {
            if (isset($this->wildcards[$eventName])) {
                unset($this->wildcards[$eventName]);
            }
        } elseif (isset($this->listeners[$eventName])) {
            unset($this->listeners[$eventName]);
        }
    }

    /**
     * @return \Closure[]
     */
    public function getListeners(string $eventName): array
    {
        $listeners = $this->getWildcardListeners($eventName);
        if (isset($this->listeners[$eventName])) {
            $listeners = \array_merge($listeners, $this->listeners[$eventName]);
        }
        \usort($listeners, $this->sortByPriority(...));

        return pluck($listeners, 'listener');
    }

    private function sortByPriority(stdClass $a, stdClass $b): int
    {
        return $a->priority <=> $b->priority;
    }

    /**
     * Get the wildcard listeners for the event.
     *
     * @param string $eventName
     * @return array<mixed>
     */
    private function getWildcardListeners(string $eventName): array
    {
        $wildcards = [];
        foreach ($this->wildcards as $key => $listeners) {
            if (\fnmatch($key, $eventName)) {
                $wildcards[] = $listeners;
            }
        }

        return \array_merge(...$wildcards);
    }
}
