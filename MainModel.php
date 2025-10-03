<?php

declare(strict_types=1);

namespace JTL;

use stdClass;

/**
 * Class MainModel
 * @package JTL
 */
abstract class MainModel
{
    /**
     * @param int|null    $id
     * @param null|object $data
     * @param null|mixed  $option
     */
    public function __construct(?int $id = null, ?object $data = null, mixed $option = null)
    {
        if ($data !== null) {
            $this->loadObject($data);
        } elseif ($id !== null) {
            $this->load($id, $data, $option);
        }
    }

    /**
     * @param int        $id
     * @param mixed|null $data
     * @param mixed|null $option
     * @return mixed
     */
    abstract public function load(int $id, mixed $data = null, mixed $option = null);

    /**
     * @return string[]
     */
    public function getProperties(): array
    {
        return \array_keys(\get_object_vars($this));
    }

    /**
     * @param array<string, mixed> $options
     */
    public function setOptions(array $options): self
    {
        $methods = \get_class_methods($this);
        foreach ($options as $key => $value) {
            $method = 'set' . \ucfirst($key);
            if (\in_array($method, $methods, true)) {
                $this->$method($value);
            }
        }

        return $this;
    }

    /**
     * @throws \JsonException
     */
    public function toJSON(): false|string
    {
        $item = new stdClass();
        foreach (\array_keys(\get_object_vars($this)) as $member) {
            $method = 'get' . \mb_substr($member, 1);
            if (\method_exists($this, $method)) {
                $item->$member = $this->$method();
            }
        }

        return \json_encode($item, \JSON_THROW_ON_ERROR);
    }

    public function toCSV(): string
    {
        $csv = '';
        foreach (\array_keys(\get_object_vars($this)) as $i => $member) {
            $method = 'get' . \mb_substr($member, 1);
            if (\method_exists($this, $method)) {
                $sep = '';
                if ($i > 0) {
                    $sep = ';';
                }

                $csv .= $sep . $this->$method();
            }
        }

        return $csv;
    }

    /**
     * @param string[] $nonpublics
     * @return stdClass
     */
    public function getPublic(array $nonpublics): stdClass
    {
        $item = new stdClass();
        foreach (\array_keys(\get_object_vars($this)) as $member) {
            if (!\in_array($member, $nonpublics, true)) {
                $item->$member = $this->$member;
            }
        }

        return $item;
    }

    /**
     * @param object $obj
     */
    public function loadObject(object $obj): void
    {
        foreach (\array_keys(\get_object_vars($obj)) as $member) {
            $method = 'set' . \mb_substr($member, 1);
            if (\method_exists($this, $method)) {
                $this->$method($obj->$member);
            }
        }
    }
}
