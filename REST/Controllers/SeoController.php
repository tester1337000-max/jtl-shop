<?php

declare(strict_types=1);

namespace JTL\REST\Controllers;

use Exception;
use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\Model\DataModelInterface;
use JTL\REST\Models\SeoModel;
use League\Fractal\Manager;
use League\Route\RouteGroup;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class SeoController
 * @package JTL\REST\Controllers
 * @todo: table has no primary keys, models cannot be uniquely loaded
 */
class SeoController extends AbstractController
{
    public function __construct(Manager $fractal, protected DbInterface $db, protected JTLCacheInterface $cache)
    {
        parent::__construct(SeoModel::class, $fractal, $this->db, $this->cache);
    }

    /**
     * @OA\Get(
     *     path="/seo/{seoId}",
     *     tags={"seo"},
     *     description="Get a single seo, use slug as ID",
     *     summary="Get a single seo",
     *    @OA\Parameter(
     *          description="ID of seo to modify",
     *          in="path",
     *          name="seoId",
     *          required=true,
     *          @OA\Schema(
     *              format="text",
     *              type="string"
     *          )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/SeoModel"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID supplied"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="seo not found"
     *     )
     * )
     * @OA\Get(
     *     path="/seo",
     *     tags={"seo"},
     *     description="Get a list of seos",
     *     summary="Get a list of seos",
     *    @OA\Parameter(
     *         description="optional, default value is 10",
     *         name="limit",
     *         required=false,
     *         @OA\Schema(
     *           format="int64",
     *           type="integer"
     *         ),
     *         in="query"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="A list of seos"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID supplied"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No seos found"
     *     )
     * )
     * @OA\Delete(
     *     path="/seo/{seoId}",
     *     description="Deletes a single seo based on the slug supplied",
     *     summary="Delete a single seo",
     *     operationId="deleteseo",
     *     tags={"seo"},
     *     @OA\Parameter(
     *         description="ID of seo to delete",
     *         in="path",
     *         name="seoId",
     *         required=true,
     *         @OA\Schema(
     *             format="text",
     *             type="string"
     *         )
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="seo deleted"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="seo not found"
     *     )
     * )
     * @OA\Put(
     *     path="/seo/{seoId}",
     *     tags={"seo"},
     *     operationId="updateseo",
     *     summary="Update an existing seo",
     *     description="",
     *     @OA\Parameter(
     *         description="ID of seo to modify",
     *         in="path",
     *         name="seoId",
     *         required=true,
     *         @OA\Schema(
     *             format="text",
     *             type="string"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="seo object that needs to be modified",
     *         @OA\JsonContent(ref="#/components/schemas/SeoModel")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID supplied",
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="seo not found",
     *     ),
     *     @OA\Response(
     *         response=405,
     *         description="Validation exception",
     *     )
     * )
     * @OA\Post(
     *     path="/seo",
     *     tags={"seo"},
     *     operationId="createseo",
     *     summary="Create a new seo",
     *     description="",
     *     @OA\RequestBody(
     *         required=true,
     *         description="Order object that needs to be created",
     *         @OA\JsonContent(ref="#/components/schemas/SeoModel")
     *     ),
     *     @OA\Response(
     *         response=405,
     *         description="Validation exception",
     *     )
     * )
     */

    /**
     * @inheritdoc
     */
    public function registerRoutes(RouteGroup $routeGroup): void
    {
        $routeGroup->get('/seo', $this->index(...));
        $routeGroup->get('/seo/{id}', $this->show(...));
        $routeGroup->put('/seo/{id}', $this->update(...));
        $routeGroup->post('/seo', $this->create(...));
        $routeGroup->delete('/seo/{id}', $this->delete(...));
    }

    /**
     * @inheritdoc
     */
    public function show(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $id = ($params['id'] ?? 0);
        try {
            $class    = $this->modelClass;
            $instance = (new $class($this->db));
            if (\property_exists($instance, 'full')) {
                $instance->full = $this->full;
            }
            $result = $instance->init(['slug' => $id], DataModelInterface::ON_NOTEXISTS_FAIL);
        } catch (Exception) {
            return $this->sendNotFoundResponse();
        }
        return $this->respondWithModel($result);
    }

    /**
     * @inheritdoc
     */
    public function create(ServerRequestInterface $request, array $params): ResponseInterface
    {
        return $this->setStatusCode(501)->respondWithArray([]);
    }

    /**
     * @inheritdoc
     */
    public function delete(ServerRequestInterface $request, array $params): ResponseInterface
    {
        return $this->setStatusCode(501)->respondWithArray([]);
    }

    /**
     * @inheritdoc
     */
    public function update(ServerRequestInterface $request, array $params): ResponseInterface
    {
        return $this->setStatusCode(501)->respondWithArray([]);
    }

    /**
     * @inheritdoc
     */
    protected function createRequestValidationRules(ServerRequestInterface $request): array
    {
        return [
            'slug'       => 'required|max:255',
            'type'       => 'required|max:255',
            'id'         => 'required|integer',
            'languageID' => 'required|integer'
        ];
    }

    /**
     * @inheritdoc
     */
    protected function updateRequestValidationRules(ServerRequestInterface $request): array
    {
        return [
            'slug'       => 'required|max:255',
            'type'       => 'required|max:255',
            'languageID' => 'required|integer'
        ];
    }
}
