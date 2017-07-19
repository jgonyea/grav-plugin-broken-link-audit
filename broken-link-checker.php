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
            $rel_links[$valid_routes_keys[$i]] = $this->findInvalidRelativeLinks(
                $content,
                $inspection_level,
                $valid_routes
            );
            $i++;
        }
        $this->saveInvalidLinks($rel_links);
    }


    /**
     * @param $content
     * @param $inspection_level
     * @param $valid_routes
     * @return array
     *   Array of links found within the content.
     */
    public function findInvalidRelativeLinks($content, $inspection_level, $valid_routes)
    {
        if ($inspection_level == 'raw') {
            $pattern = '/\(([^)]+)\)/';
            preg_match_all($pattern, $content, $links);
        } elseif ($inspection_level == 'rendered') {
            dump("FINDING LINKS IN $inspection_level");
        }

        if ($links) {
            foreach ($links[1] as $key => $link) {
                if (strlen($link) > 5 && substr($link, 0, 4) == 'http') {
                    // Don't return external links.
                    unset($links[1][$key]);
                }
                if (isset($valid_routes[$link])) {
                    // Don't return vaild links.
                    unset($links[1][$key]);
                }
            }
            return $links[1];
        }
        return null;
    }

    public function saveInvalidLinks($links)
    {
        $filename = DATA_DIR . 'broken-links-checker/links';
        $filename .= '.yaml';
        $file = File::instance($filename);

        foreach ($links as $route => $link) {
            if (!empty($link)) {
                if (file_exists($filename)) {
                    $data = Yaml::parse($file->content());
                    $data[$route]['broken_links']= $link;
                } else {
                    $data[$route] = array(
                        'broken_links' => $link,
                    );
                }
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
}
