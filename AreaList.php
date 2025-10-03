<?php

declare(strict_types=1);

namespace JTL\OPC;

/**
 * Class AreaList
 * @package JTL\OPC
 */
class AreaList implements \JsonSerializable
{
    /**
     * @var Area[]
     */
    protected array $areas = [];

    public function clear(): self
    {
        $this->areas = [];

        return $this;
    }

    public function putArea(Area $area): self
    {
        $this->areas[$area->getId()] = $area;

        return $this;
    }

    public function hasArea(string $id): bool
    {
        return \array_key_exists($id, $this->areas);
    }

    public function getArea(string $id): ?Area
    {
        return $this->areas[$id] ?? null;
    }

    /**
     * @return Area[]
     */
    public function getAreas(): array
    {
        return $this->areas;
    }

    /**
     * @return string[] the rendered HTML content of this page
     */
    public function getPreviewHtml(): array
    {
        return \array_map(static fn(Area $area): string => $area->getPreviewHtml(), $this->areas);
    }

    /**
     * @return array<int, string>
     * @throws \Exception
     * @return string[] the rendered HTML content of this page
     */
    public function getFinalHtml(): array
    {
        return \array_map(static fn(Area $area): string => $area->getFinalHtml(), $this->areas);
    }

    /**
     * @param array<array<mixed>> $data
     * @throws \Exception
     */
    public function deserialize(array $data): void
    {
        $this->clear();
        foreach ($data as $areaData) {
            $this->putArea((new Area())->deserialize($areaData));
        }
    }

    /**
     * @return array<mixed>
     */
    public function jsonSerialize(): array
    {
        return \array_map(static fn(Area $area): array => $area->jsonSerialize(), $this->areas);
    }
}
