<?php

declare(strict_types=1);

namespace JTL\REST\Controllers;

use Exception;
use InvalidArgumentException;
use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\Media\Image;
use JTL\Model\DataModelInterface;
use JTL\REST\Models\CategoryImageModel;
use JTL\REST\Models\CharacteristicValueImageModel;
use JTL\REST\Models\ProductImageModel;
use JTL\REST\Models\ProductPropertyValueImage;
use League\Fractal\Manager;
use League\Route\RouteGroup;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use UnhandledMatchError;

/**
 * Class ImageController
 * @package JTL\REST\Controllers
 */
class ImageController extends AbstractController
{
    /**
     * @inheritdoc
     */
    public function __construct(Manager $fractal, protected DbInterface $db, protected JTLCacheInterface $cache)
    {
        parent::__construct('null', $fractal, $this->db, $this->cache);
    }

    /**
     * @return class-string<DataModelInterface>
     */
    protected function getModelClass(string $type): string
    {
        return match ($type) {
            Image::TYPE_PRODUCT              => ProductImageModel::class,
            Image::TYPE_CATEGORY             => CategoryImageModel::class,
            Image::TYPE_VARIATION            => ProductPropertyValueImage::class,
            Image::TYPE_CHARACTERISTIC_VALUE => CharacteristicValueImageModel::class,
            default                          => throw new UnhandledMatchError($type),
        };
    }

    /**
     * @inheritdoc
     * @OA\Get(
     *   path="/image/product/{productImageId}",
     *   tags={"product"},
     *   description="List product image with primary key <id>",
     *   summary="List product image with primary key <id>",
     *   @OA\Parameter(
     *       name="productImageId",
     *       in="path",
     *       description="ID of product image that needs to be fetched",
     *       required=true,
     *       @OA\Schema(
     *           type="integer"
     *       )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="List image with primary key <id>",
     *     @OA\JsonContent(ref="#/components/schemas/ProductImageModel"),
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="Product image not found"
     *   )
     * )
     * @OA\Get(
     *     path="/image/category/{categoryImageId}",
     *     tags={"category"},
     *     description="List category image with primary key <id>",
     *     summary="List category image with primary key <id>",
     *     @OA\Parameter(
     *         name="categoryImageId",
     *         in="path",
     *         description="ID of category image that needs to be fetched",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/CategoryImageModel"),
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Category image not found"
     *     )
     * )
     * @OA\Get(
     *     path="/image/variation/{id}",
     *     tags={"variation"},
     *     description="List image with primary key <id>",
     *     summary="List image with primary key <id>",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of image that needs to be fetched",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/ProductPropertyValueImage"),
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Variation images not found"
     *     )
     * )
     * @OA\Get(
     *     path="/image/characteristicvalue/{id}",
     *     tags={"characteristicvalue"},
     *     description="List image with primary key <id>",
     *     summary="List image with primary key <id>",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of image that needs to be fetched",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/CharacteristicValueImageModel"),
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Characteristic value images not found"
     *     )
     * )
     *
     * @OA\Get(
     *    path="/image/product",
     *    tags={"product"},
     *    description="List images of products",
     *    summary="List images of products",
     *    @OA\Response(
     *      response=200,
     *      description="List images of products",
     *      @OA\JsonContent(ref="#/components/schemas/ProductImageModel"),
     *    ),
     *    @OA\Response(
     *      response=404,
     *      description="Product images not found"
     *    )
     *  )
     * @OA\Get(
     *      path="/image/category",
     *      tags={"category"},
     *      description="List images of categories",
     *      summary="List images of categories",
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(ref="#/components/schemas/CategoryImageModel"),
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Category image not found"
     *      )
     *  )
     * @OA\Get(
     *      path="/image/variation",
     *      tags={"variation"},
     *      description="List images of variations",
     *      summary="List images of variations",
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(ref="#/components/schemas/ProductPropertyValueImage"),
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Variation images not found"
     *      )
     *  )
     * @OA\Get(
     *      path="/image/characteristicvalue",
     *      tags={"characteristicvalue"},
     *      description="List images of characteristicvalues",
     *      summary="List images of characteristicvalues",
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(ref="#/components/schemas/CharacteristicValueImageModel"),
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Characteristic value images not found"
     *      )
     *  )
     * @OA\Post(
     *     path="/image/product/{productId}",
     *     tags={"product"},
     *     operationId="createProductImage",
     *     summary="Create a new product image",
     *     description="Create a new product image",
     *     @OA\Parameter(
     *         name="productId",
     *         in="path",
     *         description="ID of product that needs to be fetched",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Image to upload",
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="image",
     *                     description="Image",
     *                     type="string",
     *                     format="binary"
     *                ),
     *                      @OA\Property(
     *                      property="imageID",
     *                      description="ImageID",
     *                      type="integer",
     *                      format="integer"
     *                 ),
     *                       @OA\Property(
     *                       property="hash",
     *                       description="ImageName (hash to provide unique filenames)",
     *                       type="string",
     *                       format="string"
     *                  ),
     *                       @OA\Property(
     *                       property="imageNo",
     *                       description="Position of image (sort order)",
     *                       type="integer",
     *                       format="integer"
     *                  )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *       response=200,
     *       description="A list with product images",
     *       @OA\JsonContent(ref="#/components/schemas/ProductImageModel"),
     *     ),
     *     @OA\Response(
     *         response=405,
     *         description="Validation exception",
     *     )
     * )
     * @OA\Post(
     *      path="/image/category/{categoryId}",
     *      tags={"category"},
     *      operationId="createCategoryImage",
     *      summary="Create a new category image",
     *      description="Create a new category image",
     *      @OA\Parameter(
     *          name="categoryId",
     *          in="path",
     *          description="ID of category that needs to be fetched",
     *          required=true,
     *          @OA\Schema(
     *              type="integer"
     *          )
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          description="Image to upload",
     *          @OA\MediaType(
     *              mediaType="multipart/form-data",
     *              @OA\Schema(
     *                  @OA\Property(
     *                      property="image",
     *                      description="Image",
     *                      type="string",
     *                      format="binary"
     *                 )
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *        response=200,
     *        description="Data of category image created",
     *        @OA\JsonContent(ref="#/components/schemas/CategoryImageModel"),
     *      ),
     *      @OA\Response(
     *          response=405,
     *          description="Validation exception",
     *      )
     *  )
     * @OA\Post(
     *      path="/image/variation/{variationId}",
     *      tags={"variation"},
     *      operationId="createVariationImage",
     *      summary="Create a new variation image",
     *      description="Create a new variation image",
     *      @OA\Parameter(
     *          name="variationId",
     *          in="path",
     *          description="ID of variation image that needs to be fetched",
     *          required=true,
     *          @OA\Schema(
     *              type="integer"
     *          )
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          description="Image to upload",
     *          @OA\MediaType(
     *              mediaType="multipart/form-data",
     *              @OA\Schema(
     *                  @OA\Property(
     *                      property="image",
     *                      description="Image",
     *                      type="string",
     *                      format="binary"
     *                 ),
     *                       @OA\Property(
     *                       property="imageID",
     *                       description="ImageID",
     *                       type="integer",
     *                       format="integer"
     *                  ),
     *                        @OA\Property(
     *                        property="hash",
     *                        description="ImageName (hash to provide unique filenames)",
     *                        type="string",
     *                        format="string"
     *                   ),
     *                        @OA\Property(
     *                        property="imageNo",
     *                        description="Position of image (sort order)",
     *                        type="integer",
     *                        format="integer"
     *                   )
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *        response=200,
     *        description="A list with product images",
     *        @OA\JsonContent(ref="#/components/schemas/ProductImageModel"),
     *      ),
     *      @OA\Response(
     *          response=405,
     *          description="Validation exception",
     *      )
     *  )
     * @OA\Post(
     *       path="/image/characteristicvalue/{characteristicvalueId}",
     *       tags={"characteristicvalue"},
     *       operationId="createCharacteristicValueImage",
     *       summary="Create a new characteristicvalue image",
     *       description="Create a new characteristicvalue image",
     *       @OA\Parameter(
     *           name="characteristicvalueId",
     *           in="path",
     *           description="ID of characteristicvalue image that needs to be fetched",
     *           required=true,
     *           @OA\Schema(
     *               type="integer"
     *           )
     *       ),
     *       @OA\RequestBody(
     *           required=true,
     *           description="Image to upload",
     *           @OA\MediaType(
     *               mediaType="multipart/form-data",
     *               @OA\Schema(
     *                   @OA\Property(
     *                       property="image",
     *                       description="Image",
     *                       type="string",
     *                       format="binary"
     *                  )
     *               )
     *           )
     *       ),
     *       @OA\Response(
     *         response=200,
     *         description="A list with characteristicvalue images",
     *         @OA\JsonContent(ref="#/components/schemas/ProductImageModel"),
     *       ),
     *       @OA\Response(
     *           response=405,
     *           description="Validation exception",
     *       )
     *   )
     */
    public function registerRoutes(RouteGroup $routeGroup): void
    {
        $routeGroup->get('/image/{type}/{id}', $this->show(...));
        $routeGroup->get('/image/{type}', $this->index(...));
        $routeGroup->put('/image/{type}/{id}', $this->update(...));
        $routeGroup->post('/image/{type}', $this->create(...));
        $routeGroup->post('/image/{type}/{refid:\d+}', $this->create(...));
        $routeGroup->delete('/image/{type}/{id}[/{withfiles}]', $this->delete(...));
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
        try {
            $this->modelClass = $this->getModelClass($params['type']);
        } catch (UnhandledMatchError) {
            return $this->sendCustomResponse(500, 'Error occurred listing items - unknown type');
        }

        return parent::index($request, $params);
    }

    /**
     * will replace create method
     * @param ServerRequestInterface $request
     * @param array<string, mixed>   $params
     * @return ResponseInterface
     */
    public function createImageItem(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $validatorResponse = $this->validateRequest($request, $this->createRequestValidationRules($request));
        if ($validatorResponse !== true) {
            return $this->sendInvalidFieldResponse($validatorResponse);
        }
        try {
            $this->modelClass = $this->getModelClass($params['type']);
            $result           = $this->createItem($request->withAttribute('ref', $params['refid'] ?? 0));
            $this->createdItem($result);
        } catch (UnhandledMatchError) {
            return $this->sendCustomResponse(500, 'Error occurred creating item - unknown type');
        } catch (Exception $e) {
            return $this->sendCustomResponse(500, 'Error occurred creating item - duplicate ID? ' . $e->getMessage());
        }

        return $this->setStatusCode(201)->respondWithModel($result);
    }

    /**
     * @inheritdoc
     */
    protected function createItem(ServerRequestInterface $request): DataModelInterface
    {
        $uploads = $request->getUploadedFiles();
        if (!isset($uploads['image']) || (\is_array($uploads['image']) && \count($uploads['image']) === 0)) {
            throw new InvalidArgumentException('Error occurred creating image - no data given');
        }
        if (\count($uploads['image']) > 1) {
            throw new InvalidArgumentException('Only one image at a time please');
        }
//        $modelHasImages = $item->getAttribValue('images')->count() > 0;
        return match ($this->modelClass) {
            ProductImageModel::class             => $this->createProductImage($request),
            ProductPropertyValueImage::class     => $this->createProductPropertyValueImage($request),
            CharacteristicValueImageModel::class => $this->createCharacteristicImage($request),
            CategoryImageModel::class            => $this->createCategoryImage($request),
            default                              => throw new InvalidArgumentException('Unknown image type')
        };
    }

    /**
     * @throws Exception
     */
    private function createProductPropertyValueImage(ServerRequestInterface $request): DataModelInterface
    {
        $reference = (int)($request->getAttribute('ref') ?? 0);
        /** @var ProductPropertyValueImage $model */
        $model    = new $this->modelClass($this->db);
        $basePath = \PFAD_ROOT . \PFAD_MEDIA_IMAGE_STORAGE;
        $body     = $request->getParsedBody();
        if (!\is_array($body)) {
            throw new InvalidArgumentException('Error occurred creating image - no data given');
        }
        foreach ($request->getUploadedFiles()['image'] as $file) {
            $fileName = \str_replace(' ', '_', $file->getClientFilename());
            $file->moveTo($basePath . $fileName);
            $model->setPath($fileName);
            $model->setId(
                ((int)$body['id'] > 0)
                    ? (int)$body['id']
                    : $model->getNewID()
            );
        }
        if ($reference > 0) {
            $model->setPropertyValueID($reference);
            $model->save();
        }

        return $model;
    }

    /**
     * @throws Exception
     */
    private function createCharacteristicImage(ServerRequestInterface $request): DataModelInterface
    {
        $reference = (int)($request->getAttribute('ref') ?? 0);
        /** @var CharacteristicValueImageModel $model */
        $model    = new $this->modelClass($this->db);
        $basePath = \PFAD_ROOT . \PFAD_MEDIA_IMAGE_STORAGE;
        foreach ($request->getUploadedFiles()['image'] as $file) {
            $fileName = \str_replace(' ', '_', $file->getClientFilename());
            $file->moveTo($basePath . $fileName);
            $model->setPath($fileName);
        }
        if ($reference > 0) {
            $model->setId($reference);
            $model->save();
        }

        return $model;
    }

    /**
     * @throws Exception
     */
    private function createProductImage(ServerRequestInterface $request): DataModelInterface
    {
        $reference = (int)($request->getAttribute('ref') ?? 0);
        /** @var ProductImageModel $model */
        $model    = new $this->modelClass($this->db);
        $basePath = \PFAD_ROOT . \PFAD_MEDIA_IMAGE_STORAGE;
        $body     = $request->getParsedBody();
        if (!\is_array($body)) {
            throw new InvalidArgumentException('Error occurred creating image - no data given');
        }
        foreach ($request->getUploadedFiles()['image'] as $file) {
            $fileName = \str_replace(' ', '_', $file->getClientFilename());
            $file->moveTo($basePath . $fileName);
            $imageData        = new \stdClass();
            $imageData->kBild = (int)$body['imageID'];
            $imageData->cPfad = $body['hash'];
            $this->db->upsert('tbild', $imageData);
            $model->setImageID((int)$body['imageID']);
            $model->setImageNo((int)$body['imageNo']);
            $model->setPath($fileName);
            $model->setId($model->getNewID());
        }
        if ($reference > 0) {
            $model->setProductID($reference);
            $model->save();
        }

        return $model;
    }

    /**
     * @throws Exception
     */
    private function createCategoryImage(ServerRequestInterface $request): DataModelInterface
    {
        $reference = (int)($request->getAttribute('ref') ?? 0);
        /** @var CategoryImageModel $model */
        $model       = new $this->modelClass($this->db);
        $model->type = '';
        $basePath    = \PFAD_ROOT . \STORAGE_CATEGORIES;
        foreach ($request->getUploadedFiles()['image'] as $file) {
            $fileName = \str_replace(' ', '_', $file->getClientFilename());
            $file->moveTo($basePath . $fileName);
            $model->setPath($fileName);
        }
        if ($reference > 0) {
            $model->setCategoryID($reference);
            $model->save();
        }

        return $model;
    }

    /**
     * @inheritdoc
     */
    public function show(ServerRequestInterface $request, array $params): ResponseInterface
    {
        try {
            $class = $this->getModelClass($params['type']);
        } catch (UnhandledMatchError) {
            return $this->sendCustomResponse(500, 'Error occurred showing item - unknown type');
        }

        return $this->getImage($class, $params['id']);
    }

    /**
     * will replace delete-method
     * @param array<string, mixed> $params
     */
    public function deleteImageItem(ServerRequestInterface $request, array $params): ResponseInterface
    {
        try {
            /** @var class-string<DataModelInterface> $class */
            $class  = $this->getModelClass($params['type']);
            $result = $class::load(['id' => $params['id']], $this->db, DataModelInterface::ON_NOTEXISTS_FAIL);
        } catch (UnhandledMatchError | Exception) {
            return $this->sendNotFoundResponse('Item with id ' . $params['id'] . ' does not exist');
        }
        try {
            if (($params['withfiles'] ?? '') === 'withfiles') {
                $this->deleteFiles($params['type'], $result);
            }
            $result->delete();
            $this->deletedItem($result);
        } catch (Exception $e) {
            return $this->sendCustomResponse(500, 'Error occurred deleting item: ' . $e->getMessage());
        }

        return $this->sendEmptyResponse();
    }

    protected function deleteFiles(string $type, DataModelInterface $item): void
    {
        $path = match ($type) {
            Image::TYPE_PRODUCT              => \PFAD_ROOT . \PFAD_MEDIA_IMAGE_STORAGE,
            Image::TYPE_CATEGORY             => \PFAD_ROOT . \STORAGE_CATEGORIES,
            Image::TYPE_VARIATION            => \PFAD_ROOT . \STORAGE_VARIATIONS,
            Image::TYPE_CHARACTERISTIC_VALUE => \PFAD_ROOT . \STORAGE_CHARACTERISTIC_VALUES,
            default                          => throw new UnhandledMatchError($type),
        };
        /** @var ProductImageModel|CategoryImageModel|ProductPropertyValueImage|CharacteristicValueImageModel $item */
        $path .= $item->getPath();

        $real = \realpath($path);
        if ($real !== false && \str_starts_with($real, \PFAD_ROOT . \PFAD_MEDIA_IMAGE_STORAGE) && \file_exists($real)) {
            \unlink($real);
        }
    }

    protected function deletedItem(DataModelInterface $item): void
    {
        if ($this->cacheID !== null) {
            $this->cache->flush($this->cacheID);
        }
        if (\count($this->cacheTags) > 0) {
            $this->cache->flushTags($this->cacheTags);
        }
    }

    /**
     * @param class-string<DataModelInterface> $class
     */
    public function getImage(string $class, string $id): ResponseInterface
    {
        try {
            $result = $class::load(['id' => $id], $this->db, DataModelInterface::ON_NOTEXISTS_FAIL);
        } catch (Exception) {
            return $this->sendNotFoundResponse();
        }
        return $this->respondWithModel($result);
    }
}
