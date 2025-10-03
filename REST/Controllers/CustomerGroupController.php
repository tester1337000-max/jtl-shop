<?php

declare(strict_types=1);

namespace JTL\REST\Controllers;

use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\Model\DataModelInterface;
use JTL\REST\Models\CustomerGroupModel;
use League\Fractal\Manager;
use League\Route\RouteGroup;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use stdClass;

/**
 * Class CustomerGroupController
 * @package JTL\REST\Controllers
 */
class CustomerGroupController extends AbstractController
{
    public function __construct(Manager $fractal, protected DbInterface $db, protected JTLCacheInterface $cache)
    {
        parent::__construct(CustomerGroupModel::class, $fractal, $this->db, $this->cache);
    }

    /**
     * @inheritdoc
     * @OA\Get(
     *   path="/customerGroup",
     *   tags={"customergroup"},
     *   summary="List customer groups",
     *   @OA\Response(
     *     response=200,
     *     description="A list with customer groups"
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="Customer groups not found"
     *   )
     * )
     * @OA\Get(
     *     path="/customerGroup/{customerGroupId}",
     *     tags={"customergroup"},
     *     description="Get a customer group by ID",
     *     summary="Get a customer group by ID",
     *     operationId="getCustomerGroupById",
     *     @OA\Parameter(
     *         name="customerGroupId",
     *         in="path",
     *         description="ID of customer group that needs to be fetched",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/CustomerGroupModel"),
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Customer group not found"
     *     )
     * )
     * @OA\Delete(
     *     path="/customerGroup/{customerGroupId}",
     *     description="Deletes a single customer group based on the ID supplied",
     *     summary="Delete a single customer group",
     *     operationId="deleteCustomerGroup",
     *     tags={"customergroup"},
     *     @OA\Parameter(
     *         description="ID of customer group to delete",
     *         in="path",
     *         name="customerGroupId",
     *         required=true,
     *         @OA\Schema(
     *             format="int64",
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Customer group deleted"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Customer group not found"
     *     )
     * )
     * @OA\Put(
     *     path="/customerGroup/{customerGroupId}",
     *     tags={"customergroup"},
     *     operationId="updateCustomerGroup",
     *     summary="Update an existing customergroup",
     *     description="",
     *     @OA\Parameter(
     *         description="ID of customer group to modify",
     *         in="path",
     *         name="customergroupId",
     *         required=true,
     *         @OA\Schema(
     *             format="int64",
     *             type="integer"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Customer group object that needs to be modified",
     *         @OA\JsonContent(ref="#/components/schemas/CustomerGroupModel")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID supplied",
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Customer group not found",
     *     ),
     *     @OA\Response(
     *         response=405,
     *         description="Validation exception",
     *     )
     * )
     * @OA\Post(
     *     path="/customerGroup",
     *     tags={"customergroup"},
     *     operationId="createCustomerGroup",
     *     summary="Create a new customergroup",
     *     description="",
     *     @OA\RequestBody(
     *         required=true,
     *         description="Customer group object that needs to be created",
     *         @OA\JsonContent(ref="#/components/schemas/CustomerGroupModel")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/CustomerGroupModel")
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
     *         description="Create customergroup object",
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/CustomerGroupModel")
     *     )
     * )
     */
    public function registerRoutes(RouteGroup $routeGroup): void
    {
        $routeGroup->get('/customerGroup', $this->index(...));
        $routeGroup->get('/customerGroup/{id}', $this->show(...));
        $routeGroup->put('/customerGroup/{id}', $this->update(...));
        $routeGroup->post('/customerGroup', $this->create(...));
        $routeGroup->delete('/customerGroup/{id}', $this->delete(...));
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
            // tmerkmalwert has no auto increment ID
            $id       = $this->db->getSingleInt(
                'SELECT MAX(kKundengruppe) AS newID FROM tkundengruppe',
                'newID'
            );
            $data->id = ++$id;
        }

        return $data;
    }

    /**
     * @inheritdoc
     */
    protected function createRequestValidationRules(ServerRequestInterface $request): array
    {
        return [
            'id'           => 'integer',
            'name'         => 'required|max:255',
            'discount'     => 'numeric',
            'default'      => 'in:Y,N',
            'shopLogin'    => 'in:Y,N',
            'net'          => 'integer',
            'localization' => 'array',
            'attributes'   => 'array',
        ];
    }
}
