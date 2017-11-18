<?php
namespace BGameServer\Douniu;

class CallZhuangHandle
{
    const TIME_OUT = 60;
    private $midConnections = [];
    private $beginTime;
    public function __construct($midConnections)
    {
        $this->midConnections = $midConnections;
        $this->beginTime = time();
    }
    public function doResult($roomId)
    {
        $nowTime = time();
        $diffTime = $nowTime - $this->beginTime;
        //如果所有人都没有叫庄，则抛出异常
        if ($diffTime == self::TIME_OUT) {
            //决定庄
            $zhuangMid = Room::decideZhuang($roomId);
            return [$this->midConnections, [
                'event' => 'result_zhuang',
                'zhuang' => $zhuangMid
            ]];
        }
        if ($diffTime > self::TIME_OUT) {
            throw new \Exception("timeout");
        }

        $targetIndex = intval($diffTime / self::TIME_OUT);
        return [$this->midConnections, [
            'event' => 'wait_call_zhuang',
            'timeout' => self::TIME_OUT - ($diffTime % self::TIME_OUT)
        ]];
    }
}