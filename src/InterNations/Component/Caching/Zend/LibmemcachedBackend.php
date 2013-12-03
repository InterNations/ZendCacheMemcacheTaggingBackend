<?php
namespace InterNations\Component\Caching\Zend;

use Zend_Cache_Backend_Libmemcached as BaseLibmemcachedBackend;
use Memcached;

/**
 * Had to be overwritten because the default doesn't support
 * providing a fully configured Memcached instance and fetching it
 */
class LibmemcachedBackend extends BaseLibmemcachedBackend
{
    /**
     * @param Memcached $memcache
     */
    public function __construct(Memcached $memcache)
    {
        $this->_memcache = $memcache;
    }

    /**
     * @return Memcached
     */
    public function getMemcache()
    {
        return $this->_memcache;
    }
}
