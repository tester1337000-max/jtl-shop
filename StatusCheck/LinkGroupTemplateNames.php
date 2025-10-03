<?php

declare(strict_types=1);

namespace JTL\Backend\StatusCheck;

use JTL\Router\Route;

use function Functional\pluck;

class LinkGroupTemplateNames extends AbstractStatusCheck
{
    /**
     * @var \stdClass[]
     */
    private array $duplicates = [];

    public function isOK(): bool
    {
        $this->duplicates = $this->db->getObjects(
            'SELECT * FROM tlinkgruppe
                GROUP BY cTemplatename
                HAVING COUNT(*) > 1'
        );

        return \count($this->duplicates) === 0;
    }

    /**
     * @return \stdClass[]
     */
    public function getData(): array
    {
        return $this->duplicates;
    }

    public function getURL(): ?string
    {
        return $this->adminURL . Route::LINKS;
    }

    public function getTitle(): string
    {
        return \__('getDuplicateLinkGroupTemplateNamesTitle');
    }

    public function generateMessage(): void
    {
        $this->addNotification(
            \sprintf(
                \__('getDuplicateLinkGroupTemplateNamesMessage'),
                \implode(', ', pluck($this->duplicates, 'cName'))
            )
        );
    }
}
