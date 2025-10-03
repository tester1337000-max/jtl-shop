<?php

declare(strict_types=1);

namespace JTL\Redirect\DomainObjects;

use JTL\DataObjects\AbstractDomainObject;
use stdClass;

class RedirectRefererDomainObject extends AbstractDomainObject
{
    public function __construct(
        public int $redirectID,
        public int $botID,
        public string $url,
        public string $ip,
        public ?int $timestamp = null,
        public ?int $id = null,
        array $modifiedKeys = []
    ) {
        parent::__construct($modifiedKeys);
    }

    /**
     * @inheritdoc
     */
    public function toObject(bool $deep = false): stdClass
    {
        $obj = (object)$this->toArray($deep);
        if (isset($obj->id)) {
            $obj->kRedirectReferer = $obj->id;
        }
        if (isset($obj->botID)) {
            $obj->kBesucherBot = $obj->botID;
        }
        if (isset($obj->url)) {
            $obj->cRefererUrl = $obj->url;
        }
        if (isset($obj->ip)) {
            $obj->cIP = $obj->ip;
        }
        if (isset($obj->timestamp)) {
            $obj->dDate = $obj->timestamp;
        }
        if (isset($obj->redirectID)) {
            $obj->kRedirect = $obj->redirectID;
        }
        unset($obj->botID, $obj->id, $obj->url, $obj->ip, $obj->timestamp, $obj->redirectID);

        return $obj;
    }

    public static function fromObject(stdClass $data): self
    {
        return new self(
            (int)$data->kRedirect,
            (int)$data->kBesucherBot,
            $data->cRefererUrl,
            $data->cIP,
            (int)$data->dDate,
            (int)$data->kRedirectReferer
        );
    }
}
