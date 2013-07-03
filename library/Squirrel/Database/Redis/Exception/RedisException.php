<?php

namespace Squirrel\Database\Redis\Exception;

/**
 * Redis exception class.
 *
 * @package Squirrel\Database\Redis\Exception
 * @author Valérian Galliat
 */
class RedisException extends \RuntimeException
{
    /**
     * Gets the last error as an error exception.
     * 
     * @return \ErrorException
     */
    public static function getLastError()
    {
        $error = error_get_last();
        
        return new \ErrorException(
            $error['message'],
            $error['type'],
            0,
            $error['file'],
            $error['line']
        );
    }
}
