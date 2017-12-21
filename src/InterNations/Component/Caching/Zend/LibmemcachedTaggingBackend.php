<?php
namespace InterNations\Component\Caching\Zend;

use Zend_Cache_Backend_Libmemcached as BaseLibmemcachedBackend;
use Memcached;

class LibmemcachedTaggingBackend extends BaseLibmemcachedBackend
{
    use MemcacheTaggingTrait;

    /** @param Memcached|array $options */
    public function __construct($options = [])
    {
        if ($options instanceof Memcached) {
            $this->_memcache = $options;
        } else {
            parent::__construct($options);
        }
    }

    public function getMemcache(): Memcached
    {
        return $this->_memcache;
    }

    public function loadTagRevisions(array $tags = [])
    {
        return $this->_memcache->getMulti($tags) ?: [];
    }
}
