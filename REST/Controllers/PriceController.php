<?php

declare(strict_types=1);

namespace JTL\REST\Controllers;

use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\REST\Models\PriceModel;
use League\Fractal\Manager;
use League\Route\RouteGroup;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class PriceController
 * @package JTL\REST\Controllers
 */
class PriceController extends AbstractController
{
    public function __construct(Manager $fractal, protected DbInterface $db, protected JTLCacheInterface $cache)
    {
        parent::__construct(PriceModel::class, $fractal, $this->db, $this->cache);
    }

    /**
     * @OA\Get(
     *     path="/price/{priceId}",
     *     tags={"price"},
     *     description="Get a single price",
     *     summary="Get a single price",
     *    @OA\Parameter(
     *        description="optional, default value is 10",
     *        name="limit",
     *        required=false,
     *        @OA\Schema(
     *          format="int64",
     *          type="integer"
     *        ),
     *        in="query"
     *    ),
     *    @OA\Parameter(
     *        description="ID of price to be fetched",
     *        name="priceId",
     *        required=true,
     *        @OA\Schema(
     *          format="int64",
     *          type="integer"
     *        ),
     *        in="path"
     *    ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/PriceModel"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID supplied"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Price not found"
     *     )
     * )
     * @OA\Get(
     *     path="/price",
     *     tags={"price"},
     *     description="Get a list of prices",
     *     summary="Get a list of prices",
     *     @OA\Response(
     *         response=200,
     *         description="A list of prices"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID supplied"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No prices found"
     *     )
     * )
     * @OA\Delete(
     *     path="/price/{priceId}",
     *     description="Deletes a single price based on the ID supplied",
     *     summary="Delete a single price",
     *     operationId="deletePrice",
     *     tags={"price"},
     *     @OA\Parameter(
     *         description="ID of price to delete",
     *         in="path",
     *         name="priceId",
     *         required=true,
     *         @OA\Schema(
     *             format="int64",
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Price deleted"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Price not found"
     *     )
     * )
     * @OA\Put(
     *     path="/price/{priceId}",
     *     tags={"price"},
     *     operationId="updatePrice",
     *     summary="Update an existing price",
     *     description="",
     *     @OA\Parameter(
     *         description="ID of price to modify",
     *         in="path",
     *         name="priceId",
     *         required=true,
     *         @OA\Schema(
     *             format="int64",
     *             type="integer"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Price object that needs to be modified",
     *         @OA\JsonContent(ref="#/components/schemas/PriceModel")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID supplied",
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Price not found",
     *     ),
     *     @OA\Response(
     *         response=405,
     *         description="Validation exception",
     *     )
     * )
     * @OA\Post(
     *     path="/price",
     *     tags={"price"},
     *     operationId="createPrice",
     *     summary="Create a new price",
     *     description="",
     *     @OA\RequestBody(
     *         required=true,
     *         description="Order object that needs to be created",
     *         @OA\JsonContent(ref="#/components/schemas/PriceModel")
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
        $routeGroup->get('/price', $this->index(...));
        $routeGroup->get('/price/{id}', $this->show(...));
        $routeGroup->put('/price/{id}', $this->update(...));
        $routeGroup->post('/price', $this->create(...));
        $routeGroup->delete('/price/{id}', $this->delete(...));
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
            'id'         => 'integer',
            'productID'  => 'required|integer',
            'customerID' => 'integer'
        ];
    }

    /**
     * @inheritdoc
     */
    protected function updateRequestValidationRules(ServerRequestInterface $request): array
    {
        return [
            'id'         => 'required|integer',
            'productID'  => 'required|integer',
            'customerID' => 'integer'
        ];
    }
}
