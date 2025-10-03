<?php

declare(strict_types=1);

namespace JTL\REST\Controllers;

use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\Model\DataModelInterface;
use JTL\REST\Models\CharacteristicModel;
use League\Fractal\Manager;
use League\Route\RouteGroup;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use stdClass;

/**
 * Class CharacteristicController
 * @package JTL\REST\Controllers
 */
class CharacteristicController extends AbstractController
{
    public function __construct(Manager $fractal, protected DbInterface $db, protected JTLCacheInterface $cache)
    {
        parent::__construct(CharacteristicModel::class, $fractal, $this->db, $this->cache);
    }

    /**
     * @inheritdoc
     * @OA\Get(
     *   path="/characteristic",
     *   tags={"characteristic"},
     *   summary="List characteristics",
     *   @OA\Parameter(
     *       description="optional, default value is 10",
     *       name="limit",
     *       required=false,
     *       @OA\Schema(
     *          format="int64",
     *          type="integer"
     *        ),
     *        in="query"
     *    ),
     *   @OA\Response(
     *     response=200,
     *     description="A list with characteristics"
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="Characteristics not found"
     *   )
     * )
     * @OA\Get(
     *     path="/characteristic/{characteristicId}",
     *     tags={"characteristic"},
     *     description="Get a characteristic by ID",
     *     summary="Get a characteristic by ID",
     *     operationId="getCharacteristicById",
     *     @OA\Parameter(
     *         name="characteristicId",
     *         in="path",
     *         description="ID of characteristic that needs to be fetched",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/CharacteristicModel"),
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Characteristic not found"
     *     )
     * )
     * @OA\Delete(
     *     path="/characteristic/{characteristicId}",
     *     description="Deletes a single characteristic based on the ID supplied",
     *     summary="Delete a single characteristic",
     *     operationId="deleteCharacteristic",
     *     tags={"characteristic"},
     *     @OA\Parameter(
     *         description="ID of characteristic to delete",
     *         in="path",
     *         name="characteristicId",
     *         required=true,
     *         @OA\Schema(
     *             format="int64",
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Characteristic deleted"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Characteristic not found"
     *     )
     * )
     * @OA\Put(
     *     path="/characteristic/{characteristicId}",
     *     tags={"characteristic"},
     *     operationId="updateCharacteristic",
     *     summary="Update an existing characteristic",
     *     description="",
     *     @OA\Parameter(
     *         description="ID of characteristic to modify",
     *         in="path",
     *         name="characteristicId",
     *         required=true,
     *         @OA\Schema(
     *             format="int64",
     *             type="integer"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Characteristic object that needs to be modified",
     *         @OA\JsonContent(ref="#/components/schemas/CharacteristicModel")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID supplied",
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Characteristic not found",
     *     ),
     *     @OA\Response(
     *         response=405,
     *         description="Validation exception",
     *     )
     * )
     * @OA\Post(
     *     path="/characteristic",
     *     tags={"characteristic"},
     *     operationId="createCharacteristic",
     *     summary="Create a new characteristic",
     *     description="",
     *     @OA\RequestBody(
     *         required=true,
     *         description="Characteristic object that needs to be created",
     *         @OA\JsonContent(ref="#/components/schemas/CharacteristicModel")
     *     ),
     *     @OA\Response(
     *         response=405,
     *         description="Validation exception",
     *     )
     * )
     */
    public function registerRoutes(RouteGroup $routeGroup): void
    {
        $routeGroup->get('/characteristic', $this->index(...));
        $routeGroup->get('/characteristic/{id}', $this->show(...));
        $routeGroup->put('/characteristic/{id}', $this->update(...));
        $routeGroup->post('/characteristic', $this->create(...));
        $routeGroup->delete('/characteristic/{id}', $this->delete(...));
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
            // tmerkmal has no auto increment ID
            $id       = $this->db->getSingleInt(
                'SELECT MAX(kMerkmal) AS newID FROM tmerkmal',
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
            'sort'         => 'integer',
            'name'         => 'required|max:255',
            'type'         => 'in:TEXTSWATCHES,IMGSWATCHES,RADIO,BILD,BILD-TEXT,SELECTBOX,TEXT',
            'isMulti'      => 'integer',
            'localization' => 'array',
        ];
    }

    /**
     * @inheritdoc
     */
    protected function updateRequestValidationRules(ServerRequestInterface $request): array
    {
        return [
            'name'         => 'max:255',
            'localization' => 'array',
        ];
    }
}
