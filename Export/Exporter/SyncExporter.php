<?php

declare(strict_types=1);

namespace JTL\Export\Exporter;

use JTL\Export\AsyncCallback;
use JTL\Router\Route;
use JTL\Session\Backend;
use JTL\Shop;
use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface;

/**
 * Class SyncExporter
 * @package JTL\Export\Exporter
 */
class SyncExporter extends AbstractExporter
{
    public function getNextStep(AsyncCallback $cb): ResponseInterface
    {
        if ($this->started === true) {
            return new RedirectResponse(
                \sprintf(
                    '%s/%s?e=%d&back=admin&token=%s&max=%d',
                    Shop::getAdminURL(),
                    Route::EXPORT_START,
                    $this->getQueue()->jobQueueID,
                    Backend::get('jtl_token'),
                    $cb->getProductCount()
                ),
                301
            );
        }

        return $this->finish($cb);
    }

    public function finish(AsyncCallback $cb): ResponseInterface
    {
        parent::finish($cb);

        return new RedirectResponse(
            \sprintf(
                '%s/%s?action=exported&token=%s&kExportformat=%d&max=%d&hasError=%d',
                Shop::getAdminURL(),
                Route::EXPORT,
                Backend::get('jtl_token'),
                $this->getModel()->getId(),
                $cb->getProductCount(),
                (int)($cb->getError() !== '' && $cb->getError() !== null)
            ),
            301
        );
    }
}
