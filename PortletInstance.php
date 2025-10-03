<?php

declare(strict_types=1);

namespace JTL\OPC;

use JTL\Events\Dispatcher;
use JTL\Events\Event;
use JTL\Helpers\GeneralObject;
use JTL\Media\Image;
use JTL\Media\MultiSizeImage;
use JTL\Shop;

/**
 * Class PortletInstance
 * @package JTL\OPC
 */
class PortletInstance implements \JsonSerializable
{
    use MultiSizeImage;

    /**
     * @var array<mixed>
     */
    protected array $properties = [];

    /**
     * @var array<mixed>
     */
    protected array $runtimeProperties = [];

    /**
     * @var array<string, mixed>
     */
    protected array $attributes = [];

    /**
     * @var array<mixed>
     */
    protected array $styles = [];

    /**
     * @var array<mixed>
     */
    protected array $animations = [];

    /**
     * @var array<string, int>
     */
    protected array $widthHeuristics = [
        'xs' => 1,
        'sm' => 1,
        'md' => 1,
        'lg' => 1,
    ];

    protected string $uid;

    protected AreaList $subareaList;

    /**
     * @param Portlet           $portlet
     * @param array<mixed>|null $data
     * @throws \Exception
     */
    public function __construct(protected Portlet $portlet, ?array $data = null)
    {
        $this->setImageType(Image::TYPE_OPC);
        $this->properties  = $portlet->getDefaultProps();
        $this->subareaList = new AreaList();
        $this->uid         = 'uid_' . \uniqid('', false);

        if (!empty($data)) {
            $this->deserialize($data);
        }

        $this->portlet->initInstance($this);
    }

    /**
     * @return Portlet
     */
    public function getPortlet(): Portlet
    {
        return $this->portlet;
    }

    /**
     * @throws \Exception
     */
    public function getPreviewHtml(): string
    {
        $previewHtml = $this->portlet->getPreviewHtml($this);
        $root        = $this->parseHtml($previewHtml);
        if ($root !== null) {
            $root->setAttribute('data-portlet', \json_encode($this->getData(), \JSON_THROW_ON_ERROR));
            $root->setAttribute('data-uid', $this->getUid());
            if ($this->portlet->rendersForms($this)) {
                $root->setAttribute('data-portlet-with-forms', 'true');
            }
            $result = $root->ownerDocument?->saveHTML($root) ?: '';
        } else {
            $result = '
                <div data-portlet="'
                . \htmlspecialchars(\json_encode($this->getData(), \JSON_THROW_ON_ERROR), \ENT_QUOTES)
                . '" style="text-align:center;height:4em;line-height:4em;'
                . 'background-color:#ffe1dd;color:red;border:1px dashed red;">
                        <i class="fas fa-exclamation-circle"></i> '
                . \__('missingPortletHTML') . '
                </div>
            ';
        }

        Dispatcher::getInstance()->fire(Event::OPC_PORTLET_RENDERMOUNTPOINT, [
            'portletInstance' => $this,
            'result'          => &$result
        ]);

        return $result;
    }

    /**
     * @throws \Exception
     */
    public function getFinalHtml(bool $inContainer = true): string
    {
        $finalHtml = $this->portlet->getFinalHtml($this, $inContainer);
        $opc       = Shop::Container()->getOPC();
        if ($opc->isPreviewMode()) {
            $root = $this->parseHtml($finalHtml);
            if ($root !== null) {
                if ($root->getElementsByTagName('form')->length > 0) {
                    $root->setAttribute('data-portlet-with-forms', 'true');
                }
                $root->setAttribute('data-uid', $this->getUid());
                $finalHtml = $root->ownerDocument?->saveHTML($root) ?: '';
            }
        }

        Dispatcher::getInstance()->fire(Event::OPC_PORTLET_GETFINALHTML, [
            'portletInstance' => $this,
            'result'          => &$finalHtml
        ]);

        return $finalHtml;
    }

    /**
     * @param string $html HTML markup for exactly one element
     * @return \DOMElement|null the DOM element object described by the markup of $html
     */
    protected function parseHtml(string $html): ?\DOMElement
    {
        $dom = new \DOMDocument('1.0', 'utf-8');
        // suppress mark-up warnings like embedded svg tags the DOMDocument parser can not handle
        \libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        \libxml_clear_errors();
        $body = $dom->getElementsByTagName('body')[0];
        if ($body !== null) {
            return $dom->getElementsByTagName('body')[0]->firstChild;
        }

        return null;
    }

    /**
     * @throws \Exception
     */
    public function getConfigPanelHtml(): string
    {
        return $this->portlet->getConfigPanelHtml($this);
    }

    /**
     * @throws \Exception
     */
    public function getSubareaPreviewHtml(string $id): string
    {
        return $this->getSubarea($id)?->getPreviewHtml() ?? '';
    }

    /**
     * @throws \Exception
     */
    public function getSubareaFinalHtml(string $id, bool $inContainer = true): string
    {
        return $this->getSubarea($id)?->getFinalHtml($inContainer) ?? '';
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function getProperty(string $name): mixed
    {
        return $this->properties[$name] ?? '';
    }

    public function setProperty(string $name, mixed $value): self
    {
        $this->properties[$name] = $value;

        return $this;
    }

    public function __get(string $name): mixed
    {
        if (\array_key_exists($name, $this->runtimeProperties)) {
            return $this->runtimeProperties[$name];
        }

        return $this->getProperty($name);
    }

    public function __set(string $name, mixed $value): void
    {
        $this->runtimeProperties[$name] = $value;
    }

    public function __isset(string $name): bool
    {
        return \array_key_exists($name, $this->runtimeProperties);
    }

    public function hasProperty(string $name): bool
    {
        return \array_key_exists($name, $this->properties);
    }

    public function getSubareaList(): AreaList
    {
        return $this->subareaList;
    }

    public function getSubarea(string $id): ?Area
    {
        return $this->subareaList->getArea($id);
    }

    public function hasSubarea(string $id): bool
    {
        return $this->subareaList->hasArea($id);
    }

    public function putSubarea(Area $area): self
    {
        $this->subareaList->putArea($area);

        return $this;
    }

    public function getAttribute(string $name): string
    {
        return $this->attributes[$name] ?? '';
    }

    public function getUid(): string
    {
        return $this->uid;
    }

    public function setAttribute(string $name, mixed $value): self
    {
        $this->attributes[$name] = $value;

        return $this;
    }

    public function addClass(string $className): self
    {
        $classes = \explode(' ', $this->getAttribute('class'));

        if (!\in_array($className, $classes, true)) {
            $classes[] = $className;
        }

        $this->setAttribute('class', \implode(' ', $classes));

        return $this;
    }

    protected function setBoxStyles(): void
    {
        foreach ($this->getProperty('box-styles') as $styleName => $styleValue) {
            if (\preg_match('/^-?(\d*\.)?\d+$/', $styleValue)) {
                $styleValue .= 'px';
            }
            $this->setStyle($styleName, $styleValue);
        }
    }

    /**
     * @return array<mixed>
     */
    public function getStyles(): array
    {
        foreach ($this->portlet->getStylesPropertyDesc() as $propname => $propdesc) {
            if ($this->hasProperty($propname) === false) {
                continue;
            }
            if ($propname === 'box-styles') {
                $this->setBoxStyles();
            } elseif ($propname !== 'custom-class' && !\str_starts_with($propname, 'hidden-')) {
                $this->setStyle($propname, $this->getProperty($propname));
            }
        }

        return $this->styles;
    }

    /**
     * @return array<mixed>
     */
    public function getAnimations(): array
    {
        foreach ($this->portlet->getAnimationsPropertyDesc() as $propname => $propdesc) {
            if ($this->hasProperty($propname)) {
                $this->setAnimation($propname, $this->getProperty($propname));
            }
        }

        return $this->animations;
    }

    public function setStyle(string $name, mixed $value): self
    {
        $this->styles[$name] = $value;

        return $this;
    }

    public function setAnimation(string $name, mixed $value): self
    {
        $this->animations[$name] = $value;

        return $this;
    }

    public function getStyleString(): string
    {
        $styleString = '';

        foreach ($this->getStyles() as $styleName => $styleValue) {
            if ($styleValue === '') {
                continue;
            }
            $styleString .= $styleName . ':' . \htmlspecialchars($styleValue, \ENT_QUOTES) . '; ';
        }

        return $styleString;
    }

    public function getStyleClasses(): string
    {
        $res = '';

        if ($this->getProperty('hidden-xs')) {
            $res .= 'opc-hidden-xs ';
        }
        if ($this->getProperty('hidden-sm')) {
            $res .= 'opc-hidden-sm ';
        }
        if ($this->getProperty('hidden-md')) {
            $res .= 'opc-hidden-md ';
        }
        if ($this->getProperty('hidden-lg')) {
            $res .= 'opc-hidden-lg ';
        }
        if ($this->getProperty('hidden-xl')) {
            $res .= 'opc-hidden-xl ';
        }
        if (!empty($this->getProperty('custom-class'))) {
            $res .= $this->getProperty('custom-class');
        }

        return $res;
    }

    public function getAnimationClass(): string
    {
        /** @var string $style */
        $style = $this->getProperty('animation-style');

        return $style !== '' ? 'wow ' . \htmlspecialchars($style) : '';
    }

    /**
     * @return array<mixed>
     */
    public function getAnimationData(): array
    {
        $data = [];

        foreach ($this->portlet->getAnimationsPropertyDesc() as $propname => $propdesc) {
            if (
                $this->hasProperty($propname)
                && \str_starts_with($propname, 'wow-')
                && !empty($this->getProperty($propname))
            ) {
                $value = $this->getProperty($propname);
                if (\is_string($value)) {
                    $value = \htmlspecialchars($value);
                }
                $data[$propname] = $value;
            }
        }

        return $data;
    }

    public function getAnimationDataAttributeString(): string
    {
        $res = '';
        foreach ($this->getAnimationData() as $key => $val) {
            $res .= ' data-' . $key . '="' . $val . '"';
        }

        return $res;
    }

    public function updateAttributes(): self
    {
        $this->setAttribute('style', $this->getStyleString());
        foreach ($this->getAnimations() as $aniName => $aniValue) {
            if ($aniName === 'animation-style' && !empty($aniValue)) {
                $this->addClass('wow ' . $aniValue);
            } elseif (!empty($aniValue)) {
                $this->setAttribute($aniName, $aniValue);
            }
        }

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        $this->updateAttributes();

        return $this->attributes;
    }

    public function getAttributeString(): string
    {
        $result = '';
        foreach ($this->getAttributes() as $name => $value) {
            $result .= ' ' . $name . '="' . $value . '"';
        }

        return $result;
    }

    public function getDataAttributeString(): string
    {
        return 'data-portlet="' . $this->getDataAttribute() . '"';
    }

    public function getDataAttribute(): string
    {
        return \htmlspecialchars(\json_encode($this->getData(), \JSON_THROW_ON_ERROR), \ENT_QUOTES);
    }

    /**
     * @return array<mixed>
     */
    public function getData(): array
    {
        return $this->jsonSerializeShort();
    }

    /**
     * @param string|null              $src
     * @param string|null              $alt
     * @param string|null              $title
     * @param int|array<string, mixed> $divisor
     * @param string|null              $default
     * @return array{srcset: string, srcsizes: string, src: string, alt: string,
     *      title: string, realWidth: int, realHeight: int}
     */
    public function getImageAttributes(
        ?string $src = null,
        ?string $alt = null,
        ?string $title = null,
        int|array $divisor = 1,
        ?string $default = null
    ): array {
        /** @var string $src */
        $src = $src ?? $this->getProperty('src');
        /** @var string $alt */
        $alt = $alt ?? $this->getProperty('alt');
        /** @var string $title */
        $title    = $title ?? $this->getProperty('title');
        $srcset   = '';
        $srcsizes = '';
        $filepath = \PFAD_ROOT . \STORAGE_OPC . \urldecode($src);
        $sizes    = [0, 0];
        if (\is_file($filepath)) {
            $sizes = \getimagesize($filepath) ?: [0, 0];
        }
        $realWidth  = $sizes[0];
        $realHeight = $sizes[1];
        $portrait   = $realWidth < $realHeight;
        $aspect     = $realWidth > 0 ? $realWidth / $realHeight : 1.0;
        if (empty($src)) {
            return [
                'srcset'     => '',
                'srcsizes'   => '',
                'src'        => $default ?? '',
                'alt'        => $alt,
                'title'      => $title,
                'realWidth'  => $realWidth,
                'realHeight' => $realHeight,
            ];
        }
        $imgSettings = Image::getSettings()[Image::TYPE_OPC];
        $this->generateAllImageSizes(true, 1, \rawurldecode($src));
        foreach ($this->getImages()[1] as $size => $url) {
            $url = \str_replace(' ', '%20', $url);
            if ($size === 'xl') {
                $srcset .= $url . ' ' . $realWidth . 'w,';
            } elseif ($portrait) {
                $width = $imgSettings[$size]['width'] ?? null;
                if ($width !== null) {
                    $width  *= $aspect;
                    $width  = (int)\round($width);
                    $srcset .= $url . ' ' . $width . 'w,';
                }
            } else {
                $width = $imgSettings[$size]['width'] ?? null;
                if ($width !== null) {
                    $srcset .= $url . ' ' . $width . 'w,';
                }
            }
        }

        $srcset = \mb_substr($srcset, 0, -1); // remove trailing comma
        foreach ($this->widthHeuristics as $breakpoint => $col) {
            if (empty($col)) {
                continue;
            }
            $factor = 1;
            if (\is_array($divisor) && !empty($divisor[$breakpoint])) {
                $factor = (float)($divisor[$breakpoint] / 12);
            }
            switch ($breakpoint) {
                case 'xs':
                    $srcsizes .= '(max-width: 767px) '
                        . (int)($col * 100 * $factor) . 'vw, ';
                    break;
                case 'sm':
                    $srcsizes .= '(max-width: 991px) '
                        . (int)($col * 100 * $factor) . 'vw, ';
                    break;
                case 'md':
                    $srcsizes .= '(max-width: 1299px) '
                        . (int)($col * 100 * $factor) . 'vw, ';
                    break;
                case 'lg':
                    $srcsizes .= '(min-width: 1300px) '
                        . (int)($col * 100 * $factor) . 'vw, ';
                    break;
                default:
                    break;
            }
        }

        $srcsizes .= '100vw';

        return [
            'srcset'     => $srcset,
            'srcsizes'   => $srcsizes,
            'src'        => \str_replace(' ', '%20', $this->getImage(Image::SIZE_XL) ?? ''),
            'alt'        => $alt,
            'title'      => $title,
            'realWidth'  => $realWidth,
            'realHeight' => $realHeight,
        ];
    }

    /**
     * @param int|array<string, mixed> $divisor
     */
    public function getImageAttributeString(
        ?string $src = null,
        ?string $alt = null,
        ?string $title = null,
        int|array $divisor = 1,
        ?string $default = null
    ): string {
        $imgAttribs = $this->getImageAttributes($src, $alt, $title, $divisor, $default);

        return \sprintf(
            "srcset='%s' srcsizes='%s' src='%s' alt='%s' title='%s'",
            $imgAttribs['srcset'],
            $imgAttribs['srcsizes'],
            $imgAttribs['src'],
            $imgAttribs['alt'],
            $imgAttribs['title']
        );
    }

    /**
     * @param int|array<string, mixed> $divisor
     */
    public function setImageAttributes(
        ?string $src = null,
        ?string $alt = null,
        ?string $title = null,
        int|array $divisor = 1,
        ?string $default = null
    ): self {
        $imageAttributes = $this->getImageAttributes($src, $alt, $title, $divisor, $default);

        $this->setAttribute('srcset', $imageAttributes['srcset']);
        $this->setAttribute('srcsizes', $imageAttributes['srcsizes']);
        $this->setAttribute('src', $imageAttributes['src']);
        $this->setAttribute('alt', $imageAttributes['alt']);
        $this->setAttribute('title', $imageAttributes['title']);

        return $this;
    }

    /**
     * @param array<mixed> $data
     * @return $this
     * @throws \Exception
     */
    public function deserialize(mixed $data): self
    {
        if (GeneralObject::isCountable('properties', $data)) {
            foreach ($data['properties'] as $name => $value) {
                $this->setProperty($name, $value);
            }
        }
        if (GeneralObject::isCountable('subareas', $data)) {
            foreach ($data['subareas'] as $areaData) {
                $area = new Area();
                $area->deserialize($areaData);
                $this->putSubarea($area);
            }
        }
        if (GeneralObject::isCountable('widthHeuristics', $data)) {
            $this->widthHeuristics = $data['widthHeuristics'];
        }
        if (!empty($data['uid'])) {
            $this->uid = $data['uid'];
        }

        return $this;
    }

    /**
     * @return array{id: int, class: string, title: string, properties: array<mixed>,
     *      widthHeuristics: array<string, int>}
     */
    public function jsonSerializeShort(): array
    {
        return [
            'id'              => $this->portlet->getId(),
            'class'           => $this->portlet->getClass(),
            'title'           => $this->portlet->getTitle(),
            'properties'      => $this->properties,
            'widthHeuristics' => $this->widthHeuristics,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $result             = $this->jsonSerializeShort();
        $result['subareas'] = $this->subareaList->jsonSerialize();

        return $result;
    }
}
