<?php
/**
 * Cache interface.
 *
 * Quota protection depends on this layer: a bot pulling the same dead URL 200
 * times a day must cost one event, not 200. That is why NEGATIVE results are
 * cached as well.
 *
 * @package no404
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface No404_Cache_Interface {

	/**
	 * @param string $key Key (raw; the implementation adds its own prefix/hash).
	 * @return mixed|null Null when the entry is missing or expired.
	 */
	public function get( $key );

	/**
	 * @param string $key   Key.
	 * @param mixed  $value Serialisable value.
	 * @param int    $ttl   Seconds.
	 * @return void
	 */
	public function set( $key, $value, $ttl );

	/**
	 * Deletes every entry belonging to this plugin (called when settings change).
	 *
	 * @return void
	 */
	public function flush();
}
