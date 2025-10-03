<?php

declare(strict_types=1);

namespace JTL\REST\Controllers;

use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\REST\Models\TaxZoneModel;
use League\Fractal\Manager;
use League\Route\RouteGroup;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class TaxZoneController
 * @package JTL\REST\Controllers
 */
class TaxZoneController extends AbstractController
{
    public function __construct(Manager $fractal, protected DbInterface $db, protected JTLCacheInterface $cache)
    {
        parent::__construct(TaxZoneModel::class, $fractal, $this->db, $this->cache);
    }

    /**
     * @inheritdoc
     * @OA\Get(
     *   path="/taxzone",
     *   tags={"taxzone"},
     *   summary="List tax zones",
     *   @OA\Response(
     *     response=200,
     *     description="A list with tax zones"
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="Tax zones not found"
     *   )
     * )
     * @OA\Get(
     *     path="/taxzone/{taxzoneId}",
     *     tags={"taxzone"},
     *     description="Get a tax zone by ID",
     *     operationId="getTaxzoneById",
     *     @OA\Parameter(
     *         name="taxzoneId",
     *         in="path",
     *         description="ID of tax zone that needs to be fetched",
     *         required=true,
     *         @OA\Schema(
     *             format="int64",
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/TaxZoneModel"),
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Tax zone not found"
     *     )
     * )
     * @OA\Delete(
     *     path="/taxzone/{taxzoneId}",
     *     description="Deletes a single tax zone based on the ID supplied",
     *     summary="Delete a single tax zone",
     *     operationId="deleteTaxZone",
     *     tags={"taxzone"},
     *     @OA\Parameter(
     *         description="ID of tax zone to delete",
     *         in="path",
     *         name="taxzoneId",
     *         required=true,
     *         @OA\Schema(
     *             format="int64",
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Tax zone deleted"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Tax zone not found"
     *     )
     * )
     * @OA\Put(
     *     path="/taxzone/{taxzoneId}",
     *     tags={"taxzone"},
     *     operationId="updateTaxZone",
     *     summary="Update an existing tax zone",
     *     description="",
     *     @OA\Parameter(
     *         description="ID of tax zone to modify",
     *         in="path",
     *         name="taxzoneId",
     *         required=true,
     *         @OA\Schema(
     *             format="int64",
     *             type="integer"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Tax zone object that needs to be modified",
     *         @OA\JsonContent(ref="#/components/schemas/TaxZoneModel")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID supplied",
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Tax zone not found",
     *     ),
     *     @OA\Response(
     *         response=405,
     *         description="Validation exception",
     *     )
     * )
     * @OA\Post(
     *     path="/taxzone",
     *     tags={"taxzone"},
     *     operationId="createTaxZone",
     *     summary="Create a new tax zone",
     *     description="",
     *     @OA\RequestBody(
     *         required=true,
     *         description="Tax zone object that needs to be created",
     *         @OA\JsonContent(ref="#/components/schemas/TaxZoneModel")
     *     ),
     *     @OA\Response(
     *         response=405,
     *         description="Validation exception",
     *     )
     * )
     */
    public function registerRoutes(RouteGroup $routeGroup): void
    {
        $routeGroup->get('/taxzone', $this->index(...));
        $routeGroup->get('/taxzone/{id}', $this->show(...));
        $routeGroup->put('/taxzone/{id}', $this->update(...));
        $routeGroup->post('/taxzone', $this->create(...));
        $routeGroup->delete('/taxzone/{id}', $this->delete(...));
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
