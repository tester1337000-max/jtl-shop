<?php

declare(strict_types=1);

namespace JTL\REST\Controllers;

use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\Helpers\Seo;
use JTL\Model\DataModelInterface;
use JTL\REST\Models\ManufacturerModel;
use JTL\REST\Models\SeoModel;
use Laminas\Diactoros\UploadedFile;
use League\Fractal\Manager;
use League\Route\RouteGroup;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use stdClass;

/**
 * Class ManufacturerController
 * @package JTL\REST\Controllers
 */
class ManufacturerController extends AbstractController
{
    public function __construct(Manager $fractal, protected DbInterface $db, protected JTLCacheInterface $cache)
    {
        parent::__construct(ManufacturerModel::class, $fractal, $this->db, $this->cache);
    }

    /**
     * @inheritdoc
     * @OA\Get(
     *   path="/manufacturer",
     *   tags={"manufacturer"},
     *   summary="List manufacturers",
     *   @OA\Response(
     *     response=200,
     *     description="A list with manufacturers"
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="Manufacturers not found"
     *   )
     * )
     * @OA\Get(
     *     path="/manufacturer/{manufacturerId}",
     *     tags={"manufacturer"},
     *     description="Get a manufacturer by ID",
     *     summary="Get a manufacturer by ID",
     *     operationId="getManufacturerById",
     *     @OA\Parameter(
     *         name="manufacturerId",
     *         in="path",
     *         description="ID of manufacturer that needs to be fetched",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/ManufacturerModel"),
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Manufacturer not found"
     *     )
     * )
     * @OA\Delete(
     *     path="/manufacturer/{manufacturerId}",
     *     description="Deletes a single manufacturer based on the ID supplied",
     *     summary="Delete a single manufacturer",
     *     operationId="deleteManufacturer",
     *     tags={"manufacturer"},
     *     @OA\Parameter(
     *         description="ID of manufacturer to delete",
     *         in="path",
     *         name="manufacturerId",
     *         required=true,
     *         @OA\Schema(
     *             format="int64",
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Manufacturer deleted"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Manufacturer not found"
     *     )
     * )
     * @OA\Put(
     *     path="/manufacturer/{manufacturerId}",
     *     tags={"manufacturer"},
     *     operationId="updateManufacturer",
     *     summary="Update an existing manufacturer",
     *     description="",
     *     @OA\Parameter(
     *         description="ID of manufacturer to modify",
     *         in="path",
     *         name="manufacturerId",
     *         required=true,
     *         @OA\Schema(
     *             format="int64",
     *             type="integer"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Manufacturer object that needs to be modified",
     *         @OA\JsonContent(ref="#/components/schemas/ManufacturerModel")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID supplied",
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Manufacturer not found",
     *     ),
     *     @OA\Response(
     *         response=405,
     *         description="Validation exception",
     *     )
     * )
     * @OA\Post(
     *     path="/manufacturer",
     *     tags={"manufacturer"},
     *     operationId="createManufacturer",
     *     summary="Create a new manufacturer",
     *     description="",
     *     @OA\RequestBody(
     *         required=true,
     *         description="Manufacturer object that needs to be created",
     *         @OA\JsonContent(ref="#/components/schemas/ManufacturerModel")
     *     ),
     *     @OA\Response(
     *         response=405,
     *         description="Validation exception",
     *     )
     * )
     */
    public function registerRoutes(RouteGroup $routeGroup): void
    {
        $routeGroup->get('/manufacturer', $this->index(...));
        $routeGroup->get('/manufacturer/{id}', $this->show(...));
        $routeGroup->put('/manufacturer/{id}', $this->update(...));
        $routeGroup->post('/manufacturer', $this->create(...));
        $routeGroup->delete('/manufacturer/{id}', $this->delete(...));
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
    protected function createItem(ServerRequestInterface $request): DataModelInterface
    {
        $item = parent::createItem($request);
        /** @var ManufacturerModel $item */
        $uploads = $request->getUploadedFiles();
        if (!isset($uploads['image']) || (\is_array($uploads['image']) && \count($uploads['image']) === 0)) {
            return $item;
        }
        if (!\is_array($uploads['image'])) {
            $uploads['image'] = [$uploads['image']];
        }
        $modelHasImages = !empty($item->getAttribValue('image'));
        /** @var UploadedFile $file */
        foreach ($uploads['image'] as $file) {
            $file->moveTo(\PFAD_ROOT . STORAGE_MANUFACTURERS . $file->getClientFilename());
            if (!$modelHasImages && $file->getClientFilename() !== null) {
                $item->setWasLoaded(true);
                $item->image = $file->getClientFilename();
                $item->save(['image'], false);
            }
        }

        return $item;
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
            $id       = $this->db->getSingleInt(
                'SELECT MAX(kHersteller) AS newID FROM thersteller',
                'newID'
            );
            $data->id = ++$id;
        }

        return $data;
    }

    /**
     * @inheritdoc
     * @throws \Exception
     * @param ManufacturerModel $item
     */
    protected function createdItem(DataModelInterface $item): void
    {
        $baseSeo = Seo::getSeo($item->getSlug());
        $model   = new SeoModel($this->db);
        foreach ($item->getLocalization() as $localization) {
            $seo           = new stdClass();
            $seo->cSeo     = Seo::checkSeo($baseSeo);
            $seo->cKey     = 'kHersteller';
            $seo->kKey     = $item->getId();
            $seo->kSprache = $localization->getLanguageID();
            $model::create($seo, $this->db);
        }
        $this->cacheID = \CACHING_GROUP_MANUFACTURER . '_' . $item->getId();
        parent::createdItem($item);
    }

    /**
     * @inheritdoc
     * @param ManufacturerModel $item
     */
    protected function deletedItem(DataModelInterface $item): void
    {
        $this->db->queryPrepared(
            'DELETE FROM tseo WHERE cKey = :keyname AND kKey = :keyid',
            ['keyname' => 'kHersteller', 'keyid' => $item->getId()]
        );
        parent::deletedItem($item);
    }

    /**
     * @inheritdoc
     */
    protected function createRequestValidationRules(ServerRequestInterface $request): array
    {
        return [
            'id'           => 'integer',
            'name'         => 'required|max:255',
            'localization' => 'required|array'
        ];
    }
}
