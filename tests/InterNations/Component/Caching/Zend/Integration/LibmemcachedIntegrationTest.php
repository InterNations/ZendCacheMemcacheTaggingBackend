<?php
namespace InterNations\Component\Caching\Tests\Zend\Integration;

use Memcached;
use InterNations\Component\Caching\Zend\LibmemcachedTaggingBackend;

class LibmemcachedIntegrationTest extends AbstractIntegrationTest
{
    public function setUp()
    {
        if (!class_exists('Memcached')) {
            $this->markTestSkipped('pecl/memcached not installed');
        }

        $this->memcache = new Memcached();
        $this->memcache->addServer(
            ZEND_CACHE_TAGGING_BACKEND_MEMCACHED1_HOST,
            (int) ZEND_CACHE_TAGGING_BACKEND_MEMCACHED1_PORT
        );
        $this->memcache->addServer(
            ZEND_CACHE_TAGGING_BACKEND_MEMCACHED2_HOST,
            (int) ZEND_CACHE_TAGGING_BACKEND_MEMCACHED2_PORT
        );
        $this->backend = new LibmemcachedTaggingBackend($this->memcache);
    }

    public function provideBackendsWithOnlyOneServer()
    {
        $backends = [];

        $memcache = new Memcached();
        $memcache->addServers(
            [
                [ZEND_CACHE_TAGGING_BACKEND_MEMCACHED1_HOST, (int) ZEND_CACHE_TAGGING_BACKEND_MEMCACHED1_PORT],
                [ZEND_CACHE_TAGGING_BACKEND_MEMCACHED2_HOST, (int) ZEND_CACHE_TAGGING_BACKEND_MEMCACHED_INVALID_PORT],
            ]
        );
        $backends[] = [new LibmemcachedTaggingBackend($memcache)];

        $memcache = new Memcached();
        $memcache->addServers(
            [
                [ZEND_CACHE_TAGGING_BACKEND_MEMCACHED1_HOST, (int) ZEND_CACHE_TAGGING_BACKEND_MEMCACHED_INVALID_PORT],
                [ZEND_CACHE_TAGGING_BACKEND_MEMCACHED2_HOST, (int) ZEND_CACHE_TAGGING_BACKEND_MEMCACHED2_PORT],
            ]
        );
        $backends[] = [new LibmemcachedTaggingBackend($memcache)];

        return $backends;
    }
}
