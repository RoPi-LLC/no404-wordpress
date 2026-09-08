<?php
/**
 * Option store: defaults, sanitising and masking.
 *
 * Everything lives as an array in a single option row (`no404_settings`).
 * Because `get_option` works in the blog context, on multisite each store uses
 * its own key automatically.
 *
 * @package no404
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class No404_Options {

	const OPTION = 'no404_settings';

	/** Placeholder used when showing the key masked. */
	const MASK = '••••••••';

	/**
	 * Address of the no404 service.
	 *
	 * The vast majority of users never touch this field; only people hosting
	 * their own installation change it. Leaving the field empty restores this.
	 */
	const DEFAULT_API_BASE = 'https://no404.tr';

	/**
	 * @return array Default settings.
	 */
	public static function defaults() {
		return array(
			'enabled'       => 1,
			'api_base'      => self::DEFAULT_API_BASE,
			'api_key'       => '',
			'cache_ttl'     => No404_Client::DEFAULT_CACHE_TTL,
			'timeout_ms'    => No404_Client::DEFAULT_TIMEOUT_MS,
			'force_301'     => 0,
			'exclude_paths' => '',
		);
	}

	/**
	 * @return array Stored settings, merged over the defaults.
	 */
	public static function all() {
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return array_merge( self::defaults(), $saved );
	}

	/**
	 * @param string $key     Setting name.
	 * @param mixed  $default Value returned when the setting is missing.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::all();

		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Turns the "Excluded paths" text into an array of prefixes.
	 *
	 * @return string[]
	 */
	public static function exclude_prefixes() {
		$raw = (string) self::get( 'exclude_paths', '' );
		if ( '' === trim( $raw ) ) {
			return array();
		}

		$lines  = preg_split( '/[\r\n,]+/', $raw );
		$result = array();

		foreach ( (array) $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			if ( 0 !== strpos( $line, '/' ) ) {
				$line = '/' . $line;
			}
			$result[] = rtrim( $line, '/' );
		}

		return array_values( array_unique( array_filter( $result ) ) );
	}

	/**
	 * The masked form of the API key — the full key is NEVER shown on screen.
	 *
	 * @return string
	 */
	public static function masked_key() {
		$key = (string) self::get( 'api_key', '' );
		if ( '' === $key ) {
			return '';
		}

		return self::MASK . substr( $key, -4 );
	}

	/**
	 * Sanitises the raw input coming from a Settings API save.
	 *
	 * @param mixed $input Raw POST data.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$current = self::all();

		if ( ! is_array( $input ) ) {
			return $current;
		}

		$clean = array();

		$clean['enabled']   = empty( $input['enabled'] ) ? 0 : 1;
		$clean['force_301'] = empty( $input['force_301'] ) ? 0 : 1;

		// API base. An EMPTY field restores the default — this is the user's
		// "I typed something wrong, let me undo it" route. A non-empty but invalid
		// value keeps the previous one and shows a warning.
		$raw_base = isset( $input['api_base'] ) ? trim( (string) $input['api_base'] ) : '';

		if ( '' === $raw_base ) {
			$api_base = self::DEFAULT_API_BASE;
		} else {
			$api_base = esc_url_raw( $raw_base, array( 'http', 'https' ) );
			if ( '' === $api_base ) {
				$api_base = $current['api_base'];
				add_settings_error(
					self::OPTION,
					'no404_api_base',
					__( 'The no404 address must be a valid http(s) URL. The previous value has been kept. Leave the field empty to restore the default.', 'no404-auto-404-redirect' )
				);
			}
		}

		$clean['api_base'] = rtrim( $api_base, '/' );

		// API key: an empty or still-masked submission keeps the stored key.
		$api_key = isset( $input['api_key'] ) ? trim( (string) $input['api_key'] ) : '';
		if ( '' === $api_key || false !== strpos( $api_key, self::MASK ) ) {
			$clean['api_key'] = $current['api_key'];
		} else {
			$clean['api_key'] = preg_replace( '/[^A-Za-z0-9\-_]/', '', $api_key );
		}

		$clean['cache_ttl'] = isset( $input['cache_ttl'] )
			? max( 60, min( 604800, (int) $input['cache_ttl'] ) )
			: $current['cache_ttl'];

		$clean['timeout_ms'] = isset( $input['timeout_ms'] )
			? max( 200, min( 10000, (int) $input['timeout_ms'] ) )
			: $current['timeout_ms'];

		$clean['exclude_paths'] = isset( $input['exclude_paths'] )
			? sanitize_textarea_field( (string) $input['exclude_paths'] )
			: $current['exclude_paths'];

		// Settings changed → previous results are stale (especially on a key change).
		$cache = new No404_WP_Cache();
		$cache->flush();

		return $clean;
	}
}
