<?php

declare(strict_types=1);

namespace JTL\Checkout;

/**
 * Class ShippingSurchargeArea
 * @package JTL\Checkout
 */
class ShippingSurchargeArea
{
    public string $ZIPFrom;

    public string $ZIPTo;

    public function __construct(string $ZIPFrom, string $ZIPTo)
    {
        if ($this->getNumber($ZIPFrom) < $this->getNumber($ZIPTo)) {
            $this->setZIPFrom($ZIPFrom)
                ->setZIPTo($ZIPTo);
        } else {
            $this->setZIPFrom($ZIPTo)
                ->setZIPTo($ZIPFrom);
        }
    }

    public function getZIPFrom(): string
    {
        return $this->ZIPFrom;
    }

    public function setZIPFrom(string $ZIPFrom): self
    {
        $this->ZIPFrom = \str_replace(' ', '', $ZIPFrom);

        return $this;
    }

    public function getZIPTo(): string
    {
        return $this->ZIPTo;
    }

    public function setZIPTo(string $ZIPTo): self
    {
        $this->ZIPTo = \str_replace(' ', '', $ZIPTo);

        return $this;
    }

    public function isInArea(string $zip): bool
    {
        $zipNumber = $this->getNumber($zip);

        return $this->getLetters($zip) === $this->getLetters($this->ZIPFrom)
            && $this->getNumber($this->ZIPFrom) <= $zipNumber
            && $this->getNumber($this->ZIPTo) >= $zipNumber;
    }

    public function getArea(): string
    {
        return $this->ZIPFrom . ' - ' . $this->ZIPTo;
    }

    private function getNumber(string $zip): int
    {
        \preg_match('/\d+/', $zip, $number);

        return (int)($number[0] ?? 0);
    }

    private function getLetters(string $zip): string
    {
        \preg_match('/[A-Za-z]+/', $zip, $letters);

        return $letters[0] ?? '';
    }

    public function lettersMatch(): bool
    {
        return $this->getLetters($this->ZIPFrom) === $this->getLetters($this->ZIPTo);
    }
}
