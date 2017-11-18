<?php 
namespace BGameServer\Douniu;

use BGameServer\Douniu\Protocol;
use Workerman\Lib\Timer;

class GameServer
{
    private static $gameServer = null;
    private $connectionList = [];
    private $zhuangHandleList = [];
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

    //叫庄处理
    public function startZhuangTimer()
    {
        Timer::add(1, [$this, 'doZhuangHandle']);
    }
    public function addZhuangHandle($handleKey, $handle)
    {
        $this->zhuangHandleList[$handleKey] = $handle;
    }
    public function removeZhuangHandle($handleKey)
    {
        unset($this->zhuangHandleList[$handleKey]);
    }
    public function doZhuangHandle()
    {
        foreach ($this->zhuangHandleList as $handleKey => $handle) {
            try {
                list($connectionIds, $return) = $handle->doResult($handleKey);
            } catch (\Exception $ex) {
                $this->removeZhuangHandle($handleKey);
                continue;
            }
            foreach ($connectionIds as $cid) {
                if (isset($this->connectionList[$cid])) {
                    $this->connectionList[$cid]->send(Protocol::getMessage($return));
                }
            }
        }
    }
    //叫庄处理结束

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