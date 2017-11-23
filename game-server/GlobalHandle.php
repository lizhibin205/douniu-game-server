<?php
namespace BGameServer\Douniu;

class GlobalHandle
{
    private $roomId;
    public function __construct($roomId)
    {
        $this->roomId = $roomId;
    }
    public function doResult()
    {
        return Room::returnStatus($this->roomId);
    }
}