<?php
namespace BGameServer\Douniu;

class Protocol
{
    public static function getConnectMessage()
    {
        return json_encode([
            'code' => 200,
            'message' => 'connect successful',
            'data' => null
        ]);
    }

    public static function getErrorMessage($message)
    {
        return json_encode([
            'code' => 500,
            'message' => $message,
            'data' => null
        ]);
    }

    public static function getMessage($return)
    {
        return json_encode([
            'code' => 200,
            'message' => 'successful',
            'data' => $return
        ]);
    }

    public static function parseOnMessageCommand($data)
    {
        if (!isset($data['command'])) {
            throw new \Exception("command not exists!");
        }
        if (!isset($data['action'])) {
            throw new \Exception("action not exists!");
        }
        return [ucfirst($data['command']), $data['action']];
    }
}