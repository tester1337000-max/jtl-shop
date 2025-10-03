<?php

declare(strict_types=1);

namespace JTL\REST\Controllers;

use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\REST\Models\CartModel;
use League\Fractal\Manager;
use League\Route\RouteGroup;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class CartController
 * @package JTL\REST\Controllers
 */
class CartController extends AbstractController
{
    public function __construct(Manager $fractal, protected DbInterface $db, protected JTLCacheInterface $cache)
    {
        parent::__construct(CartModel::class, $fractal, $this->db, $this->cache);
    }

    /**
     * @inheritdoc
     * @OA\Get(
     *     path="/cart/{cartId}",
     *     tags={"cart"},
     *     summary="Get cart by ID",
     *     @OA\Parameter(
     *         description="ID of cart that needs to be fetched",
     *         in="path",
     *         name="cartId",
     *         required=true,
     *         @OA\Schema(
     *             format="int64",
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/CartModel"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID supplied"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Cart not found"
     *     )
     * )
     * @OA\Get(
     *     path="/cart",
     *     tags={"cart"},
     *     summary="List carts",
     *     description="List all carts",
     *     @OA\Response(
     *       response=200,
     *       description="A list with carts"
     *     ),
     *     @OA\Parameter(
     *       description="optional, default value is 3",
     *       name="limit",
     *       required=false,
     *       @OA\Schema(
     *         format="int64",
     *         type="integer"
     *       ),
     *       in="query"
     *   ),
     *   @OA\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     * @OA\Delete(
     *     path="/cart/{cartId}",
     *     description="Deletes a single cart based on the ID supplied",
     *     summary="Delete a single cart",
     *     operationId="deleteCart",
     *     tags={"cart"},
     *     @OA\Parameter(
     *         description="ID of cart to delete",
     *         in="path",
     *         name="cartId",
     *         required=true,
     *         @OA\Schema(
     *             format="int64",
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Cart deleted"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Categoty not found"
     *     )
     * )
     * @OA\Put(
     *     path="/cart/{cartId}",
     *     tags={"cart"},
     *     operationId="updateCart",
     *     summary="Update an existing cart",
     *     description="",
     *     @OA\Parameter(
     *         description="ID of cart to update",
     *         in="path",
     *         name="cartId",
     *         required=true,
     *         @OA\Schema(
     *             format="int64",
     *             type="integer"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Cart object that needs to be modified",
     *         @OA\JsonContent(ref="#/components/schemas/CartModel")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID supplied",
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Cart not found",
     *     ),
     *     @OA\Response(
     *         response=405,
     *         description="Validation exception",
     *     )
     * )
     * @OA\Post(
     *     path="/cart",
     *     tags={"cart"},
     *     operationId="createCart",
     *     summary="Create a new cart",
     *     description="",
     *     @OA\RequestBody(
     *         required=true,
     *         description="Cart object that needs to be created",
     *         @OA\JsonContent(ref="#/components/schemas/CartModel")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/CartModel")
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
     *         description="Create cart object",
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/CartModel")
     *     )
     * )
     */
    public function registerRoutes(RouteGroup $routeGroup): void
    {
        $routeGroup->get('/cart', $this->index(...));
        $routeGroup->get('/cart/{id}', $this->show(...));
        $routeGroup->put('/cart/{id}', $this->update(...));
        $routeGroup->post('/cart', $this->create(...));
        $routeGroup->delete('/cart/{id}', $this->delete(...));
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
            'id'                => 'integer',
            'cartID'            => 'integer',
            'customerID'        => 'integer',
            'deliveryAddressID' => 'integer',
            'paymentInfoID'     => 'integer',
        ];
    }

    /**
     * @inheritdoc
     */
    protected function updateRequestValidationRules(ServerRequestInterface $request): array
    {
        return [
            'id'                 => 'required|integer',
            'shippingMethodName' => 'max:255',
            'paymentMethodName'  => 'max:255',
            'orderNO'            => 'max:255',
            'shippingInfo'       => 'max:255',
            'trackingID'         => 'max:255',
            'logistics'          => 'max:255',
            'trackingURL'        => 'max:255',
        ];
    }
}
