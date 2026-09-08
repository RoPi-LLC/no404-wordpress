<?php
/**
 * The 404 catcher: runs on `template_redirect` and issues a real, server-side
 * 301/302.
 *
 * PRIORITY: {@see No404_Redirector::PRIORITY} is deliberately high. Plugins such
 * as Yoast, Rank Math and Redirection maintain their own redirect tables and
 * THEY MUST RUN FIRST. no404 is the last resort; if one of them found a match
 * and redirected, this code is never reached.
 *
 * @package no404
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class No404_Redirector {

	/** `template_redirect` priority — AFTER every other redirect plugin. */
	const PRIORITY = 9999;

	/** @var No404_Client */
	protected $client;

	/** @var string[] Hosts allowed as a redirect target for this request. */
	protected $allowed_hosts = array();

	/**
	 * @param No404_Client $client        Core client.
	 * @param string[]     $allowed_hosts Allowed redirect hosts.
	 */
	public function __construct( No404_Client $client, array $allowed_hosts ) {
		$this->client        = $client;
		$this->allowed_hosts = $allowed_hosts;
	}

	/** Registers the hook. */
	public function register() {
		add_action( 'template_redirect', array( $this, 'maybe_redirect' ), self::PRIORITY );
	}

	/**
	 * On a 404, asks no404 and redirects to the suggested target.
	 *
	 * FAIL-OPEN: returns quietly in every failure case, so the page renders as usual.
	 *
	 * @return void
	 */
	public function maybe_redirect() {
		if ( ! $this->should_handle() ) {
			return;
		}

		$path = $this->client->normalize_path( $this->current_path() );
		if ( '' === $path || '/' === $path ) {
			return;
		}

		/**
		 * Filter to skip this request without asking no404 at all.
		 *
		 * @param bool   $skip Whether to skip.
		 * @param string $path The normalised path.
		 */
		if ( apply_filters( 'no404_skip_request', false, $path ) ) {
			return;
		}

		$result = $this->client->resolve( $path, $this->referrer() );
		if ( null === $result || empty( $result['redirect'] ) ) {
			return;
		}

		$target = $this->client->validate_target( $result['redirect'], $path );
		if ( '' === $target ) {
			return;
		}

		$status = $this->client->decide_status( $result );

		/**
		 * Last chance to change the redirect target and status code.
		 *
		 * @param string $target Hedef URL.
		 * @param int    $status 301 veya 302.
		 * @param array  $result API sonucu.
		 * @param string $path   Kaynak yol.
		 */
		$target = (string) apply_filters( 'no404_redirect_target', $target, $status, $result, $path );
		$status = (int) apply_filters( 'no404_redirect_status', $status, $result, $path );

		if ( '' === $target || ( 301 !== $status && 302 !== $status ) ) {
			return;
		}

		// WordPress's own allow-list stops an escape to an external host. We add our
		// own allowed hosts for the duration of this call only. The filter must stay
		// in place across BOTH the pre-validation and wp_safe_redirect's own check —
		// remove it too early and safe_redirect treats our valid target as external
		// and drops it.
		add_filter( 'allowed_redirect_hosts', array( $this, 'filter_allowed_hosts' ) );
		$validated = wp_validate_redirect( $target, '' );
		// No chains: a single redirect, then exit.
		$sent = ( '' !== $validated ) ? wp_safe_redirect( $validated, $status, 'no404' ) : false;
		remove_filter( 'allowed_redirect_hosts', array( $this, 'filter_allowed_hosts' ) );

		if ( $sent ) {
			exit;
		}
	}

	/**
	 * The `allowed_redirect_hosts` filter.
	 *
	 * @param string[] $hosts Currently allowed hosts.
	 * @return string[]
	 */
	public function filter_allowed_hosts( $hosts ) {
		if ( ! is_array( $hosts ) ) {
			$hosts = array();
		}

		return array_values( array_unique( array_merge( $hosts, $this->allowed_hosts ) ) );
	}

	/**
	 * Should this request be handled?
	 *
	 * @return bool
	 */
	protected function should_handle() {
		if ( ! is_404() ) {
			return false;
		}
		if ( headers_sent() ) {
			return false; // Cannot redirect any more; do not break the page.
		}

		// Real page views only.
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		if ( 'GET' !== $method && 'HEAD' !== $method ) {
			return false;
		}

		if ( is_admin() || is_feed() || is_robots() || is_trackback() || is_preview() ) {
			return false;
		}
		if ( wp_doing_ajax() || wp_doing_cron() ) {
			return false;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			return false;
		}
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return false;
		}
		if ( function_exists( 'is_favicon' ) && is_favicon() ) {
			return false;
		}
		if ( is_customize_preview() ) {
			return false;
		}

		return true;
	}

	/**
	 * Returns the raw requested path.
	 *
	 * @return string
	 */
	protected function current_path() {
		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return '';
		}

		// Validation happens in the core (normalize_path + is_ignored_path).
		return esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );
	}

	/**
	 * Where the visitor came from.
	 *
	 * Server-side, the `Referer` header is the REAL origin (unlike the JS snippet,
	 * where it would be the 404 page itself), so it can be used directly.
	 *
	 * @return string
	 */
	protected function referrer() {
		if ( empty( $_SERVER['HTTP_REFERER'] ) ) {
			return '';
		}

		return esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
	}
}
