<?php
namespace BGameServer\Douniu\Cache;

class SingleMemory
{
    /**
     * 进程数据池
     * @var array
     */
    private static $data = [];

    /**
     * 读取数据
     * @param string $flag
     */
    public static function load($flag)
    {
        return isset(self::$data[$flag]) ? self::$data[$flag] : null;
    }

    /**
     * 保存数据
     * @param string $flag
     */
    public static function save($flag, $data)
    {
        self::$data[$flag] = $data;
    }
}