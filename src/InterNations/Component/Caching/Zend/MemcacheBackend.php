<?php
namespace InterNations\Component\Caching\Zend;

use Zend_Cache_Backend_Memcached as BaseMemcacheBackend;
use Memcache;

/**
 * Had to be overwritten because the default doesn't support
 * providing a fully configured Memcache instance and fetching it
 */
class MemcacheBackend extends BaseMemcacheBackend
{
    /**
     * @param Memcache $memcache
     */
    public function __construct(Memcache $memcache)
    {
        $this->_memcache = $memcache;
    }

    /**
     * @return Memcache
     */
    public function getMemcache()
    {
        return $this->_memcache;
    }
}
