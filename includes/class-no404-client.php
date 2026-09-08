<?php
/**
 * no404 — the platform-independent core (the behaviour contract).
 *
 * THE CLASS BODY IS NOT TIED TO THE WordPress API: the platform's HTTP and cache
 * layers sit behind interfaces (`No404_Http_Interface`, `No404_Cache_Interface`).
 * That is what lets `tests/test-core.php` run without WordPress and verify the
 * matching / caching / 301-302 logic in isolation. DO NOT add `wp_*` calls here —
 * anything platform-specific belongs in an adapter.
 *
 * Responsibilities:
 *   - Building the request, with a short timeout (fail-open)
 *   - The local cache (quota protection, negatives included)
 *   - Never asking about static/admin paths (the blacklist)
 *   - The 301/302 decision (source + score)
 *   - Target validation: open-redirect and loop protection
 *
 * @package no404
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class No404_Client {

	/** Cache schema version — bump it when the shape changes; old entries are skipped. */
	const CACHE_SCHEMA = 'v1';

	/** CATALOG matches above this score count as permanent (301). */
	const HIGH_CONFIDENCE_SCORE = 0.5;

	/** Default timeout (ms). Short enough not to hold up the store's 404 page. */
	const DEFAULT_TIMEOUT_MS = 1500;

	/** Default result lifetime (seconds). */
	const DEFAULT_CACHE_TTL = 3600;

	/** How long to wait before retrying while the API is unreachable (seconds). */
	const OUTAGE_TTL = 60;

	/** Wait after a quota/limit hit (seconds). If the monthly quota is gone, stop asking. */
	const QUOTA_TTL = 300;

	/** Wait after a configuration error — invalid key, inactive subscription (seconds). */
	const CONFIG_ERROR_TTL = 300;

	/** Maximum path length the API accepts (matches ingestHitSchema). */
	const MAX_PATH_LENGTH = 2048;

	/** Circuit breaker key. */
	const OUTAGE_KEY = 'outage';

	/**
	 * Extensions that have no catalogue counterpart and would just burn quota.
	 *
	 * @var string[]
	 */
	protected $ignored_extensions = array(
		'css', 'js', 'mjs', 'map', 'json', 'xml', 'txt', 'php', 'asp', 'aspx',
		'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'svg', 'ico', 'bmp', 'tiff',
		'woff', 'woff2', 'ttf', 'otf', 'eot',
		'mp3', 'mp4', 'webm', 'ogg', 'wav', 'avi', 'mov',
		'pdf', 'zip', 'gz', 'tar', 'rar', 'doc', 'docx', 'xls', 'xlsx', 'csv',
		'env', 'sql', 'bak', 'log', 'yml', 'yaml', 'ini',
	);

	/**
	 * Path prefixes that are never asked about. The platform wrapper adds its own.
	 *
	 * @var string[]
	 */
	protected $ignored_prefixes = array(
		'/.well-known',
		'/cgi-bin',
	);

	/** @var No404_Http_Interface */
	protected $http;

	/** @var No404_Cache_Interface */
	protected $cache;

	/** @var string API base, without a trailing slash. */
	protected $api_base = '';

	/** @var string */
	protected $api_key = '';

	/** @var int */
	protected $timeout_ms = self::DEFAULT_TIMEOUT_MS;

	/** @var int */
	protected $cache_ttl = self::DEFAULT_CACHE_TTL;

	/** @var bool Send every redirect as a 301 (default: off). */
	protected $force_301 = false;

	/** @var string[] Hosts allowed as a redirect target (lower case). */
	protected $allowed_hosts = array();

	/** @var string Client identity (User-Agent). */
	protected $user_agent = 'no404-plugin';

	/**
	 * @param array                 $config Ayarlar.
	 * @param No404_Http_Interface  $http   HTTP adapter.
	 * @param No404_Cache_Interface $cache  Cache adapter.
	 */
	public function __construct( array $config, No404_Http_Interface $http, No404_Cache_Interface $cache ) {
		$this->http  = $http;
		$this->cache = $cache;

		if ( isset( $config['api_base'] ) ) {
			$this->api_base = rtrim( (string) $config['api_base'], '/' );
		}
		if ( isset( $config['api_key'] ) ) {
			$this->api_key = trim( (string) $config['api_key'] );
		}
		if ( isset( $config['timeout_ms'] ) ) {
			$this->timeout_ms = max( 200, min( 10000, (int) $config['timeout_ms'] ) );
		}
		if ( isset( $config['cache_ttl'] ) ) {
			$this->cache_ttl = max( 60, min( 604800, (int) $config['cache_ttl'] ) );
		}
		if ( isset( $config['force_301'] ) ) {
			$this->force_301 = (bool) $config['force_301'];
		}
		if ( isset( $config['user_agent'] ) ) {
			$this->user_agent = (string) $config['user_agent'];
		}
		if ( ! empty( $config['allowed_hosts'] ) && is_array( $config['allowed_hosts'] ) ) {
			foreach ( $config['allowed_hosts'] as $host ) {
				$host = strtolower( trim( (string) $host ) );
				if ( '' !== $host ) {
					$this->allowed_hosts[] = $host;
				}
			}
			$this->allowed_hosts = array_values( array_unique( $this->allowed_hosts ) );
		}
		if ( ! empty( $config['ignored_prefixes'] ) && is_array( $config['ignored_prefixes'] ) ) {
			foreach ( $config['ignored_prefixes'] as $prefix ) {
				$prefix = $this->normalize_path( $prefix );
				if ( '' !== $prefix && '/' !== $prefix ) {
					$this->ignored_prefixes[] = $prefix;
				}
			}
			$this->ignored_prefixes = array_values( array_unique( $this->ignored_prefixes ) );
		}
	}

	/** Is the plugin operable (does it have a key and a base URL)? */
	public function is_configured() {
		return '' !== $this->api_key && '' !== $this->api_base;
	}

	/**
	 * Resolves a 404 path.
	 *
	 * FAIL-OPEN: never throws under any circumstance. If no404 is slow, down, or
	 * returns something malformed, this returns `null` and the store renders its
	 * own 404 page.
	 *
	 * @param string $path     The path that returned 404.
	 * @param string $referrer Where the visitor came from (optional).
	 *
	 * @return array|null found/redirect/score/source, or null when there is no redirect.
	 */
	public function resolve( $path, $referrer = '' ) {
		try {
			if ( ! $this->is_configured() ) {
				return null;
			}

			$path = $this->normalize_path( $path );
			if ( '' === $path || '/' === $path || strlen( $path ) > self::MAX_PATH_LENGTH ) {
				return null;
			}
			if ( $this->is_ignored_path( $path ) ) {
				return null;
			}

			$key    = $this->cache_key( $path );
			$cached = $this->cache->get( $key );
			if ( is_array( $cached ) && isset( $cached['source'] ) ) {
				return $cached;
			}

			// Circuit breaker: while the API is unreachable or out of quota, do not
			// retry on every single 404.
			if ( null !== $this->cache->get( self::OUTAGE_KEY ) ) {
				return null;
			}

			$response = $this->http->get(
				$this->build_url( $path, $referrer ),
				$this->timeout_ms,
				$this->user_agent
			);

			return $this->handle_response( $response, $key );
		} catch ( Exception $e ) {
			return null;
		} catch ( Throwable $e ) { // PHP 7+ fatal-error guard.
			return null;
		}
	}

	/**
	 * Turns an HTTP response into a result and writes the cache entries it needs.
	 *
	 * @param array  $response Output of the HTTP adapter.
	 * @param string $key      The cache key for this path.
	 *
	 * @return array|null
	 */
	protected function handle_response( array $response, $key ) {
		// Transport failure (timeout, DNS, TLS) → trip the breaker, do not stall the store.
		if ( empty( $response['ok'] ) ) {
			$this->cache->set( self::OUTAGE_KEY, 1, self::OUTAGE_TTL );
			return null;
		}

		$status = isset( $response['status'] ) ? (int) $response['status'] : 0;

		if ( 429 === $status ) {
			// Rate limit or monthly quota. Neither changes in the short term.
			$this->cache->set( self::OUTAGE_KEY, 1, self::QUOTA_TTL );
			return null;
		}
		if ( 403 === $status || 404 === $status ) {
			// Invalid key, paused site, inactive subscription → a configuration problem.
			$this->cache->set( self::OUTAGE_KEY, 1, self::CONFIG_ERROR_TTL );
			return null;
		}
		if ( $status >= 500 ) {
			$this->cache->set( self::OUTAGE_KEY, 1, self::OUTAGE_TTL );
			return null;
		}

		$payload = $this->decode( isset( $response['body'] ) ? $response['body'] : '' );

		if ( 200 !== $status || null === $payload || empty( $payload['success'] ) ) {
			// Including 422 (invalid path): there is no point asking about this PATH again.
			$this->cache->set( $key, $this->empty_result(), $this->cache_ttl );
			return null;
		}

		$result = array(
			'found'    => ! empty( $payload['found'] ),
			'redirect' => isset( $payload['redirect'] ) && is_string( $payload['redirect'] ) ? $payload['redirect'] : null,
			'score'    => isset( $payload['score'] ) ? (float) $payload['score'] : 0.0,
			'source'   => isset( $payload['source'] ) && is_string( $payload['source'] ) ? $payload['source'] : 'NONE',
		);

		// Negative results are cached too — this is what actually protects the quota.
		$this->cache->set( $key, $result, $this->cache_ttl );

		return $result;
	}

	/**
	 * Decides the redirect's HTTP status code.
	 *
	 * A 301 is cached PERMANENTLY by browsers and by Google; issuing one for a
	 * speculative match cannot be undone, even if the catalogue is corrected later.
	 *
	 * @param array $result Output of resolve().
	 * @return int 301 veya 302.
	 */
	public function decide_status( array $result ) {
		if ( $this->force_301 ) {
			return 301;
		}

		$source = isset( $result['source'] ) ? $result['source'] : 'NONE';
		$score  = isset( $result['score'] ) ? (float) $result['score'] : 0.0;

		if ( 'REDIRECT' === $source ) {
			return 301; // A human defined it; it is certain.
		}
		if ( 'CATALOG' === $source && $score >= self::HIGH_CONFIDENCE_SCORE ) {
			return 301;
		}

		return 302; // Low-scoring CATALOG and FALLBACK → keep it reversible.
	}

	/**
	 * Validates the redirect target: open-redirect and loop protection.
	 *
	 * @param string $redirect     The target returned by the API.
	 * @param string $current_path The current (404) path, normalised.
	 *
	 * @return string A safe URL, or an empty string if it is rejected.
	 */
	public function validate_target( $redirect, $current_path ) {
		if ( ! is_string( $redirect ) || '' === trim( $redirect ) ) {
			return '';
		}

		// Header injection: control characters never get through.
		if ( preg_match( '/[\x00-\x1F\x7F]/', $redirect ) ) {
			return '';
		}

		$redirect = trim( $redirect );
		if ( strlen( $redirect ) > self::MAX_PATH_LENGTH ) {
			return '';
		}

		$parts = self::parse_url_parts( $redirect );
		if ( false === $parts || ! is_array( $parts ) ) {
			return '';
		}

		// A relative target with no scheme or host is taken to be our own site.
		if ( empty( $parts['host'] ) ) {
			// "//evil.com" is protocol-relative; non-relative input is rejected too.
			if ( 0 !== strpos( $redirect, '/' ) || 0 === strpos( $redirect, '//' ) ) {
				return '';
			}
			if ( $this->normalize_path( $redirect ) === $current_path ) {
				return ''; // Loop.
			}
			return $redirect;
		}

		$scheme = isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : '';
		if ( 'http' !== $scheme && 'https' !== $scheme ) {
			return '';
		}

		$host = strtolower( $parts['host'] );
		if ( ! in_array( $host, $this->allowed_hosts, true ) ) {
			return '';
		}

		// Loop: a redirect to the same path on an allowed host.
		if ( $this->normalize_path( isset( $parts['path'] ) ? $parts['path'] : '/' ) === $current_path ) {
			return '';
		}

		return $redirect;
	}

	/**
	 * Should this path never be asked about?
	 *
	 * @param string $path The normalised path.
	 * @return bool
	 */
	public function is_ignored_path( $path ) {
		$lower = strtolower( $path );

		foreach ( $this->ignored_prefixes as $prefix ) {
			$prefix = strtolower( $prefix );
			if ( $lower === $prefix || 0 === strpos( $lower, $prefix . '/' ) ) {
				return true;
			}
		}

		$slash    = strrpos( $lower, '/' );
		$basename = ( false === $slash ) ? $lower : substr( $lower, $slash + 1 );
		$dot      = strrpos( $basename, '.' );
		if ( false === $dot || $dot === strlen( $basename ) - 1 ) {
			return false;
		}

		return in_array( substr( $basename, $dot + 1 ), $this->ignored_extensions, true );
	}

	/**
	 * Splits a URL into its components.
	 *
	 * WordPress wants `wp_parse_url()` rather than `parse_url()`: older PHP versions
	 * gave inconsistent results for scheme-less input (`//host/path`), and
	 * wp_parse_url normalises that away.
	 *
	 * The only reason for the wrapper is `tests/test-core.php`: the core must run
	 * without WordPress loaded. The test defines its own `wp_parse_url` stub, so no
	 * fallback is needed here — the function always exists.
	 *
	 * @param string $url The URL to parse.
	 * @return array|false|null The components, or false/null when parsing fails.
	 */
	private static function parse_url_parts( $url ) {
		return wp_parse_url( $url );
	}

	/**
	 * Brings a path into canonical form.
	 *
	 * Applies the SAME rules as `cleanPath` on the no404 server, so the local cache
	 * key and the path the server sees line up. The query string is discarded — the
	 * server only takes the `pathname` anyway, and keeping `?utm_source=...` would
	 * fragment the cache and burn quota for nothing.
	 *
	 * @param string $raw Ham yol veya tam URL.
	 * @return string A path starting with "/", or an empty string.
	 */
	public function normalize_path( $raw ) {
		$raw = (string) $raw;
		$raw = str_replace( array( "\r", "\n", "\t", "\0" ), '', $raw );
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return '';
		}

		// Tam URL geldiyse yolunu al.
		if ( preg_match( '#^https?://#i', $raw ) ) {
			$parsed = self::parse_url_parts( $raw );
			$raw    = ( is_array( $parsed ) && isset( $parsed['path'] ) ) ? $parsed['path'] : '/';
		}

		// Drop the query string and the fragment.
		$raw = substr( $raw, 0, strcspn( $raw, '?#' ) );

		// Treat a backslash as a slash (this is what the WHATWG URL spec does for http(s)).
		$raw = str_replace( '\\', '/', $raw );

		if ( '' === $raw ) {
			return '/';
		}
		if ( 0 !== strpos( $raw, '/' ) ) {
			$raw = '/' . $raw;
		}

		$raw = preg_replace( '#/{2,}#', '/', $raw );
		if ( strlen( $raw ) > 1 ) {
			$raw = rtrim( $raw, '/' );
		}

		return '' === $raw ? '/' : $raw;
	}

	/**
	 * Diagnostic call behind "test the connection" on the settings screen.
	 *
	 * Deliberately BYPASSES the cache and the circuit breaker — the user needs to
	 * see the real state.
	 *
	 * @param string $path Test edilecek yol.
	 * @return array code/status/found/redirect/score/source/detail
	 */
	public function ping( $path = '/no404-baglanti-testi' ) {
		$out = array(
			'code'     => 'unknown',
			'status'   => 0,
			'found'    => false,
			'redirect' => null,
			'score'    => 0.0,
			'source'   => 'NONE',
			'detail'   => '',
		);

		if ( '' === $this->api_base ) {
			$out['code'] = 'no_api_base';
			return $out;
		}
		if ( '' === $this->api_key ) {
			$out['code'] = 'no_api_key';
			return $out;
		}

		$response = $this->http->get(
			$this->build_url( $this->normalize_path( $path ), '' ),
			max( 5000, $this->timeout_ms ), // During a test the user can afford to wait.
			$this->user_agent
		);

		if ( empty( $response['ok'] ) ) {
			$out['code']   = 'unreachable';
			$out['detail'] = isset( $response['error'] ) ? (string) $response['error'] : '';
			return $out;
		}

		$status        = isset( $response['status'] ) ? (int) $response['status'] : 0;
		$out['status'] = $status;
		$payload       = $this->decode( isset( $response['body'] ) ? $response['body'] : '' );

		if ( is_array( $payload ) && isset( $payload['message'] ) && is_string( $payload['message'] ) ) {
			$out['detail'] = $payload['message'];
		}

		if ( 200 === $status && is_array( $payload ) && ! empty( $payload['success'] ) ) {
			$out['code']     = 'ok';
			$out['found']    = ! empty( $payload['found'] );
			$out['redirect'] = isset( $payload['redirect'] ) && is_string( $payload['redirect'] ) ? $payload['redirect'] : null;
			$out['score']    = isset( $payload['score'] ) ? (float) $payload['score'] : 0.0;
			$out['source']   = isset( $payload['source'] ) && is_string( $payload['source'] ) ? $payload['source'] : 'NONE';
			return $out;
		}

		switch ( $status ) {
			case 404:
				$out['code'] = 'invalid_key';
				break;
			case 403:
				$out['code'] = 'forbidden';
				break;
			case 429:
				$out['code'] = 'rate_limited';
				break;
			case 422:
				$out['code'] = 'invalid_path';
				break;
			default:
				$out['code'] = $status >= 500 ? 'server_error' : 'unexpected';
		}

		return $out;
	}

	/**
	 * Builds the request URL.
	 *
	 * @param string $path     The normalised path.
	 * @param string $referrer Referrer.
	 * @return string
	 */
	protected function build_url( $path, $referrer ) {
		$query = 'path=' . rawurlencode( $path );

		$referrer = trim( (string) $referrer );
		if ( '' !== $referrer ) {
			$query .= '&ref=' . rawurlencode( substr( $referrer, 0, self::MAX_PATH_LENGTH ) );
		}

		return $this->api_base . '/api/v1/resolve/' . rawurlencode( $this->api_key ) . '?' . $query;
	}

	/**
	 * Cache key for a path. Changing the API key drops the old entries.
	 *
	 * @param string $path The normalised path.
	 * @return string
	 */
	protected function cache_key( $path ) {
		return self::CACHE_SCHEMA . ':' . substr( md5( $this->api_key ), 0, 8 ) . ':' . md5( $path );
	}

	/** @return array The "no match found" result (used for negative caching). */
	protected function empty_result() {
		return array(
			'found'    => false,
			'redirect' => null,
			'score'    => 0.0,
			'source'   => 'NONE',
		);
	}

	/**
	 * Decodes JSON; a malformed body never turns into an exception.
	 *
	 * @param string $body The raw body.
	 * @return array|null
	 */
	protected function decode( $body ) {
		if ( ! is_string( $body ) || '' === $body ) {
			return null;
		}

		$decoded = json_decode( $body, true );

		return is_array( $decoded ) ? $decoded : null;
	}
}
