<?php

declare(strict_types=1);

namespace JTL\Installation\Faker;

use Faker\Provider\Base as FakerBase;

/**
 * Class Commerce
 * @package JTL\Installation\Faker
 */
class Commerce extends FakerBase
{
    /**
     * @var string[]
     */
    protected static array $category = [
        'Antiques & Art',
        'Cars & Motorcycles: Vehicles',
        'Cars & Motorcycles: Parts',
        'Baby',
        'Beauty & Health',
        'Stamps',
        'Business & Industrial',
        'Books',
        'Office & Stationery',
        'Computers, Tablets & Networking',
        'Gourmet',
        'Movies & DVDs',
        'Photo & Camcorder',
        'Garden & Terrace',
        'Mobile Phones & Communication',
        'Household Appliances',
        'Pet Supplies',
        'Home Improvement',
        'Real Estate',
        'Clothing & Accessories',
        'Model Making',
        'Music',
        'Musical Instruments',
        'Furniture & Living',
        'Coins',
        'PC & Video Games',
        'Travel',
        'Collectibles & Antiques',
        'Toys',
        'Sports',
        'Tickets',
        'TV, Video & Audio',
        'Watches & Jewelry',
        'Novels',
        'Software',
        'Hardware',
        'Women\'s Jewelry',
        'Spirits',
        'Home Cinema',
        'Camera & Photo',
        'Headphones',
        'Drugstore Items',
        'Navigation',
        'Chargers',
        'Landline Phones',
        'Wearables',
        'MP3 Players',
        'CD Players',
        'Miscellaneous',
        'Shoes',
        'Accessories',
        'Sweaters',
        'Smart Home',
        'Indoor Lighting',
        'Outdoor Lighting',
        'Terrace',
        'Camping',
        'Fans',
        'Children\'s Room',
        'Backpacks',
        'Sandals',
        'Sneakers',
        'Equipment',
        'Yoga',
        'Body Care',
        'Make-Up',
        'Fragrances',
        'Sunscreen',
        'Oral Care',
        'Snowboards',
        'Football',
        'Basketball',
        'Handball',
        'Badminton',
        'Gold',
        'Sports Bags',
        'Underwear',
        'Cycling',
        'Dietary Supplements',
        'Dog Food'
    ];

    /**
     * @var string[]
     */
    protected static array $adjective = [
        'Small',
        'Ergonomic',
        'Rustic',
        'Intelligent',
        'Gorgeous',
        'Incredible',
        'Fantastic',
        'Practical',
        'Smooth',
        'Enormous',
        'Mediocre',
        'Light',
        'Aerodynamic',
        'Durable',
        'Stylish',
        'High-quality',
        'Great',
        'Affordable',
        'Exorbitant',
        'Excellent',
        'Good',
        'Popular',
        'Soft',
        'Hard',
        'Impressive',
        'Gigantic',
        'Greasy',
        'Sticky',
        'Entertaining',
        'Concerning',
        'Neat',
        'Bold',
        'Lively',
        'Quirky',
        'Airy',
        'Rich',
        'Elegant',
        'Overpriced',
    ];

    /**
     * @var string[]
     */
    protected static array $material = [
        'Steel',
        'Concrete',
        'Plastic',
        'Cotton',
        'Granite',
        'Rubber',
        'Leather',
        'Silk',
        'Wool',
        'Linen',
        'Marble',
        'Iron',
        'Bronze',
        'Copper',
        'Aluminum',
        'Paper',
        'Plutonium',
        'Plastic',
        'Water',
        'Polyamide',
        'Viscose',
        'Elastane',
        'Glass',
        'Velvet',
    ];

    /**
     * @var string[]
     */
    protected static array $product = [
        'Chair',
        'Car',
        'Computer',
        'Glove',
        'Pants',
        'Shirt',
        'Bikini',
        'Shoes',
        'Hat',
        'Plate',
        'Knife',
        'Bottle',
        'Coat',
        'Paperweight',
        'Underwear',
        'Chair',
        'Bed',
        'Mobile Phone',
        'Lamp',
        'Keyboard',
        'Bag',
        'Bench',
        'Watch',
        'Wallet',
        'Pliers',
        'Kettle',
        'Slide',
        'Sock',
        'Glasses',
        'Umbrella',
        'Laptop',
        'Telephone',
        'Board',
        'Glass',
        'Plate',
        'Fork',
        'Bra',
        'Ring',
        'Cushion',
        'Blanket',
        'Scarf',
        'Cap',
        'Mobile Phone',
        'Swim Trunks',
        'Tank Top',
        'Cloth',
        'Fan',
        'Air Purifier',
        'Mailbox',
        'Battery',
        'Toilet Seat',
        'Razor',
        'Band-Aid',
        'Blender'
    ];

    public function productName(): string
    {
        return static::randomElement(self::$adjective)
            . ' ' . static::randomElement(self::$material)
            . ' ' . static::randomElement(self::$product);
    }

    public function productNameByIndex(int $productIdx, int $adjectiveIdx, int $materialIdx): string
    {
        return self::$adjective[$adjectiveIdx]
            . ' ' . self::$material[$materialIdx]
            . ' ' . self::$product[$productIdx];
    }

    public function department(): string
    {
        return static::randomElement(self::$category);
    }

    public function category(): string
    {
        return static::randomElement(self::$category);
    }

    public function material(): string
    {
        return static::randomElement(self::$material);
    }
}
