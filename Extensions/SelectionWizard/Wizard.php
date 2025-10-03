<?php

declare(strict_types=1);

namespace JTL\Extensions\SelectionWizard;

use JTL\Catalog\Category\Kategorie;
use JTL\Filter\Items\Characteristic;
use JTL\Filter\Option;
use JTL\Filter\ProductFilter;
use JTL\Filter\SearchResults;
use JTL\Nice;
use JTL\Shop;
use JTL\Shopsetting;
use JTL\Smarty\JTLSmarty;
use stdClass;

/**
 * Class Wizard
 * @package JTL\Extensions\SelectionWizard
 */
class Wizard
{
    private int $kAuswahlAssistentOrt = 0;

    private int $kAuswahlAssistentGruppe = 0;

    private string $cKey = '';

    private int $kKey = 0;

    private int $kSprache = 0;

    private string $cName = '';

    private string $cBeschreibung = '';

    private int $nAktiv = 0;

    /**
     * @var Question[]
     */
    private array $questions = [];

    /**
     * @var Question[] - keys are kMerkmal
     */
    private array $questionsAssoc = [];

    private int $nCurQuestion = 0;

    /**
     * @var array<mixed>
     */
    private array $selections = [];

    private ?ProductFilter $productFilter;

    /**
     * @var array<mixed>
     */
    private array $config;

    public function __construct(string $keyName, int $id, int $languageID = 0, bool $activeOnly = true)
    {
        $this->config = Shop::getSettingSection(\CONF_AUSWAHLASSISTENT);
        $languageID   = $languageID ?: Shop::getLanguageID();
        if (
            $id > 0
            && $languageID > 0
            && !empty($keyName)
            && Nice::getInstance()->checkErweiterung(\SHOP_ERWEITERUNG_AUSWAHLASSISTENT)
        ) {
            $this->loadFromDB($keyName, $id, $languageID, $activeOnly);
        }
    }

    private function loadFromDB(string $keyName, int $id, int $languageID, bool $activeOnly = true): void
    {
        $cache   = Shop::Container()->getCache();
        $cacheID = 'jtl_sw_' . $keyName . '_' . $id . '_' . $languageID . '_' . (int)$activeOnly;
        if (($item = $cache->get($cacheID)) !== false) {
            foreach (\get_object_vars($item) as $name => $value) {
                $this->$name = $value;
            }
            return;
        }
        $item = Shop::Container()->getDB()->getSingleObject(
            'SELECT *
                FROM tauswahlassistentort AS ao
                    JOIN tauswahlassistentgruppe AS ag
                        ON ao.kAuswahlAssistentGruppe = ag.kAuswahlAssistentGruppe
                            AND ao.cKey = :ckey
                            AND ao.kKey = :kkey
                            AND ag.kSprache = :ksprache' .
            ($activeOnly ? ' AND ag.nAktiv = 1' : ''),
            [
                'ckey'     => $keyName,
                'kkey'     => $id,
                'ksprache' => $languageID
            ]
        );
        $this->init($item, $activeOnly);
        $cache->set($cacheID, $this, [\CACHING_GROUP_CORE]);
    }

    private function init(?stdClass $item, bool $activeOnly): void
    {
        if ($item === null) {
            return;
        }
        $this->kAuswahlAssistentOrt    = (int)$item->kAuswahlAssistentOrt;
        $this->kAuswahlAssistentGruppe = (int)$item->kAuswahlAssistentGruppe;
        $this->kKey                    = (int)$item->kKey;
        $this->kSprache                = (int)$item->kSprache;
        $this->nAktiv                  = (int)$item->nAktiv;
        $this->cKey                    = $item->cKey;
        $this->cName                   = $item->cName;
        $this->cBeschreibung           = $item->cBeschreibung;

        $questionIDs = Shop::Container()->getDB()->getInts(
            'SELECT kAuswahlAssistentFrage AS id
                FROM tauswahlassistentfrage
                WHERE kAuswahlAssistentGruppe = :groupID' .
            ($activeOnly ? ' AND nAktiv = 1 ' : ' ') .
            'ORDER BY nSort',
            'id',
            ['groupID' => $this->kAuswahlAssistentGruppe]
        );

        $this->questions = [];

        foreach ($questionIDs as $questionID) {
            $question                                  = new Question($questionID);
            $this->questions[]                         = $question;
            $this->questionsAssoc[$question->kMerkmal] = $question;
        }
    }

    public function setNextSelection(int $kWert): self
    {
        if ($this->nCurQuestion < \count($this->questions)) {
            $this->selections[] = $kWert;
            ++$this->nCurQuestion;
        }

        return $this;
    }

    public function filter(): self
    {
        $params = [];
        if ($this->cKey === \AUSWAHLASSISTENT_ORT_KATEGORIE) {
            $params['kKategorie'] = $this->kKey;
            if (\count($this->selections) > 0) {
                $params['MerkmalFilter_arr'] = $this->selections;
            }
        } elseif (\count($this->selections) > 0) {
            $params['kMerkmalWert'] = $this->selections[0];
            if (\count($this->selections) > 1) {
                $params['MerkmalFilter_arr'] = \array_slice($this->selections, 1);
            }
        }
        $productFilter   = Shop::buildProductFilter($params);
        $currentCategory = isset($params['kKategorie'])
            ? new Kategorie($params['kKategorie'])
            : null;
        $filterOptions   = (new SearchResults())->setFilterOptions(
            $productFilter,
            $currentCategory,
            true
        )->getCharacteristicFilterOptions();
        foreach ($filterOptions as $option) {
            /** @var Characteristic $option */
            if (!\array_key_exists($option->getValue(), $this->questionsAssoc)) {
                continue;
            }
            $question                    = $this->questionsAssoc[$option->getValue()];
            $question->oWert_arr         = $option->getOptions();
            $question->nTotalResultCount = 0;
            foreach ($option->getOptions() as $value) {
                $question->nTotalResultCount                            += $value->getCount();
                $question->oWert_assoc[$value->getData('kMerkmalWert')] = $value;
            }
        }
        $this->productFilter = $productFilter;

        return $this;
    }

    public function fetchForm(JTLSmarty $smarty): string
    {
        return $smarty->assign('AWA', $this)
            ->assign('Einstellungen', Shopsetting::getInstance()->getAll())
            ->fetch('selectionwizard/form.tpl');
    }

    public function getLocationId(): int
    {
        return $this->kAuswahlAssistentOrt;
    }

    public function getGroupId(): int
    {
        return $this->kAuswahlAssistentGruppe;
    }

    public function getLocationKeyName(): string
    {
        return $this->cKey;
    }

    public function getLocationKeyId(): int
    {
        return $this->kKey;
    }

    public function getName(): string
    {
        return $this->cName;
    }

    public function getDescription(): string
    {
        return \preg_replace('/\s+/', ' ', \trim($this->cBeschreibung)) ?? '';
    }

    public function isActive(): bool
    {
        return $this->nAktiv === 1;
    }

    public function getQuestion(int $id): ?Question
    {
        return $this->questions[$id] ?? null;
    }

    /**
     * @return Question[]
     */
    public function getQuestions(): array
    {
        return $this->questions;
    }

    public function getQuestionCount(): int
    {
        return \count($this->questions);
    }

    public function getCurQuestion(): int
    {
        return $this->nCurQuestion;
    }

    /**
     * @return array<mixed>
     */
    public function getSelections(): array
    {
        return $this->selections;
    }

    /**
     * @return Option|mixed
     */
    public function getSelectedValue(int $questionID): mixed
    {
        $question      = $this->questions[$questionID];
        $selectedValue = $this->selections[$questionID];

        return $question->oWert_assoc[$selectedValue];
    }

    public function getNaviFilter(): ProductFilter
    {
        return $this->productFilter;
    }

    public function getLastSelectedValue(): ?Option
    {
        $question      = \end($this->questions);
        $selectedValue = \end($this->selections);

        return $question->oWert_assoc[$selectedValue] ?? null;
    }

    public function getConf(string $name): ?string
    {
        return $this->config[$name] ?? null;
    }

    public static function isRequired(): bool
    {
        return Shop::getSettingValue(\CONF_AUSWAHLASSISTENT, 'auswahlassistent_nutzen') === 'Y';
    }

    /**
     * @param int[]|numeric-string[] $selected
     */
    public static function startIfRequired(
        string $keyName,
        int $id,
        int $languageID = 0,
        ?JTLSmarty $smarty = null,
        array $selected = [],
        ?ProductFilter $pf = null
    ): ?self {
        // only start if enabled in the backend settings
        if (!self::isRequired()) {
            return null;
        }
        $filterCount = $pf !== null ? $pf->getFilterCount() : 0;
        // only start if no filters are already set
        if ($filterCount === 0) {
            $wizard = new self($keyName, $id, $languageID, true);
            // only start if the respective selection wizard group is enabled (active)
            if ($wizard->isActive()) {
                foreach (\array_filter($selected, '\is_numeric') as $kMerkmalWert) {
                    $wizard->setNextSelection((int)$kMerkmalWert);
                }
                $wizard->filter();
                $smarty?->assign('AWA', $wizard);

                return $wizard;
            }
        }

        return null;
    }

    /**
     * @return stdClass[]
     */
    public static function getLinks(): array
    {
        return Shop::Container()->getDB()->selectAll('tlink', 'nLinkart', \LINKTYP_AUSWAHLASSISTENT);
    }

    public function getLanguageID(): int
    {
        return $this->kSprache;
    }
}
