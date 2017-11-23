<?php
namespace BGameServer\Douniu;

class RoomBroadcast extends Room
{
    public static function broadcast($roomId, $event, $otherMessage = [])
    {
        $data = self::$roomList[$roomId];
        return array_merge([
            'event' => $event,
            'game_status' => $data['status'],
            'players' => array_keys($data['connection_ids']),
            'players_info' => $data['players_info'],
            'ready_mid' => array_keys($data['ready_status']),
            'zhuang_mid' => $data['zhuang'],
            'call_zhuang' => $data['zhuang_calling'],
            'result' => $data['status'] == 20 ? $data['game'] : null,
        ], $otherMessage);
    }
}