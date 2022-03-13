<?php
namespace Grav\Plugin\BrokenLinkAudit;

use Grav\Common\Grav;

class Auditor
{

    function __construct($options = [])
    {
        /** @var Config $config */
        $config = Grav::instance()['config'];

        /** @var UniformResourceLocator $locator */
        $locator = Grav::instance()['locator'];

        $search_type = $config->get('plugins.tntsearch.search_type', 'auto');
        $stemmer = $config->get('plugins.tntsearch.stemmer', 'default');
        $limit = $config->get('plugins.tntsearch.limit', 20);
        $snippet = $config->get('plugins.tntsearch.snippet', 300);
        
        $data_path = $locator->findResource('user://data', true) . '/broken-link-audit';

        /** @var Language $language */
        $language = Grav::instance()['language'];

        if ($language->enabled()) {
            $active = $language->getActive();
            $default = $language->getDefault();
            $this->language = $active ?: $default;
            $this->index =  $this->language . '.index';
        }

        if (!file_exists($data_path)) {
            mkdir($data_path);
        }
    }

    public function count_hits($options = [])
    {
        // todo: call sqlite db for hit count
        return 99;
    }

}
