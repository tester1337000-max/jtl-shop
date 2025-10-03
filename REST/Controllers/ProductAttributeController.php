<?php

declare(strict_types=1);

namespace JTL\REST\Controllers;

use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\REST\Models\ProductAttributeModel;
use League\Fractal\Manager;
use League\Route\RouteGroup;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class ProductAttributeController
 * @package JTL\REST\Controllers
 */
class ProductAttributeController extends AbstractController
{
    public function __construct(Manager $fractal, protected DbInterface $db, protected JTLCacheInterface $cache)
    {
        parent::__construct(ProductAttributeModel::class, $fractal, $this->db, $this->cache);
    }

    /**
     * @inheritdoc
     * @OA\Get(
     *     path="/productattribute/{productattributeId}",
     *     tags={"productattribute"},
     *     description="Get a single product attribute",
     *     summary="Get a single product attribute",
     *     @OA\Parameter(
     *         description="ID of product attribute to delete",
     *         in="path",
     *         name="productattributeId",
     *         required=true,
     *         @OA\Schema(
     *             format="int64",
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/ProductAttributeModel"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID supplied"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="ProductAttribute not found"
     *     )
     * )
     * @OA\Get(
     *     path="/productattribute",
     *     tags={"productattribute"},
     *     description="Get a list of product attributes",
     *     summary="Get a list of product attributes",
     *     @OA\Response(
     *         response=200,
     *         description="A list of product attributes"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID supplied"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No product attributes found"
     *     )
     * )
     * @OA\Delete(
     *     path="/productattribute/{productattributeId}",
     *     description="Deletes a single product attribute based on the ID supplied",
     *     summary="Delete a single product attribute",
     *     operationId="deleteProductAttribute",
     *     tags={"productattribute"},
     *     @OA\Parameter(
     *         description="ID of product attribute to delete",
     *         in="path",
     *         name="productattributeId",
     *         required=true,
     *         @OA\Schema(
     *             format="int64",
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="ProductAttribute deleted"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product attribute not found"
     *     )
     * )
     * @OA\Put(
     *     path="/productattribute/{productattributeId}",
     *     tags={"productattribute"},
     *     operationId="updateProductAttribute",
     *     summary="Update an existing product attribute",
     *     description="",
     *     @OA\Parameter(
     *         description="ID of product attribute to modify",
     *         in="path",
     *         name="productattributeId",
     *         required=true,
     *         @OA\Schema(
     *             format="int64",
     *             type="integer"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="ProductAttribute object that needs to be modified",
     *         @OA\JsonContent(ref="#/components/schemas/ProductAttributeModel")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID supplied",
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="ProductAttribute not found",
     *     ),
     *     @OA\Response(
     *         response=405,
     *         description="Validation exception",
     *     )
     * )
     * @OA\Post(
     *     path="/productattribute",
     *     tags={"productattribute"},
     *     operationId="createProductAttribute",
     *     summary="Create a new product attribute",
     *     description="",
     *     @OA\RequestBody(
     *         required=true,
     *         description="ProductAttribute object that needs to be created",
     *         @OA\JsonContent(ref="#/components/schemas/ProductAttributeModel")
     *     ),
     *     @OA\Response(
     *         response=405,
     *         description="Validation exception",
     *     )
     * )
     */
    public function registerRoutes(RouteGroup $routeGroup): void
    {
        $routeGroup->get('/productattribute', $this->index(...));
        $routeGroup->get('/productattribute/{id}', $this->show(...));
        $routeGroup->put('/productattribute/{id}', $this->update(...));
        $routeGroup->post('/productattribute', $this->create(...));
        $routeGroup->delete('/productattribute/{id}', $this->delete(...));
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
            'id'        => 'required|integer',
            'productID' => 'required|integer',
            'name'      => 'required|max:255',
            'cWert'     => 'required'
        ];
    }

    /**
     * @inheritdoc
     */
    protected function updateRequestValidationRules(ServerRequestInterface $request): array
    {
        return [
            'id'   => 'integer',
            'name' => 'required|max:255'
        ];
    }
}
