<?php

namespace Test;

use Zend\Cache\Backend as CacheBackend;

class RedisTest extends \PHPUnit_Framework_TestCase
{
    public function testInit()
    {
        $backend = new CacheBackend\Redis();
    }
}

