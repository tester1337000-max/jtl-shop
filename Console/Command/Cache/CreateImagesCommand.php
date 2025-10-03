<?php

declare(strict_types=1);

namespace JTL\Console\Command\Cache;

use JTL\Console\Command\Command;
use JTL\Media\Image\Category;
use JTL\Media\Image\Characteristic;
use JTL\Media\Image\CharacteristicValue;
use JTL\Media\Image\ConfigGroup;
use JTL\Media\Image\Manufacturer;
use JTL\Media\Image\OPC;
use JTL\Media\Image\Product;
use JTL\Media\Image\Variation;
use JTL\Media\IMedia;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'cache:images:create',
    description: 'Create images in various sizes',
    hidden: false
)]
class CreateImagesCommand extends Command
{
    private bool $products = false;

    private bool $manufacturers = false;

    private bool $categories = false;

    private bool $characteristics = false;

    private bool $configGroups = false;

    private bool $characteristicValues = false;

    private bool $variations = false;

    private bool $opc = false;

    private bool $printErrors = false;

    private bool $all = false;

    /**
     * @var string[]
     */
    private array $errors = [];

    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->addOption('printErrors', 'e', InputOption::VALUE_NONE, 'Show error messages')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Create all images')
            ->addOption('products', 'p', InputOption::VALUE_NONE, 'Create product images')
            ->addOption('categories', 'c', InputOption::VALUE_NONE, 'Create category images')
            ->addOption('opc', 'o', InputOption::VALUE_NONE, 'Create OPC images')
            ->addOption('configgroups', null, InputOption::VALUE_NONE, 'Create config group images')
            ->addOption('characteristics', null, InputOption::VALUE_NONE, 'Create characteristic images')
            ->addOption('characteristicvalues', null, InputOption::VALUE_NONE, 'Create characteristic value images')
            ->addOption('variations', null, InputOption::VALUE_NONE, 'Create variation images')
            ->addOption('manufacturers', 'm', InputOption::VALUE_NONE, 'Create manufacturer images');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->products             = $this->getOption('products');
        $this->manufacturers        = $this->getOption('manufacturers');
        $this->categories           = $this->getOption('categories');
        $this->variations           = $this->getOption('variations');
        $this->characteristics      = $this->getOption('characteristics');
        $this->characteristicValues = $this->getOption('characteristicvalues');
        $this->configGroups         = $this->getOption('configgroups');
        $this->opc                  = $this->getOption('opc');
        $this->all                  = $this->getOption('all');
        $this->printErrors          = $this->getOption('printErrors');
        $this->generateImages();

        return Command::SUCCESS;
    }

    /**
     * @return IMedia[]
     */
    private function getInstances(): array
    {
        $instances = [];
        if ($this->all === true || $this->products === true) {
            $instances[] = new Product($this->db);
        }
        if ($this->all === true || $this->categories === true) {
            $instances[] = new Category($this->db);
        }
        if ($this->all === true || $this->manufacturers === true) {
            $instances[] = new Manufacturer($this->db);
        }
        if ($this->all === true || $this->variations === true) {
            $instances[] = new Variation($this->db);
        }
        if ($this->all === true || $this->characteristics === true) {
            $instances[] = new Characteristic($this->db);
        }
        if ($this->all === true || $this->characteristicValues === true) {
            $instances[] = new CharacteristicValue($this->db);
        }
        if ($this->all === true || $this->configGroups === true) {
            $instances[] = new ConfigGroup($this->db);
        }
        if ($this->all === true || $this->opc === true) {
            $instances[] = new OPC($this->db);
        }

        return $instances;
    }

    private function generateImages(): int
    {
        ProgressBar::setFormatDefinition(
            'cache',
            " \033[44;37m %message:-37s% \033[0m\n %current%/%max% %bar% %percent:3s%%"
        );
        $total  = 0;
        $failed = 0;
        $io     = $this->getIO();
        foreach ($this->getInstances() as $instance) {
            $generated = 0;
            $totalAll  = $instance->getUncachedImageCount();
            $bar       = new ProgressBar($io, $totalAll);
            $bar->setFormat('cache');
            $bar->setMessage(\sprintf('Generating images for %s', $instance::class));
            $bar->start();
            $images = $instance->getImages(true, 0, $totalAll);
            foreach ($images as $image) {
                $bar->setMessage(
                    \sprintf(
                        'Generating image: %d-%d (%s)',
                        $image->id,
                        $image->number,
                        $image->name,
                    )
                );
                $res = $instance->cacheImage($image);
                foreach ($res as $item) {
                    if (($item->success ?? true) !== false) {
                        continue;
                    }
                    ++$failed;
                    if ($item->error !== null) {
                        $this->errors[] = $item->error;
                    }
                    break;
                }
                $bar->advance();
                ++$total;
                ++$generated;
            }

            $bar->setMessage(\sprintf('%d %s images generated', $generated, $instance::class));
            $bar->finish();
            $io->newLine();
        }
        if ($failed > 0) {
            $io->error(\sprintf('%d errors', $failed));
            if ($this->printErrors === true) {
                foreach ($this->errors as $error) {
                    $io->writeln($error);
                }
            }
        }

        return $total;
    }
}
