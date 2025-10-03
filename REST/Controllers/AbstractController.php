<?php

declare(strict_types=1);

namespace JTL\REST\Controllers;

use Exception;
use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\Model\DataModelInterface;
use JTL\REST\Transformers\DataModelTransformer;
use League\Fractal\Manager;
use League\Fractal\Pagination\Cursor;
use League\Route\RouteGroup;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Rakit\Validation\Validator;
use stdClass;

use function Functional\first;

/**
 * Class AbstractController
 * @package JTL\REST\Controllers
 */
abstract class AbstractController
{
    use ResponseTrait;

    protected bool $full = false;

    /**
     * @var class-string<DataModelInterface>|'null'
     */
    protected string $modelClass;

    /**
     * @var string[]
     */
    protected array $cacheTags = [];

    protected ?string $cacheID = null;

    protected ?string $primaryKeyName = null;

    protected Validator $validator;

    /**
     * @param class-string<DataModelInterface>|'null' $modelClass
     */
    public function __construct(
        string $modelClass,
        Manager $fractal,
        protected DbInterface $db,
        protected JTLCacheInterface $cache
    ) {
        $this->modelClass = $modelClass;
        $this->validator  = new Validator();
        $this->setFractal($fractal);
    }

    /**
     * @param RouteGroup $routeGroup
     */
    abstract public function registerRoutes(RouteGroup $routeGroup): void;

    /**
     * @param ServerRequestInterface $request
     * @param array<string, mixed>   $params
     * @return ResponseInterface
     */
    public function index(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $cursor         = null;
        $limit          = \max(1, (int)($request->getQueryParams()['limit'] ?? 10));
        $currentCursor  = (int)($request->getQueryParams()['cursor'] ?? 0);
        $previousCursor = (int)($request->getQueryParams()['previous'] ?? 0);
        /** @var DataModelInterface $model */
        $model = new $this->modelClass($this->db);
        try {
            $primary = $this->primaryKeyName ?? $model->getKeyName(true);
        } catch (Exception) {
            $primary = '';
        }
        if ($primary !== '') {
            if ($currentCursor > 0) {
                $all = $this->db->getCollection(
                    'SELECT * FROM ' . $model->getTableName()
                    . ' WHERE ' . $primary . ' > :lid
                        LIMIT :lmt',
                    ['lid' => $currentCursor, 'lmt' => $limit]
                );
            } else {
                $all = $this->db->getCollection(
                    'SELECT * FROM ' . $model->getTableName() . ' LIMIT :lmt',
                    ['lmt' => $limit]
                );
            }
            $newCursor = $all->last()->$primary ?? -1;
            $cursor    = new Cursor($currentCursor, $previousCursor, $newCursor, $all->count());
        } else {
            // tables like tartikelwarenlager do not have a unique identifier per row but combined keys
            $all = $this->db->getCollection(
                'SELECT * FROM ' . $model->getTableName() . ' LIMIT :lmt',
                ['lmt' => $limit]
            );
        }
        $res = $all->map(function (stdClass $e) {
            /** @var DataModelInterface $instance */
            $instance = new $this->modelClass($this->db);
            $instance->fill($e);
            $instance->setWasLoaded(true);

            return $instance;
        });

        return $this->respondWithCollection($res, new DataModelTransformer(), [], $cursor);
    }

    /**
     * @param ServerRequestInterface $request
     * @param array<string, mixed>   $params
     * @return ResponseInterface
     */
    public function show(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $id = (int)($params['id'] ?? 0);
        try {
            $class    = $this->modelClass;
            $instance = (new $class($this->db));
            if (\property_exists($instance, 'full')) {
                $instance->full = $this->full;
            }
            $result = $instance->init(['id' => $id], DataModelInterface::ON_NOTEXISTS_FAIL);
        } catch (Exception) {
            return $this->sendNotFoundResponse();
        }

        return $this->respondWithModel($result);
    }

    /**
     * @param ServerRequestInterface $request
     * @param array<string, mixed>   $params
     * @return ResponseInterface
     */
    public function update(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $id                = (int)($params['id'] ?? 0);
        $validatorResponse = $this->validateRequest($request, $this->updateRequestValidationRules($request));
        if ($validatorResponse !== true) {
            return $this->sendInvalidFieldResponse($validatorResponse);
        }
        try {
            $class  = $this->modelClass;
            $result = $class::load(['id' => $id], $this->db, DataModelInterface::ON_NOTEXISTS_FAIL);
        } catch (Exception) {
            return $this->sendNotFoundResponse('Item with id ' . $id . ' does not exist');
        }
        try {
            $result = $this->updateFromRequest($result, $request);
        } catch (Exception $e) {
            return $this->sendCustomResponse(500, 'Error occurred: ' . $e->getMessage());
        }
        if (\array_key_exists('lastModified', $result->getAttributes())) {
            $result->lastModified = 'now()';
        }
        try {
            $result->save();
            $this->updatedItem($result);
        } catch (Exception $e) {
            return $this->sendCustomResponse(500, $e->getMessage());
        }

        return $this->respondWithModel($result);
    }

    /**
     * @param DataModelInterface     $model
     * @param ServerRequestInterface $request
     * @return DataModelInterface
     */
    protected function updateFromRequest(DataModelInterface $model, ServerRequestInterface $request): DataModelInterface
    {
        /** @var array<mixed> $body */
        $body = $this->getRequestData($request)['body'];
        $this->hydrateModel($body, $model->getAttributes(), $model);

        return $model;
    }

    /**
     * @param ServerRequestInterface $request
     * @param array<mixed>           $params
     * @return ResponseInterface
     */
    public function create(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $validatorResponse = $this->validateRequest($request, $this->createRequestValidationRules($request));
        if ($validatorResponse !== true) {
            return $this->sendInvalidFieldResponse($validatorResponse);
        }
        try {
            $result = $this->createItem($request);
            $this->createdItem($result);
        } catch (\InvalidArgumentException) {
            return $this->setStatusCode(400)->respondWithArray([]);
        } catch (Exception $e) {
            return $this->sendCustomResponse(500, 'Error occurred creating item - duplicate ID? ' . $e->getMessage());
        }

        return $this->setStatusCode(201)->respondWithModel($result);
    }

    /**
     * @param ServerRequestInterface $request
     * @return DataModelInterface
     * @throws Exception
     */
    protected function createItem(ServerRequestInterface $request): DataModelInterface
    {
        /** @var DataModelInterface $model */
        $model = new $this->modelClass($this->db);
        $data  = new stdClass();
        /** @var array<mixed> $body */
        $body = $this->getRequestData($request)['body'];
        $this->hydrateModel($body, $model->getAttributes(), $data);

        return $model::create($this->getCreateBaseData($request, $model, $data), $this->db);
    }

    /**
     * @param ServerRequestInterface $request
     * @param array<string, mixed>   $params
     * @return ResponseInterface
     */
    public function delete(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $id = (int)($params['id'] ?? 0);
        try {
            $result = new $this->modelClass($this->db);
            /** @var DataModelInterface $result */
            $result = $result->init(['id' => $id], DataModelInterface::ON_NOTEXISTS_FAIL);
        } catch (Exception) {
            return $this->sendNotFoundResponse('Item with id ' . $id . ' does not exist');
        }
        /** @var DataModelInterface $result */
        try {
            $result->delete();
            $this->deletedItem($result);
        } catch (Exception $e) {
            return $this->sendCustomResponse(500, 'Error occurred deleting item: ' . $e->getMessage());
        }

        return $this->sendEmptyResponse();
    }

    /**
     * @param ServerRequestInterface $request
     * @param DataModelInterface     $model
     * @param stdClass               $data
     * @return stdClass
     */
    protected function getCreateBaseData(
        ServerRequestInterface $request,
        DataModelInterface $model,
        stdClass $data
    ): stdClass {
        if (\array_key_exists('lastModified', $model->getAttributes())) {
            $data->lastModified = $data->lastModified ?? 'now()';
        }
        if (\array_key_exists('dErstellt', $model->getAttributes())) {
            $data->dErstellt = $data->dErstellt ?? 'now()';
        }

        return $data;
    }

    /**
     * Validate HTTP request against the rules
     *
     * @param ServerRequestInterface $request
     * @param array<string, string>  $rules
     * @return true|array<mixed>
     */
    protected function validateRequest(ServerRequestInterface $request, array $rules): true|array
    {
        /** @var array<mixed> $body */
        $body       = $this->getRequestData($request)['body'];
        $validation = $this->validator->validate($body, $rules);
        if ($validation->passes()) {
            return true;
        }

        return \array_map(static fn($value) => first($value), $validation->errors()->toArray());
    }

    /**
     * @param DataModelInterface $item
     */
    protected function createdItem(DataModelInterface $item): void
    {
        if ($this->cacheID !== null) {
            $this->cache->flush($this->cacheID);
        }
        if (\count($this->cacheTags) > 0) {
            $this->cache->flushTags($this->cacheTags);
        }
    }

    /**
     * @param DataModelInterface $item
     */
    protected function updatedItem(DataModelInterface $item): void
    {
        if ($this->cacheID !== null) {
            $this->cache->flush($this->cacheID);
        }
        if (\count($this->cacheTags) > 0) {
            $this->cache->flushTags($this->cacheTags);
        }
    }

    /**
     * @param DataModelInterface $item
     */
    protected function deletedItem(DataModelInterface $item): void
    {
        if ($this->cacheID !== null) {
            $this->cache->flush($this->cacheID);
        }
        if (\count($this->cacheTags) > 0) {
            $this->cache->flushTags($this->cacheTags);
        }
    }

    /**
     * @param ServerRequestInterface $request
     * @return array<string, string>
     */
    protected function createRequestValidationRules(ServerRequestInterface $request): array
    {
        return [];
    }

    /**
     * @param ServerRequestInterface $request
     * @return array<string, string>
     */
    protected function updateRequestValidationRules(ServerRequestInterface $request): array
    {
        return [];
    }

    /**
     * @param ServerRequestInterface $request
     * @return array<string, string|array<string, string>>
     */
    protected function getRequestData(ServerRequestInterface $request): array
    {
        return match ($request->getMethod()) {
            'POST'   => ['body' => $request->getParsedBody()],
            'GET'    => ['queryParams' => $request->getQueryParams(), 'id' => $request->getAttribute('id')],
            'DELETE' => $request->getAttribute('id'),
            'PUT'    => ['body' => \array_merge($request->getParsedBody(), ['id' => $request->getAttribute('id')])],
            default  => [],
        };
    }

    /**
     * @param array<mixed>                $body
     * @param array<mixed>                $modelAttributes
     * @param DataModelInterface|stdClass $data
     */
    protected function hydrateModel(array $body, array $modelAttributes, DataModelInterface|stdClass $data): void
    {
        foreach ($body as $attr => $value) {
            if (\array_key_exists($attr, $modelAttributes)) {
                $data->$attr = $value;
            }
        }
    }
}
