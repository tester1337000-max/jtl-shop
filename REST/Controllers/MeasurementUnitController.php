<?php

declare(strict_types=1);

namespace JTL\REST\Controllers;

use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\REST\Models\MeasurementUnitModel;
use League\Fractal\Manager;
use League\Route\RouteGroup;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class MeasurementUnitController
 * @package JTL\REST\Controllers
 */
class MeasurementUnitController extends AbstractController
{
    public function __construct(Manager $fractal, protected DbInterface $db, protected JTLCacheInterface $cache)
    {
        parent::__construct(MeasurementUnitModel::class, $fractal, $this->db, $this->cache);
    }

    /**
     * @inheritdoc
     * @OA\Get(
     *     path="/measurementunit/{measurementunitId}",
     *     tags={"measurementunit"},
     *     summary="Get measurement unit by ID",
     *     @OA\Parameter(
     *         description="ID of measurement unit that needs to be fetched",
     *         in="path",
     *         name="measurementunitId",
     *         required=true,
     *         @OA\Schema(
     *             format="int64",
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/MeasurementUnitModel"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID supplied"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Measurement unit not found"
     *     )
     * )
     * @OA\Get(
     *   path="/measurementunit",
     *   tags={"measurementunit"},
     *   summary="List measurementunits",
     *   description="List all measurementunits",
     *   @OA\Response(
     *     response=200,
     *     description="A list with measurementunits"
     *   ),
     *   @OA\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     * @OA\Delete(
     *     path="/measurementunit/{measurementunitId}",
     *     description="Deletes a single measurementunit based on the ID supplied",
     *     summary="Delete a single measurementunit",
     *     operationId="deleteMeasurementUnit",
     *     tags={"measurementunit"},
     *     @OA\Parameter(
     *         description="ID of measurementunit to delete",
     *         in="path",
     *         name="measurementunitId",
     *         required=true,
     *         @OA\Schema(
     *             format="int64",
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Measurement unit deleted"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Categoty not found"
     *     )
     * )
     * @OA\Put(
     *     path="/measurementunit/{measurementunitId}",
     *     tags={"measurementunit"},
     *     operationId="updateMeasurementUnit",
     *     summary="Update an existing measurementunit",
     *     description="",
     *     @OA\Parameter(
     *         description="ID of measurementunit to update",
     *         in="path",
     *         name="measurementunitId",
     *         required=true,
     *         @OA\Schema(
     *             format="int64",
     *             type="integer"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Measurement unit object that needs to be modified",
     *         @OA\JsonContent(ref="#/components/schemas/MeasurementUnitModel")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID supplied",
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Measurement unit not found",
     *     ),
     *     @OA\Response(
     *         response=405,
     *         description="Validation exception",
     *     )
     * )
     * @OA\Post(
     *     path="/measurementunit",
     *     tags={"measurementunit"},
     *     operationId="createMeasurementUnit",
     *     summary="Create a new measurementunit",
     *     description="",
     *     @OA\RequestBody(
     *         required=true,
     *         description="Measurement unit object that needs to be created",
     *         @OA\JsonContent(ref="#/components/schemas/MeasurementUnitModel")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/MeasurementUnitModel")
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
     *         description="Create measurementunit object",
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/MeasurementUnitModel")
     *     )
     * )
     */
    public function registerRoutes(RouteGroup $routeGroup): void
    {
        $routeGroup->get('/measurementunit', $this->index(...));
        $routeGroup->get('/measurementunit/{id}', $this->show(...));
        $routeGroup->put('/measurementunit/{id}', $this->update(...));
        $routeGroup->post('/measurementunit', $this->create(...));
        $routeGroup->delete('/measurementunit/{id}', $this->delete(...));
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
