<?php

namespace go\core\cache;

/**
 * Cache implementation that uses serialized objects in files on disk.
 * The cache is persistent accross requests.
 * 
 * @copyright (c) 2014, Intermesh BV http://www.intermesh.nl
 * @author Merijn Schering <mschering@intermesh.nl>
 * @license http://www.gnu.org/licenses/agpl-3.0.html AGPLv3
 */
class None implements CacheInterface {

	private $cache = [];

	/**
	 * Store any value in the cache
	 * 
	 * @param string $key
	 * @param mixed $value Will be serialized
	 * @param boolean $persist Cache must be available in next requests. Use false of it's just for this script run.
	 * @param int $ttl Time to live in seconds
	 */
	public function set(string $key, $value, bool $persist = true, int $ttl = 0) {
		$this->cache[$key] = $value;
	}

	/**
	 * Get a value from the cache
	 * 
	 * @param string $key
	 * @return mixed Stored value or NULL if not found 
	 */
	public function get(string $key) {
		if (isset($this->cache[$key])) {
			return $this->cache[$key];
		}
		return null;
	}

	/**
	 * Delete a value from the cache
	 * 
	 * @param string $key 
	 */
	public function delete(string $key) {
		unset($this->cache[$key]);
	}

	/**
	 * Flush all values 
	 *
	 * @param bool $onDestruct
	 */
	public function flush(bool $onDestruct = true) {
		$this->cache = [];
	}

	public static function isSupported(): bool
	{
		return true;
	}

}
