<?php

declare(strict_types=1);

namespace JTL\Backend\Wizard;

/**
 * Class QuestionType
 * @package JTL\Backend\Wizard
 */
class QuestionType
{
    public const BOOL = 0;

    public const TEXT = 1;

    public const EMAIL = 2;

    public const SELECT = 3;

    public const MULTI_BOOL = 4;

    public const PLUGIN = 5;

    public const NUMBER = 6;
}
