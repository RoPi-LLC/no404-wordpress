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
	 * @param array  $headers    Extra request headers (name => value), e.g. Authorization.
	 *
	 * @return array{ok:bool,status:int,body:string,error:string,location?:string}
	 *               ok=false → transport failure (DNS, timeout, TLS); status is 0.
	 *               ok=true  → an HTTP response arrived; status may be 3xx/4xx/5xx.
	 *               location  → the Location header when the response is a 3xx the
	 *                           transport did not follow; optional.
	 */
	public function get( $url, $timeout_ms, $user_agent, array $headers = array() );
}
