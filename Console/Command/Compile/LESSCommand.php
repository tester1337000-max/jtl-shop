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
    name: 'compile:less',
    description: 'Compile all theme specific less files',
    hidden: false
)]
class LESSCommand extends Command
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
        $compiler         = new Compiler();
        $themeParam       = $this->getOption('theme');
        $templateDirParam = $this->getOption('templateDir');
        $templateDir      = $templateDirParam === null
            ? \PFAD_TEMPLATES . 'Evo/themes/'
            : \PFAD_TEMPLATES . \rtrim($templateDirParam, '/') . '/themes/';
        if ($themeParam === null) {
            $compiled   = 0;
            $fileSystem = Shop::Container()->get(LocalFilesystem::class);
            /** @var FileAttributes $themeFolder */
            foreach ($fileSystem->listContents($templateDir) as $themeFolder) {
                if (\basename($themeFolder->path()) === 'base') {
                    continue;
                }
                if (!$compiler->compileLess(\basename($themeFolder->path()), $templateDir)) {
                    $io->error($compiler->getErrors());

                    return Command::FAILURE;
                }
                ++$compiled;
            }
            if ($compiled === 0) {
                $io->error($compiler->getErrors());
                $io->writeln('<info>No files were compiled.</info>');

                return Command::FAILURE;
            }
            $io->listing($compiler->getCompiled());
        } elseif ($compiler->compileLess($themeParam, $templateDir)) {
            $io->listing($compiler->getCompiled());
        } else {
            $io->error(\__(\sprintf('Theme %s could not be compiled.', $themeParam)));

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
