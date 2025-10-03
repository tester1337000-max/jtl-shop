<?php

declare(strict_types=1);

namespace JTL\Backend\Upgrade\Step;

use JTL\Filesystem\Filesystem;
use JTL\Shop;

final class CheckDirPermissions extends AbstractStep
{
    /**
     * @var string[]
     */
    private static array $directories = [
        \PFAD_DBES,
        \PFAD_INCLUDES,
        \PFAD_INCLUDES . 'src/',
        \PFAD_INCLUDES . 'vendor/',
        \PFAD_INCLUDES . 'libs/',
        \PFAD_INCLUDES . 'modules/',
        \PFAD_INCLUDES . 'ext/',
        \PFAD_INCLUDES . 'captcha/',
        \PFAD_ADMIN,
        \PFAD_ADMIN . 'includes/',
        \PFAD_ADMIN . 'locale/',
        \PFAD_ADMIN . 'mailtemplates/',
        \PFAD_ADMIN . 'opc/',
        \PFAD_ADMIN . 'templates/',
        \PFAD_ADMIN . 'templates_c/',
    ];

    public function getTitle(): string
    {
        return \__('Checking dir permissions...');
    }

    public function run(mixed $data = null): StepConfiguration
    {
        $this->startTiming();
        $fs = Shop::Container()->get(Filesystem::class);
        foreach (self::$directories as $dir) {
            if (!$fs->directoryExists($dir)) {
                throw new StepFailedException(\sprintf(\__('Directory %s does not exist.'), $dir));
            }
            if ($fs->visibility($dir) !== 'public') {
                throw new StepFailedException(\sprintf(\__('Directory %s is not writable.'), $dir));
            }
        }

        return $this->progress;
    }
}
