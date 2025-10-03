<?php

declare(strict_types=1);

namespace JTL\dbeS\Push;

use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\dbeS\Mapper;
use JTL\Helpers\Text;
use JTL\XML;
use Psr\Log\LoggerInterface;
use ZipArchive;

/**
 * Class AbstractPush
 * @package JTL\dbeS\Push
 */
abstract class AbstractPush
{
    protected const XML_FILE = 'data.xml';

    protected const TEMP_DIR = \PFAD_ROOT . \PFAD_DBES . \PFAD_SYNC_TMP;

    protected Mapper $mapper;

    public function __construct(
        protected DbInterface $db,
        protected JTLCacheInterface $cache,
        protected LoggerInterface $logger
    ) {
        $this->mapper = new Mapper();
    }

    /**
     * @return array<mixed>|string|void
     */
    abstract public function getData();

    /**
     * @param array<mixed>|mixed $arr
     * @param array<mixed>       $excludes
     * @return array<mixed>
     */
    protected function buildAttributes(mixed &$arr, array $excludes = []): array
    {
        $attributes = [];
        if (!\is_array($arr)) {
            return $attributes;
        }
        foreach (\array_keys($arr) as $key) {
            if (!\in_array($key, $excludes, true) && $key[0] === 'k') {
                $attributes[$key] = $arr[$key];
                unset($arr[$key]);
            }
        }

        return $attributes;
    }

    /**
     * @param array<mixed> $xml
     */
    public function zipRedirect(string $zip, array $xml, string $wawiVersion): void
    {
        $xmlfile = \fopen(self::TEMP_DIR . self::XML_FILE, 'wb');
        if ($xmlfile === false) {
            \syncException('Cannot open xml file', 5);
        }
        $serializedXML = $wawiVersion === 'unknown'
            ? \strtr(Text::convertISO(XML::serialize($xml)), "\0", ' ')
            : XML::serialize($xml);
        \fwrite($xmlfile, $serializedXML);
        \fclose($xmlfile);
        if (\file_exists(self::TEMP_DIR . self::XML_FILE)) {
            $archive = new ZipArchive();
            if (
                $archive->open(self::TEMP_DIR . $zip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== false
                && $archive->addFile(self::TEMP_DIR . self::XML_FILE, self::XML_FILE)
            ) {
                $archive->close();
                \readfile(self::TEMP_DIR . $zip);
                exit;
            }
            $archive->close();
            \syncException($archive->getStatusString());
        }
    }
}
