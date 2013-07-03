<?php

namespace Squirrel\Database\Redis;

/**
 * Wrapper for a single Redis request
 * implementing Redis protocol for messages.
 * 
 * @package Squirrel\Database\Redis
 * @author Valérian Galliat
 */
class Request
{
    /**
     * @var resource
     */
    protected $socket;

    /**
     * @var string[]
     */
    protected $arguments;

    /**
     * @var integer
     */
    protected $count;

    /**
     * @param resource $socket
     * @param string[] $arguments Arguments to pass to Redis server.
     * @param integer $count Optional array count.
     */
    public function __construct($socket, array $arguments, $count = null)
    {
        $this->socket = $socket;
        $this->arguments = $arguments;

        if ($count !== null) {
            $this->count = $count;
        } else {
            $this->count = count($this->arguments);
        }
    }

    /**
     * Executes request arguments in socket.
     */
    public function execute()
    {
        $buffer = $this->compile();
        
        try {
            $result = fwrite($this->socket, $buffer, strlen($buffer));

            if ($result === false) {
                throw RedisException::getLastError();
            }
        } catch (\ErrorException $exception) {
            throw new RedisCommunicationException(
                'Error while writing to the server socket.',
                RedisCommunicationException::WRITE, $exception
            );
        }
    }

    /**
     * Gets a binary string representation of request
     * regarding of Redis protocol.
     *
     * @return string
     */
    protected function compile()
    {
        $request = '*' . $this->count . "\r\n";

        for ($i = 0; $i < $this->count; $i++) {
            $request .= '$' . strlen($this->arguments[$i]) . "\r\n";
            $request .= $this->arguments[$i] . "\r\n";
        }

        return $request;
    }
}
