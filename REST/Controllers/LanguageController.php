<?php

declare(strict_types=1);

namespace JTL\REST\Controllers;

use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\Language\LanguageModel;
use League\Fractal\Manager;
use League\Route\RouteGroup;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class LanguageController
 * @package JTL\REST\Controllers
 */
class LanguageController extends AbstractController
{
    public function __construct(Manager $fractal, protected DbInterface $db, protected JTLCacheInterface $cache)
    {
        parent::__construct(LanguageModel::class, $fractal, $this->db, $this->cache);
    }

    /**
     * @inheritdoc
     * @OA\Get(
     *   path="/language",
     *   tags={"language"},
     *   summary="List languages",
     *   @OA\Response(
     *     response=200,
     *     description="A list with languages"
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="Languages not found"
     *   )
     * )
     * @OA\Get(
     *     path="/language/{languageId}",
     *     tags={"language"},
     *     description="Get a language by ID",
     *     summary="Get a language by ID",
     *     operationId="getLanguageById",
     *     @OA\Parameter(
     *         name="languageId",
     *         in="path",
     *         description="ID of language that needs to be fetched",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/LanguageModel"),
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Language not found"
     *     )
     * )
     * @OA\Delete(
     *     path="/language/{languageId}",
     *     description="Deletes a single language based on the ID supplied",
     *     summary="Delete a single language",
     *     operationId="deleteLanguage",
     *     tags={"language"},
     *     @OA\Parameter(
     *         description="ID of language to delete",
     *         in="path",
     *         name="languageId",
     *         required=true,
     *         @OA\Schema(
     *             format="int64",
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Language deleted"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Language not found"
     *     )
     * )
     * @OA\Put(
     *     path="/language/{languageId}",
     *     tags={"language"},
     *     operationId="updateLanguage",
     *     summary="Update an existing language",
     *     description="",
     *     @OA\Parameter(
     *         description="ID of language to modify",
     *         in="path",
     *         name="languageId",
     *         required=true,
     *         @OA\Schema(
     *             format="int64",
     *             type="integer"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Language object that needs to be modified",
     *         @OA\JsonContent(ref="#/components/schemas/LanguageModel")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID supplied",
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Language not found",
     *     ),
     *     @OA\Response(
     *         response=405,
     *         description="Validation exception",
     *     )
     * )
     * @OA\Post(
     *     path="/language",
     *     tags={"language"},
     *     operationId="createLanguage",
     *     summary="Create a new language",
     *     description="",
     *     @OA\RequestBody(
     *         required=true,
     *         description="Language object that needs to be created",
     *         @OA\JsonContent(ref="#/components/schemas/LanguageModel")
     *     ),
     *     @OA\Response(
     *         response=405,
     *         description="Validation exception",
     *     )
     * )
     */
    public function registerRoutes(RouteGroup $routeGroup): void
    {
        $routeGroup->get('/language', $this->index(...));
        $routeGroup->get('/language/{id}', $this->show(...));
        $routeGroup->put('/language/{id}', $this->update(...));
        $routeGroup->post('/language', $this->create(...));
        $routeGroup->delete('/language/{id}', $this->delete(...));
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
            'id'     => 'required|integer',
            'nameDE' => 'required|max:255',
            'nameEN' => 'required|max:255'
        ];
    }
}
