<?php

declare(strict_types=1);

namespace JTL\REST;

use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\Events\Dispatcher;
use JTL\REST\Controllers\AbstractController;
use JTL\REST\Controllers\AttributeController;
use JTL\REST\Controllers\CacheController;
use JTL\REST\Controllers\CartController;
use JTL\REST\Controllers\CartItemController;
use JTL\REST\Controllers\CategoryAttributeController;
use JTL\REST\Controllers\CategoryController;
use JTL\REST\Controllers\CharacteristicController;
use JTL\REST\Controllers\CharacteristicValueController;
use JTL\REST\Controllers\CurrencyController;
use JTL\REST\Controllers\CustomerController;
use JTL\REST\Controllers\CustomerGroupController;
use JTL\REST\Controllers\ImageController;
use JTL\REST\Controllers\LanguageController;
use JTL\REST\Controllers\ManufacturerController;
use JTL\REST\Controllers\MeasurementUnitController;
use JTL\REST\Controllers\OrderController;
use JTL\REST\Controllers\PriceController;
use JTL\REST\Controllers\ProductAttributeController;
use JTL\REST\Controllers\ProductController;
use JTL\REST\Controllers\SeoController;
use JTL\REST\Controllers\ShippingMethodController;
use JTL\REST\Controllers\StockController;
use JTL\REST\Controllers\TaxClassController;
use JTL\REST\Controllers\TaxRateController;
use JTL\REST\Controllers\TaxZoneController;
use League\Fractal\Manager;
use League\Route\RouteGroup;

/**
 * @OA\Info(
 *     description="This is the JTL Shop 5 REST API. You can find
out more about this at
[https://www.jtl-software.de](https://www.jtl-software.de)",
 *     version="1.0.0",
 *     title="JTL REST API",
 *     @OA\Contact(
 *         email="info@jtl-software.de"
 *     )
 * )
 * @OA\Tag(
 *     name="product",
 *     description="All the products"
 * )
 * @OA\SecurityScheme(
 *     securityScheme="api_key",
 *     type="apiKey",
 *     in="header",
 *     name="X-API-KEY"
 * )
 */
final class Registrator
{
    /**
     * @var class-string[]
     */
    private static array $classes = [
        CategoryController::class,
        CategoryAttributeController::class,
        ProductController::class,
        ManufacturerController::class,
        AttributeController::class,
        CacheController::class,
        CharacteristicController::class,
        CharacteristicValueController::class,
        LanguageController::class,
        ProductAttributeController::class,
        SeoController::class,
        TaxClassController::class,
        TaxRateController::class,
        TaxZoneController::class,
        OrderController::class,
        CartController::class,
        CartItemController::class,
        CustomerController::class,
        StockController::class,
        PriceController::class,
        CustomerGroupController::class,
        ImageController::class,
        ShippingMethodController::class,
        CurrencyController::class,
        MeasurementUnitController::class,
    ];

    public function __construct(
        protected Manager $manager,
        protected DbInterface $db,
        protected JTLCacheInterface $cache
    ) {
    }

    public function register(RouteGroup $routeGroup): void
    {
        $classes = Dispatcher::getInstance()->getData(\HOOK_RESTAPI_REGISTER_CONTROLLER, self::$classes);
        foreach ($classes as $class) {
            /** @var AbstractController $instance */
            $instance = new $class($this->manager, $this->db, $this->cache);
            $instance->registerRoutes($routeGroup);
        }
    }
}
