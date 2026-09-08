<?php
/**
 * HTTP transport interface.
 *
 * The core (No404_Client) knows nothing about the transport layer: the
 * WordPress adapter uses `wp_remote_get`, and the tests pass in a fake
 * implementation. This is the boundary that keeps the core testable without
 * WordPress.
 *
 * @package no404
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface No404_Http_Interface {

	/**
	 * Performs a GET request. NEVER throws — failures come back in the array.
	 *
	 * @param string $url        Full URL.
	 * @param int    $timeout_ms Timeout in milliseconds.
	 * @param string $user_agent User-Agent to send.
	 *
	 * @return array{ok:bool,status:int,body:string,error:string}
	 *               ok=false → transport failure (DNS, timeout, TLS); status is 0.
	 *               ok=true  → an HTTP response arrived; status may be 4xx/5xx.
	 */
	public function get( $url, $timeout_ms, $user_agent );
}
