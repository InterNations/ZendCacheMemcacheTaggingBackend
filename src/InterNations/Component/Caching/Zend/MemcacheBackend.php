<?php
namespace InterNations\Component\Caching\Zend;

use Zend_Cache_Backend_Memcached as BaseMemcacheBackend;
use Memcache;

class MemcacheBackend extends BaseMemcacheBackend
{
    /**
     * Constructor is overridden because the parent implementation does not support passing a fully configured
     * Memcached instance
     */
    public function __construct(Memcache $memcache)
    {
        $this->_memcache = $memcache;
    }

    public function getMemcache(): Memcache
    {
        return $this->_memcache;
    }
}
