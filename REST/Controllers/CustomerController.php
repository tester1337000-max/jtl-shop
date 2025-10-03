<?php

declare(strict_types=1);

namespace JTL\REST\Controllers;

use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\Model\DataModelInterface;
use JTL\REST\Models\CustomerModel;
use JTL\REST\Transformers\DataModelTransformer;
use JTL\Shop;
use League\Fractal\Manager;
use League\Fractal\Pagination\Cursor;
use League\Route\RouteGroup;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class CustomerController
 * @package JTL\REST\Controllers
 */
class CustomerController extends AbstractController
{
    public function __construct(Manager $fractal, protected DbInterface $db, protected JTLCacheInterface $cache)
    {
        parent::__construct(CustomerModel::class, $fractal, $this->db, $this->cache);
    }

    /**
     * @inheritdoc
     *  * @OA\Delete(
     *     path="/customer/{customerId}",
     *     description="Deletes a single customer based on the ID supplied",
     *     summary="Delete a single customer",
     *     operationId="deleteCustomer",
     *     tags={"customer"},
     *     @OA\Parameter(
     *         description="ID of customer to delete",
     *         in="path",
     *         name="customerId",
     *         required=true,
     *         @OA\Schema(
     *             format="int64",
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Customer deleted"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Customer not found"
     *     )
     * )
     * @OA\Get(
     *   path="/customer",
     *   tags={"customer"},
     *   summary="List customers",
     *   @OA\Response(
     *     response=200,
     *     description="A list with customers"
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="Customer not found"
     *   )
     * )
     * @OA\Get(
     *     path="/customer/{customerId}",
     *     tags={"customer"},
     *     description="Get a customer by ID",
     *     summary="Get a customer by ID",
     *     operationId="getCustomerById",
     *     @OA\Parameter(
     *         name="customerId",
     *         in="path",
     *         description="ID of customer that needs to be fetched",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/CustomerModel"),
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Customer not found"
     *     )
     * )
     * @OA\Put(
     *     path="/customer/{customerId}",
     *     tags={"customer"},
     *     operationId="updateCustomer",
     *     summary="Update an existing customer",
     *     description="",
     *     @OA\Parameter(
     *         description="ID of customer to modify",
     *         in="path",
     *         name="customerId",
     *         required=true,
     *         @OA\Schema(
     *             format="int64",
     *             type="integer"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Customer object that needs to be modified",
     *         @OA\JsonContent(ref="#/components/schemas/CustomerModel")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID supplied",
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Customer not found",
     *     ),
     *     @OA\Response(
     *         response=405,
     *         description="Validation exception",
     *     )
     * )
     * @OA\Post(
     *     path="/customer",
     *     tags={"customer"},
     *     operationId="createCustomer",
     *     summary="Create a new customer",
     *     description="",
     *     @OA\RequestBody(
     *         required=true,
     *         description="Customer object that needs to be created",
     *         @OA\JsonContent(ref="#/components/schemas/CustomerModel")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/CustomerModel")
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
     *         description="Create customer object",
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/CustomerModel")
     *     )
     * )
     */
    public function registerRoutes(RouteGroup $routeGroup): void
    {
        $routeGroup->get('/customer', $this->index(...));
        $routeGroup->get('/customer/{id}', $this->show(...));
        $routeGroup->put('/customer/{id}', $this->update(...));
        $routeGroup->post('/customer', $this->create(...));
        $routeGroup->delete('/customer/{id}', $this->delete(...));
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
        } catch (\Exception) {
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
        $res = $all->map(function (\stdClass $e) {
            /** @var CustomerModel $instance */
            $instance = new $this->modelClass($this->db);
            $instance->fill($e);
            $instance->setWasLoaded(true);

            return $instance;
        });
        foreach ($res as $customer) {
            $this->decryptCustomerData($customer);
        }

        return $this->respondWithCollection($res, new DataModelTransformer(), [], $cursor);
    }

    private function decryptCustomerData(CustomerModel $customer): void
    {
        $cryptoService = Shop::Container()->getCryptoService();

        $customer->setSurname(\trim($cryptoService->decryptXTEA($customer->getSurname() ?? '')));
        $customer->setCompany(\trim($cryptoService->decryptXTEA($customer->getCompany() ?? '')));
        $customer->setAdditional(\trim($cryptoService->decryptXTEA($customer->getAdditional() ?? '')));
        $customer->setStreet(\trim($cryptoService->decryptXTEA($customer->getStreet() ?? '')));
        $customer->setPassword('');
    }

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
        } catch (\Exception) {
            return $this->sendNotFoundResponse();
        }
        return $this->respondWithModel($result);
    }

    /**
     * @inheritdoc
     */
    protected function createRequestValidationRules(ServerRequestInterface $request): array
    {
        return [
            'id'                => 'integer',
            'customerGroupID'   => 'integer',
            'languageID'        => 'integer',
            'customerNO'        => 'max:255',
            'firstname'         => 'max:255',
            'surname'           => 'max:255',
            'company'           => 'max:255',
            'additional'        => 'max:255',
            'street'            => 'max:255',
            'streetNO'          => 'max:255',
            'additionalAddress' => 'max:255',
            'zip'               => 'max:255',
            'city'              => 'max:255',
            'state'             => 'max:255',
            'country'           => 'max:255',
            'tel'               => 'max:255',
            'mobile'            => 'max:255',
            'fax'               => 'max:255',
            'mail'              => 'max:255',
            'ustidnr'           => 'max:255',
            'www'               => 'max:255',
            'loginAttempts'     => 'integer',
            'locked'            => 'in:Y,N',
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
