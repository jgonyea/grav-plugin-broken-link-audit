<?php
namespace Grav\Plugin\BrokenLinkAudit;

use Grav\Common\Grav;
use Grav\Common\Page\Page;
use Grav\Plugin\BrokenLinkAudit\Recorder;
use Medoo\Medoo;

class Auditor
{
    /** @var Medoo $pdo */
    public $pdo;

    /** @var string $data_path */
    private $data_path;

    private $grav;

    public function __construct($options = [])
    {
        $this->grav = Grav::instance();

        if (!isset($this->pdo)) {
            $this->pdo = $this->connect();

            // Ensure expected tables' structure exists.
            $this->checkTables();
        }
    }


    public function countRoutes(): int
    {
        $data = $this->pdo->select("per_route", [
            "unique_routes" => Medoo::raw("COUNT(DISTINCT route)")
        ]);

        return $data[0]['unique_routes'];
    }

    /**
     * Connects to database.
     *
     * @return void
     */
    private function connect(): Medoo
    {
        $grav = Grav::instance();
        $bla_config = $grav['config']['plugins']['broken-link-audit'];

        $language = $grav['language'];
        $language_prefix = "en";
        if ($language->enabled()) {
            $active = $language->getActive();
            $default = $language->getDefault();
            $this->language = $active ?: $default;
            $language_prefix = $this->language;
        }

        $db_opts = [];
        switch ($bla_config['report_storage']['type']) {
            case 'mysql':
                // todo: Add MySQL/ MariaDB options here.
                $db_opts = [

                ];

                break;

            case 'sqlite':
                $locator = $grav['locator'];
                $this->data_path = $locator->findResource('user://data', true) . '/broken-link-audit';

                // Create data folder.
                if (!file_exists($this->data_path)) {
                    mkdir($this->data_path);
                    $grav['log']->notice('Created Broken Link Audit data folder.');
                }

                $db_opts = [
                    'database_type' => 'sqlite',
                    'database_file' => $this->data_path . "/" . $language_prefix . ".sqlite"
                ];
                break;

            default:
                $locator = $grav['locator'];
                $this->data_path = $locator->findResource('user://data', true) . '/broken-link-audit';

                // In memory only db.
                $db_opts = [
                    'database_type' => 'sqlite',
                    'database_file' => ':memory:'
                ];
        }

        $database = new Medoo($db_opts);

        return $database;
    }

    public function checkTables(): void
    {
        /** @var Medoo $pdo */
        $this->pdo = $this->connect();
        try {
            $this->pdo->select("per_route", "*");
        } catch (\Exception $e) {
            $this->pdo->create("per_route", [
                "route" => [
                    "TEXT",
                    "NOT NULL",
                ],
                "link_type" => [
                    "TEXT",
                    "NOT NULL",
                ],
                "link" => [
                    "TEXT",
                    "NOT NULL",
                ],
                "last_found" => [
                    "NUMERIC",
                    "NOT NULL",
                ],
            ]);
        }
    }

    /**
     *
     *
     * @param Page $page
     * @return void
     */
    public function scanPage($page): void
    {
        $bla_config = $this->grav['config']['plugins']['broken-link-audit'];
        $inspection_level = $bla_config['inspection_level'];
        $valid_routes = $this->grav['pages']->routes();

        if ($inspection_level == 'raw') {
            $content = $page->raw();
            // Get all links on page.
            $links = $this->findRawPageLinks($content);
            // Find bad links.
            $bad_links = $links;
            // Save bad links to db.
            $this->saveInvalidLinks($page->route(), $bad_links);
        } elseif ($inspection_level == 'rendered') {
            // todo: find rendered content of a page.
        }
    }

    /**
     * Writes out link data to database.
     *
     * @param array $routes
     * @return void
     */
    public function saveInvalidLinks($route, $links):void
    {
        if (!empty($links)) {
            $data[$route] = $links;

            foreach ($links as $type => $link_type) {
                foreach ($link_type as $link) {
                    $link = trim($link);
                    $where = [
                        "AND" => [
                            "route[=]" => $route,
                            "link[=]" => $link,
                            ]
                        ];

                        $row_data = [
                            "route" => $route,
                            "link_type" => $type,
                            "link" => $link,
                            "last_found" => time(),
                        ];

                        // Check if link exists in database.
                        $result = $this->pdo->has("per_route", $where);

                        // If link already exists, update the epoch.
                        if ($result) {
                            $this->pdo->update("per_route", $row_data, $where);
                        } else {
                            $this->pdo->insert("per_route", $row_data);
                        }
                }
            }
        }
    }

    /**
     * Scans content for markdown links.
     *
     * @param string $content
     * @return array
     */
    private function findRawPageLinks($content): array
    {
        $links = [];

        // Create list of matching URLS to patterns.
        foreach ($this->rawInspectionPatterns() as $type => $page_pattern) {
            preg_match_all($page_pattern, $content, $matches);

            if (count($matches[0]) > 0) {
                $links[$type] = $matches[0];
            }
        }

        return $links;
    }

    private function rawInspectionPatterns(): array
    {
        return array(
            'page_relative'     =>  '/[^!]\[[^!].*\]\((?!http)[^\/].*\)/',
            'page_absolute'     =>  '/[^!]\[[^!].*\]\(\/.*\)/',
            'page_remote'       =>  '/[^!]\[[^!].*\]\(http.*\)/',

            'combined'          =>  '/\[!\[.*\]\(.*\)\]\(.*\)/',

            'media_relative'    =>  '/\![[^!].*\]\((?!http)(?!user)(?!theme)(?!plugin)[^\/].*\)/',
            'media_absolute'    =>  '/[^\[]!\[[^!].*\]\(\/.*\)/',
            'media_remote'      =>  '/[^\[]!\[[^!].*\]\(http.*\)/',

            'raw'               =>  '/(\[[^][]*+(?:(?R)[^][]*)*+\])(\([^)(]*+(?:(?R)[^)(]*)*+\))/',
        );
    }
}
