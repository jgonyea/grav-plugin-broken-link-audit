<?php
namespace Grav\Plugin\BrokenLinkAudit;

use DateInterval;
use DateTime;
use Grav\Common\Grav;


class AuditLink {
    private $link;
    private $type;
    private $expiration;
    private $status;
    private $baseURL;


    public function __construct($link, $type, $baseURL) {
        $this->baseURL = $baseURL;
        $this->link = $link;
        $this->type = $type;
        $this->expiration = new DateTime('@0');
        $this->status = 0;
    }

    public function getLink() {
        return $this->link;
    }

    public function getType() {
        return $this->type;
    }

    public function getStatus($forced = false){
        if ($forced || $this->isExpired()) {
            switch ($this->type) {
                case "page_remote":
                    $url = $this->link;
                    break;
                case "page_relative":
                    $url = $this->baseURL . '/' . $this->getLink();
                    break;
                case "page_absolute_relative":
                    if (substr($this->getLink(), 0, 1) == '/') {
                        $url = $this->baseURL . $this->getLink();
                    } else {
                        $url = $this->baseURL . '/' . $this->getLink();
                    }
                    break;
                default:
                    $url = $this->getLink();
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
            curl_close($ch);
            $this->setStatus($httpCode);
            $expiration = new DateTime('now');
            $expiration->modify('+1 month');
            $this->setExpiration($expiration);
        }

        return $this->status;
    }

    public function getExpiration() {
        return $this->expiration;
    }

    public function setLink($link) {
        $this->link = $link;
    }

    public function setType($type) {
        $this->type = $type;
    }

    public function setStatus($status){
        $this->status = $status;
    }

    public function setExpiration($expiration) {
        $this->expiration = $expiration;
    }

    public function isExpired(){
        $now = new DateTime('now');

        if ($now > $this->expiration){
            return true;
        }
        return false;
    }

}
