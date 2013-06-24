<?php
namespace InterNations\Component\Caching\Zend;

use OutOfBoundsException;
use Zend_Cache as Cache;
use Zend_Cache_Backend_Libmemcached as BaseLibmemcachedBackend;
use Zend_Cache_Exception as CacheException;
use Memcached;

/**
 * @SuppressWarnings(PMD.TooManyMethods)
 */
class LibmemcachedTaggingBackend extends BaseLibmemcachedBackend
{
    const TAGS_ID_SUFFIX = '_tags';
    const TAG_ID_PREFIX = 'zct_';

    /**
     * @param Memcached|array $options
     */
    public function __construct($options = [])
    {
        if ($options instanceof Memcached) {
            $this->_memcache = $options;
        } else {
            parent::__construct($options);
        }
    }

    /**
     * @return Memcached
     */
    public function getMemcache()
    {
        return $this->_memcache;
    }

    public function save($data, $id, $tags = [], $specificLifetime = false)
    {
        if (!empty($tags)) {
            $tags = array_unique($tags);
            sort($tags);
            $this->saveTagsById($tags, $id, $specificLifetime);
            $id = $this->createTaggedId($id, $tags);
        }

        if (strlen($id) > 250) {
            throw new OutOfBoundsException(sprintf('Key "%s" is longer than 250 byte', $id));
        }

        return parent::save($data, $id, [], $specificLifetime);
    }

    public function saveTagsById($tags, $id, $specificLifetime = false)
    {
        $id .= self::TAGS_ID_SUFFIX;
        return parent::save($tags, $id, [], $specificLifetime);
    }

    /**
     * @param string $id
     * @param boolean $doNotTestCacheValidity
     * @return mixed
     * @SuppressWarnings(PMD.UnusedFormalParameter)
     */
    public function load($id, $doNotTestCacheValidity = false)
    {
        if ($tags = $this->loadTagsById($id)) {
            $id = $this->createTaggedId($id, $tags);
        }
        return parent::load($id);
    }

    public function loadTagsById($id)
    {
        $id .= self::TAGS_ID_SUFFIX;
        return parent::load($id);
    }

    public function loadTagRevisions(array $tags = [])
    {
        if (!empty($tags)) {
            return $this->_memcache->getMulti($tags);
        }
        return [];
    }

    public function remove($id)
    {
        parent::remove($id . self::TAGS_ID_SUFFIX);
        return parent::remove($id);
    }

    public function getMetadatas($id)
    {
        if ($metadata = parent::getMetadatas($id)) {
            if ($tags = $this->loadTagsById($id)) {
                $metadata['tags'] = $tags;
            }
        }
        return $metadata;
    }

    /**
     * Clean cache
     *
     * @param string $mode Cleaning mode
     * @param array $tags List of tags to work on
     * @throws CacheException Invalid clean mode
     * @return boolean
     */
    public function clean($mode = Cache::CLEANING_MODE_ALL, $tags = [])
    {
        $result = null;
        switch ($mode) {
            case Cache::CLEANING_MODE_ALL:
                $result = $this->_memcache->flush();
                break;

            case Cache::CLEANING_MODE_MATCHING_ANY_TAG:
            case Cache::CLEANING_MODE_MATCHING_TAG:
                foreach ($tags as $tagName) {
                    $tagId = $this->createTagId($tagName);
                    if ($this->_memcache->increment($tagId) === false) {
                        $this->_memcache->set($tagId, 1);
                    }
                }
                break;

            case Cache::CLEANING_MODE_OLD:
                $this->_log('CLEANING_MODE_OLD is unsupported by the Memcached backend');
                break;

            default:
                throw new CacheException('Invalid clean mode');
                break;
        }

        return $result;
    }

    protected function createTagId($tagName)
    {
        return self::TAG_ID_PREFIX . $tagName;
    }

    protected function createTagPrefix(array $tagIds)
    {
        $tagIds = array_map([$this, 'createTagId'], $tagIds);

        $tagValues = $this->loadTagRevisions($tagIds);
        $uninitializedTagIds = array_diff($tagIds, array_keys($tagValues));

        foreach ($uninitializedTagIds as $uninitializedTagId) {
            $this->_memcache->set($uninitializedTagId, 1);
            $tagValues[$uninitializedTagId] = 1;
        }

        ksort($tagValues);
        return hash('sha256', http_build_query($tagValues, null, '--')) . '--';
    }

    protected function createTaggedId($id, array $tags)
    {
        return $this->createTagPrefix($tags) . $id;
    }

    public function getCapabilities()
    {
        return [
            'automatic_cleaning' => false,
            'tags'               => true,
            'expired_read'       => false,
            'priority'           => false,
            'infinite_lifetime'  => false,
            'get_list'           => false
        ];
    }
}
