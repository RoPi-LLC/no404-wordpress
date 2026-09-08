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
	 * @return array ok/status/body/error
	 */
	public function get( $url, $timeout_ms, $user_agent ) {
		$response = wp_remote_get(
			$url,
			array(
				// wp_remote_get expects seconds; a fractional value becomes milliseconds in cURL.
				'timeout'     => max( 0.2, $timeout_ms / 1000 ),
				'redirection' => 0, // The API never redirects; there is no chain to follow.
				'sslverify'   => true,
				'user-agent'  => $user_agent,
				'headers'     => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'ok'     => false,
				'status' => 0,
				'body'   => '',
				'error'  => $response->get_error_message(),
			);
		}

		return array(
			'ok'     => true,
			'status' => (int) wp_remote_retrieve_response_code( $response ),
			'body'   => (string) wp_remote_retrieve_body( $response ),
			'error'  => '',
		);
	}
}
