<?php

declare(strict_types=1);

namespace JTL\Redirect\DomainObjects;

use JTL\DataObjects\AbstractDomainObject;
use JTL\Redirect\Type;
use stdClass;

class RedirectDomainObject extends AbstractDomainObject
{
    public function __construct(
        public string $source,
        public string $destination,
        public ?int $id = 0,
        public ?string $dateCreated = null,
        public string $available = 'u',
        public int $paramHandling = 0,
        public int $type = Type::UNKNOWN,
        public int $count = 0,
        array $modifiedKeys = []
    ) {
        parent::__construct($modifiedKeys);
    }

    /**
     * @inheritdoc
     */
    public function toObject(bool $deep = false): stdClass
    {
        $arr = $this->toArray($deep);
        $obj = (object)$arr;
        if (isset($obj->id)) {
            $obj->kRedirect = $obj->id;
        }
        if (isset($obj->source)) {
            $obj->cFromUrl = $obj->source;
        }
        if (isset($obj->destination)) {
            $obj->cToUrl = $obj->destination;
        }
        if (isset($obj->count)) {
            $obj->nCount = $obj->count;
        }
        if (isset($obj->available)) {
            $obj->cAvailable = $obj->available;
        }
        if (\array_key_exists('dateCreated', $arr) && $arr['dateCreated'] === null) {
            unset($obj->dateCreated);
        }
        unset($obj->source, $obj->id, $obj->destination, $obj->count, $obj->available);

        return $obj;
    }

    public static function fromObject(stdClass $data): self
    {
        return new self(
            source: $data->cFromUrl,
            destination: $data->cToUrl,
            id: (int)$data->kRedirect,
            dateCreated: $data->dateCreated ?? null,
            available: $data->cAvailable,
            paramHandling: (int)$data->paramHandling,
            type: (int)$data->type,
            count: (int)$data->nCount
        );
    }
}
