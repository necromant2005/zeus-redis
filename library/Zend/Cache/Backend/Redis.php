<?php
namespace Zend\Cache\Backend;

class Redis extends AbstractBackend
{
    /**
     * Default Values
     */
    const DEFAULT_HOST = '127.0.0.1';
    const DEFAULT_PORT =  6379;
    const DEFAULT_TIMEOUT = 1;

    const DELIMITER = "\r\n";

    /**
     * Log message
     */
    const TAGS_UNSUPPORTED_BY_CLEAN_OF_REDIS_BACKEND = 'Zend_Cache_Backend_Redis::clean() : tags are unsupported by the Redis backend';
    const TAGS_UNSUPPORTED_BY_SAVE_OF_REDIS_BACKEND =  'Zend_Cache_Backend_Redis::save() : tags are unsupported by the Redis backend';

    protected $_options = array(
        'host' => self::DEFAULT_HOST,
        'port' => self::DEFAULT_PORT,
        'timeout' => self::DEFAULT_TIMEOUT,
    );

    /**
     * Socket
     *
     * @var resource
     */
    protected $_connection = null;

    public function __construct(array $options = array())
    {
        parent::__construct($options);
        $this->_connect();
    }

    public function __destruct()
    {
        if (is_resource($this->_connection)) {
            fclose($this->_connection);
        }
    }

    public function save($data, $id, $tags = array(), $specificLifetime = false)
    {
        $lifetime = $this->getLifetime($specificLifetime);
        $lengthId = strlen($id);
        $lengthData = strlen($data);
        $result = $this->_call('*3' . self::DELIMITER
            . '$3' . self::DELIMITER . 'SET' . self::DELIMITER .
            '$' . $lengthId . self::DELIMITER . $id . self::DELIMITER .
            '$' . $lengthData . self::DELIMITER . $data . self::DELIMITER);
        if ($lifetime) {
            $this->_call('EXPIRE '. $id . ' ' . $lifetime . self::DELIMITER);
        }

        if (count($tags) > 0) {
            $this->_log(self::TAGS_UNSUPPORTED_BY_SAVE_OF_REDIS_BACKEND);
        }

        return $result;
    }

    public function remove($id)
    {
        return $this->_call('DEL ' . $id . self::DELIMITER);
    }

    public function test($id)
    {
        return $this->_call('EXISTS ' . $id . self::DELIMITER);
    }

    public function load($id, $doNotTestCacheValidity = false)
    {
        return $this->_call('GET '. $id . self::DELIMITER);
    }

    public function clean($mode = \Zend\Cache\Cache::CLEANING_MODE_ALL, $tags = array())
    {
        switch ($mode) {
            case \Zend\Cache\Cache::CLEANING_MODE_ALL:
                return $this->_call('FLUSHDB' . self::DELIMITER);
                break;
            case \Zend\Cache\Cache::CLEANING_MODE_OLD:
                $this->_log("Zend_Cache_Backend_Redis::clean() : CLEANING_MODE_OLD is unsupported by the Redis backend");
                break;
            case \Zend\Cache\Cache::CLEANING_MODE_MATCHING_TAG:
            case \Zend\Cache\Cache::CLEANING_MODE_NOT_MATCHING_TAG:
            case \Zend\Cache\Cache::CLEANING_MODE_MATCHING_ANY_TAG:
                $this->_log(self::TAGS_UNSUPPORTED_BY_CLEAN_OF_REDIS_BACKEND);
                break;
               default:
                throw new \Exception('Invalid mode for clean() method');
                   break;
        }
    }

    protected function _connect()
    {
        if ($this->_connection) return ;
        $this->_connection = stream_socket_client('tcp://' . $this->_options['host'] . ':' . $this->_options['port'],
            $errno, $errstr, $this->_options['timeout']);
        if (!is_resource($this->_connection)) {
            throw new \Exception($errstr, $errno);
        }
        stream_set_blocking($this->_connection, false);
        stream_set_timeout($this->_connection, 0, $this->_options['timeout']);
    }

    protected function _call($request)
    {
        $this->_write($request);
        return $this->_parse();
    }

    protected function _write($data)
    {
        if (!fwrite($this->_connection, $data)) throw new Exception('Writing data is failed');
        return true;
    }

    protected function _read($length)
    {
        $buffer = fread($this->_connection, $length);
        if (!$buffer) throw new \Exception('Can\'t read data from socket');
        return $buffer;
    }

    protected function _readLine($length=100)
    {
        $read  = array($this->_connection);
        $write = null;
        $except = null;
        stream_select($read, $write, $except, 0);
        return $buffer = stream_get_line($this->_connection, 100, self::DELIMITER);
    }

    public function _parse()
    {
        $buffer = $this->_readLine();
        switch ($buffer[0]) {
            case '-':
                return false;
            case '+':
                return true;
            case '$':
                $count = (int)substr($buffer, 1);
                return substr(fread($this->_connection, $count+2), 0, -2);
                break;
            case ':':
                return (int)substr($buffer, 1);
            case '*':
                throw new \Exception('* - is unknow result');
            case '':
                return true;
            default:
                throw new \Exception('Unknow result "' . $buffer . '"');
        }
    }

    public function ___expire()
    {}
}

