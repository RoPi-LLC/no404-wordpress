<?php
/**
 * WordPress cache adapter (transients).
 *
 * Transients are per site (blog), so after `switch_to_blog` on multisite the
 * right store's cache is used automatically — nothing extra to do.
 *
 * @package no404
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class No404_WP_Cache implements No404_Cache_Interface {

	/** Shared prefix for every transient name (uninstall cleanup relies on it). */
	const PREFIX = 'no404_';

	/** Option holding the generation counter. Incrementing it invalidates the cache. */
	const GENERATION_OPTION = 'no404_cache_generation';

	/** @var int|null */
	protected $generation = null;

	/**
	 * @param string $key Key.
	 * @return mixed|null
	 */
	public function get( $key ) {
		$value = get_transient( $this->name( $key ) );

		// The transient API signals "missing" with false; our contract says null.
		return ( false === $value ) ? null : $value;
	}

	/**
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 * @param int    $ttl   Seconds.
	 * @return void
	 */
	public function set( $key, $value, $ttl ) {
		set_transient( $this->name( $key ), $value, max( 1, (int) $ttl ) );
	}

	/**
	 * Invalidates the cache.
	 *
	 * Instead of deleting row by row we increment a generation counter: when an
	 * external object cache (Redis/Memcached) is installed the transients are NOT
	 * in the database, so `DELETE ... LIKE` does nothing. The counter works on
	 * every setup. Old entries fall away when their own TTL expires.
	 *
	 * @return void
	 */
	public function flush() {
		$next = $this->current_generation() + 1;
		update_option( self::GENERATION_OPTION, $next, false );
		$this->generation = $next;
	}

	/**
	 * Turns a key into a transient name.
	 *
	 * @param string $key Raw key.
	 * @return string
	 */
	protected function name( $key ) {
		return self::PREFIX . $this->current_generation() . '_' . md5( (string) $key );
	}

	/** @return int */
	protected function current_generation() {
		if ( null === $this->generation ) {
			$this->generation = (int) get_option( self::GENERATION_OPTION, 0 );
		}

		return $this->generation;
	}
}
