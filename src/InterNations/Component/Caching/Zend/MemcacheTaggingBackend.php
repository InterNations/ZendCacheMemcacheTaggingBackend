<?php
namespace InterNations\Component\Caching\Zend;

use Zend_Cache_Backend_Memcached as BaseMemcachedBackend;
use Memcache;

class MemcacheTaggingBackend extends BaseMemcachedBackend
{
    use MemcacheTaggingTrait;

    /** @param Memcache|array $options */
    public function __construct($options = [])
    {
        if ($options instanceof Memcache) {
            $this->_memcache = $options;
        } else {
            parent::__construct($options);
        }
    }

    /** @return Memcache */
    public function getMemcache()
    {
        return $this->_memcache;
    }

    public function loadTagRevisions(array $tags = [])
    {
        return $this->_memcache->get($tags);
    }
}
