<?php

declare(strict_types=1);

namespace JTL\REST\Controllers;

use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\REST\Models\ShippingMethodModel;
use League\Fractal\Manager;
use League\Route\RouteGroup;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class ShippingMethodController
 * @package JTL\REST\Controllers
 */
class ShippingMethodController extends AbstractController
{
    public function __construct(Manager $fractal, protected DbInterface $db, protected JTLCacheInterface $cache)
    {
        parent::__construct(ShippingMethodModel::class, $fractal, $this->db, $this->cache);
    }

    /**
     * @inheritdoc
     * @OA\Get(
     *     path="/shippingmethod/{shippingmethodId}",
     *     tags={"shippingmethod"},
     *     summary="Get shipping method by ID",
     *     @OA\Parameter(
     *         description="ID of shipping method that needs to be fetched",
     *         in="path",
     *         name="shippingmethodId",
     *         required=true,
     *         @OA\Schema(
     *             format="int64",
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/ShippingMethodModel"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID supplied"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Shipping method not found"
     *     )
     * )
     * @OA\Get(
     *   path="/shippingmethod",
     *   tags={"shippingmethod"},
     *   summary="List shippingmethods",
     *   description="List all shippingmethods",
     *   @OA\Response(
     *     response=200,
     *     description="A list with shippingmethods"
     *   ),
     *   @OA\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     * @OA\Delete(
     *     path="/shippingmethod/{shippingmethodId}",
     *     description="Deletes a single shippingmethod based on the ID supplied",
     *     summary="Delete a single shippingmethod",
     *     operationId="deleteShippingMethod",
     *     tags={"shippingmethod"},
     *     @OA\Parameter(
     *         description="ID of shippingmethod to delete",
     *         in="path",
     *         name="shippingmethodId",
     *         required=true,
     *         @OA\Schema(
     *             format="int64",
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Shipping method deleted"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Categoty not found"
     *     )
     * )
     * @OA\Put(
     *     path="/shippingmethod/{shippingmethodId}",
     *     tags={"shippingmethod"},
     *     operationId="updateShippingMethod",
     *     summary="Update an existing shippingmethod",
     *     description="",
     *     @OA\Parameter(
     *         description="ID of shippingmethod to update",
     *         in="path",
     *         name="shippingmethodId",
     *         required=true,
     *         @OA\Schema(
     *             format="int64",
     *             type="integer"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Shipping method object that needs to be modified",
     *         @OA\JsonContent(ref="#/components/schemas/ShippingMethodModel")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID supplied",
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Shipping method not found",
     *     ),
     *     @OA\Response(
     *         response=405,
     *         description="Validation exception",
     *     )
     * )
     * @OA\Post(
     *     path="/shippingmethod",
     *     tags={"shippingmethod"},
     *     operationId="createShippingMethod",
     *     summary="Create a new shippingmethod",
     *     description="",
     *     @OA\RequestBody(
     *         required=true,
     *         description="Shipping method object that needs to be created",
     *         @OA\JsonContent(ref="#/components/schemas/ShippingMethodModel")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/ShippingMethodModel")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="An array of validation errors",
     *         @OA\JsonContent(
     *              type="object",
     *              @OA\Property(
     *                  property="invalid_fields",
     *                  type="object",
     *                  @OA\Property(property="name",type="string",example="The Name is required"),
     *                  @OA\Property(property="description",type="string",example="The Description maximum is 255")
     *              )
     *          ),
     *     ),
     *     @OA\RequestBody(
     *         description="Create shippingmethod object",
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/ShippingMethodModel")
     *     )
     * )
     */
    public function registerRoutes(RouteGroup $routeGroup): void
    {
        $routeGroup->get('/shippingmethod', $this->index(...));
        $routeGroup->get('/shippingmethod/{id}', $this->show(...));
        $routeGroup->put('/shippingmethod/{id}', $this->update(...));
        $routeGroup->post('/shippingmethod', $this->create(...));
        $routeGroup->delete('/shippingmethod/{id}', $this->delete(...));
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
            'id' => 'integer'
        ];
    }

    /**
     * @inheritdoc
     */
    protected function updateRequestValidationRules(ServerRequestInterface $request): array
    {
        return [
            'id'   => 'required|integer',
            'name' => 'max:255'
        ];
    }
}
