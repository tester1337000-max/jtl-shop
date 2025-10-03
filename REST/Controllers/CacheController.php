<?php

declare(strict_types=1);

namespace JTL\REST\Controllers;

use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use League\Fractal\Manager;
use League\Route\RouteGroup;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class CacheController
 * @package JTL\REST\Controllers
 */
class CacheController extends AbstractController
{
    public function __construct(Manager $fractal, protected DbInterface $db, protected JTLCacheInterface $cache)
    {
        parent::__construct('null', $fractal, $this->db, $this->cache);
    }

    /**
     * @OA\Delete(
     *     path="/cache/{cacheId}",
     *     description="Deletes a single cache item based on the ID supplied",
     *     summary="Delete a single cache item",
     *     operationId="deleteCache",
     *     tags={"cache"},
     *     @OA\Parameter(
     *         description="ID of cache to delete",
     *         in="path",
     *         name="cacheId",
     *         required=true,
     *         @OA\Schema(
     *             format="int64",
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=202,
     *         description="Cache deleted"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Cache not found"
     *     )
     * )
     * @OA\Delete(
     *      path="/cache/all",
     *      description="Deletes cache",
     *      summary="Delete cache",
     *      operationId="deleteCache",
     *      tags={"cache"},
     *      @OA\Response(
     *          response=202,
     *          description="Cache deleted"
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Cache not found"
     *      )
     *  )
     * @OA\Delete(
     *       path="/cache/cachetags/{cachetags}",
     *       description="Deletes cache",
     *       summary="Delete cache",
     *       operationId="deleteCache",
     *       tags={"cachetags"},
     *       @OA\Parameter(
     *           description="Tags of cache to delete",
     *           in="path",
     *           name="cachetags",
     *           required=true,
     *           @OA\Schema(
     *               format="string",
     *               type="array",
     *               @OA\Items(
     *                   type="string"
     *               )
     *          )
     *       ),
     *       @OA\Response(
     *           response=202,
     *           description="Cache deleted"
     *       ),
     *       @OA\Response(
     *           response=404,
     *           description="Cache not found"
     *       )
     *   )
     */

    /**
     * @inheritdoc
     */
    public function registerRoutes(RouteGroup $routeGroup): void
    {
        $routeGroup->delete('cache/all', $this->deleteAll(...));
        $routeGroup->delete('cache/{id}', $this->delete(...));
        $routeGroup->delete('cache/cachetags/{cachetags}', $this->deleteTag(...));
    }

    /**
     * @return ResponseInterface
     */
    public function deleteAll(): ResponseInterface
    {
        return $this->setStatusCode(202)->respondWithArray(['data' => $this->cache->flushAll()]);
    }

    /**
     * @inheritdoc
     */
    public function delete(ServerRequestInterface $request, array $params): ResponseInterface
    {
        return $this->setStatusCode(202)->respondWithArray(['data' => $this->cache->flush($params['id'])]);
    }

    public function deleteTag(ServerRequestInterface $request): ResponseInterface
    {
        $validatorResponse = $this->validateRequest($request, $this->deleteTagRequestValidationRules());
        if ($validatorResponse !== true) {
            return $this->sendInvalidFieldResponse($validatorResponse);
        }
        $tags = $request->getQueryParams()['tags'];
        foreach ($tags as &$tag) {
            if (\str_starts_with($tag, 'CACHING_GROUP_') && \defined($tag)) {
                $tag = \constant($tag);
            }
        }

        return $this->setStatusCode(202)->respondWithArray(['data' => $this->cache->flushTags($tags)]);
    }

    /**
     * @param ServerRequestInterface $request
     * @return array<string, string>
     */
    protected function deleteRequestValidationRules(ServerRequestInterface $request): array
    {
        return ['id' => 'required'];
    }

    /**
     * @return array<string, string>
     */
    protected function deleteTagRequestValidationRules(): array
    {
        return ['tags' => 'required|array'];
    }

    public function getRequestData(ServerRequestInterface $request): array
    {
        return ['body' => $request->getQueryParams(), 'id' => $request->getAttribute('id')];
    }
}
