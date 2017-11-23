<?php 
namespace BGameServer\Douniu;

use BGameServer\Douniu\Protocol;
use Workerman\Lib\Timer;

class GameServer
{
    private static $gameServer = null;
    private $connectionList = [];
    private $zhuangHandleList = [];
    private $globalHandleList = [];
    private function __construct(){}
    public static function getInstance()
    {
        if (is_null(self::$gameServer)) {
            self::$gameServer = new self();
        }
        return self::$gameServer;
    }

    public function onConnect($connection)
    {
        $this->connectionList[$connection->id] = $connection;
        $connection->send(Protocol::getConnectMessage());
    }

    public function onMessage($connection, $data)
    {
        $data = json_decode($data, true);
        try {
            list($command, $action) = Protocol::parseOnMessageCommand($data);
            list($return, $connectionIds) = $this->callCommand($connection, $command, $action, $data);
            if (is_null($connectionIds) || empty($connectionIds)) {
                $connectionIds = [$connection->id];
            }
            foreach ($connectionIds as $cid) {
                if (isset($this->connectionList[$cid])) {
                    $this->connectionList[$cid]->send(Protocol::getMessage($return));
                }
            }
        } catch (\Exception $ex) {
            $connection->send(Protocol::getErrorMessage($ex->getMessage()));
        }
    }

    public function onClose($connection)
    {
        unset($this->connectionList[$connection->id]);
    }

    public function onError($connection, $code, $msg)
    {
    }

    //全局游戏计时器
    public function startGlobalTimer()
    {
        Timer::add(1, [$this, 'doGlobalHandle']);
    }
    public function addGlobalHandle($handleKey, $handle)
    {
        $this->globalHandleList[$handleKey] = $handle;
    }
    public function removeGlobalHandle($handleKey)
    {
        unset($this->globalHandleList[$handleKey]);
    }
    public function doGlobalHandle()
    {
        foreach ($this->globalHandleList as $handleKey => $handle) {
            try {
                list($return, $connectionIds) = $handle->doResult($handleKey);
                if (is_null($return)) {
                    continue;
                }
            } catch (\Exception $ex) {
                $this->removeGlobalHandle($handleKey);
                continue;
            }
            foreach ($connectionIds as $cid) {
                if (isset($this->connectionList[$cid])) {
                    $this->connectionList[$cid]->send(Protocol::getMessage($return));
                }
            }
        }
    }
    //全局游戏计时器结束

    protected function callCommand($connection, $command, $action, $data)
    {
        $className = "BGameServer\Douniu\\{$command}";
        if (!class_exists($className)) {
            throw new \Exception("command[{$className}] not exists(2)!");
        }
        $commandObj = new $className($connection, $data);
        if (!method_exists($commandObj, $action)) {
            throw new \Exception("action[{$action}] not exists(2)!");
        }
        return $commandObj->$action();
    }
}