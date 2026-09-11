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

	/** Ad categories the no404 API accepts in `ad=` (it drops anything else). */
	const AD_CATEGORIES = array( 'google', 'microsoft', 'meta', 'other' );

	/** `utm_medium` values that mark paid traffic (lower case) — same list as the server. */
	const PAID_MEDIUMS = array(
		'cpc',
		'ppc',
		'paid',
		'paidsearch',
		'paid_search',
		'paid-search',
		'paidsocial',
		'paid_social',
		'paid-social',
		'display',
		'cpm',
		'cpv',
		'banner',
		'retargeting',
		'remarketing',
	);

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

	/** Maximum visitor User-Agent length the API keeps. */
	const MAX_USER_AGENT_LENGTH = 512;

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

	/** @var string Site-specific secret for the visitor ID ('' = no ID is sent). */
	protected $visitor_secret = '';

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
		if ( isset( $config['visitor_secret'] ) ) {
			$this->visitor_secret = (string) $config['visitor_secret'];
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
	 * AD CLICKS: when `$ad` is a known category (see detect_ad_category), the cache
	 * is NOT read — the API is asked every time. Ad 404s are few and each one is a
	 * paid click; answering them from the cache would leave them uncounted in the
	 * dashboard. If no404 cannot be reached, the cached result is still used.
	 *
	 * VISITOR: the request leaves from this server, so without `$visitor` no404
	 * would record every 404 under the server's own IP and the plugin's user agent.
	 * See visitor_headers(): the IP is truncated to its network before it is sent.
	 *
	 * @param string $path     The path that returned 404.
	 * @param string $referrer Where the visitor came from (optional).
	 * @param string $ad       Ad category from detect_ad_category() ('' = not an ad click).
	 * @param array  $visitor  ip / user_agent / country of the visitor (optional).
	 *
	 * @return array|null found/redirect/score/source, or null when there is no redirect.
	 */
	public function resolve( $path, $referrer = '', $ad = '', array $visitor = array() ) {
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

			$ad     = in_array( $ad, self::AD_CATEGORIES, true ) ? $ad : '';
			$key    = $this->cache_key( $path );
			$cached = $this->cache->get( $key );
			$cached = ( is_array( $cached ) && isset( $cached['source'] ) ) ? $cached : null;
			if ( null !== $cached && '' === $ad ) {
				return $cached;
			}

			// Circuit breaker: while the API is unreachable or out of quota, do not
			// retry on every single 404.
			if ( null !== $this->cache->get( self::OUTAGE_KEY ) ) {
				return $cached;
			}

			$response = $this->http->get(
				$this->build_url( $path, $referrer, $ad ),
				$this->timeout_ms,
				$this->user_agent,
				array_merge( $this->auth_headers(), $this->visitor_headers( $visitor ) )
			);

			$result = $this->handle_response( $response, $key );

			// An ad click that could not be answered falls back to what we knew.
			return ( null === $result && null !== $cached ) ? $cached : $result;
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
			// The API's own 301/302 decision (0 when absent — older API versions).
			'redirect_status' => self::read_redirect_status( $payload ),
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
	 * Order: the "force 301" setting → the API's `redirectStatus` (computed from
	 * the 301 threshold the site owner picked in the no404 panel) → the local
	 * rule below, which only applies to API versions that don't send the field.
	 *
	 * @param array $result Output of resolve().
	 * @return int 301 veya 302.
	 */
	public function decide_status( array $result ) {
		if ( $this->force_301 ) {
			return 301;
		}

		$api_status = isset( $result['redirect_status'] ) ? (int) $result['redirect_status'] : 0;
		if ( 301 === $api_status || 302 === $api_status ) {
			return $api_status;
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
	 * `redirectStatus` from an API payload: 301, 302, or 0 when absent/invalid.
	 * Anything else (a 307, a string, a missing field) is ignored so the local
	 * rule in decide_status() stays in charge.
	 *
	 * @param array $payload Decoded API response.
	 * @return int
	 */
	private static function read_redirect_status( array $payload ) {
		$value = isset( $payload['redirectStatus'] ) && is_numeric( $payload['redirectStatus'] )
			? (int) $payload['redirectStatus']
			: 0;
		return ( 301 === $value || 302 === $value ) ? $value : 0;
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
			$this->build_url( $this->normalize_path( $path ), '', '' ),
			max( 5000, $this->timeout_ms ), // During a test the user can afford to wait.
			$this->user_agent,
			$this->auth_headers()
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

		// A redirect that survived the transport's follow limit. The address is
		// wrong rather than broken, and the Location header says what the right
		// one is, so this gets its own code instead of "unexpected response".
		if ( $status >= 300 && $status < 400 ) {
			$out['code']   = 'redirected';
			$out['detail'] = isset( $response['location'] ) ? (string) $response['location'] : '';
			return $out;
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
	 * The API key is NOT part of the URL: it travels in the Authorization header
	 * (see auth_headers). A key in the URL ends up in web server, proxy and CDN
	 * logs; a header does not.
	 *
	 * @param string $path     The normalised path.
	 * @param string $referrer Referrer.
	 * @param string $ad       Ad category ('' = not an ad click).
	 * @return string
	 */
	protected function build_url( $path, $referrer, $ad = '' ) {
		$query = 'path=' . rawurlencode( $path );

		$referrer = trim( (string) $referrer );
		if ( '' !== $referrer ) {
			$query .= '&ref=' . rawurlencode( substr( $referrer, 0, self::MAX_PATH_LENGTH ) );
		}
		if ( in_array( $ad, self::AD_CATEGORIES, true ) ) {
			$query .= '&ad=' . $ad;
		}

		return $this->api_base . '/api/v1/resolve?' . $query;
	}

	/** @return array The Authorization header carrying the API key. */
	protected function auth_headers() {
		return array( 'Authorization' => 'Bearer ' . $this->api_key );
	}

	/**
	 * Visitor headers. The plugin's own User-Agent keeps identifying the
	 * installation; these describe the visitor who hit the 404.
	 *
	 * PRIVACY: the full IP never leaves the site — only its network
	 * (see truncate_ip) and a site-keyed HMAC of it (see visitor_id).
	 * Invalid values are dropped, not sent.
	 *
	 * @param array $visitor ip / user_agent / country.
	 * @return array Header name => value.
	 */
	protected function visitor_headers( array $visitor ) {
		$headers = array();

		$ip = isset( $visitor['ip'] ) ? $this->truncate_ip( $visitor['ip'] ) : '';
		if ( '' !== $ip ) {
			$headers['X-No404-Visitor-IP'] = $ip;
		}

		$id = isset( $visitor['ip'] ) ? $this->visitor_id( $visitor['ip'] ) : '';
		if ( '' !== $id ) {
			$headers['X-No404-Visitor-Id'] = $id;
		}

		$ua = isset( $visitor['user_agent'] ) ? preg_replace( '/[\x00-\x1F\x7F]/', '', (string) $visitor['user_agent'] ) : '';
		$ua = trim( substr( (string) $ua, 0, self::MAX_USER_AGENT_LENGTH ) );
		if ( '' !== $ua ) {
			$headers['X-No404-Visitor-UA'] = $ua;
		}

		$country = isset( $visitor['country'] ) ? strtoupper( trim( (string) $visitor['country'] ) ) : '';
		// Cloudflare sends "XX" when the country is unknown.
		if ( 1 === preg_match( '/^[A-Z]{2}$/', $country ) && 'XX' !== $country ) {
			$headers['X-No404-Visitor-Country'] = $country;
		}

		return $headers;
	}

	/**
	 * Pseudonymous visitor ID: HMAC-SHA256 of the FULL IP, keyed with a secret
	 * that only this site knows. It lets no404 count unique visitors exactly
	 * (the truncated IP alone merges a whole /24), while no404 can neither
	 * reverse it to an address (a plain hash of an IPv4 address can be brute
	 * forced) nor match one visitor across two sites (each has its own secret).
	 *
	 * The packed address is hashed, so ::ffff:1.2.3.4 and 1.2.3.4 — or two
	 * spellings of one IPv6 address — yield the same ID. No secret → ''.
	 *
	 * @param string $ip Raw IP address.
	 * @return string 64 hex characters, or ''.
	 */
	public function visitor_id( $ip ) {
		if ( '' === $this->visitor_secret ) {
			return '';
		}
		$ip = trim( (string) $ip );
		if ( preg_match( '/^::ffff:(\d{1,3}(?:\.\d{1,3}){3})$/i', $ip, $m ) ) {
			$ip = $m[1];
		}
		if ( false === filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return '';
		}
		$packed = inet_pton( $ip );
		if ( false === $packed ) {
			return '';
		}

		return hash_hmac( 'sha256', $packed, $this->visitor_secret );
	}

	/**
	 * Truncates an IP address to its network: IPv4 → last octet zeroed
	 * (85.34.78.0), IPv6 → first 48 bits (2a01:4f8:1c1c::). An IPv4-mapped IPv6
	 * address counts as IPv4. Invalid input → ''.
	 *
	 * @param string $ip Raw IP address.
	 * @return string
	 */
	public function truncate_ip( $ip ) {
		$ip = trim( (string) $ip );
		if ( preg_match( '/^::ffff:(\d{1,3}(?:\.\d{1,3}){3})$/i', $ip, $m ) ) {
			$ip = $m[1];
		}

		if ( false !== filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return (string) preg_replace( '/\.\d+$/', '.0', $ip );
		}

		if ( false !== filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			$packed = inet_pton( $ip );
			if ( false === $packed || 16 !== strlen( $packed ) ) {
				return '';
			}
			$network = inet_ntop( substr( $packed, 0, 6 ) . str_repeat( "\0", 10 ) );
			return false === $network ? '' : $network;
		}

		return '';
	}

	/**
	 * Works out whether a request came from an ad click, from its RAW request URI
	 * (query string included). Returns only the CATEGORY — google, microsoft, meta,
	 * other — or '' for organic traffic. The raw click ID (gclid, msclkid…) never
	 * leaves the site. Mirrors the no404 server's own rules (lib/ad-source.ts):
	 * fbclid alone is NOT an ad, because Facebook adds it to organic shares too.
	 *
	 * @param string $raw_uri Request URI, e.g. /product?gclid=abc.
	 * @return string
	 */
	public function detect_ad_category( $raw_uri ) {
		$raw = (string) $raw_uri;
		$q   = strpos( $raw, '?' );
		if ( false === $q ) {
			return '';
		}

		$query = substr( $raw, $q + 1 );
		$hash  = strpos( $query, '#' );
		if ( false !== $hash ) {
			$query = substr( $query, 0, $hash );
		}

		$params = array();
		parse_str( $query, $params );
		$value = function ( $key ) use ( $params ) {
			return ( isset( $params[ $key ] ) && is_string( $params[ $key ] ) ) ? strtolower( trim( $params[ $key ] ) ) : '';
		};

		foreach ( array( 'gclid', 'gbraid', 'wbraid', 'gclsrc' ) as $key ) {
			if ( '' !== $value( $key ) ) {
				return 'google';
			}
		}
		if ( '' !== $value( 'msclkid' ) ) {
			return 'microsoft';
		}
		if ( in_array( $value( 'utm_medium' ), self::PAID_MEDIUMS, true ) ) {
			return self::category_for_source( $value( 'utm_source' ) );
		}
		foreach ( array( 'ttclid', 'twclid', 'li_fat_id' ) as $key ) {
			if ( '' !== $value( $key ) ) {
				return 'other';
			}
		}

		return '';
	}

	/**
	 * The ad network of a click already known to be paid, from `utm_source`.
	 * Meta's `{{site_source_name}}` yields fb, ig, an or msg — all four are Meta.
	 *
	 * @param string $source Lower-case utm_source.
	 * @return string
	 */
	private static function category_for_source( $source ) {
		if ( preg_match( '/google|adwords/', $source ) ) {
			return 'google';
		}
		if ( preg_match( '/bing|microsoft/', $source ) ) {
			return 'microsoft';
		}
		if ( preg_match( '/facebook|instagram|meta|messenger|audience_network|threads|^(fb|ig|an|msg)$/', $source ) ) {
			return 'meta';
		}
		return 'other';
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
			'found'           => false,
			'redirect'        => null,
			'score'           => 0.0,
			'source'          => 'NONE',
			'redirect_status' => 0,
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
