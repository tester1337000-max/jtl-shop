<?php

declare(strict_types=1);

namespace JTL\Media\Image;

use JTL\Language\LanguageHelper;
use JTL\MagicCompatibilityTrait;
use JTL\Shop;
use stdClass;

/**
 * Class Overlay
 * @package JTL\Media\Image
 */
class Overlay
{
    use MagicCompatibilityTrait;

    /**
     * @var array<string, string>
     */
    protected static array $mapping = [
        'cURLKlein'           => 'URLKlein',
        'cURLNormal'          => 'URLNormal',
        'cURLGross'           => 'URLGross',
        'cURLRetina'          => 'URLRetina',
        'nPosition'           => 'Position',
        'nGroesse'            => 'Size',
        'nTransparenz'        => 'Transparance',
        'nMargin'             => 'Margin',
        'nAktiv'              => 'Active',
        'nPrio'               => 'Priority',
        'kSprache'            => 'Language',
        'kSuchspecialOverlay' => 'Type',
        'cTemplate'           => 'TemplateName',
        'cSuchspecial'        => 'Name',
        'cPfadKlein'          => 'URLKlein',
        'cPfadNormal'         => 'URLNormal',
        'cPfadGross'          => 'URLGross',
        'cPfadRetina'         => 'URLRetina',
        'cBildPfad'           => 'ImageName'
    ];

    private string $path;

    /**
     * @var array<string, string>
     */
    private array $pathSizes = [];

    private int $position = 0;

    private int $active = 0;

    private int $priority = 0;

    private int $margin = 0;

    private int $transparency = 0;

    private int $size = 0;

    private int $type = 0;

    private int $language = 0;

    private string $name = '';

    private string $imageName = '';

    private string $templateName = '';

    /**
     * @var array<string, string>
     */
    private array $urlSizes = [];

    /**
     * @since 5.3.0
     */
    private ?stdClass $noImageOverlay = null;

    public const IMAGENAME_TEMPLATE = 'overlay';

    public const IMAGE_DEFAULT = [
        'name'      => 'std_kSuchspecialOverlay',
        'extension' => '.png'
    ];

    public const DEFAULT_TEMPLATE = 'default';

    public const ORIGINAL_FOLDER_NAME = 'original';

    public function __construct(int $type, int $language, ?string $template = null)
    {
        $this->setType($type)
            ->setLanguage($language)
            ->setTemplateName($template)
            ->setPath(\PFAD_TEMPLATES . $this->getTemplateName() . \PFAD_OVERLAY_TEMPLATE);
    }

    public static function getInstance(
        int $type,
        int $language,
        ?string $template = null,
        bool $setFallbackPath = true
    ): self {
        return (new self($type, $language, $template))->loadFromDB($setFallbackPath);
    }

    public function loadFromDB(bool $setFallbackPath): self
    {
        $overlay = $this->getDataForLanguage($this->getLanguage())
            ?? $this->getDataForLanguage(LanguageHelper::getDefaultLanguage()->getId());
        $this->setActive(0)
            ->setMargin(0)
            ->setPosition(0)
            ->setPriority(0)
            ->setTransparency(0)
            ->setSize(0)
            ->setImageName('')
            ->setName('');
        if ($overlay !== null) {
            $name = isset($_SESSION['AdminAccount']) && \function_exists('__')
                ? \__($overlay->cSuchspecial)
                : $overlay->cSuchspecial;
            $this->setActive((int)$overlay->nAktiv)
                ->setMargin((int)$overlay->nMargin)
                ->setPosition((int)$overlay->nPosition)
                ->setPriority((int)$overlay->nPrio)
                ->setTransparency((int)$overlay->nTransparenz)
                ->setSize((int)$overlay->nGroesse)
                ->setImageName($overlay->cBildPfad)
                ->setName($name)
                ->setPathSizes();
            if ($setFallbackPath) {
                $this->setFallbackPath($overlay->cTemplate);
            }
            $this->setURLSizes();
        }

        return $this;
    }

    private function getDataForLanguage(int $language): ?stdClass
    {
        return Shop::Container()->getDB()->getSingleObject(
            'SELECT ssos.*, sso.cSuchspecial
                 FROM tsuchspecialoverlaysprache ssos
                 LEFT JOIN tsuchspecialoverlay sso
                     ON ssos.kSuchspecialOverlay = sso.kSuchspecialOverlay
                 WHERE ssos.kSprache = :languageID
                     AND ssos.kSuchspecialOverlay = :overlayID
                     AND ssos.cTemplate IN (:templateName, :defaultTemplate)
                 ORDER BY FIELD(ssos.cTemplate, :templateName, :defaultTemplate)
                 LIMIT 1',
            [
                'languageID'      => $language,
                'overlayID'       => $this->getType(),
                'templateName'    => $this->getTemplateName(),
                'defaultTemplate' => self::DEFAULT_TEMPLATE
            ]
        );
    }

    private function setFallbackPath(string $templateName): void
    {
        $fallbackPath      = false;
        $fallbackImageName = '';
        if (
            $templateName === self::DEFAULT_TEMPLATE
            || !\file_exists(\PFAD_ROOT . $this->getPathSize(\IMAGE_SIZE_SM) . $this->getImageName())
        ) {
            $defaultImgName = self::IMAGE_DEFAULT['name']
                . '_' . $this->getLanguage()
                . '_' . $this->getType() . self::IMAGE_DEFAULT['extension'];
            $imgName        = self::IMAGE_DEFAULT['name']
                . '_' . LanguageHelper::getDefaultLanguage()->getId()
                . '_' . $this->getType() . self::IMAGE_DEFAULT['extension'];

            if (\file_exists(\PFAD_ROOT . \PFAD_SUCHSPECIALOVERLAY_NORMAL . $defaultImgName)) {
                // default fallback path
                $fallbackImageName = $defaultImgName;
                $fallbackPath      = true;
            } else {
                $overlayDefaultLanguage = $this->getDataForLanguage(LanguageHelper::getDefaultLanguage()->getId());
                if ($overlayDefaultLanguage !== null) {
                    if (
                        $overlayDefaultLanguage->cTemplate !== self::DEFAULT_TEMPLATE
                        && \file_exists(
                            \PFAD_ROOT . $this->getPathSize(\IMAGE_SIZE_SM) . $overlayDefaultLanguage->cBildPfad
                        )
                    ) {
                        // fallback path for default language
                        $fallbackImageName = $overlayDefaultLanguage->cBildPfad;
                    } elseif (\file_exists(\PFAD_ROOT . \PFAD_SUCHSPECIALOVERLAY_NORMAL . $imgName)) {
                        // default fallback path for default language
                        $fallbackImageName = $imgName;
                        $fallbackPath      = true;
                    }
                }
            }
        }
        if ($fallbackPath) {
            $this->setPath(\PFAD_SUCHSPECIALOVERLAY)
                ->setPathSizes(true);
        }
        if ($fallbackImageName !== '') {
            $this->setImageName($fallbackImageName);
        }
    }

    public function save(): void
    {
        $db          = Shop::Container()->getDB();
        $overlayData = (object)[
            'nAktiv'       => $this->getActive(),
            'nPrio'        => $this->getPriority(),
            'nTransparenz' => $this->getTransparancy(),
            'nGroesse'     => $this->getSize(),
            'nPosition'    => $this->getPosition(),
            'cBildPfad'    => $this->getImageName(),
            'nMargin'      => 5
        ];

        $check = $db->getSingleObject(
            'SELECT * FROM tsuchspecialoverlaysprache
              WHERE kSprache = :languageID
                AND kSuchspecialOverlay = :overlayID
                AND cTemplate = :templateName',
            [
                'languageID'   => $this->getLanguage(),
                'overlayID'    => $this->getType(),
                'templateName' => $this->getTemplateName()
            ]
        );
        if ($check) {
            $db->update(
                'tsuchspecialoverlaysprache',
                ['kSuchspecialOverlay', 'kSprache', 'cTemplate'],
                [$this->getType(), $this->getLanguage(), $this->getTemplateName()],
                $overlayData
            );
        } else {
            $overlayData->kSuchspecialOverlay = $this->getType();
            $overlayData->kSprache            = $this->getLanguage();
            $overlayData->cTemplate           = $this->getTemplateName();
            $db->insert('tsuchspecialoverlaysprache', $overlayData);
        }
    }

    public function setImageName(string $imageName): self
    {
        $this->imageName = $imageName;

        return $this;
    }

    public function getImageName(): string
    {
        return $this->imageName;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setTemplateName(?string $template = null): self
    {
        $this->templateName = $template
            ?? $_SESSION['cTemplate']
            ?? Shop::Container()->getTemplateService()->getActiveTemplate()->getName();

        return $this;
    }

    public function getTemplateName(): string
    {
        return $this->templateName;
    }

    /**
     * @return array<string, string>
     */
    public function getPathSizes(): array
    {
        return $this->pathSizes;
    }

    public function getPathSize(string $size): ?string
    {
        return $this->pathSizes[$size] ?? null;
    }

    public function setPathSizes(bool $default = false): self
    {
        $this->pathSizes = [
            \IMAGE_SIZE_XS => $default ? \PFAD_SUCHSPECIALOVERLAY_KLEIN : $this->getPath() . \IMAGE_SIZE_XS . '/',
            \IMAGE_SIZE_SM => $default ? \PFAD_SUCHSPECIALOVERLAY_NORMAL : $this->getPath() . \IMAGE_SIZE_SM . '/',
            \IMAGE_SIZE_MD => $default ? \PFAD_SUCHSPECIALOVERLAY_GROSS : $this->getPath() . \IMAGE_SIZE_MD . '/',
            \IMAGE_SIZE_LG => $default ? \PFAD_SUCHSPECIALOVERLAY_RETINA : $this->getPath() . \IMAGE_SIZE_LG . '/'
        ];

        return $this;
    }

    /**
     * @since 5.3.0
     */
    public function getCssAndText(): ?stdClass
    {
        return $this->noImageOverlay ?? null;
    }

    /**
     * @since 5.3.0
     */
    public function setCssAndText(?stdClass $data = null): self
    {
        $this->noImageOverlay = $data;

        return $this;
    }

    public function getURL(string $size): ?string
    {
        return $this->urlSizes[$size] ?? null;
    }

    public function setURLSizes(): self
    {
        $shopURL        = Shop::getImageBaseURL();
        $this->urlSizes = [
            \IMAGE_SIZE_XS => $shopURL . $this->getPathSize(\IMAGE_SIZE_XS) . $this->getImageName(),
            \IMAGE_SIZE_SM => $shopURL . $this->getPathSize(\IMAGE_SIZE_SM) . $this->getImageName(),
            \IMAGE_SIZE_MD => $shopURL . $this->getPathSize(\IMAGE_SIZE_MD) . $this->getImageName(),
            \IMAGE_SIZE_LG => $shopURL . $this->getPathSize(\IMAGE_SIZE_LG) . $this->getImageName()
        ];

        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): self
    {
        $this->path = $path;

        return $this;
    }

    private function setType(int $type): self
    {
        $this->type = $type;

        return $this;
    }

    private function setLanguage(int $language): self
    {
        $this->language = $language;

        return $this;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function setActive(int $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function setPriority(int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    public function setMargin(int $margin): self
    {
        $this->margin = $margin;

        return $this;
    }

    /**
     * @deprecated since 5.4.0
     */
    public function setTransparence(int $transparancy): self
    {
        return $this->setTransparency($transparancy);
    }

    public function setTransparency(int $transparancy): self
    {
        $this->transparency = $transparancy;

        return $this;
    }

    public function setSize(int $size): self
    {
        $this->size = $size;

        return $this;
    }

    public function getType(): int
    {
        return $this->type;
    }

    public function getLanguage(): int
    {
        return $this->language;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    /**
     * @deprecated since 5.4.0
     */
    public function getTransparance(): int
    {
        return $this->getTransparancy();
    }

    public function getTransparancy(): int
    {
        return $this->transparency;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getActive(): int
    {
        return $this->active;
    }

    public function getMargin(): int
    {
        return $this->margin;
    }
}
