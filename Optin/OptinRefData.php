<?php

declare(strict_types=1);

namespace JTL\Optin;

use JTL\GeneralDataProtection\IpAnonymizer;

/**
 * Class OptinRefData
 * @package JTL\Optin
 */
class OptinRefData
{
    /**
     * @var class-string<OptinInterface>|null
     */
    private ?string $optinClass = null;

    private int $languageID;

    private ?int $customerID = null;

    private ?int $customerGroupID = null;

    private string $salutation = '';

    private string $firstName = '';

    private string $lastName = '';

    private string $email = '';

    private string $realIP = '';

    private ?int $productID = null;

    /**
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        return [
            'optinClass'      => $this->optinClass,
            'languageID'      => $this->languageID,
            'customerID'      => $this->customerID,
            'salutation'      => $this->salutation,
            'firstName'       => $this->firstName,
            'lastName'        => $this->lastName,
            'email'           => $this->email,
            'realIP'          => $this->realIP,
            'productID'       => $this->productID,
            'customerGroupID' => $this->customerGroupID
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
        // items pre 5.3.0 will not have a serialized customer group id
        if ($this->customerGroupID === null) {
            $this->customerGroupID = 0;
        }
    }

    /**
     * @param class-string<OptinInterface> $optinClass
     * @return OptinRefData
     */
    public function setOptinClass(string $optinClass): self
    {
        $this->optinClass = $optinClass;

        return $this;
    }

    public function setLanguageID(int $languageID): self
    {
        $this->languageID = $languageID;

        return $this;
    }

    public function setCustomerID(int $customerID): self
    {
        $this->customerID = $customerID;

        return $this;
    }

    public function setSalutation(string $salutation): self
    {
        $this->salutation = $salutation;

        return $this;
    }

    public function setFirstName(string $firstName): self
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function setLastName(string $lastName): self
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function setRealIP(string $realIP): self
    {
        $this->realIP = $realIP;

        return $this;
    }

    public function setProductId(int $productId): self
    {
        $this->productID = $productId;

        return $this;
    }

    /**
     * @return class-string<OptinInterface>
     */
    public function getOptinClass(): string
    {
        return $this->optinClass ?? '';
    }

    public function getLanguageID(): int
    {
        return $this->languageID;
    }

    public function getCustomerID(): int
    {
        return $this->customerID ?? 0;
    }

    public function getSalutation(): string
    {
        return $this->salutation;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getRealIP(): string
    {
        return $this->realIP;
    }

    public function getProductId(): int
    {
        return $this->productID ?? 0;
    }

    public function getCustomerGroupID(): ?int
    {
        return $this->customerGroupID;
    }

    public function setCustomerGroupID(?int $customerGroupID): self
    {
        $this->customerGroupID = $customerGroupID;

        return $this;
    }

    public function anonymized(): self
    {
        $this->setEmail('anonym');
        $this->setRealIP((new IpAnonymizer($this->getRealIP()))->anonymize());
        $this->setFirstName('anonym');
        $this->setLastName('anonym');

        return $this;
    }

    public function __toString(): string
    {
        return \serialize([
            $this->optinClass,
            $this->languageID,
            $this->customerID,
            $this->salutation,
            $this->firstName,
            $this->lastName,
            $this->email,
            $this->realIP,
            $this->productID,
            $this->customerGroupID
        ]);
    }
}
