<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use RocketTheme\Toolbox\File\File;
use Symfony\Component\Yaml\Yaml;

/**
 * Class BrokenLinkCheckerPlugin
 * @package Grav\Plugin
 */
class BrokenLinkCheckerPlugin extends Plugin
{
    protected $route = 'broken-links';

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
        'onPluginsInitialized' => ['onPluginsInitialized', 0]
        ];
    }

  /**
   * Initialize plugin.
   */
    public function onPluginsInitialized()
    {
        if ($this->isAdmin()) {
            $this->initializeAdmin();
        }
        return;
    }

  /**
   * Admin side initialization.
   */
    public function initializeAdmin()
    {
        /** @var Uri $uri */
        $uri = $this->grav['uri'];

        $this->enable([
            'onTwigTemplatePaths' => ['onTwigAdminTemplatePaths', 0],
            'onAdminMenu' => ['onAdminMenu', 0],
            'onPagesInitialized' => ['onPagesInitialized', 0],
        ]);
    }

  /**
   * Add navigation item to the admin plugin
   */
    public function onAdminMenu()
    {
        // Set title of the admin page.
        $this->grav['twig']->plugins_hooked_nav['PLUGIN_BLC.ADMIN.TITLE'] = ['route' => $this->route, 'icon' => 'fa-chain-broken'];
    }

  /**
   * Add plugin templates path
   */
    public function onTwigAdminTemplatePaths()
    {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/admin/templates';
        $this->grav['twig']->blc_links = $this->getInvalidLinks();
        $config = $this->config();
        $this->grav['twig']->blc_inspection = $config['inspection_level'];
    }

  /**
   *  Build search
   */
    public function onPagesInitialized()
    {
        $page_slug = $this->grav['page']->slug();
        if ($page_slug != 'broken-links') {
            return;
        }

        $valid_routes = $this->grav['pages']->routes();
        $valid_routes_keys = array_keys($valid_routes);
        $inspection_level = $this->config()['inspection_level'];

        // Debug line forces raw inspection method until rendered content is built.
        $inspection_level = 'raw';

        $all_pages = $this->grav['pages']->all()->published();

        // Iterator for matching full routes with what page we're on.
        $i = 0;
        foreach ($all_pages as $key => $page) {
            if ($inspection_level == 'raw') {
                $content = $page->raw();
            } elseif ($inspection_level == 'rendered') {
                // todo: find rendered content of a page.
            }
            $all_links[$valid_routes_keys[$i]] = $this->findPageLinks(
                $content,
                $inspection_level
            );
            $i++;
        }
        $bad_links = $this->checkLinks($all_links, $inspection_level, $valid_routes);

        $this->saveInvalidLinks($bad_links);
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

    public function saveInvalidLinks($links)
    {
        $filename = DATA_DIR . 'broken-links-checker/links';
        $filename .= '.yaml';
        $file = File::instance($filename);
        // Reset report.
        if ($file->exists()) {
            $file->delete();
        }
        foreach ($links as $route => $link) {
            if (!empty($link)) {
                $data[$route] = $link;
                $file->save(Yaml::dump($data));
            }
        }
    }

    public function getInvalidLinks()
    {
        $data = array("Run Report" => []);
        $filename = DATA_DIR . 'broken-links-checker/links';
        $filename .= '.yaml';
        $file = File::instance($filename);
        if (file_exists($filename)) {
            $data = Yaml::parse($file->content());
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

        //return $bad_links;
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
