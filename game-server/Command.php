<?php
namespace BGameServer\Douniu;

class Command
{
    protected $connection = null;
    protected $data = null;
    public function __construct($connection, $data)
    {
        $this->connection = $connection;
        $this->data = $data;
    }
    public function getConnectId()
    {
        return $this->connection->id;
    }
    public function getMid()
    {
        if (!isset($this->data['mid'])) {
            throw new \Exception('no mid param!');
        }
        return $this->data['mid'];
    }
    public function reply($return, $connectionIds = null)
    {
        return [$return, $connectionIds];
    }
}