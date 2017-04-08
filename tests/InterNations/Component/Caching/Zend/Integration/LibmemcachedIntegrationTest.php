<?php
namespace InterNations\Component\Caching\Tests\Zend\Integration;

use Memcached;
use InterNations\Component\Caching\Zend\LibmemcachedTaggingBackend;

/** @group integration */
class LibmemcachedIntegrationTest extends AbstractIntegrationTest
{
    const STORED_TAGS = ['zct_tag1', 'zct_tag2', 'zct_tag3'];
    const MEMCACHED_CONNECTION_FAILURE = 3;

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

    public function testReturnEmptyArrayOnConnectionFailure(): void
    {
        $this->backend->save('FooBar', 'id1122', ['tag1', 'tag2', 'tag3']);

        $result = $this->backend->loadTagRevisions(static::STORED_TAGS);
        $this->assertEquals(Memcached::RES_SUCCESS, $this->backend->getMemcache()->getResultCode());
        $this->assertInternalType('array', $result);

        static::stopAllServers();

        $resultAfterConnectionFailure = $this->backend->loadTagRevisions(static::STORED_TAGS);

        $this->assertEquals(static::MEMCACHED_CONNECTION_FAILURE, $this->backend->getMemcache()->getResultCode());
        $this->assertEquals([], $resultAfterConnectionFailure);

        //restart servers for other tests
        static::startAllServers();
    }

}
