<?php

declare(strict_types=1);

namespace JTL\Settings\Option;

use JTL\Settings\Section;

enum SelectionWizard: string implements OptionInterface
{
    case DO_USE             = 'auswahlassistent_nutzen';
    case DISPLAY            = 'auswahlassistent_anzeigeformat';
    case SHOW_ALL_QUESTIONS = 'auswahlassistent_allefragen';
    case QTY_SHOW           = 'auswahlassistent_anzahl_anzeigen';

    public function getValue(): string
    {
        return $this->value;
    }

    public function getSection(): Section
    {
        return Section::SELECTIONWIZARD;
    }
}
