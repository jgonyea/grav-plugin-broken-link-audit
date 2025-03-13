<?php
namespace Grav\Plugin\Console;

use Grav\Console\ConsoleCommand;
use Grav\Plugin\BrokenLinkAudit;
use Grav\Plugin\BrokenLinkAuditPlugin;
use Grav\Plugin\BrokenLinkAudit\Auditor;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class ClearCommand
 *
 * @package Grav\Plugin\Console
 */
class ClearCommand extends ConsoleCommand
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
            ->setName("clear")
            ->setDescription("Erases the links database.")
            ->addArgument(
                'url',
                InputArgument::OPTIONAL,
                'The name of the person that should be greeted'
            )
            ->addOption(
                'all',
                'a',
                InputOption::VALUE_NONE,
                'Clear all links'
            )
            ->addOption(
                'route',
                'r',
                InputOption::VALUE_NONE,
                'Single Route'
            )
            ->setHelp('The <info>clear</info> command clears links from the Broken Link Audit database.')
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

        // Load the Medoo instance from the plugin
        $auditor = new Auditor();

        // If the 'all' option is set, clear all links from both tables
        if ($this->options['all']) {
            $this->output->writeln('<info>Clearing all links...</info>');

            try {
                // Clear the 'links' table.
                $auditor->pdo->delete('links', []);
                $this->output->writeln('<info>All links cleared from the "links" table.</info>');

                // Clear the 'per_route' table.
                $auditor->pdo->delete('per_route', []);
                $this->output->writeln('<info>All links cleared from the "per_route" table.</info>');

                // Clear auto-increment value.
                $auditor->pdo->delete('sqlite_sequence', [
                    "name" => 'links'
                ]);
                $this->output->writeln('<info>Auto-increment reset.</info>');

            } catch (\Exception $e) {
                $this->output->writeln('<error>Failed to clear database: ' . $e->getMessage() . '</error>');
            }
        } else if ($this->input->getArgument('url')) {
            // If a URL is specified, clear links for that specific route.
            // TODO: write code for specifc url clearing.
            // Find link ID.
            // clear link from 'per_route' table.
            // clear link from 'links' table.

        } else {
            // If no options are set, show help message
            $this->output->writeln('<info>No specific action chosen. Use --all or specify a URL.</info>');
        }
    }
}
