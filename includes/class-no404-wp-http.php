<?php
/**
 * WordPress HTTP adapter (`wp_remote_get`).
 *
 * @package no404
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class No404_WP_Http implements No404_Http_Interface {

	/**
	 * @param string $url        Full URL.
	 * @param int    $timeout_ms Timeout in milliseconds.
	 * @param string $user_agent User-Agent.
	 *
	 * @return array ok/status/body/error/location
	 */
	public function get( $url, $timeout_ms, $user_agent ) {
		$response = wp_remote_get(
			$url,
			array(
				// wp_remote_get expects seconds; a fractional value becomes milliseconds in cURL.
				'timeout'     => max( 0.2, $timeout_ms / 1000 ),
				/*
				 * The API itself does not redirect, but the host in front of it may:
				 * no404.tr sends every request on to www.no404.tr. 1.0.0 refused to
				 * follow that, so a default installation saw a bare 302 and reported
				 * "unexpected response". Two hops are enough for a canonical-host
				 * (and http → https) redirect and still cannot become a long chain.
				 */
				'redirection' => 2,
				'sslverify'   => true,
				'user-agent'  => $user_agent,
				'headers'     => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'ok'       => false,
				'status'   => 0,
				'body'     => '',
				'error'    => $response->get_error_message(),
				'location' => '',
			);
		}

		return array(
			'ok'       => true,
			'status'   => (int) wp_remote_retrieve_response_code( $response ),
			'body'     => (string) wp_remote_retrieve_body( $response ),
			'error'    => '',
			// Only set when the chain was longer than `redirection` allows; the
			// connection test turns it into an address the user can act on.
			'location' => (string) wp_remote_retrieve_header( $response, 'location' ),
		);
	}
}
