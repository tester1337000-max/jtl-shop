<?php

declare(strict_types=1);

namespace JTL;

use JTL\DB\DbInterface;
use stdClass;

use function Functional\first;

/**
 * Class Slider
 * @package JTL
 */
class Slider implements IExtensionPoint
{
    use MagicCompatibilityTrait;

    private int $id = 0;

    private string $name = '';

    private int $languageID = 0;

    private int $customerGroupID = 0;

    private int $pageType = 0;

    private string $theme = '';

    private bool $isActive = false;

    private string $effects = 'random';

    private int $pauseTime = 3000;

    private bool $thumbnail = false;

    private int $animationSpeed = 500;

    private bool $pauseOnHover = false;

    /**
     * @var Slide[]
     */
    private array $slides = [];

    private bool $controlNav = true;

    private bool $randomStart = false;

    private bool $directionNav = true;

    private bool $useKB = true;

    /**
     * @var array<string, string>
     */
    private static array $mapping = [
        'bAktiv'          => 'IsActive',
        'kSlider'         => 'ID',
        'cName'           => 'Name',
        'kSprache'        => 'LanguageID',
        'nSeitentyp'      => 'PageType',
        'cTheme'          => 'Theme',
        'cEffects'        => 'Effects',
        'nPauseTime'      => 'PauseTime',
        'bThumbnail'      => 'Thumbnail',
        'nAnimationSpeed' => 'AnimationSpeed',
        'bPauseOnHover'   => 'PauseOnHover',
        'oSlide_arr'      => 'Slides',
        'bControlNav'     => 'ControlNav',
        'bRandomStart'    => 'RandomStart',
        'bDirectionNav'   => 'DirectionNav',
        'bUseKB'          => 'UseKB',
        'kKundengruppe'   => 'CustomerGroupID'
    ];

    private function __clone()
    {
    }

    public function __construct(private readonly DbInterface $db)
    {
    }

    private function getMapping(string $value): ?string
    {
        return self::$mapping[$value] ?? null;
    }

    /**
     * @inheritdoc
     */
    public function init(int $id): self
    {
        $loaded = $this->load($id);
        if ($id > 0 && $loaded === true && \count($this->slides) > 0) {
            Shop::Smarty()->assign('oSlider', $this);
        }

        return $this;
    }

    public function set(stdClass $data): self
    {
        foreach (\get_object_vars($data) as $field => $value) {
            if (($mapping = $this->getMapping($field)) !== null) {
                $method = 'set' . $mapping;
                $this->$method($value);
            }
        }

        return $this;
    }

    public function load(int $int = 0, bool $active = true): bool
    {
        if ($int <= 0 && $this->id <= 0) {
            return false;
        }
        $activeSQL = $active ? ' AND bAktiv = 1 ' : '';
        if ($int === 0) {
            $int = $this->id;
        }
        $data = $this->db->getObjects(
            'SELECT *, tslider.kSlider AS id 
                FROM tslider
                LEFT JOIN tslide
                    ON tslider.kSlider = tslide.kSlider
                WHERE tslider.kSlider = :sliderID' . $activeSQL .
            ' ORDER BY tslide.nSort',
            ['sliderID' => $int]
        );
        /** @var stdClass|null $first */
        $first = first($data);
        if ($first === null) {
            return false;
        }
        $this->setID($first->id);
        foreach ($data as $slideData) {
            $slideData->kSlider = $this->getID();
            if ($slideData->kSlide !== null) {
                $slide = new Slide(0, $this->db);
                $slide->map($slideData);
                $this->slides[] = $slide;
            }
        }
        $this->set($first);

        return $this->getID() > 0;
    }

    public function save(): bool
    {
        return $this->id > 0
            ? $this->update()
            : $this->append();
    }

    private function append(): bool
    {
        $slider = new stdClass();
        foreach (self::$mapping as $type => $methodName) {
            $method        = 'get' . $methodName;
            $slider->$type = $this->$method();
            if (\is_bool($slider->$type)) {
                $slider->$type = (int)$slider->$type;
            }
        }
        unset($slider->oSlide_arr, $slider->slides, $slider->kSlider);

        $kSlider = $this->db->insert('tslider', $slider);

        if ($kSlider > 0) {
            $this->id = $kSlider;

            return true;
        }

        return false;
    }

    private function update(): bool
    {
        $slider = new stdClass();
        foreach (self::$mapping as $type => $methodName) {
            $method        = 'get' . $methodName;
            $slider->$type = $this->$method();
            if (\is_bool($slider->$type)) {
                $slider->$type = (int)$slider->$type;
            }
        }
        unset($slider->oSlide_arr, $slider->slides, $slider->kSlider);

        return $this->db->update('tslider', 'kSlider', $this->getID(), $slider) >= 0;
    }

    public function delete(): bool
    {
        $id = $this->getID();
        if ($id !== 0) {
            $affected = $this->db->delete('tslider', 'kSlider', $id);
            $this->db->delete('textensionpoint', ['cClass', 'kInitial'], ['Slider', $id]);
            if ($affected > 0) {
                foreach ($this->slides as $slide) {
                    $slide->delete();
                }

                return true;
            }
        }

        return false;
    }

    public function getID(): int
    {
        return $this->id;
    }

    public function setID(int|string $kSlider): void
    {
        $this->id = (int)$kSlider;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getLanguageID(): int
    {
        return $this->languageID;
    }

    public function setLanguageID(int|string $languageID): void
    {
        $this->languageID = (int)$languageID;
    }

    public function getCustomerGroupID(): int
    {
        return $this->customerGroupID;
    }

    public function setCustomerGroupID(int|string $customerGroupID): void
    {
        $this->customerGroupID = (int)$customerGroupID;
    }

    public function getPageType(): int
    {
        return $this->pageType;
    }

    public function setPageType(int|string $pageType): void
    {
        $this->pageType = (int)$pageType;
    }

    public function getTheme(): string
    {
        return $this->theme;
    }

    public function setTheme(string $theme): void
    {
        $this->theme = $theme;
    }

    public function getIsActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool|int|string $isActive): void
    {
        $this->isActive = (bool)$isActive;
    }

    public function getEffects(): string
    {
        return $this->effects;
    }

    public function setEffects(string $effects): void
    {
        $this->effects = $effects;
    }

    public function getPauseTime(): int
    {
        return $this->pauseTime;
    }

    public function setPauseTime(int|string $pauseTime): void
    {
        $this->pauseTime = (int)$pauseTime;
    }

    public function getThumbnail(): bool
    {
        return $this->thumbnail;
    }

    public function setThumbnail(bool|int|string $thumbnail): void
    {
        $this->thumbnail = (bool)$thumbnail;
    }

    public function getAnimationSpeed(): int
    {
        return $this->animationSpeed;
    }

    public function setAnimationSpeed(int|string $animationSpeed): void
    {
        $this->animationSpeed = (int)$animationSpeed;
    }

    public function getPauseOnHover(): bool
    {
        return $this->pauseOnHover;
    }

    public function setPauseOnHover(bool|int|string $pauseOnHover): void
    {
        $this->pauseOnHover = (bool)$pauseOnHover;
    }

    /**
     * @return Slide[]
     */
    public function getSlides(): array
    {
        return $this->slides;
    }

    /**
     * @param Slide[] $slides
     */
    public function setSlides(array $slides): void
    {
        $this->slides = $slides;
    }

    public function getControlNav(): bool
    {
        return $this->controlNav;
    }

    public function setControlNav(bool|int|string $controlNav): void
    {
        $this->controlNav = (bool)$controlNav;
    }

    public function getRandomStart(): bool
    {
        return $this->randomStart;
    }

    public function setRandomStart(bool|int|string $randomStart): void
    {
        $this->randomStart = (bool)$randomStart;
    }

    public function getDirectionNav(): bool
    {
        return $this->directionNav;
    }

    public function setDirectionNav(bool|int|string $directionNav): void
    {
        $this->directionNav = (bool)$directionNav;
    }

    public function getUseKB(): bool
    {
        return $this->useKB;
    }

    public function setUseKB(bool|int|string $useKB): void
    {
        $this->useKB = (bool)$useKB;
    }
}
