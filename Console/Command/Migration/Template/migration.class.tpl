<?php

declare(strict_types=1);

namespace JTL\Migrations;

use JTL\Update\IMigration;
use JTL\Update\Migration;

/**
 * Class Migration{$timestamp}
 * @since 5.X.Y
 */
class Migration{$timestamp} extends Migration implements IMigration
{
    public function getAuthor(): string
    {
        return '{$author}';
    }

    public function getDescription(): string
    {
        return '{$description}';
    }

    /**
     * @inheritdoc
     */
    public function up(): void
    {
    }

    /**
     * @inheritdoc
     */
    public function down(): void
    {
    }
}
