<?php

declare(strict_types=1);

namespace JTL\REST\Controllers;

use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\REST\Models\CartItemModel;
use League\Fractal\Manager;
use League\Route\RouteGroup;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class CartItemController
 * @package JTL\REST\Controllers
 */
class CartItemController extends AbstractController
{
    public function __construct(Manager $fractal, protected DbInterface $db, protected JTLCacheInterface $cache)
    {
        parent::__construct(CartItemModel::class, $fractal, $this->db, $this->cache);
    }

    /**
     * @OA\Get(
     *     path="/cartitem/{cartItemId}",
     *     tags={"cartitem"},
     *     summary="Get cartItem by ID",
     *     @OA\Parameter(
     *         description="ID of cartitem that needs to be fetched",
     *         in="path",
     *         name="cartItemId",
     *         required=true,
     *         @OA\Schema(
     *             format="int64",
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/CartItemModel"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID supplied"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Cartitem not found"
     *     )
     * )
     * @OA\Get(
     *     path="/cartitem",
     *     tags={"cartitem"},
     *     summary="List cartitems",
     *     description="List all cartitems",
     *     @OA\Response(
     *       response=200,
     *       description="A list with cartitems"
     *     ),
     *     @OA\Parameter(
     *       description="optional, default value is 10",
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
     */

    /**
     * @inheritdoc
     */
    public function registerRoutes(RouteGroup $routeGroup): void
    {
        $routeGroup->get('/cartitem', $this->index(...));
        $routeGroup->get('/cartitem/{id}', $this->show(...));
        $routeGroup->put('/cartitem/{id}', $this->update(...));
        $routeGroup->post('/cartitem', $this->create(...));
        $routeGroup->delete('/cartitem/{id}', $this->delete(...));
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
    public function update(ServerRequestInterface $request, array $params): ResponseInterface
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
    protected function createRequestValidationRules(ServerRequestInterface $request): array
    {
        return [
            'id'                 => 'integer',
            'cartID'             => 'integer',
            'productID'          => 'integer',
            'shippingClassID'    => 'integer',
            'name'               => 'max:255',
            'deliveryState'      => 'max:255',
            'sku'                => 'max:255',
            'unit'               => 'max:255',
            'netSinglePrice'     => 'numeric',
            'price'              => 'numeric',
            'taxPercent'         => 'numeric',
            'qty'                => 'numeric',
            'posType'            => 'integer',
            'notice'             => 'max:255',
            'unique'             => 'max:255',
            'responsibility'     => 'max:255',
            'configItemID'       => 'integer',
            'orderItemID'        => 'integer',
            'stockBefore'        => 'numeric',
            'longestMinDelivery' => 'integer',
            'longestMaxDelivery' => 'integer',
        ];
    }

    /**
     * @inheritdoc
     */
    protected function updateRequestValidationRules(ServerRequestInterface $request): array
    {
        return [
            'id' => 'required|integer'
        ];
    }
}
