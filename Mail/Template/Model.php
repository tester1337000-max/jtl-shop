<?php

declare(strict_types=1);

namespace JTL\Mail\Template;

use JTL\DB\DbInterface;
use JTL\Helpers\Text;
use JTL\Shop;
use stdClass;

use function Functional\first;
use function Functional\map;
use function Functional\tail;

/**
 * Class Model
 * @package JTL\Mail\Template
 */
final class Model
{
    public const SYNTAX_OK          = 0;
    public const SYNTAX_FAIL        = 1;
    public const SYNTAX_NOT_CHECKED = -1;

    private int $id = 0;

    private string $name = '';

    private ?string $description = null;

    private string $type = 'text/html';

    private string $moduleID = '';

    private ?string $fileName = null;

    /**
     * @var 'Y'|'N'
     */
    private string $active = 'Y';

    private bool $showAKZ = true;

    private bool $showAGB = true;

    private bool $showWRB = true;

    private bool $showWRBForm = true;

    private bool $showDSE = true;

    /**
     * @var self::SYNTAX_*
     */
    private int $hasError = self::SYNTAX_OK;

    private int $languageID = 0;

    private int $pluginID = 0;

    /**
     * @var array<int, string>
     */
    private array $subject = [];

    /**
     * @var array<int, string>
     */
    private array $html = [];

    /**
     * @var array<int, string>
     */
    private array $text = [];

    /**
     * @var array<int, array<int, string>|null>
     */
    private array $attachments = [];

    /**
     * @var array<int, array<int, string>|null>
     */
    private array $attachmentNames = [];

    private mixed $data = null;

    private int $priority;

    /**
     * @var array<string, string>
     */
    private static array $mapping = [
        'kEmailvorlage' => 'ID',
        'cName'         => 'Name',
        'cBeschreibung' => 'Description',
        'cMailTyp'      => 'Type',
        'cModulId'      => 'ModuleID',
        'cDateiname'    => 'FileName',
        'cAktiv'        => 'ActiveCompat',
        'nAKZ'          => 'ShowAKZ',
        'nAGB'          => 'ShowAGB',
        'nWRB'          => 'ShowWRB',
        'nWRBForm'      => 'ShowWRBForm',
        'nDSE'          => 'ShowDSE',
        'nFehlerhaft'   => 'SyntaxCheck',
        'kPlugin'       => 'PluginID',
        'nPrio'         => 'Priority'
    ];

    /**
     * @var array<string, string>
     */
    private static array $localizedMapping = [
        'kEmailvorlage' => 'ID',
        'kSprache'      => 'LanguageID',
        'cBetreff'      => 'Subject',
        'cContentHtml'  => 'HTML',
        'cContentText'  => 'Text',
        'cPDFS'         => 'Attachments',
        'cPDFNames'     => 'AttachmentNames'
    ];

    public function __construct(private readonly DbInterface $db)
    {
    }

    /**
     * @param string|null $type
     * @return ($type is null ? array<string, string> : string|null)
     */
    public function getMapping(?string $type = null): array|string|null
    {
        $combined = \array_merge(self::$mapping, self::$localizedMapping);

        return $type === null ? $combined : $combined[$type] ?? null;
    }

    /**
     * @param string|null $type
     * @return ($type is null ? array<string, string> : string|null)
     */
    public function getMainMapping(?string $type = null): array|string|null
    {
        return $type === null ? self::$mapping : self::$mapping[$type] ?? null;
    }

    /**
     * @param string|null $type
     * @return ($type is null ? array<string, string> : string|null)
     */
    public function getLocalizedMapping(?string $type = null): array|string|null
    {
        return $type === null ? self::$localizedMapping : self::$localizedMapping[$type] ?? null;
    }

    public function getID(): int
    {
        return $this->id;
    }

    public function setID(int|string $id): void
    {
        $this->id = (int)$id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): string
    {
        return $this->description ?? '';
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getModuleID(): string
    {
        return $this->moduleID;
    }

    public function setModuleID(string $moduleID): void
    {
        $this->moduleID = $moduleID;
    }

    public function getFileName(): string
    {
        return $this->fileName ?? '';
    }

    public function setFileName(string $fileName): void
    {
        $this->fileName = $fileName;
    }

    public function getActiveCompat(): string
    {
        return $this->active;
    }

    public function setActiveCompat(bool|int|string $active): void
    {
        if (\in_array($active, ['Y', 'y', true, 1], true)) {
            $this->active = 'Y';
        } elseif (\in_array($active, ['N', 'n', false, 0], true)) {
            $this->active = 'N';
        }
    }

    public function getActive(): bool
    {
        return $this->active === 'Y';
    }

    public function setActive(bool $active): void
    {
        $this->active = $active === true ? 'Y' : 'N';
    }

    public function getShowAKZ(): bool
    {
        return $this->showAKZ;
    }

    public function setShowAKZ(bool|int|string $showAKZ): void
    {
        if (\is_bool($showAKZ)) {
            $this->showAKZ = $showAKZ;
        } elseif (\in_array($showAKZ, ['Y', 'y', true, 1, '1'], true)) {
            $this->showAKZ = true;
        } elseif (\in_array($showAKZ, ['N', 'n', false, 0, '0'], true)) {
            $this->showAKZ = false;
        }
    }

    public function getShowAGB(): bool
    {
        return $this->showAGB;
    }

    public function setShowAGB(bool|int|string $show): void
    {
        if (\is_bool($show)) {
            $this->showAGB = $show;
        } elseif (\in_array($show, ['Y', 'y', true, 1, '1'], true)) {
            $this->showAGB = true;
        } elseif (\in_array($show, ['N', 'n', false, 0, '0'], true)) {
            $this->showAGB = false;
        }
    }

    public function getShowWRB(): bool
    {
        return $this->showWRB;
    }

    public function setShowWRB(bool|int|string $show): void
    {
        if (\is_bool($show)) {
            $this->showWRB = $show;
        } elseif (\in_array($show, ['Y', 'y', true, 1, '1'], true)) {
            $this->showWRB = true;
        } elseif (\in_array($show, ['N', 'n', false, 0, '0'], true)) {
            $this->showWRB = false;
        }
    }

    public function getShowWRBForm(): bool
    {
        return $this->showWRBForm;
    }

    public function setShowWRBForm(bool|int|string $show): void
    {
        if (\is_bool($show)) {
            $this->showWRBForm = $show;
        } elseif (\in_array($show, ['Y', 'y', true, 1, '1'], true)) {
            $this->showWRBForm = true;
        } elseif (\in_array($show, ['N', 'n', false, 0, '0'], true)) {
            $this->showWRBForm = false;
        }
    }

    public function getShowDSE(): bool
    {
        return $this->showDSE;
    }

    public function setShowDSE(bool|int|string $show): void
    {
        if (\is_bool($show)) {
            $this->showDSE = $show;
        } elseif (\in_array($show, ['Y', 'y', true, 1, '1'], true)) {
            $this->showDSE = true;
        } elseif (\in_array($show, ['N', 'n', false, 0, '0'], true)) {
            $this->showDSE = false;
        }
    }

    public function getHasError(): bool
    {
        return $this->hasError === self::SYNTAX_FAIL;
    }

    public function setHasError(bool|int $hasError): void
    {
        $this->hasError = (int)$hasError > 0 ? self::SYNTAX_FAIL : self::SYNTAX_OK;
        if ($this->hasError === self::SYNTAX_FAIL) {
            $this->setActive(false);
        }
    }

    public function setSyntaxCheck(bool|int $checked): void
    {
        if ((int)$checked >= 0) {
            $this->setHasError($checked);
        } else {
            $this->hasError = self::SYNTAX_NOT_CHECKED;
        }
    }

    public function getSyntaxCheck(): int
    {
        return $this->hasError;
    }

    public function getLanguageID(): int
    {
        return $this->languageID;
    }

    public function setLanguageID(int|string $languageID): void
    {
        $this->languageID = (int)$languageID;
    }

    public function getPluginID(): int
    {
        return $this->pluginID;
    }

    public function setPluginID(int|string $pluginID): void
    {
        $this->pluginID = (int)$pluginID;
    }

    /**
     * @return array<int, string>
     */
    public function getSubjects(): array
    {
        return $this->subject;
    }

    public function getSubject(?int $languageID = null): string
    {
        return $this->subject[$languageID ?? Shop::getLanguageID()] ?? '';
    }

    public function setSubject(string $subject, int $languageID): void
    {
        $this->subject[$languageID] = $subject;
    }

    /**
     * @return array<int, string>
     */
    public function getAllHTML(): array
    {
        return $this->html;
    }

    public function getHTML(?int $languageID = null): string
    {
        return $this->html[$languageID ?? Shop::getLanguageID()] ?? '';
    }

    public function setHTML(string $html, int $languageID): void
    {
        $this->html[$languageID] = $html;
    }

    /**
     * @return array<int, string>
     */
    public function getAllText(): array
    {
        return $this->text;
    }

    public function getText(?int $languageID = null): string
    {
        return $this->text[$languageID ?? Shop::getLanguageID()] ?? '';
    }

    public function setText(string $text, int $languageID): void
    {
        $this->text[$languageID] = $text;
    }

    /**
     * @param int|null $languageID
     * @return string[]
     */
    public function getAttachments(?int $languageID = null): array
    {
        return $this->attachments[$languageID ?? Shop::getLanguageID()] ?? [];
    }

    /**
     * @return array<int, array<int, string>|null>
     */
    public function getAllAttachments(): array
    {
        return $this->attachments;
    }

    /**
     * @param array<int, array<int, string>> $attachments
     */
    public function setAllAttachments(array $attachments): void
    {
        $this->attachments = $attachments;
    }

    public function removeAttachments(int $languageID): void
    {
        $this->attachments[$languageID] = null;
    }

    /**
     * @param string[]|string|null $attachments
     * @param int                  $languageID
     */
    public function setAttachments(array|string|null $attachments, int $languageID): void
    {
        if ($attachments === null) {
            // if (DB-)NULL, use class-default
            return;
        }
        $this->attachments[$languageID] = \is_string($attachments)
            ? Text::parseSSK($attachments)
            : $attachments;
    }

    /**
     * @return string[]
     */
    public function getAttachmentNames(?int $languageID = null): array
    {
        return $this->attachmentNames[$languageID ?? Shop::getLanguageID()] ?? [];
    }

    /**
     * @return array<int, array<int, string>|null>
     */
    public function getAllAttachmentNames(): array
    {
        return $this->attachmentNames;
    }

    /**
     * @param array<int, array<int, string>|null> $names
     */
    public function setAllAttachmentNames(array $names): void
    {
        $this->attachmentNames = $names;
    }

    /**
     * @param array<int, string>|string|null $names
     * @param int                            $languageID
     */
    public function setAttachmentNames(array|string|null $names, int $languageID): void
    {
        $this->attachmentNames[$languageID] = \is_string($names)
            ? Text::parseSSK($names)
            : $names;
    }

    public function getData(): mixed
    {
        return $this->data;
    }

    public function setData(mixed $data): void
    {
        $this->data = $data;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }

    public function save(): int
    {
        $res = 0;
        $upd = new stdClass();
        foreach ($this->getMainMapping() as $field => $method) {
            $method = 'get' . $method;
            $data   = $this->$method();
            if (\is_bool($data)) {
                $data = (int)$data;
            }
            $upd->$field = \is_array($data) ? Text::createSSK($data) : $data;
        }
        $this->db->update('temailvorlage', 'kEmailvorlage', $this->getID(), $upd);
        foreach ($this->text as $langID => $text) {
            $upd = new stdClass();
            /**
             * @var string $field
             * @var string $method
             */
            foreach ($this->getLocalizedMapping() as $field => $method) {
                $method = 'get' . $method;
                $data   = $this->$method($langID);
                if (\is_bool($data)) {
                    $data = (int)$data;
                }
                $upd->$field = \is_array($data) ? Text::createSSK($data) : $data;
            }
            $upd->kSprache = $langID;
            $this->db->delete('temailvorlagesprache', ['kEmailvorlage', 'kSprache'], [$this->getID(), $langID]);
            $this->db->insert('temailvorlagesprache', $upd);
            ++$res;
        }

        return $res;
    }

    public function load(string $templateID): ?self
    {
        $data = $this->loadFromDB($templateID);
        if ($data === null) {
            return null;
        }
        $arrayRows = ['cBetreff', 'cContentHtml', 'cContentText', 'cPDFS', 'cPDFNames'];
        /** @var stdClass $res */
        $res = first($data);
        foreach ($arrayRows as $row) {
            $res->$row = $res->kSprache > 0 ? [$res->kSprache => $res->$row] : [];
        }
        /** @var stdClass $item */
        foreach (tail($data) as $item) {
            $keys = \get_object_vars($item);
            foreach ($keys as $k => $v) {
                if (\in_array($k, $arrayRows, true)) {
                    $res->$k[$item->kSprache] = $v;
                }
            }
        }
        foreach (\get_object_vars($res) as $key => $value) {
            /** @var string|null $mapping */
            $mapping = $this->getMapping($key);
            if ($mapping === null) {
                continue;
            }
            $method = 'set' . $mapping;
            if (\is_array($value)) {
                // setter with language ID
                foreach ($value as $langID => $content) {
                    $this->$method($content, $langID);
                }
            } else {
                $this->$method($value);
            }
        }

        return $this;
    }

    /**
     * @return stdClass[]|null
     */
    private function loadFromDB(string $templateID): ?array
    {
        $pluginID = 0;
        $moduleID = $templateID;
        if (\str_starts_with($templateID, 'kPlugin_')) {
            // $templateID looks like "kPlugin_1234_someplugin" or "kPlugin_123_some_plugin",
            // multiple underscores are possible
            $data = \explode('_', $templateID);

            [, $pluginID, $moduleID] = [\array_shift($data), \array_shift($data), \implode('_', $data)];
        }
        $data = $this->db->getObjects(
            'SELECT *, temailvorlage.kEmailvorlage AS id
                FROM temailvorlage
                LEFT JOIN temailvorlagesprache
                    ON temailvorlage.kEmailvorlage = temailvorlagesprache.kEmailvorlage
                WHERE temailvorlage.kPlugin = :pid
                    AND cModulId = :mid',
            ['pid' => (int)$pluginID, 'mid' => $moduleID]
        );

        return \count($data) === 0
            ? null
            : map(
                $data,
                static function (stdClass $e): stdClass {
                    $e->kSprache      = (int)$e->kSprache;
                    $e->kPlugin       = (int)$e->kPlugin;
                    $e->kEmailvorlage = (int)$e->id;
                    $e->nAKZ          = (int)$e->nAKZ;
                    $e->nAGB          = (int)$e->nAGB;
                    $e->nWRB          = (int)$e->nWRB;
                    $e->nWRBForm      = (int)$e->nWRBForm;
                    $e->nDSE          = (int)$e->nDSE;
                    $e->nFehlerhaft   = (int)$e->nFehlerhaft;
                    $e->cAktiv        = $e->cAktiv === 'Y';
                    $e->cBetreff      = $e->cBetreff ?? '';
                    $e->cContentHtml  = $e->cContentHtml ?? '';
                    $e->cContentText  = $e->cContentText ?? '';
                    $e->nPrio         = (int)$e->nPrio;

                    return $e;
                }
            );
    }

    /**
     * this is only useful for revisions
     *
     * @return array<int, stdClass>
     */
    public function viewCompat(): array
    {
        $res = [];
        foreach ($this->html as $langID => $data) {
            $item                = new stdClass();
            $item->kEmailvorlage = $this->getID();
            $item->cBetreff      = $this->getSubject($langID);
            $item->cContentHtml  = $this->getHTML($langID);
            $item->cContentText  = $this->getText($langID);
            $res[$langID]        = $item;
        }

        return $res;
    }
}
