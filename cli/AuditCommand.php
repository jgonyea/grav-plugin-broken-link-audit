<?php
namespace Grav\Plugin\Console;

use Grav\Console\ConsoleCommand;
use Grav\Plugin\BrokenLinkAudit;
use Grav\Plugin\BrokenLinkAuditPlugin;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class AuditCommand
 *
 * @package Grav\Plugin\Console
 */
class AuditCommand extends ConsoleCommand
{
    /**
     * @var array
     */
    protected $options = [];

    /**
     * Predefined cli colors.
     *
     * @var array
     */
    protected $colors = [
        'DEBUG'     => 'green',
        'INFO'      => 'cyan',
        'NOTICE'    => 'yellow',
        'WARNING'   => 'yellow',
        'ERROR'     => 'red',
        'CRITICAL'  => 'red',
        'ALERT'     => 'red',
        'EMERGENCY' => 'magenta'
    ];

    /**
     * Greets a person with or without yelling
     */
    protected function configure()
    {
        $this
            ->setName("audit")
            ->setDescription("Starts a broken link audit")
            ->addArgument(
                'url',
                InputArgument::OPTIONAL,
                'The name of the person that should be greeted'
            )
            ->addOption(
                'all',
                'a',
                InputOption::VALUE_NONE,
                'Scan all pages'
            )
            ->setHelp('The <info>audit</info> scans pages for broken links.')
        ;
    }

    /**
     * @return int|null|void
     */
    protected function serve()
    {
        // Collects the arguments and options as defined
        $this->options = [
            'all' => $this->input->getOption('all')
        ];

        $this->initializePages();

        $greetings = 'Kicking off audit';
        
        // If "all" is set, run scan on all pages.
        if ($this->options['all']) {
            BrokenLinkAuditPlugin::scanPages();
            $greetings = $greetings . ' of all pages';
        }

        // finally we write to the output the greetings
        $this->output->writeln($greetings);
    }
}
