<?php
namespace BGameServer\Douniu;

use BGame\Douniu\Douniu;

class Room extends Command
{
    const MIN_ROOM_PLAYER = 2;
    const MAX_ROOM_PLAYER = 9;
    const ROOM_TOKEN      = 'token123#';

    protected static $roomList = [];
    protected static $roomConnectMap = [];//connectId => roomId

    /**
     * __construct
     * @param unknown $connection
     * @param unknown $data
     */
    public function __construct($connection, $data)
    {
        parent::__construct($connection, $data);
        //验证token
        $mid   = $this->getMid();
        $roomId  = empty($this->data['room_id']) ? '' : $this->data['room_id'];
        $token = empty($this->data['token']) ? '' : $this->data['token'];
        if ($token != md5($mid . $roomId . self::ROOM_TOKEN)) {
            //throw new \Exception("mid validation failure");
        }
    }

    /**
     * 创建房间
     * done:ver2
     * @throws \Exception
     * @return unknown[]|string[]
     */
    public function create()
    {
        //redirect to enter
        $this->enter();
    }

    /**
     * 进入房间
     * done:ver2
     * @throws \Exceptions
     * @return unknown[]|string[]
     */
    public function enter()
    {
        $roomId = empty($this->data['room_id']) ? 0 : intval($this->data['room_id']);
        if ($roomId <= 0) {
            throw new \Exception("room id illegal format");
        }
        $mid = $this->getMid();
        $connectionId = $this->getConnectId();

        //如果房间不存在，则初始化它
        if (!isset(self::$roomList[$roomId])) {
            $this->createRoomData($roomId);
            GameServer::getInstance()->addGlobalHandle($roomId, new GlobalHandle($roomId));
        }

        //玩家加入房间
        self::$roomList[$roomId]['connection_ids'][$mid] = $connectionId;
        self::$roomList[$roomId]['players_info'][$mid] = [
            'name' => empty($this->data['name']) ? '' : $this->data['name'],
            'avatar' => empty($this->data['avatar']) ? '' : $this->data['avatar'],
        ];
        self::$roomList[$roomId]['last_update_time'] = time();

        return $this->reply(RoomBroadcast::broadcast($roomId, 'enter_room'), Room::getConnections($roomId));
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

        //玩家准备
        if (self::$roomList[$roomId]['status'] > 0) {
            //已经开始，不能准备
            throw new \Exception("game is already started!");
        }
        if (count(self::$roomList[$roomId]['ready_status']) >= self::MAX_ROOM_PLAYER) { 
            throw new \Exception("player is full!");
        }
        self::$roomList[$roomId]['ready_status'][$mid] = $this->getConnectId();
        self::$roomList[$roomId]['last_update_time'] = time();

        return $this->reply(RoomBroadcast::broadcast($roomId, 'get_ready_status'), self::$roomList[$roomId]['connection_ids']);
    }

    /**
     * 取消准备状态
     * done:ver1
     * @throws \Exceptions
     * @return unknown[]|string[]
     */
    public function no_ready()
    {
        $roomId = $this->getRoomId();
        $mid = $this->getMid();

        //玩家取消准备
        if (self::$roomList[$roomId]['status'] > 0) {
            //已经开始，不能取消
            throw new \Exception("game is already started!");
        }
        unset(self::$roomList[$roomId]['ready_status'][$mid]);
        self::$roomList[$roomId]['last_update_time'] = time();

        return $this->reply(RoomBroadcast::broadcast($roomId, 'get_ready_status'), self::$roomList[$roomId]['connection_ids']);
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

        if (is_null(self::$roomList[$roomId]['game'])) {
            throw new \Exception("game is not start");
        }

        $gameResult = self::$roomList[$roomId]['game'];
        $preCards = array_slice($gameResult['players'][$mid]['cards'], 0, 4);
        return $this->reply([
            'event' => 'get_pre_card',
            'create_mid' => self::$roomList[$roomId]['create_mid'],
            'call_zhuang' => self::$roomList[$roomId]['zhuang_calling'],
            'game_status' => self::$roomList[$roomId]['status'],
            'players' => array_keys(self::$roomList[$roomId]['connection_ids']),
            'players_info' => self::$roomList[$roomId]['players_info'],
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

        if (self::$roomList[$roomId]['status'] != 10) {
            throw new \Exception("you can't call zhuang");
        }

        if (!in_array($mid, self::$roomList[$roomId]['zhuang_calling'])) {
            self::$roomList[$roomId]['zhuang_calling'][] = $mid;
        }

        return $this->reply(RoomBroadcast::broadcast($roomId, 'call_zhuang'), Room::getConnections($roomId));
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
            'game_status' => self::$roomList[$roomId]['status'],
            'players' => array_keys(self::$roomList[$roomId]['connection_ids']),
            'call_zhuang' => self::$roomList[$roomId]['zhuang_calling'],
            'zhuang_mid' => self::$roomList[$roomId]['zhuang'],
            'cards' => $cardIds,
            'players_info' => self::$roomList[$roomId]['players_info'],
            'ready_mid' => array_keys(self::$roomList[$roomId]['ready_status']),
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
        self::$roomList[$roomId]['last_update_time'] = time();
        //更新游戏阶段3
        self::$roomList[$roomId]['status'] = 3;
        //开牌通知结果API
        self::sendGameResultToApi($roomId);
        //开牌通知结果API
        return $this->reply([
            'event' => 'game_result',
            'game_status' => self::$roomList[$roomId]['status'],
            'create_mid' => self::$roomList[$roomId]['create_mid'],
            'result' => self::$roomList[$roomId]['game'],
            'players' => array_keys(self::$roomList[$roomId]['connection_ids']),
            'players_info' => self::$roomList[$roomId]['players_info'],
            'ready_mid' => array_keys(self::$roomList[$roomId]['ready_status']),
        ], self::$roomList[$roomId]['connection_ids']);
    }

    /**
     * 开始下一局
     * 
     */
    public function next()
    {
        $roomId = $this->getRoomId();
        $mid = $this->getMid();
        if (!isset(self::$roomList[$roomId])) {
            throw new \Exception("room exists!");
        }
        if (self::$roomList[$roomId]['status'] != 3) {
            throw new \Exception('game is not end');
        }
        //只有房主才能开始下局
        if ($mid != self::$roomList[$roomId]['create_mid']) {
            throw new \Exception("you can't do next");
        }
        //格式化数据
        self::$roomList[$roomId]['status'] = 0;
        self::$roomList[$roomId]['ready_status'] = [];
        self::$roomList[$roomId]['zhuang_status'] = [];
        self::$roomList[$roomId]['game'] = null;
        self::$roomList[$roomId]['zhuang'] = null;
        self::$roomList[$roomId]['zhuang_calling'] = [];
        self::$roomList[$roomId]['zhuang_calling'] = [];
        self::$roomList[$roomId]['last_update_time'] = time();

        return $this->reply([
            'event' => 'next',
            'create_mid' => self::$roomList[$roomId]['create_mid'],
            'game_status' => self::$roomList[$roomId]['status'],
            'players' => array_keys(self::$roomList[$roomId]['connection_ids']),
            'players_info' => self::$roomList[$roomId]['players_info'],
            'ready_mid' => array_keys(self::$roomList[$roomId]['ready_status']),
            'zhuang_mid' => self::$roomList[$roomId]['zhuang'],
            'call_zhuang' => self::$roomList[$roomId]['zhuang_calling'],
        ], self::$roomList[$roomId]['connection_ids']);
    }

    /**
     * 聊天广播
     * done:ver2
     * @throws \Exception
     * @return unknown[]|string[]
     */
    public function chat()
    {
        $roomId = $this->getRoomId();
        $mid = $this->getMid();
        $chat = empty($this->data['chat']) ? '' : $this->data['chat'];
        $type = empty($this->data['type']) ? '' : $this->data['type'];
        $name = empty($this->data['name']) ? '' : $this->data['name'];
        self::$roomList[$roomId]['last_update_time'] = time();

        return $this->reply([
            'event' => 'chat',
            'chat_mid' => $mid,
            'chat' => $chat,
            'type' => $type,
            'name' => $name,
            'players' => array_keys(self::$roomList[$roomId]['connection_ids']),
            'players_info' => self::$roomList[$roomId]['players_info'],
        ], Room::getConnections($roomId));
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
     * 初始化房间数据
     * @param unknown $roomId
     */
    private function createRoomData($roomId)
    {
        self::$roomList[$roomId] = [
            'status' => 0,//房间状态
            'connection_ids' => [], //mid => connection_id
            'players_info' => [], //mid => array

            'ready_status' => [], //mid => 1
            'zhuang_status' => [], //mid => 1
            'game' => null,//游戏卡牌数据
            'zhuang' => null,//谁是庄，值是mid
            'create_time' => time(),//房间创建时间，
            'zhuang_calling' => [],//叫庄玩家的mid列表
            'create_time' => time(),//房间创建时间
            'last_update_time' => time(),//房间最近操作时间
        ];
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
        //更新游戏状态2
        self::$roomList[$roomId]['status'] = 2;
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
     * 返回当前房间的socket连接
     * @param unknown $roomId
     * @return array|mixed
     */
    public static function getConnections($roomId)
    {
        if (!isset(self::$roomList[$roomId])) {
            return [];
        } else {
            return self::$roomList[$roomId]['connection_ids'];
        }
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
            //清空死房间
            if ($now - $room['last_update_time'] > 3600 * 6) {
                unset(self::$roomList[$roomKey]);
            }
        }
    }

    /**
     * 游戏结果发送给API
     * @param unknown $roomId
     */
    public static function sendGameResultToApi($roomId)
    {
        if (!isset(self::$roomList[$roomId])) {
            return ;
        }
        $game = self::$roomList[$roomId];

        $url = "http://testqipai1.tcpan.com/game.php";
        $client = HttpHelper::getInstance()->getClient();
        $data = [
            'game_result' => $game['game'],
            'zhuang'      => $game['zhuang']
        ];
        $postData = http_build_query($data);
        $request = $client->request("POST", $url, [
            'Content-Type' =>  'application/x-www-form-urlencoded',
            'Content-Length' => strlen($postData),
        ]);
        $request->on('response', function ($response) {
            $response->on('data', function ($chunk) {
                //echo $chunk;
            });
            $response->on('end', function() {
                //echo 'DONE';
            });
        });
        $request->on('error', function (\Exception $e) {
            //echo $e;
        });
        $request->end($postData);
    }

    /**
     * 计时器使用
     * @param unknown $roomId
     * @return NULL[]
     */
    public static function returnStatus($roomId)
    {
        $gameStatus = intval(self::$roomList[$roomId]['status']);
        switch ($gameStatus) {
            case 0:
                //准备游戏阶段，如果准备玩家大于等于MIN_ROOM_PLAYER，且在5s之后，自动进入下status=10
                if (count(self::$roomList[$roomId]['ready_status']) >= self::MIN_ROOM_PLAYER) {
                    if (!isset(self::$roomList[$roomId]['ready_start_time'])) {
                        self::$roomList[$roomId][ready_start_time] = time();
                    }
                    if (time() - self::$roomList[$roomId]['ready_start_time'] > 5) {
                        self::$roomList[$roomId]['status'] = 10;//开牌了
                        $game = new Douniu();
                        $game->init(array_keys(self::$roomList[$roomId]['ready_status']));
                        self::$roomList[$roomId]['game'] = $game->getResult();
                        self::$roomList[$roomId]['last_update_time'] = time();
                        return [RoomBroadcast::broadcast($roomId, 'game_start'), Room::getConnections($roomId)];
                    }
                } else {
                    return [null, null];
                }
                break;
            case 10:
                //叫鸡阶段
                if (!isset(self::$roomList[$roomId]['call_zhuang_start_time'])) {
                    self::$roomList[$roomId]['call_zhuang_start_time'] = time();
                }
                $passTime = time() - self::$roomList[$roomId]['call_zhuang_start_time'];
                if ($passTime <= 10) {
                    //叫庄到计时间
                    return [RoomBroadcast::broadcast($roomId, 'wait_call_zhuang', [
                        'timeout' => 10 - $passTime
                    ]), Room::getConnections($roomId)];
                } else {
                    //叫庄结束
                    
                }
                break;
            default:
                //return [RoomBroadcast::broadcast($roomId, 'clock_wait_start'), Room::getConnections($roomId)];
                return [null, null];
        }
    }
}