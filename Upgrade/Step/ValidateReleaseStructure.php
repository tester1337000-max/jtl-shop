<?php

declare(strict_types=1);

namespace JTL\Backend\Upgrade\Step;

final class ValidateReleaseStructure extends AbstractStep
{
    public function getTitle(): string
    {
        return \__('Validating contents...');
    }

    public function run(mixed $data = null): StepConfiguration
    {
        $this->startTiming();
        $dir      = \rtrim($this->progress->source, '/') . '/';
        $index    = $dir . 'index.php';
        $includes = $dir . \PFAD_INCLUDES;
        $defines  = $dir . \PFAD_INCLUDES . 'defines.php';
        if (
            !$this->manager->fileExists('root://' . $index)
            || !$this->manager->directoryExists('root://' . $includes)
            || !$this->manager->fileExists('root://' . $defines)
        ) {
            $this->progress->addError(\sprintf(\__('%s does not contain a shop release.'), $dir));
            $this->stopTiming();
            throw new StepFailedException(\__('Not a shop release.'));
        }
        $this->stopTiming();

        return $this->progress;
    }
}
