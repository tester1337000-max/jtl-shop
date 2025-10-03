<?php

declare(strict_types=1);

namespace JTL\Smarty;

use JTL\DB\DbInterface;
use JTL\Shop;
use Smarty\Resource\CustomPlugin;

/**
 * Class SmartyResourceNiceDB
 * @package JTL\Smarty
 */
class SmartyResourceNiceDB extends CustomPlugin
{
    public function __construct(private readonly DbInterface $db, private string $type = ContextType::EXPORT)
    {
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function fetch($name, &$source, &$mtime): void
    {
        if ($this->type === ContextType::EXPORT) {
            $source = $this->getExportSource((int)$name);
        } elseif ($this->type === ContextType::MAIL) {
            $source = $this->getMailSource($name);
        } elseif ($this->type === ContextType::NEWSLETTER) {
            $source = $this->getNewsletterSource($name);
        } else {
            $source = '';
            Shop::Container()->getLogService()->notice(
                'Template-Typ {type} wurde nicht gefunden',
                ['type' => $this->type]
            );
        }
    }

    private function getNewsletterSource(string $name): string
    {
        $source = '';
        $parts  = \explode('_', $name);
        $table  = 'tnewslettervorlage';
        $row    = 'kNewsletterVorlage';
        if ($parts[0] === 'NL') {
            $table = 'tnewsletter';
            $row   = 'kNewsletter';
        }
        $newsletter = $this->db->select($table, $row, $parts[1]);
        if ($newsletter === null) {
            return $source;
        }
        if ($parts[2] === 'html') {
            $source = $newsletter->cInhaltHTML;
        } elseif ($parts[2] === 'text' || $parts[2] === 'plain') {
            $source = $newsletter->cInhaltText;
        }

        return $source;
    }

    private function getMailSource(string $name): string
    {
        $pcs = \explode('_', $name);
        if (isset($pcs[0], $pcs[1], $pcs[2], $pcs[3]) && $pcs[3] === 'anbieterkennzeichnung') {
            // Anbieterkennzeichnungsvorlage holen
            $vl = $this->db->getSingleObject(
                "SELECT tevs.cContentHtml, tevs.cContentText
                    FROM temailvorlage tev
                    JOIN temailvorlagesprache tevs
                        ON tevs.kEmailVorlage = tev.kEmailvorlage
                        AND tevs.kSprache = :langID
                    WHERE tev.cModulId = 'core_jtl_anbieterkennzeichnung'",
                ['langID' => (int)$pcs[4]]
            );
        } else {
            // Plugin Emailvorlage?
            $vl = $this->db->select(
                'temailvorlagesprache',
                ['kEmailvorlage', 'kSprache'],
                [(int)$pcs[1], (int)$pcs[2]]
            );
        }
        if ($vl !== null && isset($vl->cContentHtml)) {
            if ($pcs[0] === 'html') {
                $source = $vl->cContentHtml;
            } elseif ($pcs[0] === 'text' || $pcs[0] === 'plain') {
                $source = $vl->cContentText;
            } else {
                $source = '';
                Shop::Container()->getLogService()->notice(
                    'Ungueltiger Emailvorlagen-Typ: {type}',
                    ['type' => $pcs[0]]
                );
            }
        } else {
            $source = '';
            Shop::Container()->getLogService()->notice(
                'Emailvorlage mit der ID {id}  in der Sprache {lang} wurde nicht gefunden',
                ['id' => (int)$pcs[1], 'lang' => (int)$pcs[2]]
            );
        }

        return $source;
    }

    private function getExportSource(int $id): string
    {
        return $this->db->select('texportformat', 'kExportformat', $id)->cContent ?? '';
    }

    protected function fetchTimestamp($name): int
    {
        return \time();
    }
}
