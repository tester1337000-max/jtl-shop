<?php

declare(strict_types=1);

namespace JTL\Media;

use Exception;
use JTL\Media\Image\Category;
use JTL\Media\Image\Characteristic;
use JTL\Media\Image\CharacteristicValue;
use JTL\Media\Image\ConfigGroup;
use JTL\Media\Image\Manufacturer;
use JTL\Media\Image\News;
use JTL\Media\Image\NewsCategory;
use JTL\Media\Image\OPC;
use JTL\Media\Image\Product;
use JTL\Media\Image\Variation;
use JTL\Shop;

use function Functional\first;
use function Functional\some;

/**
 * Class Media
 * @package JTL\Media
 */
class Media
{
    private static ?self $instance = null;

    /**
     * @var IMedia[]
     */
    private array $registeredClasses = [];

    /**
     * @var array<string, class-string<IMedia>>
     */
    private static array $classMapper = [
        Image::TYPE_CATEGORY             => Category::class,
        Image::TYPE_CHARACTERISTIC       => Characteristic::class,
        Image::TYPE_CHARACTERISTIC_VALUE => CharacteristicValue::class,
        Image::TYPE_CONFIGGROUP          => ConfigGroup::class,
        Image::TYPE_MANUFACTURER         => Manufacturer::class,
        Image::TYPE_NEWS                 => News::class,
        Image::TYPE_NEWSCATEGORY         => NewsCategory::class,
        Image::TYPE_OPC                  => OPC::class,
        Image::TYPE_PRODUCT              => Product::class,
        Image::TYPE_VARIATION            => Variation::class
    ];

    /**
     * @return class-string<IMedia>
     */
    public static function getClass(string $imageType): string
    {
        return self::$classMapper[$imageType] ?? Product::class;
    }

    public static function getInstance(): self
    {
        return self::$instance ?? new self();
    }

    public function __construct()
    {
        self::$instance = $this;
        $db             = Shop::Container()->getDB();
        foreach (self::$classMapper as $imageType => $class) {
            $this->register(new $class($db), $imageType);
        }
    }

    public function register(IMedia $media, string $imageType): self
    {
        $this->registeredClasses[] = $media;
        if (!\array_key_exists($imageType, self::$classMapper)) {
            self::$classMapper[$imageType] = \get_class($media);
        }

        return $this;
    }

    /**
     * @return IMedia[]
     */
    public function getRegisteredClasses(): array
    {
        return $this->registeredClasses;
    }

    /**
     * @return class-string<IMedia>[]
     */
    public function getRegisteredClassNames(): array
    {
        return \array_values(self::$classMapper);
    }

    public function isValidRequest(string $requestUri): bool
    {
        return some($this->registeredClasses, fn(IMedia $e): bool => $e::isValid($requestUri));
    }

    /**
     * @throws Exception
     */
    public function handleRequest(string $requestUri): void
    {
        /** @var IMedia|null $first */
        $first = first($this->registeredClasses, fn(IMedia $type): bool => $type::isValid($requestUri));

        $first?->handle($requestUri);
    }
}
