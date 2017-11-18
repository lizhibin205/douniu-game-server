<?php
namespace BGameServer\Douniu\Model;

use BGameServer\Douniu\Cache\SingleMemory;

class GameRoom
{
    /**
     * 游戏局数据
     * @var array
     */
    private $data = null;

    /**
     * 房间Id
     * @var number
     */
    private $roomId = null;

    /**
     * 创建房间
     * @param int $roomId
     * @return GameRoom
     */
    public static function create($roomId)
    {
        $flag = self::class . "_" . $roomId;
        $data = [
            'status' => 1,//房间状态，1创建，2准备阶段，3叫庄阶段，4开牌阶段
            'connection_ids' => [], //mid => connection_id
            'ready_status' => [], //mid => 1
            'zhuang_status' => [], //mid => 1
            'game' => null,
            'zhuang' => null,//谁是庄，值是mid
            'create_time' => time(),//房间创建时间
        ];

        //保存数据
        SingleMemory::save($flag, $data);
        return new self($roomId);
    }

    /**
     * 构造函数
     * @param int $roomId
     */
    public function __construct($roomId)
    {
        $this->roomId = $roomId;
        $flag = self::class . "_" . $this->roomId;
        $this->data = SingleMemory::load($flag);
        if (is_null($this->data)) {
            throw new \Exception("房间ID不存在", GameRoomError::ROOM_NOT_EXISTS);
        }
    }

    /**
     * 构析函数
     */
    public function __destruct()
    {
        $flag = self::class . "_" . $this->roomId;
        SingleMemory::save($flag, $this->data);
    }
}