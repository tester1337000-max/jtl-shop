<?php

declare(strict_types=1);

namespace JTL\Backend\Upgrade\Step;

use GuzzleHttp\Client;

final class DownloadRelease extends AbstractStep
{
    public function getTitle(): string
    {
        return \sprintf(\__('Downloading archive %s...'), $this->progress->downloadURL);
    }

    public function run(mixed $data = null): StepConfiguration
    {
        $this->startTiming();
        $tmpFile = \PFAD_ROOT . \PFAD_DBES_TMP . '.release.tmp.zip';
        if (\file_exists($tmpFile)) {
            \unlink($tmpFile);
        }
        $client = new Client();
        $client->get(
            $this->progress->downloadURL,
            ['sink' => $tmpFile, 'progress' => $data]
        );
        $this->progress->tmpFile = $tmpFile;
        $this->progress->addInfo(\sprintf(\__('Downloaded archive %s'), $this->progress->downloadURL));
        $this->stopTiming();

        return $this->progress;
    }
}
