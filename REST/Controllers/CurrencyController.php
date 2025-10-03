<?php

declare(strict_types=1);

namespace JTL\REST\Controllers;

use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\REST\Models\CurrencyModel;
use League\Fractal\Manager;
use League\Route\RouteGroup;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class CurrencyController
 * @package JTL\REST\Controllers
 */
class CurrencyController extends AbstractController
{
    public function __construct(Manager $fractal, protected DbInterface $db, protected JTLCacheInterface $cache)
    {
        parent::__construct(CurrencyModel::class, $fractal, $this->db, $this->cache);
    }

    /**
     * @inheritdoc
     * @OA\Get(
     *     path="/currency/{currencyId}",
     *     tags={"currency"},
     *     summary="Get currency by ID",
     *     @OA\Parameter(
     *         description="ID of currency that needs to be fetched",
     *         in="path",
     *         name="currencyId",
     *         required=true,
     *         @OA\Schema(
     *             format="int64",
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/CurrencyModel"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID supplied"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Currency not found"
     *     )
     * )
     * @OA\Get(
     *   path="/currency",
     *   tags={"currency"},
     *   summary="List currencies",
     *   description="List all currencies",
     *   @OA\Response(
     *     response=200,
     *     description="A list of currencies"
     *   ),
     *   @OA\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     * @OA\Delete(
     *     path="/currency/{currencyId}",
     *     description="Deletes a single currency based on the ID supplied",
     *     summary="Delete a single currency",
     *     operationId="deleteCurrency",
     *     tags={"currency"},
     *     @OA\Parameter(
     *         description="ID of currency to delete",
     *         in="path",
     *         name="currencyId",
     *         required=true,
     *         @OA\Schema(
     *             format="int64",
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Currency deleted"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Categoty not found"
     *     )
     * )
     * @OA\Put(
     *     path="/currency/{currencyId}",
     *     tags={"currency"},
     *     operationId="updateCurrency",
     *     summary="Update an existing currency",
     *     description="",
     *     @OA\Parameter(
     *         description="ID of currency to update",
     *         in="path",
     *         name="currencyId",
     *         required=true,
     *         @OA\Schema(
     *             format="int64",
     *             type="integer"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Currency object that needs to be modified",
     *         @OA\JsonContent(ref="#/components/schemas/CurrencyModel")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID supplied",
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Currency not found",
     *     ),
     *     @OA\Response(
     *         response=405,
     *         description="Validation exception",
     *     )
     * )
     * @OA\Post(
     *     path="/currency",
     *     tags={"currency"},
     *     operationId="createCurrency",
     *     summary="Create a new currency",
     *     description="",
     *     @OA\RequestBody(
     *         required=true,
     *         description="Currency object that needs to be created",
     *         @OA\JsonContent(ref="#/components/schemas/CurrencyModel")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/CurrencyModel")
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
     *         description="Create currency object",
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/CurrencyModel")
     *     )
     * )
     */
    public function registerRoutes(RouteGroup $routeGroup): void
    {
        $routeGroup->get('/currency', $this->index(...));
        $routeGroup->get('/currency/{id}', $this->show(...));
        $routeGroup->put('/currency/{id}', $this->update(...));
        $routeGroup->post('/currency', $this->create(...));
        $routeGroup->delete('/currency/{id}', $this->delete(...));
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
            'id'     => 'integer',
            'code'   => 'max:5',
            'name'   => 'max:255',
            'factor' => 'numeric'
        ];
    }

    /**
     * @inheritdoc
     */
    protected function updateRequestValidationRules(ServerRequestInterface $request): array
    {
        return [
            'id'               => 'integer',
            'code'             => 'max:5',
            'name'             => 'max:255',
            'nameHTML'         => 'max:255',
            'default'          => 'max:1',
            'positionBefore'   => 'max:1',
            'dividerDecimal'   => 'max:1',
            'dividerThousands' => 'max:1',
        ];
    }
}
