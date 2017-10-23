<?php
namespace BGameServer\Douniu;

use BGame\Douniu\Douniu;

class Room extends Command
{
    const MAX_ROOM_PLAYER = 2;

    private static $roomList = [];

    /**
     * 创建房间
     * @throws \Exception
     * @return unknown[]|string[]
     */
    public function create()
    {
        $roomId = $this->data['room_id'];
        $mid = $this->getMid();
        if (isset(self::$roomList[$roomId])) {
            throw new \Exception("room exists");
        }
        self::$roomList[$roomId] = [
            'status' => 0,//房间状态
            'connection_ids' => [], //mid => connection_id
            'ready_status' => [], //mid => 1
            'zhuang_status' => [], //mid => 1
            'game' => null,
            'zhuang' => null,//谁是庄，值是mid
            'create_time' => time(),//房间创建时间
        ];
        self::$roomList[$roomId]['connection_ids'][$mid] = $this->getConnectId();
        return $this->reply([
            'event' => 'create_room',
            'players' => array_keys(self::$roomList[$roomId]['connection_ids']),
            'ready_status' => array_keys(self::$roomList[$roomId]['ready_status']),
        ]);
    }

    /**
     * 进入房间
     * @throws \Exceptions
     * @return unknown[]|string[]
     */
    public function enter()
    {
        $roomId = $this->getRoomId();
        $mid = $this->getMid();
        $connectionId = $this->getConnectId();

        if (!isset(self::$roomList[$roomId]['connection_ids'][$mid])
            && count(self::$roomList[$roomId]['connection_ids']) >= self::MAX_ROOM_PLAYER) {
            throw new \Exception("room is full");
        }
        self::$roomList[$roomId]['connection_ids'][$mid] = $connectionId;
        return $this->reply([
            'event' => 'enter_room',
            'players' => array_keys(self::$roomList[$roomId]['connection_ids']),
            'ready_status' => array_keys(self::$roomList[$roomId]['ready_status']),
        ], self::$roomList[$roomId]['connection_ids']);
    }

    /**
     * 准备状态
     * @throws \Exceptions
     * @return unknown[]|string[]
     */
    public function ready()
    {
        $roomId = $this->getRoomId();
        $mid = $this->getMid();
        self::$roomList[$roomId]['ready_status'][$mid] = 1;

        if (count(self::$roomList[$roomId]['ready_status']) == self::MAX_ROOM_PLAYER) {
            //所有人准备，开局
            if (!is_null(self::$roomList[$roomId]['game'])) {
                throw new \Exception('game already start');
            }
            $game = new Douniu();
            $game->init(array_keys(self::$roomList[$roomId]['connection_ids']));
            self::$roomList[$roomId]['game'] = $game->getResult();
            //设置叫庄计时器
            GameServer::getInstance()->addZhuangHandle($roomId, new CallZhuangHandle(self::$roomList[$roomId]['connection_ids']));
            return $this->reply([
                'event' => 'game_start',
                'players' => array_keys(self::$roomList[$roomId]['connection_ids'])
            ], self::$roomList[$roomId]['connection_ids']);
        } else {
            return $this->reply([
                'event' => 'get_ready_status',
                'players' => array_keys(self::$roomList[$roomId]['connection_ids']),
                'ready_mid' => array_keys(self::$roomList[$roomId]['ready_status']),
            ], self::$roomList[$roomId]['connection_ids']);
        }
    }

    /**
     * 有人叫庄，通知所有玩家
     */
    public function call_zhuang()
    {
        $roomId = $this->getRoomId();
        $mid = $this->getMid();
        GameServer::getInstance()->removeZhuangHandle($roomId);
        self::$roomList[$roomId]['zhuang'] = $mid;

        return $this->reply([
            'event' => 'result_call_zhuang',
            'target_mid' => $mid,
        ], self::$roomList[$roomId]['connection_ids']);
    }

    /**
     * 查询牌局状态
     * 叫庄成功后后调用
     */
    public function status()
    {
        $roomId = $this->getRoomId();
        $mid = $this->getMid();
        if (is_null(self::$roomList[$roomId]['game'])) {
            throw new \Exception('game is not start');
        }
        if (is_null(self::$roomList[$roomId]['zhuang'])) {
            throw new \Exception("game is no zhuang");
        }
        //如果在游戏中，显示自己的牌
        $cardIds = self::$roomList[$roomId]['game']['players'][$mid]['cards'];
        return $this->reply([
            'event' => 'playing',
            'cards' => $cardIds,
        ]);
    }

    /**
     * 庄家开牌，发布游戏结果
     * 
     */
    public function open()
    {
        $roomId = $this->getRoomId();
        $mid = $this->getMid();
        if (is_null(self::$roomList[$roomId]['game'])) {
            throw new \Exception('game is not start');
        }
        if (is_null(self::$roomList[$roomId]['zhuang']) || self::$roomList[$roomId]['zhuang'] != $mid) {
            throw new \Exception("you can not open");
        }
        return $this->reply([
            'event' => 'game_result',
            'result' => self::$roomList[$roomId]['game']
        ], self::$roomList[$roomId]['connection_ids']);
    }

    /**
     * 聊天广播
     * @throws \Exception
     * @return unknown[]|string[]
     */
    public function chat()
    {
        $roomId = $this->getRoomId();
        $mid = $this->getMid();
        $chat = strval($this->data['chat']);

        return $this->reply([
            'event' => 'chat',
            'chat_mid' => $mid,
            'chat' => $chat,
        ], self::$roomList[$roomId]['connection_ids']);
    }

    /**
     * 获取roomId
     * @throws \Exception
     * @return unknown
     */
    private function getRoomId()
    {
        if (empty($this->data['room_id']) || !isset(self::$roomList[$this->data['room_id']])) {
            throw new \Exception("room not exists");
        }
        return $this->data['room_id'];
    }
}