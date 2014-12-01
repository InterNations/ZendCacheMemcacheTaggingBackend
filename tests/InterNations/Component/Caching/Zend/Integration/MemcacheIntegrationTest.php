<?php
namespace InterNations\Component\Caching\Tests\Zend\Integration;

use Memcache;
use InterNations\Component\Caching\Zend\MemcacheTaggingBackend;

class MemcacheIntegrationTest extends AbstractIntegrationTest
{
    public function setUp()
    {
        if (!class_exists('Memcache')) {
            $this->markTestSkipped('pecl/memcache not installed');
        }

        $this->memcache = new Memcache();
        $this->memcache->addServer(
            ZEND_CACHE_TAGGING_BACKEND_MEMCACHED1_HOST,
            (int) ZEND_CACHE_TAGGING_BACKEND_MEMCACHED1_PORT
        );
        $this->memcache->addServer(
            ZEND_CACHE_TAGGING_BACKEND_MEMCACHED2_HOST,
            (int) ZEND_CACHE_TAGGING_BACKEND_MEMCACHED2_PORT
        );
        $this->backend = new MemcacheTaggingBackend($this->memcache);
    }

    public function provideBackendsWithOnlyOneServer()
    {
        $backends = [];

        $memcache = new Memcache();
        $memcache->addServer(
            ZEND_CACHE_TAGGING_BACKEND_MEMCACHED1_HOST,
            (int) ZEND_CACHE_TAGGING_BACKEND_MEMCACHED1_PORT
        );
        $memcache->addServer(
            ZEND_CACHE_TAGGING_BACKEND_MEMCACHED2_HOST,
            (int) ZEND_CACHE_TAGGING_BACKEND_MEMCACHED_INVALID_PORT
        );
        $backends[] = [new MemcacheTaggingBackend($memcache)];

        $memcache = new Memcache();
        $memcache->addServer(
            ZEND_CACHE_TAGGING_BACKEND_MEMCACHED1_HOST,
            (int) ZEND_CACHE_TAGGING_BACKEND_MEMCACHED_INVALID_PORT
        );
        $memcache->addServer(
            ZEND_CACHE_TAGGING_BACKEND_MEMCACHED2_HOST,
            (int) ZEND_CACHE_TAGGING_BACKEND_MEMCACHED2_PORT
        );
        $backends[] = [new MemcacheTaggingBackend($memcache)];

        return $backends;
    }}
