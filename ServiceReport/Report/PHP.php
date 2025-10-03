<?php

declare(strict_types=1);

namespace JTL\ServiceReport\Report;

class PHP implements ReportInterface
{
    /**
     * @return array<string, array<string, mixed>|string|null>
     */
    public function getData(): array
    {
        return [
            'version'       => \PHP_VERSION,
            'configuration' => $this->parsePhpInfo(\INFO_CONFIGURATION)['PHP Core'] ?? null,
            'environment'   => $this->parsePhpInfo(\INFO_ENVIRONMENT)['Environment'] ?? null,
            'modules'       => $this->parsePhpInfo(\INFO_MODULES),
        ];
    }

    /**
     * @return array<string, array<string, mixed>|string>
     */
    private function parsePhpInfo(int $info): array
    {
        \ob_start();
        \phpinfo($info);
        $output = \ob_get_clean() ?: '';
        $output = \strip_tags($output, '<h2><th><td>');
        $output = \preg_replace('/<th[^>]*>([^<]+)<\/th>/', '<info>\1</info>', $output) ?? '';
        $output = \preg_replace('/<td[^>]*>([^<]+)<\/td>/', '<info>\1</info>', $output) ?? '';
        $splits = \preg_split('/(<h2[^>]*>[^<]+<\/h2>)/', $output, -1, \PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $res    = [];
        $count  = \count($splits);
        $p1     = '<info>([^<]+)<\/info>';
        $p2     = '/' . $p1 . '\s*' . $p1 . '\s*' . $p1 . '/';
        $p3     = '/' . $p1 . '\s*' . $p1 . '/';
        for ($i = 1; $i < $count; $i++) {
            if (!\preg_match('/<h2[^>]*>([^<]+)<\/h2>/', $splits[$i], $matchs)) {
                continue;
            }
            $name = \trim($matchs[1]);
            $vals = \explode("\n", $splits[$i + 1]);
            foreach ($vals as $val) {
                if (\preg_match($p2, $val, $matchs)) { // 3cols
                    $res[$name][\trim($matchs[1])] = [\trim($matchs[2]), \trim($matchs[3])];
                } elseif (\preg_match($p3, $val, $matchs)) { // 2cols
                    $res[$name][\trim($matchs[1])] = \trim($matchs[2]);
                }
            }
        }

        return $res;
    }
}
