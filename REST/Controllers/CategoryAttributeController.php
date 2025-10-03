<?php

declare(strict_types=1);

namespace JTL\REST\Controllers;

use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\REST\Models\CategoryAttributeModel;
use League\Fractal\Manager;
use League\Route\RouteGroup;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class CategoryAttributeController
 * @package JTL\REST\Controllers
 */
class CategoryAttributeController extends AbstractController
{
    public function __construct(Manager $fractal, protected DbInterface $db, protected JTLCacheInterface $cache)
    {
        parent::__construct(CategoryAttributeModel::class, $fractal, $this->db, $this->cache);
    }

    /**
     * @OA\Get(
     *   path="/categoryattribute",
     *   tags={"categoryattribute"},
     *   summary="List category attributes",
     *      @OA\Parameter(
     *        description="optional, default value is 10",
     *        name="limit",
     *        required=false,
     *        @OA\Schema(
     *          format="int64",
     *          type="integer"
     *        ),
     *        in="query"
     *     ),
     *   @OA\Response(
     *     response=200,
     *     description="A list with category attributes"
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="Category attributes not found"
     *   )
     * )
     * @OA\Get(
     *     path="/categoryattribute/{categoryattributeId}",
     *     tags={"categoryattribute"},
     *     description="Get a category attribute by ID",
     *     summary="Get a category attribute by ID",
     *     operationId="getCategoryattributeById",
     *     @OA\Parameter(
     *         name="categoryattributeId",
     *         in="path",
     *         description="ID of category attribute that needs to be fetched",
     *         required=true,
     *         @OA\Schema(
     *             format="int64",
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/CategoryAttributeModel"),
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Category attribute not found"
     *     )
     * )
     */

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

    public function registerRoutes(RouteGroup $routeGroup): void
    {
        $routeGroup->get('categoryattribute', $this->index(...));
        $routeGroup->get('categoryattribute/{id}', $this->show(...));
        $routeGroup->put('categoryattribute/{id}', $this->update(...));
        $routeGroup->post('categoryattribute', $this->create(...));
        $routeGroup->delete('categoryattribute/{id}', $this->delete(...));
    }

    /**
     * @inheritdoc
     */
    protected function createRequestValidationRules(ServerRequestInterface $request): array
    {
        return [
            'id'         => 'required|integer',
            'categoryID' => 'required|integer',
            'name'       => 'required|max:255',
            'value'      => 'required|max:255'
        ];
    }
}
