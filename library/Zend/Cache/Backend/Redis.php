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

    public function save($data, $id, $tags = array(), $specificLifetime = false)
    {
        //$lifetime = $this->getLifetime($specificLifetime);
        $lengthId = strlen($id);
        $lengthData = strlen($data);
        $result = $this->_call("*3\r\n\$3\r\nSET\r\n\$$lengthId\r\n$id\r\n\$$lengthData\r\n$data\r\n");

        if (count($tags) > 0) {
            $this->_log(self::TAGS_UNSUPPORTED_BY_SAVE_OF_REDIS_BACKEND);
        }

        return $result;
    }

    public function remove($id)
    {
        return $this->_call("DEL $id\r\n");
    }

    public function test($id)
    {
        return (bool)$this->load($id);
    }

    public function load($id, $doNotTestCacheValidity = false)
    {
        $data = $this->_call("GET $name\r\n");
        var_dump($data);
        return $data;
    }

    public function clean($mode = \Zend\Cache\Cache::CLEANING_MODE_ALL, $tags = array())
    {
        switch ($mode) {
            case \Zend\Cache\Cache::CLEANING_MODE_ALL:
                //return $this->_write("FLUSHALL\r\n");
                return true;
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


    public function ___expire()
    {}

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
        //$this->_connect();
        $this->_write($request);
        return $this->_parse();
    }

    protected function _write($data)
    {
        //$this->_connect();
        if (!fwrite($this->_connection, $data)) throw new Exception('Writing data is failed');
    }

    protected function _read($length)
    {
        //$this->_connect();
        $buffer = fread($this->_connection, $length);
        return $buffer;
    }

    protected function _readLine($length=100)
    {
        $read  = array($this->_connection);
        $write = null;
        $except = null;
        stream_select($read, $write, $except, 0);
        return $buffer = stream_get_line($this->_connection, 100, "\r\n");
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
                var_dump($buffer);
                $count = (int)substr($buffer, 1);
                $buffer = fread($this->_connection, $count+2);
                return unserialize($buffer);
            case ':':
                return substr($buffer, 1);
            case '*':
                throw new \Exception('* - is unknow result');
            default:
                throw new \Exception('Unknow result "' . $buffer . '"');
        }
    }
}

