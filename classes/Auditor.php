<?php
namespace Grav\Plugin\BrokenLinkAudit;

use DateTime;
use Grav\Common\Grav;
use Grav\Common\Page\Page;
use Grav\Plugin\BrokenLinkAudit\AuditLink;
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

    public function countBrokenLinks(): int
    {
        $where = [
            "last_status[><]" => [200, 399],
        ];
        $count = $this->pdo->count("links", $where);

        return $count;
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
                // TODO: Add MySQL/ MariaDB options here.
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
        $schemas["links"] = [
            "id" => [
                "INTEGER",
                "NOT NULL",
            ],
            "full_url" => [
                "TEXT",
                "NOT NULL",
                "UNIQUE"
            ],
            "last_status" => [
                "INTEGER",
                "NOT NULL",
                "DEFAULT 404"
            ],
            "link_type" => [
                "TEXT",
                "NOT NULL",
            ],
            "expiration" => [
                "NUMERIC",
                "NOT NULL",
                "DEFAULT 0",
            ],
            "PRIMARY KEY (<id> AUTOINCREMENT)",
        ];

        $schemas["per_route"] = [
            "page_route" => [
                "TEXT",
                "NOT NULL",
            ],
            "link_id" => [
                "NUMERIC",
                "NOT NULL"
            ]

        ];

        foreach ($schemas as $name => $schema) {
            $this->createTableIfMissing($name, $schema);
        }
    }

    /**
     * Generates a table if missing at the current PDO object.
     * @param string $name
     *   Database table name.
     * @param array $schema
     *   Database table schema.
     * @return void
     */
    private function createTableIfMissing($name, $schema)
    {
        try {
            /** @var Medoo $pdo */
            $this->pdo = $this->connect();
            $this->pdo->select($name, "*", ["LIMIT" => [0,1]]);
        } catch (\PDOException $e) {
            if ($e->getCode() == "42S02" || $e ->getCode() == "HY000") {
                $this->pdo->create($name, $schema);
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
        $all_valid_routes = $this->grav['pages']->routes();

        // TODO: fix this link clearing.
        // $this->clearLinks($page->route());

        if ($inspection_level == 'raw') {
            $content = $page->raw();
            // Get all links on page.
            $pageLinks = $this->findRawPageLinks($content);
            $auditLinks = [];

            // Create array of AuditLink objects for processing.
            foreach($pageLinks as $type => $links){
                foreach ($links as $link){
                    $auditLinks[] = new AuditLink($link, $type, $bla_config['base_url']);
                }
            }
        } elseif ($inspection_level == 'rendered') {
            // TODO: find rendered content of a page.
        }

        // Insert/ Update links in db.
        $this->saveLinks($page->route(), $auditLinks);
    }

    /**
     * Removes all links from db for $route.
     *
     * @param string $route
     * @return void
     */
    public function clearLinks($route): void
    {
        // TODO: Only clear expired links.

        $where = [
            "page_route[=]" => $route,
        ];

        // TODO: search for link id's at current route, then delete from tables.
        $results = $this->pdo->select("per_route", ['page_route','@link_id'], $where);

        if(!$results){
            return;
        }
        foreach ($results as $result){
            $this->pdo->delete(
                "per_route",
                [
                    "page_route" => $route,
                    "link_id" => $result['link_id']
                ]
            );

            $count = $this->pdo->select("per_route", ['page_route','@link_id'], ["link_id" => $result['link_id']]);
            if ($count){
            } else {
                $this->pdo->delete(
                    "links",
                    ["id" => $result['link_id']]
                );
            }
        }

    }

    /**
     * Writes out link data to database.
     *
     * @param array $route
     *   Current page route.
     * @param array AuditLink $links
     *   Array of links to save to db.
     * @return void
     */
    public function saveLinks($route, $links):void
    {
        if (empty($links)) {
            return;
        }

        $table1 = "per_route";
        $join = [
            "[<>]links (l)" => ["link_id" => "id"]
        ];
        $columns = ["link_id"];
        $where = [
            "page_route" => $route,
        ];
        $dbLinks = $this->pdo->select(
            $table1,
            $join,
            $columns,
            []
        );

        //TODO: if dbLinks returns results, cull away non-existant ones between $dbLinks and $links

        foreach ($links as $key => $link){
            // Check if link exists
            $full_url = $link->getLink();
            $results = $this->pdo->select(
                "links",
                [
                    'id',
                    'expiration',
                    'full_url',
                    'link_type',
                    'last_status'
                ],
                [ "full_url" => $full_url ]);

            if ($results) {
                // Update local link object.
                $expiration = new DateTime();
                $expiration->setTimestamp($results[0]['expiration']);
                $link->setExpiration($expiration);
                $id = $results[0]['id'];

                if ($link->isExpired()){
                    $this->pdo->update(
                        "links",
                        [
                            "full_url" => $full_url,
                            "last_status" => $link->getStatus(true),
                            "expiration" => $link->getExpiration()->format('U'),
                            "link_type" => $results[0]['link_type']

                        ],
                        ["id" => $id]
                    );
                } else {
                    $link->setStatus($results[0]['last_status']);
                }


            } else {
                // Write to links table.
                $status = $link->getStatus();
                $this->pdo->insert(
                    "links",
                    [
                        "full_url" => $link->getLink(),
                        "last_status" => $status,
                        "link_type" => $link->getType(),
                        "expiration" => $link->getExpiration()->format('U'),
                    ],
                );
                // Last insert row id.
                $id = $this->pdo->id();
            }

            // per_route table entries.
            $result = $this->pdo->select(
                "per_route",
                ["page_route", "link_id"],
                [
                    "page_route" => $route,
                    "link_id" => $id
                ]
            );
            if (!$result){
                // insert entry into table.
                $this->pdo->insert(
                    "per_route",
                    [
                        "page_route" => $route,
                        "link_id" => $id
                    ]
                );
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
