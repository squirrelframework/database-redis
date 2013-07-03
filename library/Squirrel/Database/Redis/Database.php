<?php

namespace Squirrel\Database\Redis;

use Squirrel\Database\Redis\Exception\RedisException;
use Squirrel\Database\Redis\Exception\RedisCommunicationException;

/**
 * Redis database driver.
 *
 * @package Squirrel\Database\Redis
 * @author Valérian Galliat
 */
class Database
{
    /**
     * @var string
     */
    protected $host;

    /**
     * @var integer
     */
    protected $port;

    /**
     * @var resource
     */
    protected $socket;

    /**
     * Tries to open a connection to the Redis server
     * and throws an exception if failed.
     *
     * @throws RedisCommunicationException If the server is not accessible.
     * @param string $host Optional host, default is 127.0.0.1.
     * @param integer $port Optional port, default is 6379.
     */
    public function __construct($host = '127.0.0.1', $port = 6379)
    {
        $this->host = $host;
        $this->port = $port;

        try {
            $socket = fsockopen($this->host, $this->port);

            if ($socket === false) {
                throw RedisException::getLastError();
            }
        } catch (\ErrorException $exception) {
            throw new RedisCommunicationException(
                'Unable to connect to the Redis server.',
                RedisCommunicationException::OPEN, $exception
            );
        }

        $this->socket = $socket;
    }

    /**
     * Closes the connection to the Redis server.
     */
    public function __destruct()
    {
        if (isset($this->socket)) {
            fclose($this->socket);
        }
    }

    /**
     * Executes given arguments on Redis server.
     *
     * @throws RedisCommunicationException
     * @throws RedisProtocolException
     * @throws RedisServerException
     * @param string|string[] $argument First argument or array of arguments.
     * @param string $argument,... Optional other arguments if not array.
     * @return string
     */
    public function query($argument)
    {
        if (is_array($argument)) {
            $request = new Request($this->socket, $argument);
        } else {
            $request = new Request($this->socket, func_get_args(), func_num_args());
        }

        $request->execute();
        $response = new Response($this->socket);
        return $response->parse();
    }
}
