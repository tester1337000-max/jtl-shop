<?php

declare(strict_types=1);

namespace JTL\ServiceReport\Report;

class Server implements ReportInterface
{
    /**
     * @return array<string, string>
     */
    public function getData(): array
    {
        $data                   = $_SERVER;
        $freeDiskSpace          = \disk_free_space(\PFAD_ROOT);
        $totalDiskSpace         = \disk_total_space(\PFAD_ROOT);
        $data['freeDiskSpace']  = $freeDiskSpace === false
            ? '???'
            : \number_format($freeDiskSpace / 1024 / 1024, 2, ',', '') . ' MB';
        $data['totalDiskSpace'] = $totalDiskSpace === false
            ? '???'
            : \number_format($totalDiskSpace / 1024 / 1024, 2, ',', '') . ' MB';

        return $data;
    }
}
