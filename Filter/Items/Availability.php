<?php

declare(strict_types=1);

namespace JTL\Filter\Items;

use JTL\Filter\AbstractFilter;
use JTL\Filter\FilterInterface;
use JTL\Filter\InputType;
use JTL\Filter\Join;
use JTL\Filter\Option;
use JTL\Filter\ProductFilter;
use JTL\Filter\StateSQL;
use JTL\Filter\Type;
use JTL\Shop;

/**
 * Class Availability
 * @package JTL\Filter\Items
 */
class Availability extends AbstractFilter
{
    public function __construct(ProductFilter $productFilter)
    {
        parent::__construct($productFilter);
        $this->setType(Type::AND)
            ->setIcon('fa-truck')
            ->setUrlParam(\QUERY_PARAM_AVAILABILITY)
            ->setInputType(InputType::BUTTON)
            ->setVisibility($this->getConfig('navigationsfilter')['allgemein_availabilityfilter_benutzen'])
            ->setFrontendName(Shop::isAdmin() ? \__('filterAvailability') : Shop::Lang()->get('filterAvailability'))
            ->setFilterName($this->getFrontendName());
    }

    /**
     * @param int|string $value
     * @return $this
     */
    public function setValue($value): FilterInterface
    {
        return parent::setValue((int)$value);
    }

    /**
     * @inheritdoc
     */
    public function setSeo(array $languages): FilterInterface
    {
        foreach ($languages as $language) {
            $this->cSeo[$language->kSprache] = 'available';
        }
        if ($this->getValue() === 1) {
            $this->setName(Shop::Lang()->get('ampelGruen'));
        }

        return $this;
    }

    public function mapSeoURL(string $seoString): ?int
    {
        return 1;
    }

    /**
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'taFA';
    }

    /**
     * @inheritdoc
     */
    public function getSQLCondition(): string
    {
        return "(
                    (taFA.cLagerBeachten = 'Y' AND taFA.fLagerbestand > 0)
                    OR taFA.cLagerBeachten = 'N'
                    OR taFA.cLagerKleinerNull = 'Y'
                )";
    }

    /**
     * @inheritdoc
     */
    public function getSQLJoin(): Join
    {
        return (new Join())
            ->setType('JOIN')
            ->setTable('tartikel AS taFA')
            ->setOn('tartikel.kArtikel = taFA.kArtikel')
            ->setOrigin(__CLASS__);
    }

    /**
     * @inheritdoc
     */
    public function getOptions($mixed = null): array
    {
        if ($this->options !== null) {
            return $this->options;
        }
        $this->options = [];
        if (
            (int)$this->getConfig()['global']['artikel_artikelanzeigefilter']
            !== \EINSTELLUNGEN_ARTIKELANZEIGEFILTER_ALLE
        ) {
            return $this->options;
        }
        foreach ($this->getOptionData() as $option) {
            $active = $this->getProductFilter()->filterOptionIsActive(
                $this->getClassName(),
                (int)$option->filterval
            );
            if ($active === true) {
                $this->hide();
            }
            $opt = new Option();
            $opt->setType($this->getType());
            $opt->setClassName($this->getClassName());
            $opt->setParam($this->getUrlParam());
            $opt->setName(Shop::Lang()->get('ampelGruen'));
            $opt->setValue((int)$option->filterval);
            $opt->setCount((int)$option->nAnzahl);
            $opt->setURL(
                $this->getProductFilter()->getFilterURL()->getURL(
                    (new self($this->getProductFilter()))->init((int)$option->filterval)
                )
            );
            $opt->setIsActive($active);
            $this->options[] = $opt;
        }

        return $this->options;
    }

    /**
     * @return \stdClass[]
     */
    private function getOptionData(): array
    {
        $state = $this->getProductFilter()->getCurrentStateData();
        $state->addJoin($this->getSQLJoin());
        $state->addCondition($this->getSQLCondition());
        $sql = new StateSQL();
        $sql->setJoins($state->getJoins());
        $sql->setSelect(['1 AS filterval']);
        $sql->setConditions($state->getConditions());
        $sql->setHaving($state->getHaving());
        $sql->setOrderBy('');

        return $this->getProductFilter()->getDB()->getObjects(
            'SELECT ssMerkmal.filterval, COUNT(*) AS nAnzahl
                FROM (' . $this->getProductFilter()->getFilterSQL()->getBaseQuery($sql) . ' ) AS ssMerkmal
                GROUP BY ssMerkmal.filterval
                ORDER BY ssMerkmal.filterval ASC'
        );
    }
}
