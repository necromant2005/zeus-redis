<?php

namespace Test;

use Zend\Cache\Backend as CacheBackend;

class RedisTest extends \PHPUnit_Framework_TestCase
{
    protected $_instance;
    protected $_className = 'Zend\\Cache\\Backend\\Redis';
    protected $_root  = '/tmp/cache/';

    public function setUp()
    {
        $this->mkdir();

        if (!constant('TESTS_ZEND_CACHE_BACKEND_REDIS_ENABLED')) {
            $this->markTestSkipped('Zend_Cache redis adapter tests are not enabled');
        }
        $serverValid = array(
            'host' => TESTS_ZEND_CACHE_BACKEND_REDIS_HOST,
            'port' => TESTS_ZEND_CACHE_BACKEND_REDIS_PORT,
            'persistent' => TESTS_ZEND_CACHE_BACKEND_REDIS_PERSISTENT
        );
        $serverFail = array(
            'host' => 'not.exist',
            'port' => TESTS_ZEND_CACHE_BACKEND_REDIS_PORT,
            'persistent' => TESTS_ZEND_CACHE_BACKEND_REDIS_PERSISTENT
        );
        $options = array(
            'servers' => array($serverValid, $serverFail)
        );
        $this->_instance = new \Zend\Cache\Backend\Redis($options);
        $this->_instance->setDirectives(array('logging' => true));
        $this->_instance->save('bar : data to cache', 'bar');
        $this->_instance->save('bar2 : data to cache', 'bar2');
        $this->_instance->save('bar3 : data to cache', 'bar3');
    }

    public function mkdir()
    {
        @mkdir($this->getTmpDir());
    }

    public function rmdir()
    {
        $tmpDir = $this->getTmpDir(false);
        foreach (glob("$tmpDir*") as $dirname) {
            @rmdir($dirname);
        }
    }

    public function getTmpDir($date = true)
    {
        $suffix = '';
        if ($date) {
            $suffix = date('mdyHis');
        }
        if (is_writeable($this->_root)) {
            return $this->_root . DIRECTORY_SEPARATOR . 'zend_cache_tmp_dir_' . $suffix;
        } else {
            if (getenv('TMPDIR')){
                return getenv('TMPDIR') . DIRECTORY_SEPARATOR . 'zend_cache_tmp_dir_' . $suffix;
            } else {
                die("no writable tmpdir found");
            }
        }
    }

    public function tearDown()
    {
        if ($this->_instance) {
            $this->_instance->clean();
        }
        $this->rmdir();
        unset($this->_instance);
    }

    public function testConstructorCorrectCall()
    {
        $test = new \Zend\Cache\Backend\Redis();
    }

    public function testConstructorBadOption()
    {
        try {
            $class = $this->_className;
            $test = new $class(array(1 => 'bar'));
        } catch (\Exception $e) {
            return;
        }
        $this->fail('\Exception was expected but not thrown');
    }

    public function testSetDirectivesCorrectCall()
    {
        $this->_instance->setDirectives(array('lifetime' => 3600, 'logging' => true));
    }

    public function testSetDirectivesBadArgument()
    {
        try {
            $this->_instance->setDirectives('foo');
        } catch (\Exception $e) {
            return;
        }
        $this->fail('\Exception was expected but not thrown');
    }

    public function testSetDirectivesBadDirective()
    {
        // A bad directive (not known by a specific backend) is possible
        // => so no exception here
        $this->_instance->setDirectives(array('foo' => true, 'lifetime' => 3600));
    }

    public function testSetDirectivesBadDirective2()
    {
        try {
            $this->_instance->setDirectives(array('foo' => true, 12 => 3600));
        } catch (\Exception $e) {
            return;
        }
        $this->fail('\Exception was expected but not thrown');
    }

    public function testSaveCorrectCall()
    {
        $res = $this->_instance->save('data to cache', 'foo', array('tag1', 'tag2'));
        $this->assertTrue($res);
    }

    public function testSaveWithNullLifeTime()
    {
        $this->_instance->setDirectives(array('lifetime' => null));
        $res = $this->_instance->save('data to cache', 'foo', array('tag1', 'tag2'));
        $this->assertTrue($res);
    }

    public function testSaveWithSpecificLifeTime()
    {
        $this->_instance->setDirectives(array('lifetime' => 3600));
        $res = $this->_instance->save('data to cache', 'foo', array('tag1', 'tag2'), 10);
        $this->assertTrue($res);
    }

    public function testRemoveCorrectCall()
    {
        $this->assertTrue($this->_instance->remove('bar'));
        $this->assertFalse($this->_instance->test('bar'));
        $this->assertFalse($this->_instance->remove('barbar'));
        $this->assertFalse($this->_instance->test('barbar'));
    }

    public function testTestWithANonExistingCacheId()
    {
        $this->assertFalse($this->_instance->test('barbar_non_exists_key'));
    }

    public function testTestWithAnExistingCacheIdAndANullLifeTime()
    {
        $this->_instance->setDirectives(array('lifetime' => null));
        $res = $this->_instance->test('bar');
        if (!$res) {
            $this->fail('test() return false');
        }
        if (!($res > 999999)) {
            $this->fail('test() return an incorrect integer');
        }
        return;
    }

    public function testGetWithANonExistingCacheId()
    {
        $this->assertFalse($this->_instance->load('barbar'));
    }

    public function testGetWithAnExistingCacheId()
    {
        $this->assertEquals('bar : data to cache', $this->_instance->load('bar'));
    }

    public function testGetWithAnExistingCacheIdAndUTFCharacters()
    {
        $data = '"""""' . "'" . '\n' . 'ééééé';
        $this->_instance->save($data, 'foo');
        $this->assertEquals($data, $this->_instance->load('foo'));
    }

    public function testCleanModeAll()
    {
        $this->assertTrue($this->_instance->clean('all'));
        $this->assertFalse($this->_instance->test('bar'));
        $this->assertFalse($this->_instance->test('bar2'));
    }

    public function testCleanModeOld()
    {
        $this->_instance->___expire('bar2');
        $this->assertTrue($this->_instance->clean('old'));
        $this->assertTrue($this->_instance->test('bar') > 999999);
        $this->assertFalse($this->_instance->test('bar2'));
    }

}

