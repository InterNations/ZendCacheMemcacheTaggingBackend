<?php
namespace InterNations\Component\Caching\Tests\Zend\Unit;

use InterNations\Component\Testing\AbstractTestCase;
use InterNations\Component\Caching\Zend\LibmemcachedTaggingBackend;
use PHPUnit_Framework_Error_Notice as ErrorNoticeException;
use Zend_Cache;
use Memcached;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

class LibmemcachedTaggingBackendTest extends AbstractTestCase
{
    /** @var Memcached|MockObject */
    private $memcache;

    /** @var LibmemcachedTaggingBackend */
    private $backend;

    public function setUp(): void
    {
        try {
            $this->memcache = $this->createPartialMock('Memcached', ['set', 'get', 'add', 'increment', 'getMulti', 'delete', 'flush']);
        // Hack to fix mocking issue
        } catch (ErrorNoticeException $e) {
            $this->memcache = $this->createPartialMock('Memcached', ['set', 'get', 'add', 'increment', 'getMulti', 'delete', 'flush']);
        }
        $this->backend = new LibmemcachedTaggingBackend($this->memcache);
    }

    public function testGetMemcacheInstanceSameAsProvided()
    {
        $this->assertSame($this->memcache, $this->backend->getMemcache());
        $this->assertInstanceOf('Memcached', $this->memcache);
    }

    public function testSaveWithoutTagsCallsSetOnce()
    {
        $this->memcache
            ->expects($this->once())
            ->method('set')
            ->with('some_id')
            ->willReturn(true);
        $this->memcache
            ->expects($this->never())
            ->method('add');
        $this->memcache
            ->expects($this->never())
            ->method('get');

        $this->backend->save('some_data', 'some_id', []);
    }

    public function testLoadByIdLoadsTagsById()
    {
        $this->memcache
            ->expects($this->exactly(2))
            ->method('get')
            ->with($this->logicalOr(
                $this->equalTo('some_id_tags'),
                $this->equalTo('some_id')
            ));
        $this->memcache
            ->expects($this->never())
            ->method('set');
        $this->memcache
            ->expects($this->never())
            ->method('add');

        $this->backend->load('some_id');
    }

    public function testLoadTagsById()
    {
        $this->memcache
            ->expects($this->once())
            ->method('get')
            ->with('some_id_tags');
        $this->memcache
            ->expects($this->never())
            ->method('set');
        $this->memcache
            ->expects($this->never())
            ->method('add');

        $this->backend->loadTagsById('some_id');
    }

    public function testLoadTagRevisions()
    {
        $tags = [
            'TAG1' => 4,
            'TAG2' => 2,
            'TAG3' => 5,
        ];

        $this->memcache
            ->expects($this->once())
            ->method('getMulti')
            ->will($this->returnValue($tags));
        $this->memcache
            ->expects($this->never())
            ->method('set');
        $this->memcache
            ->expects($this->never())
            ->method('add');

        $this->assertSame($tags, $this->backend->loadTagRevisions(array_keys($tags)));
    }

    public function testRemoveAlsoRemovesTaggedId()
    {
        $this->memcache
            ->expects($this->exactly(2))
            ->method('delete')
            ->with($this->logicalOr(
                $this->equalTo('some_id_tags'),
                $this->equalTo('some_id')
            ));
        $this->memcache
            ->expects($this->never())
            ->method('set');
        $this->memcache
            ->expects($this->never())
            ->method('add');

        $this->backend->remove('some_id');
    }

    public function testGetMetadataAddsTags()
    {
        $metadata = [
            0 => 'some_data',
            1 => 32456,
            2 => 23457,
        ];
        $tags = ['TAG1', 'TAG2'];

        $this->memcache
            ->expects($this->exactly(2))
            ->method('get')
            ->with($this->logicalOr(
                $this->equalTo('some_id'),
                $this->equalTo('some_id_tags')
            ))
            ->will($this->onConsecutiveCalls($metadata, [$tags]));
        $this->memcache
            ->expects($this->never())
            ->method('set');
        $this->memcache
            ->expects($this->never())
            ->method('add');

        $returnedMetadata = $this->backend->getMetadatas('some_id');

        $this->assertTrue(isset($returnedMetadata['tags']));
        $this->assertSame($tags, $returnedMetadata['tags']);
    }

    public function testCleanCreatesNewTagRevisionOnNonexistentTag()
    {
        $tags = ['TAG1'];

        $this->memcache
            ->expects($this->once())
            ->method('increment')
            ->with('zct_TAG1')
            ->will($this->returnValue(false));
        $this->memcache
            ->expects($this->once())
            ->method('set')
            ->with('zct_TAG1' , '1');

        $this->backend->clean(Zend_Cache::CLEANING_MODE_MATCHING_ANY_TAG, $tags);
    }

    public function testCleanIncrementsExistingTagsRevision()
    {
        $tags = ['TAG1'];

        $this->memcache
            ->expects($this->once())
            ->method('increment')
            ->with('zct_TAG1')
            ->will($this->returnValue(true));
        $this->memcache
            ->expects($this->never())
            ->method('set');

        $this->backend->clean(Zend_Cache::CLEANING_MODE_MATCHING_ANY_TAG, $tags);
    }

    public function testCleaningModeAllFlushesMemcache()
    {
         $this->memcache
             ->expects($this->once())
             ->method('flush');

         $this->backend->clean(Zend_Cache::CLEANING_MODE_ALL);
    }

    public function testHasTaggingCapability()
    {
        $capabilities = $this->backend->getCapabilities();
        $this->assertTrue($capabilities['tags']);
    }

    public function testTagsAreUnifiedAndSorted()
    {
        $this->memcache
            ->expects($this->at(0))
            ->method('set')
            ->willReturnCallback(function ($key, array $data) {
                $this->assertSame('id_tags', $key);
                $this->assertEquals(['tag1', 'tag2'], $data[0]);
                $this->assertInternalType('integer', $data[1], 'Timestamp');
                $this->assertSame(3600, $data[2]);

                return true;
            });
        $this->memcache
            ->expects($this->at(1))
            ->method('getMulti')
            ->with(['zct_tag1', 'zct_tag2'])
            ->will($this->returnValue([]));
        $this->memcache
            ->expects($this->at(2))
            ->method('set')
            ->with('zct_tag1', 1)
            ->willReturn(true);
        $this->memcache
            ->expects($this->at(3))
            ->method('set')
            ->with('zct_tag2', 1)
            ->willReturn(true);
        $this->memcache
            ->expects($this->at(4))
            ->method('set')
            ->willReturnCallback(
                function ($key, array $data) {
                    $this->assertSame('312d5b52f060ee2469f2a04fe7deb4c38762ce3170123a4908b42a1d16ab84ec--id', $key);
                    $this->assertSame('data', $data[0]);
                    $this->assertInternalType('integer', $data[1]);
                    $this->assertSame(3600, $data[2]);

                    return true;
                }
            );

        $this->backend->save('data', 'id', array('tag2', 'tag2', 'tag1', 'tag2'));
    }
}
