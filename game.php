<?php 
use Workerman\Worker;
use BGameServer\Douniu\HttpHelper;

define("PROJECT_ROOT", __DIR__);

//platform适应器
if (PHP_OS == 'WINNT') {
    require PROJECT_ROOT . '/workerman-for-win-master/Autoloader.php';
} else {
    require PROJECT_ROOT . '/Workerman-master/Autoloader.php';
}
require PROJECT_ROOT . '/vendor/autoload.php';

$gameServer = BGameServer\Douniu\GameServer::getInstance();
$websocketWorker = new Worker('websocket://0.0.0.0:9501');
// 启动1个进程对外提供服务，使用非共享内存
$websocketWorker->count = 1;

//on-onWorkerStart 
$websocketWorker->onWorkerStart = function ($worker) use ($gameServer)
{
    $gameServer->startGlobalTimer();
    HttpHelper::getInstance()->init();
};

//on-connect
$websocketWorker->onConnect = function ($connection) use($gameServer) {
    $gameServer->onConnect($connection);
};

//on-message
$websocketWorker->onMessage = function($connection, $data) use($gameServer) {
    $gameServer->onMessage($connection, $data);
};

//on-close
$websocketWorker->onClose = function($connection) use($gameServer) {
    $gameServer->onClose($connection);
    BGameServer\Douniu\Room::gc($connection->id);
};

//on-error
$websocketWorker->onError = function($connection, $code, $msg)  use($gameServer) {
    $gameServer->onError($connection, $code, $msg);
};

Worker::runAll();