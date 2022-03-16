<?php
namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Plugin;
use Grav\Plugin\BrokenLinkAudit\Auditor;
use Pimple\Container;
use RocketTheme\Toolbox\File\File;
use Symfony\Component\Yaml\Yaml;

/**
 * Class BrokenLinkAuditPlugin
 * @package Grav\Plugin
 */
class BrokenLinkAuditPlugin extends Plugin
{
    protected $route = 'broken-links';
    protected $auditor;

  /**
   * @return array
   *
   * The getSubscribedEvents() gives the core a list of events
   *     that the plugin wants to listen to. The key of each
   *     array section is the event that the plugin listens to
   *     and the value (in the form of an array) contains the
   *     callable (or function) as well as the priority. The
   *     higher the number the higher the priority.
   */
    public static function getSubscribedEvents()
    {
        return [
            'onPluginsInitialized' => [
                ['autoload', 100000],
                ['onPluginsInitialized', 0]
            ],
        ];
    }


    /**
     * [onPluginsInitialized:100000] Composer autoload.
     *is
     * @return ClassLoader
     */
    public function autoload(): ClassLoader
    {
        return require __DIR__ . '/vendor/autoload.php';
    }

  /**
   * Initialize plugin.
   */
    public function onPluginsInitialized()
    {
        if ($this->isAdmin()) {
            $this->auditor = new Auditor();
            $this->enable([
                'onTwigTemplatePaths' => ['onTwigAdminTemplatePaths', 0],
                'onAdminMenu' => ['onAdminMenu', 0],
                'onPagesInitialized' => ['onPagesInitialized', 0],
            ]);
        }
        return;
    }

  /**
   * Add navigation item to the admin plugin
   */
    public function onAdminMenu()
    {
        // Set title of the admin page.

        $count = new Container([
            'updates' => 0,
            'count' => function () { return $this->auditor->count_routes(); }
        ]);

        $this->grav['twig']->plugins_hooked_nav['PLUGIN_BROKEN_LINK_AUDIT.ADMIN.TITLE'] = [
            'route' => $this->route,
            'icon' => 'fa-chain-broken',
            'authorize' => ['admin.pages', 'admin.super'],
            'badge' => $count,
        ];

    }

  /**
   * Add plugin templates path
   */
    public function onTwigAdminTemplatePaths()
    {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/admin/templates';
        
        // Reads invalid links from db.
        $this->grav['twig']->bla_links = $this->getInvalidLinks();
        
        // Offer Run Reports button if links returns empty.
        if (empty($this->grav['twig']->bla_links)){
            $this->grav['twig']->bla_links = [
                "Run Report" => []
            ];
        }
        $config = $this->config();
        $this->grav['twig']->bla_inspection = $config['inspection_level'];
    }

    /**
     *  Build search
     */
    public function onPagesInitialized()
    {
        if ($this->grav['page']->template() != 'broken-links') {
            return;
        }

        // Shouldn't use this if we're not in admin.
        $this->grav['admin']->enablePages();
        $pages = $this->grav['pages']->all();

        $valid_routes = $this->grav['pages']->routes();
        $valid_routes_keys = array_keys($valid_routes);
        $inspection_level = $this->config()['inspection_level'];

        // Iterator for matching full routes with what page we're on.
        $i = 0;
        $all_links = [];
        foreach ($pages as $key => $page) {
            if ($inspection_level == 'raw') {
                $content = $page->raw();
            } elseif ($inspection_level == 'rendered') {
                // todo: find rendered content of a page.
            }

            $all_links[$page->route()] = $this->findPageLinks(
                $content,
                $inspection_level
            );
            $i++;
        }

        $bad_links = $this->checkLinks($all_links, $inspection_level, $valid_routes);

        $this->saveInvalidLinks($bad_links);
    }

    /**
     * Function to force a reindex from your own plugins
     */
    public function onReIndex(): void
    {
        $this->auditor->reCreateIndex();
    }



    /**
     * @param $content
     * @param $inspection_level
     * @param $valid_routes
     * @return array
     *   Array of links found within the content.
     */
    public function findPageLinks($content, $inspection_level)
    {
        $links = null;
        if ($inspection_level == 'raw') {
            // Create list of matching URLS to patterns.
            foreach ($this->raw_inspection_patterns() as $type => $page_pattern) {
                preg_match_all($page_pattern, $content, $matches);

                if (count($matches[0]) > 0) {
                    $links[$type] = $matches[0];
                }
            }
        } elseif ($inspection_level == 'rendered') {
            $links = null;
        }
        return $links;
    }

    /**
     * Writes out link data to database.
     *
     * @param array $routes
     * @return void
     */
    public function saveInvalidLinks($routes):void
    {
        foreach ($routes as $route => $link_types) {
            if (!empty($link_types)) {
                $data[$route] = $link_types;

                foreach ($link_types as $type => $link_type){

                    foreach ($link_type as $link){
                        $link = preg_replace('/\s+/', '', $link);
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

                            // Check if link exists
                            $result = $this->auditor->pdo->has("per_route", $where);

                            // If link already exists, update the epoch.
                            if($result){
                                $this->auditor->pdo->update("per_route", $row_data , $where);
                            } else {
                                $this->auditor->pdo->insert("per_route", $row_data);
                            }

                    }
                }
            }
        }
    }

    /**
     * Returns links from database.
     *
     * @return void
     */
    public function getInvalidLinks():array
    {
        $results = $this->auditor->pdo->select("per_route", [
            "route",
            "link_type",
            "link",
            "last_found",
        ]);
        $data = [];
        foreach ($results as $row){
            $route = $row['route'];
            $link_type = $row['link_type'];
            $link = $row['link'];
            $last_found = $row['last_found'];
            
            if (!isset($data[$route])) {
                $data[$route] = [];
                $data[$route][$link_type] = [];
            }
            if (!isset($data[$route][$link_type])) {
                $data[$route][$link_type] = [];
            }
            $data[$route][$link_type][] = $link;
        }

        return $data;
    }

    public function checkLinks($links, $inspection_level, $valid_routes)
    {
        // todo: make this a direct call from find links rather than having to reparse the whole thing again.
        $bad_links = array();
        foreach ($links as $path => $page) {
            if ($inspection_level == 'raw') {
                foreach ($this->raw_inspection_patterns() as $type => $pattern) {
                    if (isset($page[ $type ])) {
                        foreach ($page[ $type ] as $key => $link) {
                            switch ($type) {
                                case 'page_relative':
                                    $bad_links[$path][$type][$key] = $link;
                                    break;
                                case 'page_absolute':
                                    $bad_links[$path][$type][$key] = $link;
                                    break;
                                case 'page_remote':
                                    // Don't return remote links.
                                    $bad_links[$path][$type][$key] = $link;
                                    break;
                                case 'combined':
                                    $bad_links[$path][$type][$key] = $link;
                                    break;
                                case 'media_relative':
                                    $bad_links[$path][$type][$key] = $link;
                                    break;
                                case 'media_absolute':
                                    $bad_links[$path][$type][$key] = $link;
                                    break;
                                case 'media_remote':
                                    // Don't return remote links.
                                    //$bad_links[$path][$type][$key] = $link;
                                    break;
                                default:
                            }
                        }
                    }
                }
            }
        }

        return $bad_links;
    }

    private function raw_inspection_patterns()
    {
        return array(
        'page_relative'     =>  '/[^!]\[[^!].*\]\((?!http)[^\/].*\)/',
        'page_absolute'     =>  '/[^!]\[[^!].*\]\(\/.*\)/',
        'page_remote'       =>  '/[^!]\[[^!].*\]\(http.*\)/',

        'combined'          =>  '/\[!\[.*\]\(.*\)\]\(.*\)/',

        'media_relative'    =>  '/\![[^!].*\]\((?!http)(?!user)(?!theme)(?!plugin)[^\/].*\)/',
        'media_absolute'    =>  '/[^\[]!\[[^!].*\]\(\/.*\)/',
        'media_remote'      =>  '/[^\[]!\[[^!].*\]\(http.*\)/',
        );
    }
}
