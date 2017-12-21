<?php
namespace InterNations\Component\Caching\Zend;

use Zend_Cache_Backend_Libmemcached as BaseLibmemcachedBackend;
use Memcached;

class LibmemcachedBackend extends BaseLibmemcachedBackend
{
    /**
     * Constructor is overridden because the parent implementation does not support passing a fully configured
     * Memcached instance
     */
    public function __construct(Memcached $memcache)
    {
        $this->_memcache = $memcache;
    }

    public function getMemcache(): Memcached
    {
        return $this->_memcache;
    }
}
