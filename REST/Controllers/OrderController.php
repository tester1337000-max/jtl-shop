<?php

declare(strict_types=1);

namespace JTL\REST\Controllers;

use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\Model\DataModelInterface;
use JTL\REST\Models\OrderModel;
use League\Fractal\Manager;
use League\Route\RouteGroup;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use stdClass;

/**
 * Class OrderController
 * @package JTL\REST\Controllers
 */
class OrderController extends AbstractController
{
    public function __construct(Manager $fractal, protected DbInterface $db, protected JTLCacheInterface $cache)
    {
        parent::__construct(OrderModel::class, $fractal, $this->db, $this->cache);
    }

    /**
     * @inheritdoc
     * @OA\Get(
     *     path="/order/{orderId}",
     *     tags={"order"},
     *     description="Get a single order",
     *     summary="Get a single order",
     *     @OA\Parameter(
     *         name="orderId",
     *         in="path",
     *         description="ID of order that needs to be fetched",
     *         required=true,
     *         @OA\Schema(
     *             format="int64",
     *             type="integer"
     *         )
     *     ),
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
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/OrderModel"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID supplied"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Order not found"
     *     )
     * )
     * @OA\Get(
     *     path="/order",
     *     tags={"order"},
     *     description="Get a list of orders",
     *     summary="Get a list of orders",
     *     @OA\Response(
     *         response=200,
     *         description="A list of orders"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID supplied"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No orders found"
     *     )
     * )
     * @OA\Delete(
     *     path="/order/{orderId}",
     *     description="Deletes a single order based on the ID supplied",
     *     summary="Delete a single order",
     *     operationId="deleteOrder",
     *     tags={"order"},
     *     @OA\Parameter(
     *         description="ID of order to delete",
     *         in="path",
     *         name="orderId",
     *         required=true,
     *         @OA\Schema(
     *             format="int64",
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Order deleted"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Order not found"
     *     )
     * )
     * @OA\Put(
     *     path="/order/{orderId}",
     *     tags={"order"},
     *     operationId="updateOrder",
     *     summary="Update an existing order",
     *     description="",
     *     @OA\Parameter(
     *         description="ID of order to modify",
     *         in="path",
     *         name="orderId",
     *         required=true,
     *         @OA\Schema(
     *             format="int64",
     *             type="integer"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Order object that needs to be modified",
     *         @OA\JsonContent(ref="#/components/schemas/OrderModel")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID supplied",
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Order not found",
     *     ),
     *     @OA\Response(
     *         response=405,
     *         description="Validation exception",
     *     )
     * )
     * @OA\Post(
     *     path="/order",
     *     tags={"order"},
     *     operationId="createOrder",
     *     summary="Create a new order",
     *     description="",
     *     @OA\RequestBody(
     *         required=true,
     *         description="Order object that needs to be created",
     *         @OA\JsonContent(ref="#/components/schemas/OrderModel")
     *     ),
     *     @OA\Response(
     *         response=405,
     *         description="Validation exception",
     *     )
     * )
     */
    public function registerRoutes(RouteGroup $routeGroup): void
    {
        $routeGroup->get('/order', $this->index(...));
        $routeGroup->get('/order/{id}', $this->show(...));
        $routeGroup->put('/order/{id}', $this->update(...));
        $routeGroup->post('/order', $this->create(...));
        $routeGroup->delete('/order/{id}', $this->delete(...));
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
    protected function getCreateBaseData(
        ServerRequestInterface $request,
        DataModelInterface $model,
        stdClass $data
    ): stdClass {
        $data = parent::getCreateBaseData($request, $model, $data);
        if (!isset($data->id)) {
            // tkategorie has no auto increment ID
            $lastID   = $this->db->getSingleInt(
                'SELECT MAX(kBestellung) AS newID FROM tbestellung',
                'newID'
            );
            $data->id = ++$lastID;
        }

        return $data;
    }

    /**
     * @inheritdoc
     */
    protected function createRequestValidationRules(ServerRequestInterface $request): array
    {
        return [
            'id'                 => 'integer',
            'cartID'             => 'integer',
            'customerID'         => 'integer',
            'deliveryAddressID'  => 'integer',
            'billingAddressID'   => 'integer',
            'paymentMethodID'    => 'integer',
            'shippingMethodID'   => 'integer',
            'languageID'         => 'integer',
            'currencyID'         => 'integer',
            'shippingMethodName' => 'max:255',
            'paymentMethodName'  => 'max:255',
            'orderNO'            => 'max:255',
            'shippingInfo'       => 'max:255',
            'trackingID'         => 'max:255',
            'logistics'          => 'max:255',
            'trackingURL'        => 'max:255',
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
