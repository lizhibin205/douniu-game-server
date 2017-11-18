<?php
namespace BGameServer\Douniu;

use BGame\Douniu\Douniu;

class Room extends Command
{
    const MIN_ROOM_PLAYER = 2;
    const MAX_ROOM_PLAYER = 9;

    private static $roomList = [];

    /**
     * 创建房间
     * done:ver1
     * @throws \Exception
     * @return unknown[]|string[]
     */
    public function create()
    {
        $roomId = $this->data['room_id'];
        $mid = $this->getMid();
        if (isset(self::$roomList[$roomId])) {
            throw new \Exception("room exists!");
        }
        self::$roomList[$roomId] = [
            'status' => 0,//房间状态
            'connection_ids' => [], //mid => connection_id
            'players_info' => [], //mid => array
            'ready_status' => [], //mid => 1
            'zhuang_status' => [], //mid => 1
            'game' => null,//游戏卡牌数据
            'zhuang' => null,//谁是庄，值是mid
            'create_time' => time(),//房间创建时间，
            'create_mid' => $mid,//创建房间的人
            'zhuang_calling' => [],//叫庄玩家的mid列表
            'create_time' => time(),//房间创建时间
            'max_time' => 0,//可玩的局数
            'current_time' => 1,//当期局数
        ];
        self::$roomList[$roomId]['connection_ids'][$mid] = $this->getConnectId();
        self::$roomList[$roomId]['players_info'][$mid] = [
            'name' => empty($this->data['name']) ? '' : $this->data['name'],
            'avatar' => empty($this->data['avatar']) ? '' : $this->data['avatar'],
        ];
        self::$roomList[$roomId]['max_time'] = empty($this->data['max_time']) ? 1 : intval($this->data['max_time']);
        return $this->reply([
            'event' => 'create_room',
            'create_mid' => self::$roomList[$roomId]['create_mid'],
            'game_status' => self::$roomList[$roomId]['status'],
            'players' => array_keys(self::$roomList[$roomId]['connection_ids']),
            'players_info' => self::$roomList[$roomId]['players_info'],
            'ready_mid' => array_keys(self::$roomList[$roomId]['ready_status']),
        ]);
    }

    /**
     * 进入房间
     * done:ver1
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
        self::$roomList[$roomId]['players_info'][$mid] = [
            'name' => empty($this->data['name']) ? '' : $this->data['name'],
            'avatar' => empty($this->data['avatar']) ? '' : $this->data['avatar'],
        ];
        return $this->reply([
            'event' => 'enter_room',
            'create_mid' => self::$roomList[$roomId]['create_mid'],
            'game_status' => self::$roomList[$roomId]['status'],
            'players' => array_keys(self::$roomList[$roomId]['connection_ids']),
            'players_info' => self::$roomList[$roomId]['players_info'],
            'ready_mid' => array_keys(self::$roomList[$roomId]['ready_status']),
        ], self::$roomList[$roomId]['connection_ids']);
    }

    /**
     * 准备状态
     * done:ver1
     * @throws \Exceptions
     * @return unknown[]|string[]
     */
    public function ready()
    {
        $roomId = $this->getRoomId();
        $mid = $this->getMid();

        if (!isset(self::$roomList[$roomId])) {
            throw new \Exception("room exists!");
        }

        self::$roomList[$roomId]['ready_status'][$mid] = 1;
        return $this->reply([
            'event' => 'get_ready_status',
            'create_mid' => self::$roomList[$roomId]['create_mid'],
            'game_status' => self::$roomList[$roomId]['status'],
            'players' => array_keys(self::$roomList[$roomId]['connection_ids']),
            'ready_mid' => array_keys(self::$roomList[$roomId]['ready_status']),
        ], self::$roomList[$roomId]['connection_ids']);
    }

    /**
     * 开始游戏（只有创建房间的人才可以）
     * doen:ver1
     * @throws \Exception
     * @return unknown[]|string[]
     */
    public function start()
    {
        $roomId = $this->getRoomId();
        $mid = $this->getMid();
        if (!isset(self::$roomList[$roomId])) {
            throw new \Exception('room not exists!');
        }
        $createMid = self::$roomList[$roomId]['create_mid'];
        if ($createMid != $mid) {
            throw new \Exception("you can't start game");
        }
        if (count(self::$roomList[$roomId]['connection_ids']) < self::MIN_ROOM_PLAYER) {
            throw new \Exception("player less than " . self::MIN_ROOM_PLAYER);
        }

        if (count(self::$roomList[$roomId]['ready_status']) == count(self::$roomList[$roomId]['connection_ids'])) {
            //所有人准备，开局
            if (!is_null(self::$roomList[$roomId]['game'])) {
                throw new \Exception('game already start');
            }
            $game = new Douniu();
            $game->init(array_keys(self::$roomList[$roomId]['connection_ids']));
            self::$roomList[$roomId]['status'] = 1;
            self::$roomList[$roomId]['game'] = $game->getResult();
            //设置叫庄计时器
            GameServer::getInstance()->addZhuangHandle($roomId, new CallZhuangHandle(self::$roomList[$roomId]['connection_ids']));
            //通知发牌
            return $this->reply([
                'event' => 'game_start',
                'create_mid' => self::$roomList[$roomId]['create_mid'],
                'game_status' => self::$roomList[$roomId]['status'],
                'players' => array_keys(self::$roomList[$roomId]['connection_ids']),
                'ready_mid' => array_keys(self::$roomList[$roomId]['ready_status']),
            ], self::$roomList[$roomId]['connection_ids']);
        } else {
            throw new \Exception("players isn't all ready");
        }
    }

    /**
     * 取前4张牌
     * @throws \Exception
     * @return unknown[]|string[]
     */
    public function get_pre_card()
    {
        $roomId = $this->getRoomId();
        $mid = $this->getMid();
        if (!isset(self::$roomList[$roomId])) {
            throw new \Exception('room not exists!');
        }
        if (is_null(self::$roomList[$roomId]['game'])) {
            throw new \Exception("game is not start");
        }

        $gameResult = self::$roomList[$roomId]['game'];
        $preCards = array_slice($gameResult['players'][$mid]['cards'], 0, 4);
        return $this->reply([
            'event' => 'get_pre_card',
            'create_mid' => self::$roomList[$roomId]['create_mid'],
            'game_status' => self::$roomList[$roomId]['status'],
            'players' => array_keys(self::$roomList[$roomId]['connection_ids']),
            'ready_mid' => array_keys(self::$roomList[$roomId]['ready_status']),
            'cards' => $preCards,
        ]);
    }

    /**
     * 有人叫庄，通知所有玩家
     * @throws \Exception
     * @return unknown[]|string[]
     */
    public function call_zhuang()
    {
        $roomId = $this->getRoomId();
        $mid = $this->getMid();
        if (!isset(self::$roomList[$roomId])) {
            throw new \Exception('room not exists!');
        }
        if (is_null(self::$roomList[$roomId]['game'])) {
            throw new \Exception("game is not start");
        }

        if (!in_array($mid, self::$roomList[$roomId]['zhuang_calling'])) {
            self::$roomList[$roomId]['zhuang_calling'][] = $mid;
        }

        return $this->reply([
            'event' => 'call_zhuang',
            'call_zhuang' => self::$roomList[$roomId]['zhuang_calling'],
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
     * done:ver1
     * @throws \Exception
     * @return unknown[]|string[]
     */
    public function chat()
    {
        $roomId = $this->getRoomId();
        $mid = $this->getMid();
        $chat = strval($this->data['chat']);

        if (isset(self::$roomList[$roomId])) {
            throw new \Exception("room exists!");
        }

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

    /**
     * 决定谁是庄
     * @param unknown $roomId
     * @return void;
     */
    public static function decideZhuang($roomId)
    {
        $room = self::$roomList[$roomId];
        if (!is_null($room['zhuang'])) {
            return $room['zhuang'];
        }
        //有人叫庄？
        if (count($room['zhuang_calling']) > 0) {
            $random = mt_rand(0, count($room['zhuang_calling']) - 1);
            self::$roomList[$roomId]['zhuang'] = $room['zhuang_calling'][$random];
            return self::$roomList[$roomId]['zhuang'];
        }
        //没惹叫庄？
        $players = array_keys($room['ready_status']);
        $random = mt_rand(0, count($players) - 1);
        self::$roomList[$roomId]['zhuang'] = $players[$random];
        return self::$roomList[$roomId]['zhuang'];
    }

    /**
     * 回收Room内存数据
     * return void
     */
    public static function gc()
    {
        $now = time();
        //回收6小时前创建的房间
        foreach (self::$roomList as $roomKey => $room) {
            if ($now - $room['create_time'] > 3600 * 6) {
                unset(self::$roomList[$roomKey]);
            }
        }
    }
}