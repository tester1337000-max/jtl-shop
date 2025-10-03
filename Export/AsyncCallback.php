<?php

declare(strict_types=1);

namespace JTL\Export;

use DateTime;
use JTL\Router\Route;
use JTL\Shop;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use stdClass;

/**
 * Class AsyncCallback
 * @package JTL\Export
 */
class AsyncCallback
{
    private int $exportID = 0;

    private int $queueID = 0;

    private int $productCount = 0;

    private int $tasksExecuted = 0;

    private int $lastProductID = 0;

    private bool $isFinished = false;

    private bool $isFirst = false;

    private int $cacheHits = 0;

    private int $cacheMisses = 0;

    private string $url;

    private ?string $error = null;

    private ?string $message = null;

    public function __construct()
    {
        $this->url = Shop::getAdminURL() . '/' . Route::EXPORT_START;
    }

    public function getResponse(): ResponseInterface
    {
        return new JsonResponse($this->getCallback());
    }

    /**
     * @throws \JsonException
     */
    public function output(): void
    {
        echo \json_encode($this->getCallback(), \JSON_THROW_ON_ERROR);
    }

    private function getCallback(): stdClass
    {
        $callback                 = new stdClass();
        $callback->kExportformat  = $this->getExportID();
        $callback->kExportqueue   = $this->getQueueID();
        $callback->nMax           = $this->getProductCount();
        $callback->nCurrent       = $this->getTasksExecuted();
        $callback->nLastArticleID = $this->getLastProductID();
        $callback->bFinished      = $this->isFinished();
        $callback->bFirst         = $this->isFirst() || $this->getTasksExecuted() === 0;
        $callback->cURL           = $this->getUrl();
        $callback->cacheMisses    = $this->getCacheMisses();
        $callback->cacheHits      = $this->getCacheHits();
        $callback->lastCreated    = (new DateTime())->format('Y-m-d H:i:s');
        $callback->errorMessage   = $this->getError() ?? '';
        $callback->message        = $this->getMessage();

        return $callback;
    }

    public function getExportID(): int
    {
        return $this->exportID;
    }

    public function setExportID(int $exportID): AsyncCallback
    {
        $this->exportID = $exportID;

        return $this;
    }

    public function getQueueID(): int
    {
        return $this->queueID;
    }

    public function setQueueID(int $queueID): AsyncCallback
    {
        $this->queueID = $queueID;

        return $this;
    }

    public function getProductCount(): int
    {
        return $this->productCount;
    }

    public function setProductCount(int $productCount): AsyncCallback
    {
        $this->productCount = $productCount;
        return $this;
    }

    public function getTasksExecuted(): int
    {
        return $this->tasksExecuted;
    }

    public function setTasksExecuted(int $tasksExecuted): AsyncCallback
    {
        $this->tasksExecuted = $tasksExecuted;
        return $this;
    }

    public function getLastProductID(): int
    {
        return $this->lastProductID;
    }

    public function setLastProductID(int $lastProductID): AsyncCallback
    {
        $this->lastProductID = $lastProductID;

        return $this;
    }

    public function isFinished(): bool
    {
        return $this->isFinished;
    }

    public function setIsFinished(bool $isFinished): AsyncCallback
    {
        $this->isFinished = $isFinished;

        return $this;
    }

    public function isFirst(): bool
    {
        return $this->isFirst;
    }

    public function setIsFirst(bool $isFirst): AsyncCallback
    {
        $this->isFirst = $isFirst;

        return $this;
    }

    public function getCacheHits(): int
    {
        return $this->cacheHits;
    }

    public function setCacheHits(int $cacheHits): AsyncCallback
    {
        $this->cacheHits = $cacheHits;

        return $this;
    }

    public function getCacheMisses(): int
    {
        return $this->cacheMisses;
    }

    public function setCacheMisses(int $cacheMisses): AsyncCallback
    {
        $this->cacheMisses = $cacheMisses;

        return $this;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): AsyncCallback
    {
        $this->url = $url;

        return $this;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * @param string|null $error
     * @return AsyncCallback
     */
    public function setError(?string $error): AsyncCallback
    {
        $this->error = $error;

        return $this;
    }

    public function getMessage(): string
    {
        return $this->message ?? '';
    }

    public function setMessage(string $message): void
    {
        $this->message = $message;
    }
}
