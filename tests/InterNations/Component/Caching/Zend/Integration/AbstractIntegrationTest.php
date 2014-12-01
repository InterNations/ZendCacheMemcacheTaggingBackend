<?php
namespace InterNations\Component\Caching\Tests\Zend\Integration;

use InterNations\Component\Caching\Zend\LibmemcachedTaggingBackend;
use InterNations\Component\Caching\Zend\MemcacheTaggingBackend;
use InterNations\Component\Testing\AbstractTestCase;
use Memcache;
use Memcached;
use Zend_Cache as Cache;
use Symfony\Component\Process\Process;
use Zend_Cache_Backend_ExtendedInterface;

/**
 * @group integration
 * @large
 */
abstract class AbstractIntegrationTest extends AbstractTestCase
{
    /** @var MemcacheTaggingBackend|LibmemcachedTaggingBackend */
    protected $backend;

    /** @var Memcache|Memcached */
    protected $memcache;

    private static $servers = [];

    public static function setUpBeforeClass()
    {
        $command = 'exec memcached -p %d -l %s -u nobody';
        self::$servers[] = $server = new Process(
            sprintf(
                $command,
                ZEND_CACHE_TAGGING_BACKEND_MEMCACHED1_PORT,
                ZEND_CACHE_TAGGING_BACKEND_MEMCACHED1_HOST
            )
        );
        $server->start();

        self::$servers[] = $server = new Process(
            sprintf(
                $command,
                ZEND_CACHE_TAGGING_BACKEND_MEMCACHED2_PORT,
                ZEND_CACHE_TAGGING_BACKEND_MEMCACHED2_HOST
            )
        );
        $server->start();

        sleep(1);

        foreach (self::$servers as $server) {
            if ($errorOutput = $server->getErrorOutput()) {
                error_log('startup: ' . $errorOutput);
            }
        }
    }

    public static function tearDownAfterClass()
    {
        /** @var Process $server */
        foreach (self::$servers as $server) {
            if ($errorOutput = $server->getErrorOutput()) {
                error_log('stopping: ' . $errorOutput);
            }
            if ($server->isRunning()) {
                $server->stop(0);
            }
        }
    }

    /** @dataProvider repeat */
    public function testSimpleTaggingAndDeletion_1()
    {
        $this->assertTrue($this->backend->save('data', 'my_cache_id', ['tag1', 'tag2']));
        $this->assertSame('data', $this->backend->load('my_cache_id'));

        $this->assertRecord('data', '312d5b52f060ee2469f2a04fe7deb4c38762ce3170123a4908b42a1d16ab84ec--my_cache_id');
        $this->backend->clean(Cache::CLEANING_MODE_MATCHING_ANY_TAG, ['tag2']);
        $this->assertRecord('data', '312d5b52f060ee2469f2a04fe7deb4c38762ce3170123a4908b42a1d16ab84ec--my_cache_id');

        $this->assertFalse($this->backend->load('my_cache_id'));
    }

    /** @dataProvider repeat */
    public function testSimpleTaggingAndDeletion_2()
    {
        $this->assertTrue($this->backend->save('data', 'my_cache_id', ['tag1', 'tag2']));
        $this->assertSame('data', $this->backend->load('my_cache_id'));

        $this->assertRecord('data', '312d5b52f060ee2469f2a04fe7deb4c38762ce3170123a4908b42a1d16ab84ec--my_cache_id');
        $this->backend->clean(Cache::CLEANING_MODE_MATCHING_TAG, ['tag2']);
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

        if ($this->getName(false) !== 'testOneInstanceFails') {
            // wouldn't work with one server missing in the pool
            $this->memcache->flush();
        }
    }

    public static function repeat()
    {
        $args = [];
        for ($a = 0; $a < 100; ++$a) {
            $args[] = [];
        }

        return $args;
    }

    abstract public function provideBackendsWithOnlyOneServer();

    /**
     * @param Zend_Cache_Backend_ExtendedInterface $backend
     * @dataProvider provideBackendsWithOnlyOneServer
     * @group fails
     */
    public function testUseTagsWithOnlyOneInstance(Zend_Cache_Backend_ExtendedInterface $backend)
    {
        $this->assertTrue($backend->save('data_1', 'entry_123456_18', ['entry_18']));
        $this->assertSame('data_1', $backend->load('entry_123456_18'));

        $backend->clean(Cache::CLEANING_MODE_MATCHING_ANY_TAG, ['entry_18']);

        $this->assertFalse($backend->load('entry_123456_18'));
    }

    /**
     * @throws \Zend_Cache_Exception
     * @group fails
     */
    public function testOneInstanceFails()
    {
        $key = '0nag_entry_123456_18';
        $this->assertTrue($this->backend->save('data_1', $key, ['entry_18']));
        $this->assertSame('data_1', $this->backend->load($key));

        $this->backend->clean(Cache::CLEANING_MODE_MATCHING_ANY_TAG, ['entry_18']);

        self::$servers[1]->stop(0);
        sleep(1);

        $this->assertFalse($this->backend->load($key));
    }
}
