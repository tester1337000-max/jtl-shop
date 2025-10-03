<?php

declare(strict_types=1);

namespace JTL\Mail\Mail;

use PHPMailer\PHPMailer\PHPMailer;
use ReflectionClass;
use stdClass;

/**
 * Class Attachment
 * @package JTL\Mail\Mail
 */
final class Attachment
{
    private string $mime = 'application/octet-stream';

    private string $dir = \PFAD_ROOT . \PFAD_ADMIN . \PFAD_INCLUDES . \PFAD_EMAILPDFS;

    private string $fileName = '';

    private string $name = '';

    private string $encoding = PHPMailer::ENCODING_BASE64;

    public function getMime(): string
    {
        return $this->mime;
    }

    public function setMime(string $mime): self
    {
        $this->mime = $mime;

        return $this;
    }

    public function getDir(): string
    {
        return $this->dir;
    }

    public function setDir(string $dir): self
    {
        $this->dir = $dir;

        return $this;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function setFileName(string $fileName): self
    {
        $this->fileName = $fileName;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getEncoding(): string
    {
        return $this->encoding;
    }

    public function setEncoding(string $encoding): self
    {
        $this->encoding = $encoding;

        return $this;
    }

    public function getFullPath(): string
    {
        return $this->dir . $this->fileName;
    }

    public function toObject(): stdClass
    {
        $reflect    = new ReflectionClass($this);
        $properties = $reflect->getProperties();
        $toArray    = [];
        foreach ($properties as $property) {
            $propertyName           = $property->getName();
            $toArray[$propertyName] = $property->getValue($this);
        }

        return (object)$toArray;
    }

    public function hydrateWithObject(object $object): self
    {
        $attributes = \get_object_vars($this);
        foreach ($attributes as $attribute => $value) {
            $setMethod = 'set' . \ucfirst($attribute);
            $getMethod = 'get' . \ucfirst($attribute);
            if (
                \method_exists($this, $setMethod)
                && \method_exists($object, $getMethod)
                && $object->{$getMethod}() !== null
            ) {
                $this->$setMethod($object->{$getMethod}());
                continue;
            }
            if (
                \property_exists($object, $attribute)
                && \method_exists($this, $setMethod)
            ) {
                $this->$setMethod($object->$attribute);
            }
        }

        return $this;
    }
}
