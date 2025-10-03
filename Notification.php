<?php

declare(strict_types=1);

namespace JTL\Backend;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JTL\Backend\StatusCheck\Factory;
use JTL\DB\DbInterface;
use JTL\Events\Dispatcher;
use JTL\IO\IOResponse;
use JTL\Shop;
use JTL\Smarty\ContextType;
use JTL\Smarty\JTLSmarty;
use Traversable;

/**
 * @phpstan-implements IteratorAggregate<int, NotificationEntry>
 */
class Notification implements IteratorAggregate, Countable
{
    /**
     * @var NotificationEntry[]
     */
    private array $array = [];

    private static ?Notification $instance = null;

    public function __construct(private readonly DbInterface $db)
    {
        self::$instance = $this;
    }

    public static function getInstance(?DbInterface $db = null): self
    {
        return self::$instance ?? new self($db ?? Shop::Container()->getDB());
    }

    public function add(
        int $type,
        string $title,
        ?string $description = null,
        ?string $url = null,
        ?string $hash = null
    ): void {
        $this->addNotify(new NotificationEntry($type, $title, $description, $url, $hash));
    }

    public function addNotify(NotificationEntry $notify): void
    {
        $this->array[] = $notify;
    }

    public function getHighestType(bool $withIgnored = false): int
    {
        $type = NotificationEntry::TYPE_NONE;
        foreach ($this as $notify) {
            /** @var NotificationEntry $notify */
            if (($withIgnored || !$notify->isIgnored()) && $notify->getType() > $type) {
                $type = $notify->getType();
            }
        }

        return $type;
    }

    public function count(): int
    {
        return \count(\array_filter($this->array, static fn(NotificationEntry $item): bool => !$item->isIgnored()));
    }

    public function totalCount(): int
    {
        return \count($this->array);
    }

    public function getIterator(): Traversable
    {
        \usort(
            $this->array,
            static fn(NotificationEntry $a, NotificationEntry $b): int => $b->getType() <=> $a->getType()
        );

        return new ArrayIterator($this->array);
    }

    public function buildDefault(bool $flushCache = false): self
    {
        Shop::Container()->getGetText()->loadAdminLocale('notifications');
        $adminURL = Shop::getAdminURL() . '/';
        $cache    = Shop::Container()->getCache();
        $factory  = new Factory($this->db, $cache, $adminURL);
        if ($flushCache) {
            $cache->flushTags([\CACHING_GROUP_STATUS]);
        }
        foreach ($factory->getChecks() as $check) {
            if ($check->isOK()) {
                continue;
            }
            $check->generateMessage();
            $notification = $check->getNotification();
            if ($notification !== null) {
                $this->addNotify($notification);
            }
            if ($check->stopFurtherChecks()) {
                return $this;
            }
        }

        return $this;
    }

    protected function ignoreNotification(IOResponse $response, string $hash): void
    {
        $this->db->upsert(
            'tnotificationsignore',
            (object)[
                'user_id'           => Shop::Container()->getAdminAccount()->getID(),
                'notification_hash' => $hash,
                'created'           => 'NOW()',
            ],
            ['created']
        );

        $response->assignDom($hash, 'outerHTML', '');
    }

    protected function resetIgnoredNotifications(IOResponse $response): void
    {
        $this->db->delete(
            'tnotificationsignore',
            'user_id',
            Shop::Container()->getAdminAccount()->getID()
        );

        $this->updateNotifications($response, true);
    }

    protected function updateNotifications(IOResponse $response, bool $flushCache = false): void
    {
        Dispatcher::getInstance()->fire('backend.notification', $this->buildDefault($flushCache));
        $res    = $this->db->getCollection(
            'SELECT notification_hash
                FROM tnotificationsignore
                WHERE user_id = :userID', // AND NOW() < DATE_ADD(created, INTERVAL 7 DAY)',
            ['userID' => Shop::Container()->getAdminAccount()->getID()]
        );
        $hashes = $res->keyBy('notification_hash');
        foreach ($this->array as $notificationEntry) {
            if (($hash = $notificationEntry->getHash()) !== null && $hashes->has($hash)) {
                $notificationEntry->setIgnored(true);
                $hashes->forget($hash);
            }
        }
        if ($hashes->count() > 0) {
            $this->db->query(
                "DELETE FROM tnotificationsignore
                    WHERE notification_hash IN ('" . $hashes->implode('notification_hash', "', '") . "')"
            );
        }

        $response->assignDom('notify-drop', 'innerHTML', self::getNotifyDropIO()['tpl']);
    }

    public static function ioNotification(string $action, mixed $data = null): IOResponse
    {
        $response      = new IOResponse();
        $notifications = self::getInstance();

        switch ($action) {
            case 'update':
                $notifications->updateNotifications($response);
                break;
            case 'refresh':
                $notifications->updateNotifications($response, true);
                break;
            case 'dismiss':
                $notifications->ignoreNotification($response, (string)$data);
                break;
            case 'reset':
                $notifications->resetIgnoredNotifications($response);
                break;
            default:
                break;
        }

        return $response;
    }

    /**
     * @return array{tpl: string, type: string}
     * @former getNotifyDropIO()
     * @since 5.2.0
     */
    public static function getNotifyDropIO(): array
    {
        return [
            'tpl'  => JTLSmarty::getInstance(false, ContextType::BACKEND)
                ->assign('notifications', self::getInstance())
                ->fetch('tpl_inc/notify_drop.tpl'),
            'type' => 'notify'
        ];
    }
}
