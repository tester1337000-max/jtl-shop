<?php

declare(strict_types=1);

namespace JTL\Backend\Settings\Sections;

use JTL\Backend\Settings\Item;
use JTL\Backend\Settings\Manager;
use JTL\DB\SqlObject;
use stdClass;

/**
 * Interface SectionInterface
 * @package JTL\Backend\Settings\Sections
 */
interface SectionInterface
{
    /**
     * @param Manager $manager
     * @param int     $sectionID
     */
    public function __construct(Manager $manager, int $sectionID);

    /**
     * @param SqlObject|null $sql
     */
    public function load(?SqlObject $sql = null): void;

    /**
     * @param Item  $conf
     * @param mixed $confValue
     * @return bool
     */
    public function validate(Item $conf, mixed $confValue): bool;

    /**
     * @return string
     */
    public function getSectionMarkup(): string;

    /**
     * @param string $markup
     */
    public function setSectionMarkup(string $markup): void;

    /**
     * @param array<mixed> $data
     * @param bool         $filter
     * @param string[]     $tags
     * @return array<mixed>
     */
    public function update(array $data, bool $filter = true, array $tags = [\CACHING_GROUP_OPTION]): array;

    /**
     * @param stdClass             $object
     * @param string               $type
     * @param array<mixed>         $data
     * @param array<string, mixed> $unfiltered
     */
    public function setConfigValue(stdClass $object, string $type, array $data, array $unfiltered): void;

    /**
     * @param string $filter
     * @return Item[]
     */
    public function filter(string $filter): array;

    /**
     * @return int
     */
    public function getID(): int;

    /**
     * @param int $id
     */
    public function setID(int $id): void;

    /**
     * @return string
     */
    public function getName(): string;

    /**
     * @param string $name
     */
    public function setName(string $name): void;

    /**
     * @return int
     */
    public function getMenuID(): int;

    /**
     * @param int $menuID
     */
    public function setMenuID(int $menuID): void;

    /**
     * @return int
     */
    public function getSortID(): int;

    /**
     * @param int $sortID
     */
    public function setSortID(int $sortID): void;

    /**
     * @return string
     */
    public function getPermission(): string;

    /**
     * @param string $permission
     */
    public function setPermission(string $permission): void;

    /**
     * @return Item[]
     */
    public function getItems(): array;

    /**
     * @param Item[] $items
     */
    public function setItems(array $items): void;

    /**
     * @return int
     */
    public function getConfigCount(): int;

    /**
     * @param int $configCount
     */
    public function setConfigCount(int $configCount): void;

    /**
     * @return Subsection[]
     */
    public function getSubsections(): array;

    /**
     * @param Subsection[] $subsections
     */
    public function setSubsections(array $subsections): void;

    /**
     * @return bool
     */
    public function hasSectionMarkup(): bool;

    /**
     * @param bool $hasSectionMarkup
     */
    public function setHasSectionMarkup(bool $hasSectionMarkup): void;

    /**
     * @return string|null
     */
    public function getURL(): ?string;

    /**
     * @param string|null $url
     */
    public function setURL(?string $url): void;

    /**
     * @return int
     */
    public function getUpdateErrors(): int;

    /**
     * @param int $updateErrors
     */
    public function setUpdateErrors(int $updateErrors): void;
}
