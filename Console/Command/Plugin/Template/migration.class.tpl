<?php

/**
 * {$description}
 *
 * @author {$author}
 * @created {$created}
 */

declare(strict_types=1);

namespace Plugin\{$pluginDir}\Migration;

use JTL\Plugin\Migration;
use JTL\Update\IMigration;

/**
 * Migration
 *
 * Available methods:
 * execute            - returns affected rows
 * fetchOne           - single fetched object
 * fetchAll           - array of fetched objects
 * fetchArray         - array of fetched assoc arrays
 * dropColumn         - drops a column if exists
 * setLocalization    - add localization
 * removeLocalization - remove localization
 * setConfig          - add / update config property
 * removeConfig       - remove config property
 */
class Migration{$timestamp} extends Migration implements IMigration
{
    protected $author = '{$author}';

    protected $description = '{$description}';

    public function up()
    {
    }

    public function down()
    {
    }
}
