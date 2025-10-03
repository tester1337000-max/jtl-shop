<?php

declare(strict_types=1);

namespace JTL\Installation\Faker;

use Faker\Provider\Base;
use Intervention\Image\Drivers\Gd\Driver as GDDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use Intervention\Image\Typography\FontFactory;

/**
 * Class ImageProvider
 * @package JTL\Installation\Faker
 */
class ImageProvider extends Base
{
    private const JPEG_QUALITY = 90;

    private const DEFAULT_TEXT_COLOR = '#ffffff';

    private const DEFAULT_TEXT_FORMAT = '%width%x%height%';

    /**
     * The ultimate web 2.0 color list.
     *
     * https://docs.google.com/spreadsheets/d/1DhLdGhV4Fv_amgIbi5o2UFYZkkdp_TQNQcL6Of3LbCk/pub?output=html
     * @var string[]
     */
    private static array $colors = [
        '#5b5b95',
        '#C79810',
        '#c69d18',
        '#6BBA70',
        '#ff6600',
        '#1393c0',
        '#4CADD4',
        '#ff0000',
        '#277fba',
        '#57A9FD',
        '#B71313',
        '#9AC65C',
        '#0e63fd',
        '#99cc33',
        '#3775e2',
        '#8dbb01',
        '#ffb640',
        '#1b5891',
        '#356AA0',
        '#e24602',
        '#4caae4',
        '#ffcc00',
        '#cf5700',
        '#D15600',
        '#5d82ff',
        '#00bada',
        '#3b5999',
        '#0170ca',
        '#eb003a',
        '#98cc00',
        '#bd1d01',
        '#0061DE',
        '#FF0084',
        '#0f0f0f',
        '#4096EE',
        '#a71a10',
        '#025aa2',
        '#e84c1f',
        '#FD65C2',
        '#d50000',
        '#ef9a19',
        '#96c63f',
        '#69dbff',
        '#FFC300',
        '#9CB6DE',
        '#D61C39',
        '#d10039',
        '#fe198e',
        '#bedb8a',
        '#1a7fb3',
        '#FF7B38',
        '#ff7638',
        '#3d5381',
        '#febf0f',
        '#f28fbf',
        '#ff910d',
        '#e51837',
        '#87c1e7',
        '#009f59',
        '#00457B',
        '#2971AD',
        '#fa9b65',
        '#3F4C6B',
        '#FF1A00',
        '#003399',
        '#478898',
        '#62b857',
        '#006E2E',
        '#00722d',
        '#2d9500',
        '#ec449b',
        '#5a471c',
        '#030303',
        '#ff6200',
        '#295e92',
        '#28CF21',
        '#383121',
        '#ff4600',
        '#6dc646',
        '#CC0000',
        '#FF7400',
        '#B02B2C',
        '#e51905',
        '#c00000',
        '#f78325',
        '#924357',
        '#36393D',
        '#87be2f',
        '#6cd0f6',
        '#03646a',
        '#00a8aa',
        '#89c122',
        '#9ac80d',
        '#6b9cc9',
        '#6699cc',
        '#505050',
        '#21628c',
        '#e5791e',
        '#057db9',
        '#2acc54',
        '#5ebe8f',
        '#780000',
        '#008C00',
        '#4ABA00',
        '#7f7f7f',
        '#EA0101',
        '#003368',
        '#fcbd00',
        '#d71920',
        '#128f34',
        '#0f0e13',
        '#174c89',
        '#64bb69',
        '#73880A',
        '#F3AE48',
        '#4db848',
        '#fc0234',
        '#d11001',
        '#ff3237',
        '#FF6666',
        '#03a0fa',
        '#2e2d2e',
    ];

    /**
     * @var array<string, string>
     */
    private static array $colorMapping = [
        'AliceBlue'            => '#F0F8FF',
        'AntiqueWhite'         => '#FAEBD7',
        'Aqua'                 => '#00FFFF',
        'Aquamarine'           => '#7FFFD4',
        'Azure'                => '#F0FFFF',
        'Beige'                => '#F5F5DC',
        'Bisque'               => '#FFE4C4',
        'Black'                => '#000000',
        'BlanchedAlmond'       => '#FFEBCD',
        'Blue'                 => '#0000FF',
        'BlueViolet'           => '#8A2BE2',
        'Brown'                => '#A52A2A',
        'BurlyWood'            => '#DEB887',
        'CadetBlue'            => '#5F9EA0',
        'Chartreuse'           => '#7FFF00',
        'Chocolate'            => '#D2691E',
        'Coral'                => '#FF7F50',
        'CornflowerBlue'       => '#6495ED',
        'Cornsilk'             => '#FFF8DC',
        'Crimson'              => '#DC143C',
        'Cyan'                 => '#00FFFF',
        'DarkBlue'             => '#00008B',
        'DarkCyan'             => '#008B8B',
        'DarkGoldenRod'        => '#B8860B',
        'DarkGray'             => '#A9A9A9',
        'DarkGreen'            => '#006400',
        'DarkKhaki'            => '#BDB76B',
        'DarkMagenta'          => '#8B008B',
        'DarkOliveGreen'       => '#556B2F',
        'Darkorange'           => '#FF8C00',
        'DarkOrchid'           => '#9932CC',
        'DarkRed'              => '#8B0000',
        'DarkSalmon'           => '#E9967A',
        'DarkSeaGreen'         => '#8FBC8F',
        'DarkSlateBlue'        => '#483D8B',
        'DarkSlateGray'        => '#2F4F4F',
        'DarkTurquoise'        => '#00CED1',
        'DarkViolet'           => '#9400D3',
        'DeepPink'             => '#FF1493',
        'DeepSkyBlue'          => '#00BFFF',
        'DimGray'              => '#696969',
        'DodgerBlue'           => '#1E90FF',
        'FireBrick'            => '#B22222',
        'FloralWhite'          => '#FFFAF0',
        'ForestGreen'          => '#228B22',
        'Fuchsia'              => '#FF00FF',
        'Gainsboro'            => '#DCDCDC',
        'GhostWhite'           => '#F8F8FF',
        'Gold'                 => '#FFD700',
        'GoldenRod'            => '#DAA520',
        'Gray'                 => '#808080',
        'Green'                => '#008000',
        'GreenYellow'          => '#ADFF2F',
        'HoneyDew'             => '#F0FFF0',
        'HotPink'              => '#FF69B4',
        'IndianRed'            => '#CD5C5C',
        'Indigo'               => '#4B0082',
        'Ivory'                => '#FFFFF0',
        'Khaki'                => '#F0E68C',
        'Lavender'             => '#E6E6FA',
        'LavenderBlush'        => '#FFF0F5',
        'LawnGreen'            => '#7CFC00',
        'LemonChiffon'         => '#FFFACD',
        'LightBlue'            => '#ADD8E6',
        'LightCoral'           => '#F08080',
        'LightCyan'            => '#E0FFFF',
        'LightGoldenRodYellow' => '#FAFAD2',
        'LightGray'            => '#D3D3D3',
        'LightGreen'           => '#90EE90',
        'LightPink'            => '#FFB6C1',
        'LightSalmon'          => '#FFA07A',
        'LightSeaGreen'        => '#20B2AA',
        'LightSkyBlue'         => '#87CEFA',
        'LightSlateGray'       => '#778899',
        'LightSteelBlue'       => '#B0C4DE',
        'LightYellow'          => '#FFFFE0',
        'Lime'                 => '#00FF00',
        'LimeGreen'            => '#32CD32',
        'Linen'                => '#FAF0E6',
        'Magenta'              => '#FF00FF',
        'Maroon'               => '#800000',
        'MediumAquaMarine'     => '#66CDAA',
        'MediumBlue'           => '#0000CD',
        'MediumOrchid'         => '#BA55D3',
        'MediumPurple'         => '#9370DB',
        'MediumSeaGreen'       => '#3CB371',
        'MediumSlateBlue'      => '#7B68EE',
        'MediumSpringGreen'    => '#00FA9A',
        'MediumTurquoise'      => '#48D1CC',
        'MediumVioletRed'      => '#C71585',
        'MidnightBlue'         => '#191970',
        'MintCream'            => '#F5FFFA',
        'MistyRose'            => '#FFE4E1',
        'Moccasin'             => '#FFE4B5',
        'NavajoWhite'          => '#FFDEAD',
        'Navy'                 => '#000080',
        'OldLace'              => '#FDF5E6',
        'Olive'                => '#808000',
        'OliveDrab'            => '#6B8E23',
        'Orange'               => '#FFA500',
        'OrangeRed'            => '#FF4500',
        'Orchid'               => '#DA70D6',
        'PaleGoldenRod'        => '#EEE8AA',
        'PaleGreen'            => '#98FB98',
        'PaleTurquoise'        => '#AFEEEE',
        'PaleVioletRed'        => '#DB7093',
        'PapayaWhip'           => '#FFEFD5',
        'PeachPuff'            => '#FFDAB9',
        'Peru'                 => '#CD853F',
        'Pink'                 => '#FFC0CB',
        'Plum'                 => '#DDA0DD',
        'PowderBlue'           => '#B0E0E6',
        'Purple'               => '#800080',
        'Red'                  => '#FF0000',
        'RosyBrown'            => '#BC8F8F',
        'RoyalBlue'            => '#4169E1',
        'SaddleBrown'          => '#8B4513',
        'Salmon'               => '#FA8072',
        'SandyBrown'           => '#F4A460',
        'SeaGreen'             => '#2E8B57',
        'SeaShell'             => '#FFF5EE',
        'Sienna'               => '#A0522D',
        'Silver'               => '#C0C0C0',
        'SkyBlue'              => '#87CEEB',
        'SlateBlue'            => '#6A5ACD',
        'SlateGray'            => '#708090',
        'Snow'                 => '#FFFAFA',
        'SpringGreen'          => '#00FF7F',
        'SteelBlue'            => '#4682B4',
        'Tan'                  => '#D2B48C',
        'Teal'                 => '#008080',
        'Thistle'              => '#D8BFD8',
        'Tomato'               => '#FF6347',
        'Turquoise'            => '#40E0D0',
        'Violet'               => '#EE82EE',
        'Wheat'                => '#F5DEB3',
        'White'                => '#FFFFFF',
        'WhiteSmoke'           => '#F5F5F5',
        'Yellow'               => '#FFFF00',
        'YellowGreen'          => '#9ACD32'
    ];

    /**
     * Generate a new image to disk and return its location.
     * @param string|null $dir Path of the generated file, use system temp dir if null
     * @param int         $width Image width in pixels
     * @param int         $height Image height in pixels
     * @param string      $format Image format - jpg or png
     * @param bool        $fullPath return full path of generated file or just filename
     * @param string|null $text Text to generate on the image
     * @param string|null $textColor Text color in hexadecimal format
     * @param string|null $backgroundColor Background color in hexadecimal format
     * @param string      $fontPath Ppath to the font file
     * @param string|null $colorName Optional full color name that will be converted to hex background color
     * @return string
     */
    public static function imageFile(
        ?string $dir = null,
        int $width = 800,
        int $height = 600,
        string $format = 'png',
        bool $fullPath = true,
        ?string $text = null,
        ?string $textColor = null,
        ?string $backgroundColor = null,
        string $fontPath = \PFAD_ROOT . 'admin/templates/bootstrap/fonts/SourceCodePro-Black.ttf',
        ?string $colorName = null
    ): string {
        $dir = $dir ?? \sys_get_temp_dir();
        if (!\is_dir($dir) || !\is_writable($dir)) {
            throw new \InvalidArgumentException(\sprintf('Cannot write to directory "%s"', $dir));
        }
        // generate a random filename. Use the server address so that a file
        // generated at the same time on a different server won't have a collision.
        $name      = \md5(\uniqid($_SERVER['SERVER_ADDR'] ?? '', true));
        $filename  = \implode('.', [$name, $format]);
        $filepath  = $dir . \DIRECTORY_SEPARATOR . $filename;
        $text      = $text ?? self::DEFAULT_TEXT_FORMAT;
        $textColor = $textColor ?? self::DEFAULT_TEXT_COLOR;
        if ($backgroundColor === null && $colorName !== null) {
            $backgroundColor = self::$colorMapping[$colorName] ?? null;
        }
        $backgroundColor = $backgroundColor ?? self::randomElement(self::$colors);
        $textColor       = self::hexColor($textColor);
        $backgroundColor = self::hexColor($backgroundColor);
        $formattedText   = \str_replace(
            ['%width%', '%height%', '%format%', '%file%', '%filepath%', '%color%', '%bgcolor%'],
            [(string)$width, (string)$height, $format, $filename, $filepath, $textColor, $backgroundColor],
            $text
        );
        $manager         = new ImageManager(\extension_loaded('imagick') ? new ImagickDriver() : new GDDriver());
        $canvas          = $manager->create($width, $height);
        $canvas->fill($backgroundColor);
        $canvas->text(
            $formattedText,
            20,
            $height / 2,
            static function (FontFactory $font) use ($textColor, $fontPath): void {
                $font->file($fontPath);
                $font->size(40);
                $font->color($textColor);
                $font->align('left');
                $font->valign('top');
            }
        );
        $canvas->save($filepath, self::JPEG_QUALITY, $format);

        return $fullPath ? $filepath : $filename;
    }

    private static function hexColor(string $color): string
    {
        if (\preg_match('/^#?([A-Fa-f\d]{6}|[A-Fa-f\d]{3})$/', $color, $rgb)) {
            return '#' . $rgb[1];
        }
        throw new \InvalidArgumentException(\sprintf('Unrecognized hexcolor "%s"', $color));
    }
}
