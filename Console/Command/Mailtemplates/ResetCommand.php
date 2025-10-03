<?php

declare(strict_types=1);

namespace JTL\Console\Command\Mailtemplates;

use JTL\Console\Command\Command;
use JTL\Router\Controller\Backend\EmailTemplateController;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'mailtemplates:reset',
    description: 'Reset all mail templates',
    hidden: false
)]
class ResetCommand extends Command
{
    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $templates = $this->db->getObjects('SELECT DISTINCT kEmailVorlage FROM temailvorlagesprache');
        $count     = 0;
        foreach ($templates as $template) {
            EmailTemplateController::resetTemplate((int)$template->kEmailVorlage, $this->db);
            $count++;
        }
        $output->writeln('<info>' . $count . ' templates have been reset.</info>');

        return Command::SUCCESS;
    }
}
