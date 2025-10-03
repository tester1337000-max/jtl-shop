<?php

declare(strict_types=1);

namespace JTL\REST\Controllers;

use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\REST\Models\AttributeModel;
use League\Fractal\Manager;
use League\Route\RouteGroup;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class AttributeController
 * @package JTL\REST\Controllers
 */
class AttributeController extends AbstractController
{
    public function __construct(Manager $fractal, protected DbInterface $db, protected JTLCacheInterface $cache)
    {
        parent::__construct(AttributeModel::class, $fractal, $this->db, $this->cache);
    }

    /**
     * @OA\Get(
     *     path="/attribute/{attributeId}",
     *     tags={"attribute"},
     *     description="Get a single attribute",
     *     summary="Get a single attribute",
     *     @OA\Parameter(
     *         description="ID of attribute that needs to be fetched",
     *         in="path",
     *         name="attributeId",
     *         required=true,
     *         @OA\Schema(
     *             format="int64",
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/AttributeModel"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID supplied"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Attribute not found"
     *     )
     * )
     * @OA\Get(
     *     path="/attribute",
     *     tags={"attribute"},
     *     description="Get a list of attributes",
     *     summary="Get a list of attributes",
     *     @OA\Parameter(
     *         description="Number of attribute to be fetched. If not set, limit will be set to 10",
     *         in="query",
     *         name="limit",
     *         required=false,
     *         @OA\Schema(
     *             format="int64",
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="A list of attribute"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID supplied"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No categories found"
     *     )
     * )
     * @OA\Delete(
     *     path="/attribute/{attributeId}",
     *     description="Deletes a single attribute based on the ID supplied",
     *     summary="Delete a single attribute",
     *     operationId="deleteAttribute",
     *     tags={"attribute"},
     *     @OA\Parameter(
     *         description="ID of attribute to delete",
     *         in="path",
     *         name="attributeId",
     *         required=true,
     *         @OA\Schema(
     *             format="int64",
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Attribute deleted"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Attribute not found"
     *     )
     * )
     * @OA\Put(
     *     path="/attribute/{attributeId}",
     *     tags={"attribute"},
     *     operationId="updateAttribute",
     *     summary="Update an existing attribute",
     *     description="",
     *     @OA\Parameter(
     *         description="ID of attribute to modify",
     *         in="path",
     *         name="attributeId",
     *         required=true,
     *         @OA\Schema(
     *             format="int64",
     *             type="integer"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Attribute object that needs to be modified",
     *         @OA\JsonContent(ref="#/components/schemas/AttributeModel")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID supplied",
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Attribute not found",
     *     ),
     *     @OA\Response(
     *         response=405,
     *         description="Validation exception",
     *     )
     * )
     * @OA\Post(
     *     path="/attribute",
     *     tags={"attribute"},
     *     operationId="createAttribute",
     *     summary="Create a new attribute",
     *     description="",
     *     @OA\RequestBody(
     *         required=true,
     *         description="Attribute object that needs to be created",
     *         @OA\JsonContent(ref="#/components/schemas/AttributeModel")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/AttributeModel")
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
     *         description="Create attribute object",
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/AttributeModel")
     *     )
     * )
     */

    /**
     * @inheritdoc
     */
    public function registerRoutes(RouteGroup $routeGroup): void
    {
        $routeGroup->get('/attribute', $this->index(...));
        $routeGroup->get('/attribute/{id}', $this->show(...));
        $routeGroup->put('/attribute/{id}', $this->update(...));
        $routeGroup->post('/attribute', $this->create(...));
        $routeGroup->delete('/attribute/{id}', $this->delete(...));
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
            'id'        => 'required|numeric',
            'productID' => 'required|numeric',
            'name'      => 'required|max:255'
        ];
    }
}
