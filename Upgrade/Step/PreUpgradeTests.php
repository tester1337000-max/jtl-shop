<?php

declare(strict_types=1);

namespace JTL\Backend\Upgrade\Step;

final class PreUpgradeTests extends AbstractStep
{
    public function getTitle(): string
    {
        return \__('Executing pre-update tests...');
    }

    public function run(mixed $data = null): StepConfiguration
    {
        $this->startTiming();
        $dir        = \rtrim($this->progress->source, '/') . '/';
        $check      = $dir . 'install/test.php';
        $autoloader = $dir . 'includes/vendor/autoload.php';
        if (!$this->validate($autoloader, $check)) {
            return $this->progress;
        }
        require \PFAD_ROOT . $autoloader;
        /** @var array{passed: bool, messages: string[]}|null $testResult */
        $testResult = require \PFAD_ROOT . $check;
        if (($testResult['passed'] ?? false) === false) {
            foreach (($testResult['messages'] ?? []) as $message) {
                $this->progress->addError($message);
            }
            throw new StepFailedException(\__('Pre-update checks failed.'));
        }
        $this->progress->addInfo(\__('Pre-update checks passed.'));
        $this->stopTiming();

        return $this->progress;
    }

    private function validate(string $autoloader, string $check): bool
    {
        if (!$this->manager->fileExists('root://' . $check)) {
            $this->progress->addInfo(\sprintf(\__('No pre-update test file found at %s'), $check));

            return false;
        }
        if (!$this->manager->fileExists('root://' . $autoloader)) {
            $this->progress->addWarning(\sprintf(\__('No autoloader found at %s'), $check));

            return false;
        }
        $autoloader = \PFAD_ROOT . $autoloader;
        if (!$this->hasNoClassCollision($autoloader)) {
            $this->progress->addError(\__('Autoloader class name conflict'));

            return false;
        }

        return true;
    }

    /**
     * since the update archive brings its own autoloader we have to check for autoloader class name collisions
     * otherwise there would be the possibility to get a fata error like this:
     * Cannot declare class ComposerAutoloaderInit<hash>>, because the name is already in use
     */
    private function hasNoClassCollision(string $autoloader): bool
    {
        $newAutoloaderContent = \file_get_contents($autoloader) ?: '';
        $hits                 = null;
        $found                = \preg_match('/ComposerAutoloaderInit([^:\s]+)/', $newAutoloaderContent, $hits);
        if ($found !== 1) {
            return true;
        }
        $className             = $hits[0];
        $mainAutoloaderContent = \file_get_contents(\PFAD_ROOT . \PFAD_INCLUDES . 'vendor/autoload.php') ?: '';
        $hits                  = null;
        $found                 = \preg_match('/ComposerAutoloaderInit([^:\s]+)/', $mainAutoloaderContent, $hits);
        if ($found !== 1) {
            return true;
        }
        $mainClassName = $hits[0];

        return $mainClassName !== $className;
    }
}
