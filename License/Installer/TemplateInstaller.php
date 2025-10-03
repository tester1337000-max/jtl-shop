<?php

declare(strict_types=1);

namespace JTL\License\Installer;

use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\License\AjaxResponse;
use JTL\Plugin\Admin\Installation\Extractor;
use JTL\Plugin\Admin\Installation\InstallationResponse;
use JTL\Shop;
use JTL\XMLParser;

/**
 * Class TemplateInstaller
 * @package JTL\License\Installer
 */
class TemplateInstaller implements InstallerInterface
{
    public function __construct(protected DbInterface $db, protected JTLCacheInterface $cache)
    {
    }

    /**
     * @inheritdoc
     */
    public function update(string $exsID, string $zip, AjaxResponse $response): int
    {
        $extractor        = new Extractor(new XMLParser());
        $installResponse  = $extractor->extractTemplate($zip);
        $response->status = $installResponse->getStatus();
        if ($response->status === InstallationResponse::STATUS_FAILED) {
            $response->error      = $installResponse->getError() ?? \implode(', ', $installResponse->getMessages());
            $response->additional = $installResponse;

            return 0;
        }
        $service = Shop::Container()->getTemplateService();
        $active  = $service->getActiveTemplate();
        $service->reset();
        if ($active->getExsID() === $exsID) {
            $service->setActiveTemplate(\rtrim($installResponse->getDirName() ?? '', "/\ \n\r\t\v\0"));
        }

        return 1;
    }

    /**
     * @inheritdoc
     */
    public function install(string $itemID, string $zip, AjaxResponse $response): int
    {
        return $this->update($itemID, $zip, $response);
    }

    /**
     * @inheritdoc
     */
    public function forceUpdate(string $zip, AjaxResponse $response): int
    {
        return $this->install('', $zip, $response);
    }
}
