<?php
namespace Grav\Plugin\BrokenLinkAudit;

use Grav\Common\Grav;
use Grav\Common\Page\Page;
use Medoo\Medoo;

class Auditor
{
    /** @var Medoo $pdo */
    public $pdo;

    /** @var string $data_path */
    private $data_path;

    private $grav;

    private $language;

    public function __construct($options = [])
    {
        $this->grav = Grav::instance();

        if (!isset($this->pdo)) {
            try {
                // Ensure expected tables' structure exists.
                $this->pdo = $this->connect();
                $this->checkTables();
            } catch (\Error|\PDOException $e) {
                $this->grav['messages']->add('<a href="/admin/plugins/broken-link-audit">Broken Link Audit: ' . $e->getMessage() . '</a>', 'error');
                return null;
            }
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
    private function connect(): Medoo|null
    {
        $bla_config = $this->grav['config']['plugins']['broken-link-audit'];

        $language = $this->grav['language'];
        $language_prefix = "en";
        if ($language->enabled()) {
            $active = $language->getActive();
            $default = $language->getDefault();
            $this->language = $active ?: $default;
            $language_prefix = $this->language;
        }

        $db_opts = [];
        switch ($bla_config['report_storage']['db']) {
            case 'mysql':
                // todo: Add MySQL/ MariaDB options here.
                if (isset($bla_config['report_storage']['host']) && isset($bla_config['report_storage']['username']) && isset($bla_config['report_storage']['password'])) {
                    $db_opts = [
                        // [required]
                        'database_type' => 'mysql',
                        'server' => $bla_config['report_storage']['host'],
                        'database_name' => $bla_config['report_storage']['dbname'],
                        'username' => $bla_config['report_storage']['username'],
                        'password' => $bla_config['report_storage']['password'],

                        // [optional]
                        'charset' => 'utf8mb4',
                        'collation' => 'utf8mb4_general_ci',
                        'port' => $bla_config['report_storage']['port'],

                        // [optional] Enable logging, it is disabled by default for better performance.
                        'logging' => false,
                    ];

                    isset($bla_config['report_storage']['prefix']) ? $db_opts['prefix'] =  $bla_config['report_storage']['prefix'] : '';
                } else {
                    throw new \Error('Misconfigured MySQL/ MariaDB database settings');
                }
                break;

            case 'sqlite':
            default:
                $locator = $this->grav['locator'];
                $this->data_path = $locator->findResource('user://data', true) . '/broken-link-audit';

                // Create data folder.
                if (!file_exists($this->data_path)) {
                    mkdir($this->data_path, 0770, true);
                    $this->grav['log']->notice('Missing Broken Link Audit data.  Created new Broken Link Audit data folder at "' . $this->data_path . '".');
                }

                $db_opts = [
                    'database_type' => 'sqlite',
                    'database_file' => $this->data_path . "/" . $language_prefix . ".sqlite"
                ];
                break;
        }

        $database = new Medoo($db_opts);

        return $database;
    }

    /**
     * Create data table.
     *
     * @return void
     */
    public function checkTables(): void
    {
        try {
            /** @var Medoo $pdo */
            $this->pdo = $this->connect();
            $this->pdo->select("per_route", "*");
        } catch (\PDOException $e) {
            if ($e->getCode() == "42S02" || $e ->getCode() == "HY000") {
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
    }

    /**
     * Scans page for links.
     *
     * @param Page $page
     * @return void
     */
    public function scanPage($page): void
    {
        $bla_config = $this->grav['config']['plugins']['broken-link-audit'];
        $inspection_level = $bla_config['inspection_level'];
        $valid_routes = $this->grav['pages']->routes();
        $this->clearLinks($page->route());

        if ($inspection_level == 'raw') {
            $content = $page->raw();
            // Get all links on page.
            $links = $this->findRawPageLinks($content);

            // Find bad links.
            $bad_links = $this->findInvalidLinks($links, $page->route());

            // Save bad links to db.
            $this->saveInvalidLinks($page->route(), $bad_links);
        } elseif ($inspection_level == 'rendered') {
            // todo: find rendered content of a page.
        }
    }

    /**
     * Removes all links from db for $route.
     *
     * @param string $route
     * @return void
     */
    public function clearLinks($route): void
    {
        $where = [
            "route[=]" => $route,
        ];
        $this->pdo->delete("per_route", $where);
    }

    /**
     * Writes out link data to database.
     *
     * @param array $routes
     * @return void
     */
    public function saveInvalidLinks($route, $links):void
    {
        if (empty($links)) {
            return;
        }

        $data[$route] = $links;

        $bla_config = $this->grav['config']['plugins']['broken-link-audit'];
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

            if (count($matches[2]) > 0) {
                $links[$type] = $matches[2];
            }
        }

        return $links;
    }


    private function findInvalidLinks($full_links, $route): array
    {
        $bla_config = $this->grav['config']['plugins']['broken-link-audit'];
        $base_url = $bla_config['base_url'];
        $invalid_links = [];
        $valid_links = [];

      // Process each link type.
        foreach ($full_links as $type => $links) {
            if ("stream" === $type) {
                continue;
            }

          // Process each link.
            foreach ($links as $link) {
                if ('page_remote' === $type) {
                  $url = $link;
                } else if ('page_relative' === $type) {
                    $url = $base_url . '/' . $link;
                } else if ('page_absolute_relative' == $type) {
                    if (substr($link, 0, 1) == '/') {
                        $url = $base_url . $link;
                    } else {
                        $url = $base_url . '/' . $link;
                    }
                }

              // Curl setup.
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_NOBODY, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Avoid SSL verification issues

                $result = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);
                if ($httpCode < 200 || $httpCode >= 400) {
                    $invalid_links[$type][] = $link;
                } else {
                    $valid_links[$type][] = $link;
                }
            }
        }

        return $invalid_links;
    }

    private function rawInspectionPatterns(): array
    {
        # Raw has to be first.
        return array(
          #'raw'                       =>  '/\[(.*?)\]\(([^)]+)\)/i',
          'page_relative'             =>  '/\[(.*?)\]\((?!http|https|#|user|theme|plugin|\/)([^)]+)\)/i',
          'page_absolute_relative'    =>  '/\[(.*?)\]\((?!a-z|0-9|\.)(?!http|https|#|user|theme|plugin)([^)]+)\)/i',
          'page_remote'               =>  '/\[(.*?)\]\((https?:\/\/[^)]+)\)/i',
          'stream'                    =>  '/\[(.*?)\]\((?=user|theme|plugin)([^)]+)\)/i',
        );
    }
}
