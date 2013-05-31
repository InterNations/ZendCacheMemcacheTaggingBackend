<?php
namespace InterNations\Component\Caching\Tests\Zend;

use InterNations\Component\Caching\Zend\MemcacheTaggingBackend;
use InterNations\Component\Testing\AbstractTestCase;
use Memcache;
use Zend_Cache as Cache;
use Symfony\Component\Process\Process;

/**
 * @group integration
 * @large
 */
class MemcacheIntegrationTest extends AbstractTestCase
{
    /**
     * @var MemcacheTaggingBackend
     */
    private $backend;

    /**
     * @var Memcache
     */
    private $memcache;

    private static $servers = [];

    public static function setUpBeforeClass()
    {
        $command = 'memcached -p %d -l %s';
        static::$servers[] = new Process(
            sprintf(
                $command, 
                ZEND_CACHE_TAGGING_BACKEND_MEMCACHED1_PORT,
                ZEND_CACHE_TAGGING_BACKEND_MEMCACHED1_HOST
            )
        );
        static::$servers[] = new Process(
            sprintf(
                $command, 
                ZEND_CACHE_TAGGING_BACKEND_MEMCACHED2_PORT,
                ZEND_CACHE_TAGGING_BACKEND_MEMCACHED2_HOST
            )
        );
        foreach (static::$servers as $server) {
            $server->start();
        }
        sleep(1);
    }

    public static function tearDownAfterClass()
    {
        foreach (static::$servers as $server) {
            error_log($server->getErrorOutput());
            $server->stop();
        }
    }

    public function setUp()
    {
        if (!class_exists('Memcache')) {
            $this->markTestSkipped('pecl/memcache not installed');
        }

        $this->memcache = new Memcache();
        $this->memcache->addServer(
            ZEND_CACHE_TAGGING_BACKEND_MEMCACHED1_HOST,
            ZEND_CACHE_TAGGING_BACKEND_MEMCACHED1_PORT
        );
        $this->memcache->addServer(
            ZEND_CACHE_TAGGING_BACKEND_MEMCACHED2_HOST,
            ZEND_CACHE_TAGGING_BACKEND_MEMCACHED2_PORT
        );
        $this->backend = new MemcacheTaggingBackend($this->memcache);
    }

    /** @dataProvider repeat */
    public function testSimpleTagging()
    {
        $this->assertTrue($this->backend->save('data', 'my_cache_id', ['tag1', 'tag2']));
        $this->assertSame('data', $this->backend->load('my_cache_id'));

        $this->assertRecord('data', '312d5b52f060ee2469f2a04fe7deb4c38762ce3170123a4908b42a1d16ab84ec--my_cache_id');
        $this->backend->clean(Cache::CLEANING_MODE_MATCHING_ANY_TAG, ['tag2']);
        $this->assertRecord('data', '312d5b52f060ee2469f2a04fe7deb4c38762ce3170123a4908b42a1d16ab84ec--my_cache_id');

        $this->assertFalse($this->backend->load('my_cache_id'));
    }

    /** @dataProvider repeat */
    public function testComplexTagging()
    {
        $this->assertTrue($this->backend->save('data_1', 'my_cache_id_1', ['tag1']));
        $this->assertTrue($this->backend->save('data_2', 'my_cache_id_2', ['tag1', 'tag2']));

        $this->assertSame('data_1', $this->backend->load('my_cache_id_1'));
        $this->assertSame('data_2', $this->backend->load('my_cache_id_2'));

        $this->backend->clean(Cache::CLEANING_MODE_MATCHING_ANY_TAG, ['tag1']);

        $this->assertFalse($this->backend->load('my_cache_id_1'));
        $this->assertFalse($this->backend->load('my_cache_id_2'));
    }

    /** @dataProvider repeat */
    public function testSpecifyingTagsMoreThanOnce()
    {
        $this->assertTrue($this->backend->save('data', 'my_cache_id', ['tag1', 'tag2', 'tag1', 'tag1']));
        $this->assertSame('data', $this->backend->load('my_cache_id'));

        $this->assertRecord('data', '312d5b52f060ee2469f2a04fe7deb4c38762ce3170123a4908b42a1d16ab84ec--my_cache_id');
        $this->backend->clean(Cache::CLEANING_MODE_MATCHING_ANY_TAG, ['tag2']);
        $this->assertRecord('data', '312d5b52f060ee2469f2a04fe7deb4c38762ce3170123a4908b42a1d16ab84ec--my_cache_id');

        $this->assertFalse($this->backend->load('my_cache_id'));
    }

    /** @dataProvider repeat */
    public function testOverlongKey()
    {
        $key = str_repeat('k', 185);

        $this->setExpectedException(
            'OutOfBoundsException',
            'Key "312d5b52f060ee2469f2a04fe7deb4c38762ce3170123a4908b42a1d16ab84ec--kkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkk" is longer than 250 byte'
        );
        $this->backend->save('overlong', $key, ['tag1', 'tag2']);
    }

    private function assertRecord($expected, $key)
    {
        $record = $this->memcache->get($key);
        $this->assertCount(3, $record);
        $this->assertSame($expected, $record[0]);
    }

    public function tearDown()
    {
    if (!$this->memcache) {
return;
    }
        $this->memcache->flush();
    }

    public static function repeat()
    {
        $args = [];
        for ($a = 0; $a < 100; ++$a) {
            $args[] = [];
        }

        return $args;
    }
}
