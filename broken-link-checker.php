<?php
namespace Grav\Plugin;

use Grav\Common\Page\Collection;
use Grav\Common\Page\Page;
use Grav\Common\Page\Types;
use Grav\Common\Plugin;
use Grav\Common\Themes;
use Grav\Common\Twig\Twig;
use Grav\Common\Utils;
use RocketTheme\Toolbox\Event\Event;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

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

        if (strpos($uri->path(), $this->config->get('plugins.admin.route') . '/' . $this->route) === false) {
            return;
        }
    }

  /**
   * Add navigation item to the admin plugin
   */
    public function onAdminMenu()
    {
        $this->grav['twig']->plugins_hooked_nav['PLUGIN_BLC.ADMIN.TITLE'] = ['route' => $this->route, 'icon' => 'fa-chain-broken'];
    }

  /**
   * Add plugin templates path
   */
    public function onTwigAdminTemplatePaths()
    {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/admin/templates';
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
        $links = array();

        $valid_routes = $this->grav['pages']->routes();
        $inspection_level = $this->config()['inspection_level'];

        // Debug line until rendered content is built.
        $inspection_level = 'raw';

        dump($valid_routes);


        // * Find list of all pages
            // * Load each page content
            // * Search for links
            // Check link validity
            // Build list of bad links

        $all_pages = $this->grav['pages']->all()->published();

        foreach ($all_pages as $page) {
            if ($inspection_level == 'raw') {
                $content = $page->raw();
            } elseif ($inspection_level == 'rendered') {
                // todo: find rendered content of a page.
                // Setting content to raw until this gets finished.
                $content = $page->raw();

            }
            $rel_links = $this->findRelativeLinks($content, $inspection_level);

        }
    }


    /**
     * @param $content
     * @return array
     *   Array of links found within the content.
     */
    public function findRelativeLinks($content, $inspection_level)
    {


        if ($inspection_level == 'raw') {
            $pattern = '/\(([^)]+)\)/';
            $stuff = preg_match_all($pattern, $content, $links);
        } elseif ($inspection_level == 'rendered') {
            dump("FINDING LINKS IN $inspection_level");
        }

        if($links){
            foreach($links[0] as $key => $link){
                //dump($link);
                if(strlen($link) > 5 && substr($link, 0, 5) == '(http'){
                    //dump($content);
                    unset($links[1][$key]);
                }
            }
        }



        return $links[1];
    }
}
