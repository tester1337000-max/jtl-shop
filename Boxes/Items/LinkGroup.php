<?php

declare(strict_types=1);

namespace JTL\Boxes\Items;

use JTL\Link\LinkGroupInterface;
use JTL\Link\LinkInterface;
use JTL\Shop;

/**
 * Class LinkGroup
 * @package JTL\Boxes\Items
 */
final class LinkGroup extends AbstractBox
{
    private ?LinkGroupInterface $linkGroup = null;

    public ?string $linkGroupTemplate = null;

    /**
     * @inheritdoc
     */
    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->addMapping('oLinkGruppe', 'LinkGroup');
        $this->addMapping('oLinkGruppeTemplate', 'LinkGroupTemplate');
    }

    /**
     * @inheritdoc
     */
    public function map(array $boxData): void
    {
        parent::map($boxData);
        $this->setShow(false);
        $linkGroup = Shop::Container()->getLinkService()->getLinkGroupByID($this->getCustomID());
        if ($linkGroup !== null) {
            $linkGroup->setLinks(
                $linkGroup->getLinks()->filter(fn(LinkInterface $link): bool => $link->getPluginEnabled())
            );
            $this->setShow($linkGroup->getLinks()->count() > 0);
            $this->setLinkGroupTemplate($linkGroup->getTemplate());
        }
        $this->linkGroup = $linkGroup;
    }

    public function getLinkGroup(): ?LinkGroupInterface
    {
        return $this->linkGroup;
    }

    public function setLinkGroup(?LinkGroupInterface $linkGroup): void
    {
        $this->linkGroup = $linkGroup;
    }

    public function getLinkGroupTemplate(): ?string
    {
        return $this->linkGroupTemplate;
    }

    public function setLinkGroupTemplate(string $linkGroupTemplate): void
    {
        $this->linkGroupTemplate = $linkGroupTemplate;
    }
}
