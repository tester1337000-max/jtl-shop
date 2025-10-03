<?php

declare(strict_types=1);

namespace JTL\Helpers;

use GdImage;
use InvalidArgumentException;
use JTL\DB\DbInterface;
use JTL\Media\Image;
use JTL\Media\Image\Overlay as OverlayImage;
use JTL\Shop;

/**
 * Class Overlay
 * @package JTL\Helpers
 * @since 5.0.0
 */
readonly class Overlay
{
    public function __construct(private DbInterface $db)
    {
    }

    /**
     *  get overlays (images) from template folder (original) and create for each valid image the corresponding files
     * (sizes) and data (default settings in tsuchspecialoverlaysprache)
     * example filename: overlay_1_7.jpg | 1 -> overlay language, 7 -> overlay type
     */
    public function loadOverlaysFromTemplateFolder(string $template): bool
    {
        $overlayPath = \PFAD_ROOT . \PFAD_TEMPLATES . $template . \PFAD_OVERLAY_TEMPLATE;
        $dir         = $overlayPath . OverlayImage::ORIGINAL_FOLDER_NAME;
        if (!\is_dir($dir)) {
            return false;
        }
        if (!\is_writable($overlayPath)) {
            Shop::Container()->getAlertService()->addError(
                \sprintf(\__('errorOverlayWritePermissions'), \PFAD_TEMPLATES . $template . \PFAD_OVERLAY_TEMPLATE),
                'errorOverlayWritePermissions',
                ['saveInSession' => true]
            );

            return false;
        }

        foreach (\scandir($dir, \SORT_NUMERIC) ?: [] as $overlay) {
            $overlayParts = \explode('_', $overlay);
            if (\count($overlayParts) === 3 && $overlayParts[0] === OverlayImage::IMAGENAME_TEMPLATE) {
                $filePath = $dir . '/' . $overlay;
                $lang     = (int)$overlayParts[1];
                $type     = (int)\substr($overlayParts[2], 0, \strpos($overlayParts[2], '.') ?: null);
                if ($lang === 0 || $type === 0) {
                    continue;
                }
                $defaultOverlay = $this->db->getSingleObject(
                    'SELECT *
                      FROM tsuchspecialoverlaysprache
                      WHERE kSprache = :lang
                        AND kSuchspecialOverlay = :type
                        AND cTemplate IN (:templateName, :defaultName)
                      ORDER BY FIELD(cTemplate, :templateName, :defaultName)
                      LIMIT 1',
                    [
                        'lang'         => $lang,
                        'type'         => $type,
                        'templateName' => $template,
                        'defaultName'  => OverlayImage::DEFAULT_TEMPLATE
                    ]
                );
                // use default settings for new overlays
                if ($defaultOverlay !== null && $defaultOverlay->cTemplate !== $template) {
                    $this->saveConfig(
                        $type,
                        (array)$defaultOverlay,
                        [
                            'type'     => \mime_content_type($filePath),
                            'tmp_name' => $filePath,
                            'name'     => $overlay
                        ],
                        $lang,
                        $template
                    );
                }
            }
        }
        Shop::Container()->getCache()->flushTags([\CACHING_GROUP_ARTICLE]);

        return true;
    }

    /**
     * @param int          $overlayID
     * @param array<mixed> $post
     * @param array<mixed> $files
     * @param int|null     $lang
     * @param string|null  $template
     * @return bool
     * @former speicherEinstellung()
     */
    public function saveConfig(
        int $overlayID,
        array $post,
        array $files,
        ?int $lang = null,
        ?string $template = null
    ): bool {
        $overlay = OverlayImage::getInstance(
            $overlayID,
            $lang ?? (int)$_SESSION['editLanguageID'],
            $template,
            false
        );

        if ($overlay->getType() <= 0) {
            Shop::Container()->getAlertService()->addError(\__('invalidOverlay'), 'invalidOverlay');
            return false;
        }
        $overlay->setActive((int)$post['nAktiv'])
            ->setTransparency((int)$post['nTransparenz'])
            ->setSize((int)$post['nGroesse'])
            ->setPosition((int)($post['nPosition'] ?? 0))
            ->setPriority((int)$post['nPrio']);

        if (\mb_strlen($files['name']) > 0) {
            $template    = $template ?: Shop::Container()->getTemplateService()->getActiveTemplate()->getName();
            $overlayPath = \PFAD_ROOT . \PFAD_TEMPLATES . $template . \PFAD_OVERLAY_TEMPLATE;
            if (!\is_writable($overlayPath)) {
                Shop::Container()->getAlertService()->addError(
                    \sprintf(\__('errorOverlayWritePermissions'), \PFAD_TEMPLATES . $template . \PFAD_OVERLAY_TEMPLATE),
                    'errorOverlayWritePermissions',
                    ['saveInSession' => true]
                );

                return false;
            }

            $this->deleteImage($overlay);
            $overlay->setImageName(
                OverlayImage::IMAGENAME_TEMPLATE . '_' . $overlay->getLanguage() . '_' . $overlay->getType() .
                $this->mapFileType($files['type'])
            );
            $imageCreated = $this->saveImage($files, $overlay);
        }
        if (!isset($imageCreated) || $imageCreated) {
            $overlay->save();
        } else {
            Shop::Container()->getAlertService()->addError(
                \__('errorFileUploadGeneral'),
                'errorFileUploadGeneral',
                ['saveInSession' => true]
            );

            return false;
        }

        return true;
    }

    /**
     * @former loescheBild()
     */
    public function deleteImage(OverlayImage $overlay): void
    {
        foreach ($overlay->getPathSizes() as $path) {
            $path = \PFAD_ROOT . $path . $overlay->getImageName();
            if (\file_exists($path)) {
                @\unlink($path);
            }
        }
    }

    /**
     * @param array<mixed> $file
     * @former speicherBild()
     */
    public function saveImage(array $file, OverlayImage $overlay): bool
    {
        if (!Image::isImageUpload($file)) {
            return false;
        }
        $ext           = $this->mapFileType($file['type']);
        $original      = $file['tmp_name'];
        $sizesToCreate = [
            ['size' => \IMAGE_SIZE_XS, 'factor' => 1],
            ['size' => \IMAGE_SIZE_SM, 'factor' => 2],
            ['size' => \IMAGE_SIZE_MD, 'factor' => 3],
            ['size' => \IMAGE_SIZE_LG, 'factor' => 4]
        ];
        foreach ($sizesToCreate as $sizeToCreate) {
            if (!\is_dir(\PFAD_ROOT . $overlay->getPathSize($sizeToCreate['size']))) {
                \mkdir(\PFAD_ROOT . $overlay->getPathSize($sizeToCreate['size']), 0755, true);
            }
            $imageCreated = $this->createFixedOverlay(
                $original,
                $overlay->getSize() * $sizeToCreate['factor'],
                $overlay->getTransparancy(),
                $ext,
                \PFAD_ROOT . $overlay->getPathSize($sizeToCreate['size']) . $overlay->getImageName()
            );
            if (!$imageCreated) {
                return false;
            }
        }

        return true;
    }

    /**
     * @former erstelleFixedOverlay()
     */
    public function createFixedOverlay(
        string $image,
        int $size,
        int $transparency,
        string $extension,
        string $path
    ): bool {
        [$width, $height] = \getimagesize($image) ?: [0, 0];

        return $this->save(
            $this->load($image, $size, (int)($height * ($size / $width)), $transparency),
            $extension,
            $path
        );
    }

    /**
     * @former speicherOverlay
     */
    public function save(GdImage $im, string $extension, string $path, int $quality = 80): bool
    {
        if (!$extension) {
            return false;
        }
        return match ($extension) {
            '.jpg'  => \function_exists('imagejpeg') && \imagejpeg($im, $path, $quality),
            '.png'  => \function_exists('imagepng') && \imagepng($im, $path),
            '.gif'  => \function_exists('imagegif') && \imagegif($im, $path),
            '.bmp'  => \function_exists('imagewbmp') && \imagewbmp($im, $path),
            default => false,
        };
    }

    /**
     * @former mappeFileTyp()
     */
    public function mapFileType(string $type): string
    {
        return match ($type) {
            'image/gif'                => '.gif',
            'image/png', 'image/x-png' => '.png',
            'image/bmp'                => '.bmp',
            default                    => '.jpg',
        };
    }

    /**
     * @former ladeOverlay()
     * @throws InvalidArgumentException
     */
    public function load(string $image, int $width, int $height, int $transparency): GdImage
    {
        $src = $this->imageloadAlpha($image, $width, $height);
        if ($src === null) {
            throw new InvalidArgumentException('Could not load image ' . $image);
        }
        if ($transparency > 0) {
            /** @var GdImage|false $new */
            $new = \imagecreatetruecolor($width, $height);
            if ($new === false) {
                throw new InvalidArgumentException('Could not create image with dimensions ' . $width . '/' . $height);
            }
            \imagealphablending($new, false);
            \imagesavealpha($new, true);
            $transparent = \imagecolorallocatealpha($new, 255, 255, 255, 127) ?: 0;
            \imagefilledrectangle($new, 0, 0, $width, $height, $transparent);
            \imagealphablending($new, true);
            \imagesavealpha($new, true);

            $this->imagecopymergeAlpha($new, $src, 0, 0, 0, 0, $width, $height, 100 - $transparency);

            return $new;
        }

        return $src;
    }

    /**
     * @former imageload_alpha()
     */
    public function imageloadAlpha(string $img, int $width, int $height): ?GdImage
    {
        $imgInfo = \getimagesize($img) ?: [0, 0, 0];
        switch ($imgInfo[2]) {
            case \IMAGETYPE_GIF:
                $im = \imagecreatefromgif($img);
                break;
            case \IMAGETYPE_JPEG:
                $im = \imagecreatefromjpeg($img);
                break;
            case \IMAGETYPE_PNG:
                $im = \imagecreatefrompng($img);
                break;
            default:
                return null;
        }
        $new = \imagecreatetruecolor($width, $height);
        if ($new === false || $im === false) {
            return null;
        }
        if ($imgInfo[2] === \IMAGETYPE_GIF || $imgInfo[2] === \IMAGETYPE_PNG) {
            \imagealphablending($new, false);
            \imagesavealpha($new, true);
            $transparent = \imagecolorallocatealpha($new, 255, 255, 255, 127) ?: 0;
            \imagefilledrectangle($new, 0, 0, $width, $height, $transparent);
        }
        \imagecopyresampled($new, $im, 0, 0, 0, 0, $width, $height, $imgInfo[0], $imgInfo[1]);

        return $new;
    }

    /**
     * @former imagecopymerge_alpha()
     */
    public function imagecopymergeAlpha(
        GdImage $dst_im,
        GdImage $src_im,
        int $dst_x,
        int $dst_y,
        int $src_x,
        int $src_y,
        int $src_w,
        int $src_h,
        int $pct
    ): bool {
        $pct /= 100;
        // Get image width and height
        $w = \imagesx($src_im);
        $h = \imagesy($src_im);
        // Turn alpha blending off
        \imagealphablending($src_im, false);

        $minalpha = 0;
        // loop through image pixels and modify alpha for each
        for ($x = 0; $x < $w; $x++) {
            for ($y = 0; $y < $h; $y++) {
                // get current alpha value (represents the TANSPARENCY!)
                $colorxy = \imagecolorat($src_im, $x, $y);
                $alpha   = ($colorxy >> 24) & 0xFF;
                // calculate new alpha
                $alpha = 127 + 127 * $pct * ($alpha - 127) / (127 - $minalpha);
                // get the color index with new alpha
                $alphacolorxy = \imagecolorallocatealpha(
                    $src_im,
                    ($colorxy >> 16) & 0xFF,
                    ($colorxy >> 8) & 0xFF,
                    $colorxy & 0xFF,
                    (int)$alpha
                ) ?: 0;
                // set pixel with the new color + opacity
                if (!\imagesetpixel($src_im, $x, $y, $alphacolorxy)) {
                    return false;
                }
            }
        }

        return \imagecopy($dst_im, $src_im, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h);
    }
}
