<?php
namespace InterNations\Component\Caching\Zend;

use Zend_Cache as Cache;
use OutOfBoundsException;
use Zend_Cache_Exception as CacheException;

trait MemcacheTaggingTrait
{
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

    public function load($id, $doNotTestCacheValidity = false)
    {
        if ($tags = $this->loadTagsById($id)) {
            $id = $this->createTaggedId($id, $tags);
        }

        return parent::load($id);
    }

    public function clean($mode = Cache::CLEANING_MODE_ALL, $tags = [])
    {
        $result = null;

        switch ($mode) {
            case Cache::CLEANING_MODE_ALL:
                $this->_memcache->flush();
                $result = true;
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

    public function getCapabilities()
    {
        return [
            'automatic_cleaning' => false,
            'tags'               => true,
            'expired_read'       => false,
            'priority'           => false,
            'infinite_lifetime'  => false,
            'get_list'           => false,
        ];
    }

    private function createTagId($tagName)
    {
        return 'zct_' . $tagName;
    }

    private function createTagsRelationId($id)
    {
        return $id . '_tags';
    }

    private function createTagPrefix(array $tagIds)
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

    private function createTaggedId($id, array $tags)
    {
        return $this->createTagPrefix($tags) . $id;
    }

    public function loadTagsById($id)
    {
        return parent::load($this->createTagsRelationId($id));
    }

    public function remove($id)
    {
        parent::remove($this->createTagsRelationId($id));

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

    private function saveTagsById($tags, $id, $specificLifetime = false)
    {
        return parent::save($tags, $this->createTagsRelationId($id), [], $specificLifetime);
    }

    /**
     * @param string[] $tags
     * @return array|bool
     */
    abstract protected function loadTagRevisions(array $tags = []);
}
