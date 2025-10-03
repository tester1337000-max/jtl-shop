<?php

declare(strict_types=1);

namespace JTL\Update;

use DateTime;
use JsonSerializable;
use JTL\DB\DbInterface;

/**
 * Class Migration
 * @package JTL\Update
 */
class Migration implements JsonSerializable
{
    use MigrationTableTrait;
    use MigrationTrait;

    /**
     * @var string|null
     * @noinspection PhpMissingFieldTypeInspection - this would break plugins
     */
    protected $author = '';

    /**
     * @var string|null
     * @noinspection PhpMissingFieldTypeInspection - this would break plugins
     */
    protected $description = '';

    protected bool $deleteData = true;

    public function __construct(DbInterface $db, protected ?string $info = null, protected ?DateTime $executed = null)
    {
        $this->setDB($db);
        $this->info = \ucfirst(\strtolower($info ?? ''));
    }

    public function getId(): ?int
    {
        return MigrationHelper::mapClassNameToId($this->getName());
    }

    public function getName(): string
    {
        return \str_replace('JTL\Migrations\\', '', \get_class($this));
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function getDescription(): string
    {
        return $this->description ?: $this->info ?? '';
    }

    public function getCreated(): DateTime
    {
        return DateTime::createFromFormat('YmdHis', (string)$this->getId())
            ?: throw new \InvalidArgumentException('Invalid migration ID');
    }

    public function getExecuted(): ?DateTime
    {
        return $this->executed;
    }

    public function doDeleteData(): bool
    {
        return $this->deleteData;
    }

    public function setDeleteData(bool $deleteData): void
    {
        $this->deleteData = $deleteData;
    }

    /**
     * @return array{id: int|null, name: string, author: string|null, description: string,
     *     executed: DateTime|null, created: DateTime}
     */
    public function jsonSerialize(): array
    {
        return [
            'id'          => $this->getId(),
            'name'        => $this->getName(),
            'author'      => $this->getAuthor(),
            'description' => $this->getDescription(),
            'executed'    => $this->getExecuted(),
            'created'     => $this->getCreated()
        ];
    }
}
