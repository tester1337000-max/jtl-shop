<?php

declare(strict_types=1);

namespace JTL\Console\Command\Compile;

use JTL\Console\Command\Command;
use JTL\Filesystem\LocalFilesystem;
use JTL\Shop;
use JTL\Template\Compiler;
use League\Flysystem\FileAttributes;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'compile:sass',
    description: 'Compile all theme specific sass files',
    hidden: false
)]
class SASSCommand extends Command
{
    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->addOption('theme', null, InputOption::VALUE_OPTIONAL, 'Single theme name to compile')
            ->addOption('templateDir', null, InputOption::VALUE_OPTIONAL, 'Template directory to compile from');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io               = $this->getIO();
        $themeParam       = $this->getOption('theme');
        $templateDirParam = $this->getOption('templateDir');
        $templateDir      = $templateDirParam === null
            ? \PFAD_TEMPLATES . 'NOVA/themes/'
            : \PFAD_TEMPLATES . \rtrim($templateDirParam, '/') . '/themes/';
        $fileSystem       = Shop::Container()->get(LocalFilesystem::class);
        $compiler         = new Compiler();
        if ($themeParam !== null) {
            $compiler->compileSass($themeParam, $templateDir);
        } else {
            $compiled = 0;
            /** @var FileAttributes $themeFolder */
            foreach ($fileSystem->listContents($templateDir, false) as $themeFolder) {
                if (!$compiler->compileSass(\basename($themeFolder->path()), $templateDir)) {
                    $io->error($compiler->getErrors());
                    break;
                }
                ++$compiled;
            }
            if ($compiled === 0) {
                $io->writeln('<info>No files were compiled.</info>');
            }
        }
        if (\count($compiler->getCompiled()) > 0) {
            $io->listing($compiler->getCompiled());
        }
        if (\count($compiler->getErrors()) > 0) {
            $io->error($compiler->getErrors());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
