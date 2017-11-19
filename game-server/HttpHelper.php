<?php
namespace BGameServer\Douniu;

use Workerman\Worker;

class HttpHelper
{
    private static $http = null;
    private $client;
    public static function getInstance()
    {
        if (is_null(self::$http)) {
            self::$http = new self();
        }
        return self::$http;
    }
    private function __construct(){}
    private function __clone(){}
    public function init()
    {
        $loop = Worker::getEventLoop();
        $this->client = new \React\HttpClient\Client($loop);
        return $this->client;
    }
    public function getClient()
    {
        return $this->client;
    }
}