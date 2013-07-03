<?php

namespace Squirrel\Database\Redis\Exception;

/**
 * Communication exception with Redis server.
 * 
 * @package Squirrel\Database\Redis\Exception
 * @author Valérian Galliat
 */
class RedisCommunicationException extends RedisException
{
    const NONE = 0;
    const OPEN = 1;
    const WRITE = 2;
    const READ = 3;
}
