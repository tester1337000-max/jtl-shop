<?php

declare(strict_types=1);

namespace JTL\REST\Controllers;

use Exception;
use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\Helpers\Seo;
use JTL\Language\LanguageHelper;
use JTL\Model\DataModelInterface;
use JTL\REST\Models\CategoryImageModel;
use JTL\REST\Models\CategoryLocalizationModel;
use JTL\REST\Models\CategoryModel;
use JTL\REST\Models\SeoModel;
use Laminas\Diactoros\UploadedFile;
use League\Fractal\Manager;
use League\Route\RouteGroup;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use stdClass;

use function Functional\map;

/**
 * Class CategoryController
 * @package JTL\REST\Controllers
 */
class CategoryController extends AbstractController
{
    /**
     * @var int[]
     */
    private array $affected = [];

    /**
     * @inheritdoc
     */
    public function __construct(Manager $fractal, protected DbInterface $db, protected JTLCacheInterface $cache)
    {
        parent::__construct(CategoryModel::class, $fractal, $this->db, $this->cache);
    }

    /**
     * @inheritdoc
     * @OA\Get(
     *     path="/category/{categoryId}",
     *     tags={"category"},
     *     description="Get a single category",
     *     summary="Get a single category",
     *     @OA\Parameter(
     *         description="ID of category that needs to be fetched",
     *         in="path",
     *         name="categoryId",
     *         required=true,
     *         @OA\Schema(
     *             format="int64",
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/CategoryModel"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID supplied"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Category not found"
     *     )
     * )
     * @OA\Get(
     *     path="/category",
     *     tags={"category"},
     *     description="Get a list of categories",
     *     summary="Get a list of categories",
     *     @OA\Parameter(
     *         description="Number of categories to be fetched. If not set, limit will be set to 10",
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
     *         description="A list of categories"
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
     *     path="/category/{categoryId}",
     *     description="Deletes a single category based on the ID supplied",
     *     summary="Delete a single category",
     *     operationId="deleteCategory",
     *     tags={"category"},
     *     @OA\Parameter(
     *         description="ID of category to delete",
     *         in="path",
     *         name="categoryId",
     *         required=true,
     *         @OA\Schema(
     *             format="int64",
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Category deleted"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Categoty not found"
     *     )
     * )
     * @OA\Put(
     *     path="/category/{categoryId}",
     *     tags={"category"},
     *     operationId="updateCategory",
     *     summary="Update an existing category",
     *     description="",
     *     @OA\Parameter(
     *         description="ID of category to modify",
     *         in="path",
     *         name="categoryId",
     *         required=true,
     *         @OA\Schema(
     *             format="int64",
     *             type="integer"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Category object that needs to be modified",
     *         @OA\JsonContent(ref="#/components/schemas/CategoryModel")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID supplied",
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Category not found",
     *     ),
     *     @OA\Response(
     *         response=405,
     *         description="Validation exception",
     *     )
     * )
     * @OA\Post(
     *     path="/category",
     *     tags={"category"},
     *     operationId="createCategory",
     *     summary="Create a new category",
     *     description="",
     *     @OA\RequestBody(
     *         required=true,
     *         description="Category object that needs to be created",
     *         @OA\JsonContent(ref="#/components/schemas/CategoryModel")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/CategoryModel")
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
     *         description="Create category object",
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/CategoryModel")
     *     )
     * )
     */
    public function registerRoutes(RouteGroup $routeGroup): void
    {
        $routeGroup->get('/category', $this->index(...));
        $routeGroup->get('/category/{id}', $this->show(...));
        $routeGroup->put('/category/{id}', $this->update(...));
        $routeGroup->post('/category', $this->create(...));
        $routeGroup->delete('/category/{id}', $this->delete(...));
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
     * Use at your own risk
     * @inheritdoc
     */
    protected function createItem(ServerRequestInterface $request): DataModelInterface
    {
        $item    = parent::createItem($request);
        $uploads = $request->getUploadedFiles();
        /** @var CategoryModel $item */
        if (!isset($uploads['image']) || (\is_array($uploads['image']) && \count($uploads['image']) === 0)) {
            return $item;
        }
        if (!\is_array($uploads['image'])) {
            $uploads['image'] = [$uploads['image']];
        }
        $modelHasImages = $item->getAttribValue('images')->count() > 0;
        /** @var UploadedFile $file */
        foreach ($uploads['image'] as $file) {
            $file->moveTo(\PFAD_ROOT . \STORAGE_CATEGORIES . $file->getClientFilename());
            if (!$modelHasImages) {
                $model = new CategoryImageModel($this->db);
                $data  = (object)[
                    'id'         => $model->getNewID(),
                    'categoryID' => $item->getId(),
                    'type'       => '',
                    'path'       => $file->getClientFilename()
                ];
                $model::create($data, $this->db);
                $item->images = [(array)$data];
            }
        }
        $this->cacheID = \CACHING_GROUP_CATEGORY . '_' . $item->getId();

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
            $lastCategoryID = $this->db->getSingleInt(
                'SELECT MAX(kKategorie) AS newID FROM tkategorie',
                'newID'
            );
            $data->id       = ++$lastCategoryID;
        }

        return $data;
    }

    /**
     * @inheritdoc
     * @throws Exception
     * @param CategoryModel $item
     */
    protected function createdItem(DataModelInterface $item): void
    {
        $this->updateSlug($item);
        $this->rebuildCategoryTree(0, 1);
        parent::createdItem($item);
    }

    /**
     * @inheritdoc
     * @param CategoryModel $item
     */
    protected function updatedItem(DataModelInterface $item): void
    {
        $this->rebuildCategoryTree(0, 1);
        $this->cache->flushTags(\CACHING_GROUP_CATEGORY . '_' . $item->getId());
        parent::updatedItem($item);
    }

    /**
     * @inheritdoc
     * @param CategoryModel $item
     * @throws Exception
     */
    protected function deletedItem(DataModelInterface $item): void
    {
        $id               = $item->getId();
        $this->affected[] = $id;
        $this->deleteSubItems($id);
        $this->db->delete('tseo', ['kKey', 'cKey'], [$id, 'kKategorie']);
        $this->db->delete('tkategoriekundengruppe', 'kKategorie', $id);
        $this->rebuildCategoryTree(0, 1);
        $this->cache->flushTags(map($this->affected, fn(int $cid): string => \CACHING_GROUP_CATEGORY . '_' . $cid));
        parent::deletedItem($item);
    }

    /**
     * @throws Exception
     */
    protected function deleteSubItems(int $parentID): void
    {
        foreach (CategoryModel::loadAll($this->db, 'kOberKategorie', $parentID) as $item) {
            $this->affected[] = $item->getId();
            $this->deleteSubItems($item->getId());
            $item->delete();
        }
    }

    /**
     * @throws Exception
     * @param CategoryModel $item
     */
    private function updateSlug(DataModelInterface $item): void
    {
        $model = new SeoModel($this->db);
        // default language is not contained in localizations...
        /** @var CategoryLocalizationModel $localization */
        foreach ([$item, ...$item->getLocalization()] as $localization) {
            $languageID = \array_key_exists('languageID', $localization->getAttributes())
                ? $localization->getLanguageID()
                : LanguageHelper::getDefaultLanguage()->getId();
            try {
                $old = $model::loadByAttributes(
                    [
                        'type'       => 'kKategorie',
                        'id'         => $item->getId(),
                        'languageID' => $languageID
                    ],
                    $this->db
                );
            } catch (Exception) {
                $old = null;
            }
            $oldSlug = $old?->getSlug();
            if ($oldSlug !== null) {
                if ($oldSlug === $localization->getSlug()) {
                    continue;
                }
                $this->db->delete(
                    'tseo',
                    ['kSprache', 'cKey', 'kKey'],
                    [$languageID, 'kKategorie', $item->getId()]
                );
            }
            $seo           = new stdClass();
            $seo->cSeo     = Seo::checkSeo($localization->getSlug());
            $seo->cKey     = 'kKategorie';
            $seo->kKey     = $item->getId();
            $seo->kSprache = $languageID;
            try {
                $model::create($seo, $this->db);
            } catch (Exception) {
                // @todo
            }
        }
    }

    private function rebuildCategoryTree(int $parent_id, int $left, int $level = 0): int
    {
        $right  = $left + 1;
        $result = $this->db->selectAll(
            'tkategorie',
            'kOberKategorie',
            $parent_id,
            'kKategorie',
            'nSort, cName'
        );
        foreach ($result as $_res) {
            $right = $this->rebuildCategoryTree((int)$_res->kKategorie, $right, $level + 1);
        }
        $this->db->update(
            'tkategorie',
            'kKategorie',
            $parent_id,
            (object)[
                'lft'    => $left,
                'rght'   => $right,
                'nLevel' => $level,
            ]
        );

        return $right + 1;
    }

    /**
     * @inheritdoc
     */
    protected function createRequestValidationRules(ServerRequestInterface $request): array
    {
        return [
            'id'           => 'numeric',
            'name'         => 'required|max:255',
            'parentID'     => 'numeric',
            'sort'         => 'numeric',
            'level'        => 'numeric',
            'description'  => 'max:255',
            'slug'         => 'max:255',
            'localization' => 'array',
            'images'       => 'array',
        ];
    }

    /**
     * @inheritdoc
     */
    protected function updateRequestValidationRules(ServerRequestInterface $request): array
    {
        return [
            'id'           => 'numeric',
            'name'         => 'max:255',
            'description'  => 'max:255',
            'parentID'     => 'numeric',
            'localization' => 'array',
            'images'       => 'array',
        ];
    }
}
