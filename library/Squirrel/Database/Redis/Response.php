<?php

namespace Squirrel\Database\Redis;

use Squirrel\Database\Redis\Exception\RedisException;
use Squirrel\Database\Redis\Exception\RedisCommunicationException;
use Squirrel\Database\Redis\Exception\RedisProtocolException;
use Squirrel\Database\Redis\Exception\RedisServerException;

/**
 * Redis response class able to parse server
 * response regarding of Redis protocol.
 * 
 * @package Squirrel\Database\Redis
 * @author Valérian Galliat
 */
class Response
{
    /**
     * @var resource
     */
    protected $socket;

    /**
     * @var string
     */
    protected $buffer;

    /**
     * @var integer
     */
    protected $length;

    /**
     * @var integer
     */
    protected $pointer;

    /**
     * @param resource $socket
     */
    public function __construct($socket)
    {
        $this->socket = $socket;
        $this->buffer = '';
        $this->length = 0;
        $this->pointer = 0;
    }

    /**
     * Gets parsed response.
     *
     * @return string
     */
    public function __toString()
    {
        try {
            return $this->parse();
        } catch (RedisException $exception) {
            return '';
        }
    }

    /**
     * Parses server response.
     *
     * @throws RedisCommunicationException
     * @throws RedisProtocolException
     * @throws RedisServerException
     */
    public function parse()
    {
        $this->retrieve();

        if ($this->length === 0) {
            throw new RedisProtocolException('The response is empty.');
        }

        if ($this->length < 4) {
            throw new RedisProtocolException('The response is too short.');
        }

        $code = $this->consume();

        if ($code === '+' || $code === '-') {
            $payload = substr($this->buffer, 1, $this->length - 3);
        }

        switch ($code) {
            case '+':
                return $payload;
            case '-':
                throw new RedisServerException($payload);
            case ':':
                return $this->consumeInteger();
            case '$':
                return $this->consumeString();
            case '*':
                return $this->consumeArray();
            default:
                throw new RedisProtocolException('Illegal opcode in server response.');
        }
    }

    /**
     * @param integer $length Optional length to get.
     */
    protected function retrieve($length = null)
    {
        try {
            if ($length === null) {
                $this->buffer = fgets($this->socket);
            } else {
                $this->buffer = fgets($this->socket, $length + 1);
            }

            if ($this->buffer === false) {
                throw RedisException::getLastError();
            }
        } catch (\ErrorException $exception) {
            throw new RedisCommunicationException(
                'Error while reading the server socket.',
                RedisCommunicationException::READ, $exception
            );
        }

        $this->length = strlen($this->buffer);
        $this->pointer = 0;
    }

    /**
     * @param integer $length Optional length.
     * @return string
     */
    protected function consume($length = 1)
    {
        if ($this->pointer + $length <= $this->length) {
            $buffer = substr($this->buffer, $this->pointer, $length);
            $this->pointer += $length;
            return $buffer;
        }

        if ($length === 1) {
            $this->retrieve();
            return $this->consume();
        }

        $buffer = substr($this->buffer, $this->pointer);
        $remaining = $length - ($this->length - $this->pointer);

        do {
            $this->retrieve(min($remaining, 2048));
            $remaining -= $this->length;
            $buffer .= $this->buffer;
        } while ($remaining > 0);
        
        $this->pointer += $this->length;
        return $buffer;
    }

    /**
     * @throws RedisProtocolException
     * @return string
     */
    protected function consumeChunk()
    {
        $buffer = '';
        $char = $this->consume();

        while ($char !== "\r") {
            $buffer .= $char;
            $char = $this->consume();
        }

        if ($this->consume() !== "\n") {
            throw new RedisProtocolException(
                'Expected complete CRLF not found.'
            );
        }

        return $buffer;
    }

    /**
     * @throws RedisProtocolException
     * @return integer
     */
    protected function consumeInteger()
    {
        $chunk = $this->consumeChunk();

        if ((string) (integer) $chunk === $chunk) {
            return (integer) $chunk;
        }

        throw new RedisProtocolException(
            'Expected integer in server response is not a valid integer.'
        );
    }

    /**
     * @throws RedisProtocolException
     * @return string
     */
    protected function consumeString()
    {
        $length = $this->consumeInteger();

        if ($length === -1) {
            return null;
        }

        $string = $this->consume($length + 2);

        if (substr($string, -2) !== "\r\n") {
            throw new RedisProtocolException(
                'Expected CRLF after string response'
            );
        }

        return substr($string, 0, -2);
    }

    /**
     * @throws RedisProtocolException
     * @return array
     */
    protected function consumeArray()
    {
        $count = $this->consumeInteger();

        if ($count === -1) {
            return null;
        }

        $array = array();

        if ($count === 0) {
            return $array;
        }

        while (count($array) < $count) {
            $code = $this->consume();

            switch ($code) {
                case ':':
                    $array[] = $this->consumeInteger();
                    break;
                case '$':
                    $array[] = $this->consumeString();
                    break;
                default:
                    throw new RedisProtocolException('Illegal opcode in array.');
            }
        }

        return $array;
    }
}
