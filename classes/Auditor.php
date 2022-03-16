<?php

namespace Grav\Plugin\BrokenLinkAudit;

use Grav\Common\Grav;
use Grav\Plugin\BrokenLinkAudit\Recorder;
use Medoo\Medoo;

class Auditor
{
    public $pdo;
    private $data_path;

    function __construct($options = [])
    {
        if (!isset($this->pdo)) {
            $this->pdo = $this->connect();
        }
    }


    public function count_routes():int
    {
        $query = 'select count (DISTINCT route) from "per_page"';

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
    private function connect() :Medoo{
        $grav = Grav::instance();
        $bla_config = $grav['config']['plugins']['broken-link-audit'];

        $db_opts = [];
        switch($bla_config['database']['type']){
            
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
                    'database_file' => $this->data_path . "/en.sqlite"
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
}
