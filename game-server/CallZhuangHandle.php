<?php
namespace BGameServer\Douniu;

class CallZhuangHandle
{
    const TIME_OUT = 20;
    private $midConnections = [];
    private $beginTime;
    public function __construct($midConnections)
    {
        $this->midConnections = $midConnections;
        $this->beginTime = time();
    }
    public function doResult()
    {
        $nowTime = time();
        $diffTime = $nowTime - $this->beginTime;
        //如果所有人都没有叫庄，则抛出异常
        if ($diffTime > count($this->midConnections) * self::TIME_OUT) {
            throw new \Exception("Call zhuang timeout");
        }

        $targetIndex = intval($diffTime / self::TIME_OUT);
        $slice = array_slice($this->midConnections, $targetIndex, 1);
        return [$this->midConnections, [
            'event' => 'wait_call_zhuang',
            'target_mid' => isset($slice[0]) ? $slice[0] : null,
            'timeout' => self::TIME_OUT - ($diffTime % self::TIME_OUT)
        ]];
    }
}