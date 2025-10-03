<?php

declare(strict_types=1);

namespace JTL\Console\Command\Cache;

use JTL\Catalog\Category\Kategorie;
use JTL\Catalog\Currency;
use JTL\Catalog\Hersteller;
use JTL\Catalog\Product\Artikel;
use JTL\Console\Command\Command;
use JTL\Customer\CustomerGroup;
use JTL\Helpers\Tax;
use JTL\Language\LanguageHelper;
use JTL\Language\LanguageModel;
use JTL\Link\LinkGroupList;
use JTL\Settings\Option\Globals;
use JTL\Settings\Settings;
use JTL\Shop;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'cache:warm',
    description: 'Warm object cache',
    hidden: false
)]
class WarmCacheCommand extends Command
{
    private bool $details = true;

    private bool $list = true;

    private bool $categories = false;

    private bool $links = false;

    private bool $manufacturers = false;

    private bool $preFlush = false;

    private bool $childProducts = false;

    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->addOption('details', 'd', InputOption::VALUE_NONE, 'Warm product details')
            ->addOption('list', 'l', InputOption::VALUE_NONE, 'Warm product lists')
            ->addOption('childproducts', 'k', InputOption::VALUE_NONE, 'Warm product lists')
            ->addOption('linkgroups', 'g', InputOption::VALUE_NONE, 'Warm link groups')
            ->addOption('categories', 'c', InputOption::VALUE_NONE, 'Warm categories')
            ->addOption('manufacturers', 'm', InputOption::VALUE_NONE, 'Warm manufacturers')
            ->addOption('preflush', 'p', InputOption::VALUE_NONE, 'Flush cache before run');
    }

    private function debug(string $msg): void
    {
        $io = $this->getIO();
        if (!$io->isVerbose()) {
            return;
        }
        $io->writeln($msg);
    }

    /**
     * @param CustomerGroup[] $customerGroups
     * @param LanguageModel[] $languages
     */
    private function warmProducts(array $customerGroups, array $languages): int
    {
        if ($this->details === false && $this->list === false) {
            return 0;
        }
        $generated = 0;
        $where     = $this->childProducts ? '' : ' WHERE kVaterArtikel = 0';
        $listOpt   = Artikel::getDefaultOptions();
        $detailOpt = Artikel::getDetailOptions();
        $total     = $this->db->getSingleInt('SELECT COUNT(kArtikel) AS cnt FROM tartikel' . $where, 'cnt');
        $bar       = new ProgressBar($this->getIO(), $total);
        $bar->setFormat('cache');
        $bar->setMessage('Warming products');
        $bar->start();
        $currency = new Currency();
        $currency->getDefault();
        foreach ($this->db->getInts('SELECT kArtikel AS id FROM tartikel' . $where, 'id') as $pid) {
            foreach ($customerGroups as $customerGroup) {
                $_SESSION['Kundengruppe'] = $customerGroup;
                Tax::setTaxRates();
                foreach ($languages as $language) {
                    $languageID              = $language->getId();
                    $_SESSION['kSprache']    = $languageID;
                    $_SESSION['cISOSprache'] = $language->getCode();
                    Shop::setLanguage($languageID, $language->getCode());
                    if ($this->details === true) {
                        $product = (new Artikel($this->db, $customerGroup, $currency, $this->cache))->fuelleArtikel(
                            $pid,
                            $detailOpt,
                            $customerGroup->getID(),
                            $languageID
                        );
                        ++$generated;
                        $this->debug(
                            'Details for product ' . $pid
                            . ' in language ' . $languageID
                            . ' for customer group ' . $customerGroup->getID()
                            . ($product !== null && $product->kArtikel > 0
                                ? ' successfully loaded'
                                : ' could not be loaded')
                        );
                    }
                    if ($this->list === true) {
                        $product = (new Artikel($this->db, $customerGroup, $currency, $this->cache))->fuelleArtikel(
                            $pid,
                            $listOpt,
                            $customerGroup->getID(),
                            $languageID
                        );
                        ++$generated;
                        $this->debug(
                            'List view for product ' . $pid
                            . ' in language ' . $languageID
                            . ' for customer group ' . $customerGroup->getID()
                            . ($product !== null && $product->kArtikel > 0
                                ? ' successfully loaded'
                                : ' could not be loaded')
                        );
                    }
                }
            }
            $bar->advance();
            $bar->setMessage('Loaded product ' . $pid);
        }
        $bar->setMessage('All products loaded');
        $bar->finish();
        $this->getIO()->newLine();
        $this->getIO()->newLine();

        return $generated;
    }

    /**
     * @param CustomerGroup[] $customerGroups
     * @param LanguageModel[] $languages
     */
    private function warmCategories(array $customerGroups, array $languages): int
    {
        if ($this->categories !== true) {
            return 0;
        }
        $generated = 0;
        $total     = $this->db->getSingleInt('SELECT COUNT(kKategorie) AS cnt FROM tkategorie', 'cnt');
        $bar       = new ProgressBar($this->getIO(), $total);
        $bar->setFormat('cache');
        $bar->setMessage('Warming categories');
        $bar->start();
        foreach ($this->db->getInts('SELECT kKategorie FROM tkategorie', 'kKategorie') as $cid) {
            foreach ($customerGroups as $customerGroup) {
                foreach ($languages as $language) {
                    $category = new Kategorie($cid, $language->getId(), $customerGroup->getID(), false, $this->db);
                    ++$generated;
                    $this->debug(
                        'Category ' . $cid
                        . ($category->getID() > 0 ? ' successfully' : ' could not be')
                        . ' loaded in language ' . $language->getId()
                        . ' with customer group ' . $customerGroup->getID()
                    );
                }
            }
            $bar->advance();
            $bar->setMessage('Loaded category ' . $cid);
        }
        $bar->setMessage('All categories loaded');
        $bar->finish();
        $this->getIO()->newLine();
        $this->getIO()->newLine();

        return $generated;
    }

    /**
     * @param LanguageModel[] $languages
     */
    private function warmManufacturers(array $languages): int
    {
        if ($this->manufacturers !== true) {
            return 0;
        }
        $generated = 0;
        $total     = $this->db->getSingleInt('SELECT COUNT(kHersteller) AS cnt FROM thersteller', 'cnt');
        $bar       = new ProgressBar($this->getIO(), $total);
        $bar->setFormat('cache');
        $bar->setMessage('Warming manufacturers');
        $bar->start();
        foreach ($this->db->getInts('SELECT kHersteller FROM thersteller', 'kHersteller') as $mid) {
            foreach ($languages as $language) {
                $manufacturer = new Hersteller($mid, $language->getId());
                ++$generated;
                $this->debug(
                    'Manufacturer ' . $mid
                    . ($manufacturer->getID() > 0 ? ' successfully' : ' could not be')
                    . ' loaded in language ' . $language->getId()
                );
            }
            $bar->advance();
            $bar->setMessage('Loaded manufacturer ' . $mid);
        }
        $bar->setMessage('All manufacturers loaded');
        $bar->finish();
        $this->getIO()->newLine();
        $this->getIO()->newLine();

        return $generated;
    }

    private function warmLinks(): int
    {
        if ($this->links === false) {
            return 0;
        }
        $total = $this->db->getSingleInt('SELECT COUNT(*) AS cnt FROM tlinkgruppe', 'cnt') + 3;
        $bar   = new ProgressBar($this->getIO(), $total);
        $bar->setFormat('cache');
        $bar->setMessage('Warming link groups');
        $lgl = new LinkGroupList($this->db, $this->cache);
        $lgl->loadAll();
        $bar->start();
        $bar->advance($total);
        $bar->setMessage('All link groups loaded');
        $bar->finish();
        $this->getIO()->newLine();
        $this->getIO()->newLine();

        return $total;
    }

    private function warm(): void
    {
        $start = \microtime(true);
        $io    = $this->getIO();
        ProgressBar::setFormatDefinition(
            'cache',
            " \033[44;37m %message:-37s% \033[0m\n %current%/%max% %bar% %percent:3s%%"
        );
        Shop::setProductFilter(Shop::buildProductFilter([]));
        if ($this->preFlush === true) {
            $this->cache->flushAll();
        }
        if (\str_starts_with(\URL_SHOP, 'https://') || Settings::stringValue(Globals::CHECKOUT_SSL) === 'P') {
            $_SERVER['HTTPS'] = 'on';
        }

        global $_SESSION;
        $_SESSION             = [];
        $_SESSION['Sprachen'] = LanguageHelper::getInstance($this->db, $this->cache)->gibInstallierteSprachen();

        $generated      = 0;
        $customerGroups = $this->db->getCollection('SELECT kKundengruppe AS id FROM tkundengruppe')
            ->map(fn(\stdClass $e): CustomerGroup => new CustomerGroup((int)$e->id, $this->db))
            ->toArray();
        $languages      = LanguageModel::loadAll($this->db, [], [])->toArray();

        $generated += $this->warmCategories($customerGroups, $languages);
        $generated += $this->warmProducts($customerGroups, $languages);
        $generated += $this->warmManufacturers($languages);
        $generated += $this->warmLinks();

        $cacheStats = $this->cache->getStats();
        if (!isset($cacheStats['entries'])) {
            $io->error('Could not get cache stats. Cache is not available.');
            return;
        }
        $io->writeln('Entries in cache: ' . $cacheStats['entries'] . \PHP_EOL . 'Used Memory: ' . $cacheStats['mem']);
        $io->success(
            'Generated ' . $generated . ' cache entries for '
            . \count($customerGroups) . ' customer group(s) and '
            . \count($languages) . ' language(s) in '
            . \number_format(\microtime(true) - $start, 4) . 's.'
        );
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->details       = $this->getOption('details');
        $this->list          = $this->getOption('list');
        $this->childProducts = $this->getOption('childproducts');
        $this->categories    = $this->getOption('categories');
        $this->links         = $this->getOption('linkgroups');
        $this->manufacturers = $this->getOption('manufacturers');
        $this->preFlush      = $this->getOption('preflush');
        $this->warm();

        return Command::SUCCESS;
    }
}
