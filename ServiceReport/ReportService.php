<?php

declare(strict_types=1);

namespace JTL\ServiceReport;

use InvalidArgumentException;
use JTL\Abstracts\AbstractService;
use JTL\Interfaces\RepositoryInterface;
use JTL\ServiceReport\Report\Factory;
use JTL\Services\JTL\PasswordServiceInterface;
use stdClass;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Symfony\Component\VarDumper\VarDumper;

class ReportService extends AbstractService
{
    public const BASE_PATH = \PFAD_ROOT . \PFAD_COMPILEDIR . 'reports/';

    private string $reportID = '';

    public function __construct(
        private readonly PasswordServiceInterface $passwordService,
        private readonly ReportRepository $repository = new ReportRepository()
    ) {
    }

    protected function getRepository(): RepositoryInterface
    {
        return $this->repository;
    }

    /**
     * @param string[]       $keys
     * @param string[]|int[] $values
     * @return stdClass[]
     */
    public function getReports(array $keys, array $values): array
    {
        $reports = $this->repository->getReports($keys, $values);
        foreach ($reports as $report) {
            $report->fileExists = \file_exists(self::BASE_PATH . $report->file . '.html');
        }

        return $reports;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getReportByID(int $id): stdClass
    {
        return $this->repository->getReportByID($id);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getReportByHash(string $hash): stdClass
    {
        return $this->repository->getReportByHash($hash);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function authorize(int $id): string
    {
        $hash = \md5($this->passwordService->generate(24));
        $this->repository->getReportByID($id);
        $this->repository->updateAuthorizationData($id, $hash);

        return $hash;
    }

    public function updateReportByID(int $id, stdClass $data): int
    {
        return $this->repository->updateReportByID($id, $data);
    }

    public function deleteReportByID(int $id): bool
    {
        $report = $this->getReportByID($id);
        $path   = self::BASE_PATH . $report->file;
        foreach (['.html', '.json'] as $ext) {
            if (\file_exists($path . $ext)) {
                \unlink($path . $ext);
            }
        }

        return $this->repository->delete($id);
    }

    /**
     * @throws \JsonException
     * @throws \RuntimeException
     */
    public function createReport(): void
    {
        $this->reportID = 'report_' . \date('YmdHis');
        $data           = [];
        $factory        = new Factory();
        if (!\is_dir(self::BASE_PATH) && !\mkdir(self::BASE_PATH, 0755, true) && !\is_dir(self::BASE_PATH)) {
            throw new \RuntimeException(\sprintf(\__('Directory %s could not be created'), self::BASE_PATH));
        }
        foreach ($factory->getReports() as $report) {
            $data[\get_class($report)] = $report->getData();
        }
        $rawJson = \json_encode($data, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR);
        $file    = self::BASE_PATH . $this->reportID . '.json';
        if (\file_put_contents($file, $rawJson) === false) {
            throw new \RuntimeException(\sprintf(\__('Could not write report data to file %s'), $file));
        }
        VarDumper::setHandler($this->dumpHandler(...));
        \dump($data);
        $ins = (object)[
            'file'    => $this->reportID,
            'created' => 'NOW()',
        ];
        $this->repository->addReport($ins);
    }

    private function dumpHandler(mixed $var): ?string
    {
        $outputFile = self::BASE_PATH . $this->reportID . '.html';
        $output     = \fopen($outputFile, 'wb');
        if ($output === false) {
            return null;
        }
        $cloner = new VarCloner();
        $cloner->setMinDepth(10);

        return (new HtmlDumper($output))->dump($cloner->cloneVar($var), $output);
    }
}
