<?php

declare(strict_types=1);

namespace JTL\Backend\ShippingClassWizard;

use Illuminate\Support\Collection;
use JsonException;
use JTL\DB\DbInterface;
use JTL\Router\Controller\Backend\ShippingMethodsController;
use stdClass;

/**
 * Class ShippingMethodHelper
 * @package JTL\Backend
 */
final class Helper
{
    private DbInterface $db;

    private Builder $builder;

    private static ?int $shippingClassCount = null;

    private ShippingMethodsController $controller;

    private function __construct(DbInterface $db, ShippingMethodsController $controller)
    {
        $this->controller = $controller;
        $this->db         = $db;

        $shippingClassIds = \array_map(
            '\intval',
            \array_column(
                $db->getObjects('SELECT kVersandklasse AS id FROM tversandklasse'),
                'id'
            )
        );
        $this->builder    = new Builder($shippingClassIds);
    }

    public static function instance(DbInterface $db, ShippingMethodsController $controller): self
    {
        return new self($db, $controller);
    }

    public function getShippingClassCount(): int
    {
        if (self::$shippingClassCount === null) {
            self::$shippingClassCount = $this->db->getSingleInt(
                'SELECT COUNT(kVersandklasse) AS classCount FROM tversandklasse',
                'classCount'
            );
        }

        return self::$shippingClassCount;
    }

    public function loadDefinition(int $shippingMethodID, string $classIds = ''): Definition
    {
        $wizard = $this->db->getSingleObject(
            'SELECT kVersandart, definition, result_hash
                FROM shipping_class_wizard
                WHERE kVersandart = :id',
            [
                'id' => $shippingMethodID,
            ]
        );

        if ($wizard === null) {
            return Definition::createEmpty($classIds);
        }

        $definition = $wizard->definition ?? 'a:1:{s:12:"combinations";s:3:"all";}';
        try {
            return Definition::jsonDecode($definition, $wizard->result_hash);
        } catch (JsonException) {
            return Definition::createEmpty($classIds);
        }
    }

    /**
     * @throws JsonException
     */
    public function saveDefinition(int $shippingMethodID, Definition $shippingMethodDefinition): void
    {
        $this->db->upsert(
            'shipping_class_wizard',
            (object)[
                'kVersandart' => $shippingMethodID,
                'definition'  => \json_encode($shippingMethodDefinition, JSON_THROW_ON_ERROR),
                'result_hash' => $shippingMethodDefinition->getResultHash(),
            ]
        );
    }

    /**
     * @return Collection<int, stdClass>
     */
    public function getNamedShippingClasses(): Collection
    {
        return $this->db->getCollection(
            'SELECT kVersandklasse, cName
                FROM tversandklasse
                ORDER BY cName'
        );
    }

    public function getShippingMethod(int $methodId): ?stdClass
    {
        return $this->db->getSingleObject(
            'SELECT kVersandart, cName, cVersandklassen
                FROM tversandart
                WHERE kVersandart = :id',
            [
                'id' => $methodId,
            ]
        );
    }

    public function createResultHash(string $shippingClasses): string
    {
        return \md5(\trim($shippingClasses));
    }

    /**
     * @return string[]
     */
    public function getActiveShippingClassesOverview(string $shippingClasses): array
    {
        return $this->controller->getActiveShippingClassesOverview($shippingClasses);
    }

    public function buildShippingClasses(Definition $definition): string
    {
        switch ($definition->getCombinationType()) {
            case CombineTypes::ALL:
                return '-1';
            case CombineTypes::COMBINE_SINGLE:
                $combis = $definition->isLogicAnd()
                    ? $this->builder->combineSingleAnd($definition->getAllClassDefinitions())
                    : $this->builder->combineSingleOr($definition->getAllClassDefinitions());
                break;
            case CombineTypes::COMBINE_ALL:
                $combis = $definition->isLogicAnd()
                    ? $this->builder->combineAllAnd($definition->getAllClassDefinitions())
                    : $this->builder->combineAllOr($definition->getAllClassDefinitions());
                break;
            case CombineTypes::EXCLUSIVE:
                $combis = $definition->isLogicAnd()
                    ? $this->builder->exclusiveAnd($definition->getAllClassDefinitions())
                    : $this->builder->exclusiveOr($definition->getAllClassDefinitions());
                break;
            default:
                $combis = new Collection();
        }

        return $definition->isInverted()
            ? $this->builder->invert($combis)->implode(' ')
            : $combis->implode(' ');
    }
}
