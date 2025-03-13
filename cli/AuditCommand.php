<?php
namespace Grav\Plugin\Console;
use Grav\Common\Grav;
use Grav\Console\ConsoleCommand;
use Grav\Plugin\BrokenLinkAudit;
use Grav\Plugin\BrokenLinkAuditPlugin;
use Grav\Plugin\BrokenLinkAudit\Auditor;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;

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
     * Starts an audit of broken links.
     */
    protected function configure()
    {
        $this
            ->setName("audit")
            ->setDescription("Starts a broken link audit")
            ->addArgument(
                'route',
                InputArgument::OPTIONAL,
                'The name of the single route that should be scanned.'
            )
            ->addOption(
                'all',
                'a',
                InputOption::VALUE_NONE,
                'Scan all pages'
            )
            ->addOption(
                'route',
                'r',
                InputOption::VALUE_NONE,
                'Scan single route'
            )
            ->setHelp('The <info>audit</info> command scans pages for broken links.')
        ;
    }

    /**
     * @return int|null|void
     */
    protected function serve()
    {
        // Collects the arguments and options as defined
        $this->options = [
            'all' => $this->input->getOption('all'),
            'route'=> $this->input->getOption('route'),
        ];

        $auditor = new Auditor();
        $grav = Grav::instance();
        $this->initializePages();

        $this->output->writeln('Starting scan');

        // If "all" is set, run scan on all pages.
        if ($this->options['all']) {
            /** @var Pages $pages */
            $pages = $grav['pages'];
            if (method_exists($pages, 'enablePages')) {
                $pages->enablePages();
            }

            // Process page(s).
            $output = new ConsoleOutput();
            ProgressBar::setFormatDefinition('custom', ' %current%/%max% -- %message%');
            $progressBar = new ProgressBar($output, count($pages->all()));
            $progressBar->setFormat('custom');
            $progressBar->start();
            foreach ($pages->all() as $key => $page) {
                $progressBar->setMessage('Processing links on ' . $page->route());
                $auditor->scanPage($page);
                $progressBar->advance();
            }
        } else if ($this->options['route']) {
            $pages = $grav['pages'];

            // TODO: Add individual page scan.

            // Hard coded route.
            $route = '/broken-links';
            if (method_exists($pages, 'enablePages')) {
                $pages->enablePages();
            }
            $page = $pages->find($route);

            $auditor->scanPage($page);
        }
        $this->output->writeln("\nScan Complete");
    }
}
