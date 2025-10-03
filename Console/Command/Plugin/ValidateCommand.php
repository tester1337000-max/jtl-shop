<?php

declare(strict_types=1);

namespace JTL\Console\Command\Plugin;

use JTL\Console\Command\Command;
use JTL\Plugin\Admin\Installation\Extractor;
use JTL\Plugin\Admin\Installation\InstallationResponse;
use JTL\Plugin\Admin\Validation\PluginValidator;
use JTL\Plugin\InstallCode;
use JTL\XMLParser;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'plugin:validate',
    description: 'Validate available plugin',
    hidden: false
)]
class ValidateCommand extends Command
{
    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->setName('plugin:validate')
            ->setDescription('Validate available plugin')
            ->setDefinition(
                new InputDefinition([
                    new InputOption(
                        'plugin-dir',
                        null,
                        InputOption::VALUE_REQUIRED,
                        'Plugin dir name relative to <shoproot>/plugins/'
                    ),
                    new InputOption('zipfile', null, InputOption::VALUE_OPTIONAL, 'Absolute path to zip file'),
                    new InputOption('delete', null, null, 'Delete zip and plugin dir after validating?'),
                ])
            );
    }

    /**
     * @inheritdoc
     */
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $pluginDir = \trim($input->getOption('plugin-dir') ?? '');
        while ($pluginDir === null || \strlen($pluginDir) < 3) {
            $pluginDir = $this->getIO()->ask('Plugin dir');
        }
        $input->setOption('plugin-dir', $pluginDir);
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io         = $this->getIO();
        $pluginDir  = $input->getOption('plugin-dir');
        $delete     = $input->getOption('delete');
        $zip        = $input->getOption('zipfile');
        $parser     = new XMLParser();
        $pluginPath = \PFAD_ROOT . \PLUGIN_DIR . $pluginDir;
        if ($zip !== null) {
            if (!\file_exists($zip)) {
                $io->writeln(\sprintf('<error>Zip file does not exist:</error> <comment>%s</comment>', $zip));

                return -1;
            }
            $response = $this->unzip($zip, $parser);
            if ($response->getStatus() === InstallationResponse::STATUS_OK) {
                $io->writeln(
                    \sprintf('<info>Successfully unzipped to</info> <comment>%s</comment>', $response->getPath())
                );
            }
            if (!\is_dir($pluginPath) || !\str_contains($response->getDirName() ?? '', $pluginDir)) {
                $io->writeln('<error>Could not extract or wrong dir name</error>');
                $this->cleanup($delete, $pluginDir, $zip);

                return InstallCode::DIR_DOES_NOT_EXIST;
            }
        }
        if (\is_dir($pluginPath)) {
            $io->writeln(\sprintf('<info>Validating plugin at</info> <comment>%s</comment>', $pluginDir));
            $validator = new PluginValidator($this->db, $parser);
            $res       = $validator->validateByPath($pluginPath);
            if ($res === InstallCode::OK) {
                $io->writeln(\sprintf('<info>Successfully validated</info> <comment>%s</comment>', $pluginDir));
            } else {
                $io->writeln('<error>Could not validate. Result code: ' . $res . '</error>');
            }
            $this->cleanup($delete, $pluginDir, $zip);

            return $res;
        }
        if (\is_dir(\PFAD_ROOT . \PFAD_PLUGIN . $pluginDir)) {
            $io->writeln(\sprintf('<info>Cannot validate legacy plugin at</info> <comment>%s</comment>', $pluginDir));
        } else {
            $io->writeln(\sprintf('<error>No plugin dir:</error> <comment>%s</comment>', $pluginDir));
        }
        $this->cleanup($delete, $pluginDir, $zip);

        return 0;
    }

    private function cleanup(bool $delete, string $pluginPath, ?string $zip): void
    {
        if ($delete !== true) {
            return;
        }
        if ($zip !== null && \file_exists($zip)) {
            \unlink($zip);
        }
        if (\is_dir($pluginPath)) {
            \rmdir($pluginPath);
        }
    }

    private function unzip(string $zipfile, XMLParser $parser): InstallationResponse
    {
        return (new Extractor($parser))->extractPlugin($zipfile, false);
    }
}
