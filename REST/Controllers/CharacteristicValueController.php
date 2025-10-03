<?php

declare(strict_types=1);

namespace JTL\REST\Controllers;

use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\Model\DataModelInterface;
use JTL\REST\Models\CharacteristicValueModel;
use Laminas\Diactoros\UploadedFile;
use League\Fractal\Manager;
use League\Route\RouteGroup;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use stdClass;

/**
 * Class CharacteristicValueController
 * @package JTL\REST\Controllers
 */
class CharacteristicValueController extends AbstractController
{
    public function __construct(Manager $fractal, protected DbInterface $db, protected JTLCacheInterface $cache)
    {
        parent::__construct(CharacteristicValueModel::class, $fractal, $this->db, $this->cache);
    }

    /**
     * @inheritdoc
     */
    public function registerRoutes(RouteGroup $routeGroup): void
    {
        $routeGroup->get('/characteristicValue', $this->index(...));
        $routeGroup->get('/characteristicValue/{id}', $this->show(...));
        $routeGroup->put('/characteristicValue/{id}', $this->update(...));
        $routeGroup->post('/characteristicValue', $this->create(...));
        $routeGroup->delete('/characteristicValue/{id}', $this->delete(...));
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
    protected function updateFromRequest(DataModelInterface $model, ServerRequestInterface $request): DataModelInterface
    {
        $model   = parent::updateFromRequest($model, $request);
        $uploads = $request->getUploadedFiles();
        if (isset($uploads['imagePath']) && \count($uploads['imagePath']) > 0) {
            /** @var UploadedFile $file */
            foreach ($uploads['image'] as $file) {
                $file->moveTo(\PFAD_ROOT . STORAGE_CHARACTERISTIC_VALUES . $file->getClientFilename());
            }
        }

        return $model;
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
            // tmerkmalwert has no auto increment ID
            $lastCharacteristicValueId = $this->db->getSingleInt(
                'SELECT MAX(kMerkmalWert) AS newID FROM tmerkmalwert',
                'newID'
            );
            $data->id                  = ++$lastCharacteristicValueId;
        }

        return $data;
    }

    /**
     * @inheritdoc
     * @param CharacteristicValueModel $item
     */
    protected function deletedItem(DataModelInterface $item): void
    {
        $this->db->queryPrepared(
            'DELETE FROM tseo WHERE cKey = :keyname AND kKey = :keyid',
            ['keyname' => 'kMerkmalWert', 'keyid' => $item->getId()],
        );
        parent::deletedItem($item);
    }

    /**
     * @inheritdoc
     */
    protected function createRequestValidationRules(ServerRequestInterface $request): array
    {
        return [
            'id'               => 'integer',
            'characteristicID' => 'required|integer'
        ];
    }
}
