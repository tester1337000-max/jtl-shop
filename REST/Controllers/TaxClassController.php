<?php

declare(strict_types=1);

namespace JTL\REST\Controllers;

use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\REST\Models\TaxClassModel;
use League\Fractal\Manager;
use League\Route\RouteGroup;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class TaxRateController
 * @package JTL\REST\Controllers
 */
class TaxClassController extends AbstractController
{
    public function __construct(Manager $fractal, protected DbInterface $db, protected JTLCacheInterface $cache)
    {
        parent::__construct(TaxClassModel::class, $fractal, $this->db, $this->cache);
    }

    /**
     * @inheritdoc
     * @OA\Get(
     *   path="/taxclass",
     *   tags={"taxclass"},
     *   summary="List tax classes",
     *   @OA\Response(
     *     response=200,
     *     description="A list with tax classes"
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="Tax classes not found"
     *   )
     * )
     * @OA\Get(
     *     path="/taxclass/{taxclassId}",
     *     tags={"taxclass"},
     *     description="Get a tax class by ID",
     *     summary="Get a tax class by ID",
     *     operationId="getTaxclassById",
     *     @OA\Parameter(
     *         name="taxclassId",
     *         in="path",
     *         description="ID of tax class that needs to be fetched",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/TaxClassModel"),
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Tax class not found"
     *     )
     * )
     * @OA\Delete(
     *     path="/taxclass/{taxclassId}",
     *     description="Deletes a single tax class based on the ID supplied",
     *     summary="Delete a single tax class",
     *     operationId="deleteTaxClass",
     *     tags={"taxclass"},
     *     @OA\Parameter(
     *         description="ID of tax class to delete",
     *         in="path",
     *         name="taxclassId",
     *         required=true,
     *         @OA\Schema(
     *             format="int64",
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Tax class deleted"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Tax class not found"
     *     )
     * )
     * @OA\Put(
     *     path="/taxclass/{taxclassId}",
     *     tags={"taxclass"},
     *     operationId="updateTaxClass",
     *     summary="Update an existing tax class",
     *     description="",
     *     @OA\Parameter(
     *         description="ID of tax class to modify",
     *         in="path",
     *         name="taxclassId",
     *         required=true,
     *         @OA\Schema(
     *             format="int64",
     *             type="integer"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="TaxClass object that needs to be modified",
     *         @OA\JsonContent(ref="#/components/schemas/TaxClassModel")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID supplied",
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Tax class not found",
     *     ),
     *     @OA\Response(
     *         response=405,
     *         description="Validation exception",
     *     )
     * )
     * @OA\Post(
     *     path="/taxclass",
     *     tags={"taxclass"},
     *     operationId="createTaxClass",
     *     summary="Create a new tax class",
     *     description="",
     *     @OA\RequestBody(
     *         required=true,
     *         description="TaxClass object that needs to be created",
     *         @OA\JsonContent(ref="#/components/schemas/TaxClassModel")
     *     ),
     *     @OA\Response(
     *         response=405,
     *         description="Validation exception",
     *     )
     * )
     */
    public function registerRoutes(RouteGroup $routeGroup): void
    {
        $routeGroup->get('/taxclass', $this->index(...));
        $routeGroup->get('/taxclass/{id}', $this->show(...));
        $routeGroup->put('/taxclass/{id}', $this->update(...));
        $routeGroup->post('/taxclass', $this->create(...));
        $routeGroup->delete('/taxclass/{id}', $this->delete(...));
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
            'id'   => 'required|integer',
            'name' => 'required|max:255'
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
