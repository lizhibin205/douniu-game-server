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
            'multiple' => $data['multiple'],
            'call_zhuang' => $data['zhuang_calling'],
            'zhuang_calling_multiple' => $data['zhuang_calling_multiple'],
            'not_zhuang_calling_multiple' => $data['not_zhuang_calling_multiple'],
        ], $otherMessage);
    }
}