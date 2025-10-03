<?php

declare(strict_types=1);

namespace JTL\OPC;

use MyCLabs\Enum\Enum;

/**
 * Class InputType
 * @package JTL\Plugin\Admin
 * @extends Enum<InputType::*>
 */
class InputType extends Enum
{
    public const SELECT = 'select';

    public const RADIO = 'radio';

    public const PASSWORD = 'password';

    public const TEXTAREA = 'textarea';

    public const NUMBER = 'number';

    public const EMAIL = 'email';

    public const CHECKBOX = 'checkbox';

    public const COLOR = 'color';

    public const HIDDEN = 'hidden';

    public const HINT = 'hint';

    public const TEXT = 'text';

    public const SEARCH = 'search';

    public const TEXT_LIST = 'textlist';

    public const IMAGE = 'image';

    public const IMAGE_SET = 'image-set';

    public const ICON = 'icon';

    public const VIDEO = 'video';

    public const DATE = 'date';

    public const TIME = 'time';

    public const DATETIME = 'datetime';

    public const RICHTEXT = 'richtext';

    public const FILTER = 'filter';

    public const ZONES = 'zones';

    public const GALLERY_LAYOUT = 'gallery-layout';

    public const BOX_STYLES = 'box-styles';

    public const ROW_LAYOUT = 'row-layout';

    public const SEARCHPICKER = 'searchpicker';

    public const FILE = 'file';
}
