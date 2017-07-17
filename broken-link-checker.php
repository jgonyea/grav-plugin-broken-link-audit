<?php
namespace Grav\Plugin;

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
   * Admin side initialization
   */
  public function initializeAdmin()
  {
    /** @var Uri $uri */
    $uri = $this->grav['uri'];

    $this->enable([
      'onTwigTemplatePaths' => ['onTwigAdminTemplatePaths', 0],
      'onAdminMenu' => ['onAdminMenu', 0],
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
    $this->grav['twig']->plugins_hooked_nav['PLUGIN_BLC.TITLE'] = ['route' => $this->route, 'icon' => 'fa-chain-broken'];
  }

  /**
   * Add plugin templates path
   */
  public function onTwigAdminTemplatePaths()
  {
    $this->grav['twig']->twig_paths[] = __DIR__ . '/admin/templates';
  }


}
