<?php
/**
 * Plugin Name:       no404 – Auto 404 Redirect
 * Plugin URI:        https://no404.tr
 * Description:       Automatically redirects visitors who hit a 404 to the closest matching live URL on your site, using a real server-side 301.
 * Version:           1.0.3
 * Requires at least: 6.0
 * Tested up to:      7.1
 * Requires PHP:      7.4
 * Author:            no404
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       no404-auto-404-redirect
 * Domain Path:       /languages
 *
 * @package no404
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NO404_VERSION', '1.0.3' );
define( 'NO404_PLUGIN_FILE', __FILE__ );
define( 'NO404_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once NO404_PLUGIN_DIR . 'includes/interface-no404-http.php';
require_once NO404_PLUGIN_DIR . 'includes/interface-no404-cache.php';
require_once NO404_PLUGIN_DIR . 'includes/class-no404-client.php';
require_once NO404_PLUGIN_DIR . 'includes/class-no404-wp-http.php';
require_once NO404_PLUGIN_DIR . 'includes/class-no404-wp-cache.php';
require_once NO404_PLUGIN_DIR . 'includes/class-no404-options.php';
require_once NO404_PLUGIN_DIR . 'includes/class-no404-redirector.php';

/**
 * The plugin's singleton instance.
 *
 * Multisite: settings must be re-read after every `switch_to_blog`, which is why
 * the client is cached per blog ID.
 */
final class No404_Plugin {

	/** @var No404_Plugin|null */
	protected static $instance = null;

	/** @var No404_Client|null */
	protected $client = null;

	/** @var int|null The blog the client was built for. */
	protected $client_blog_id = null;

	/** @return No404_Plugin */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Registers the hooks.
	 *
	 * The source language is English; localisations come from the catalogues under
	 * `languages/` and from translate.wordpress.org.
	 */
	public function boot() {
		$this->register_translations_path();

		if ( is_admin() ) {
			require_once NO404_PLUGIN_DIR . 'includes/class-no404-admin.php';
			$admin = new No404_Admin();
			$admin->register();
		}

		// The front-end hook is deferred to `init`: while plugins are loading,
		// `home_url()` is not yet reliable (multisite and domain-mapping plugins
		// come into play later).
		if ( ! is_admin() ) {
			add_action( 'init', array( $this, 'setup_frontend' ) );
		}
	}

	/** Wires up the front-end redirector (only when the settings allow it). */
	public function setup_frontend() {
		if ( ! $this->is_active() ) {
			return;
		}

		$redirector = new No404_Redirector( $this->client(), $this->allowed_hosts() );
		$redirector->register();
	}

	/**
	 * Registers the bundled translations directory with WordPress's catalogue registry.
	 *
	 * We deliberately do NOT call `load_plugin_textdomain()`: since WordPress 4.6
	 * translations load by themselves on the first `__()` call, and calling that
	 * function is flagged as a warning by the WordPress.org Plugin Check.
	 *
	 * But automatic loading only looks under `WP_LANG_DIR/plugins/` — that is, at
	 * translations DOWNLOADED from translate.wordpress.org. The plugin's OWN
	 * `languages/` folder is not on that list (see
	 * WP_Textdomain_Registry::get_paths_for_domain), because the only thing that
	 * adds a path there is load_plugin_textdomain(). The consequence: if we simply
	 * dropped the call and did nothing else, the bundled .mo files would NEVER be
	 * loaded and the plugin would appear in English in every language.
	 *
	 * So we register the path directly. The registry checks `WP_LANG_DIR/plugins/`
	 * first and falls through to ours, which means a newer translation downloaded
	 * from wp.org always OVERRIDES the bundled copy — exactly the order we want.
	 *
	 * `WP_Textdomain_Registry` arrived in WordPress 6.1; without it this is skipped
	 * silently and the plugin falls back to its source language (English).
	 */
	private function register_translations_path() {
		if ( ! isset( $GLOBALS['wp_textdomain_registry'] ) || ! $GLOBALS['wp_textdomain_registry'] instanceof WP_Textdomain_Registry ) {
			return;
		}

		$GLOBALS['wp_textdomain_registry']->set_custom_path( 'no404-auto-404-redirect', NO404_PLUGIN_DIR . 'languages' );
	}

	/** @return bool Is redirecting switched on and configured? */
	public function is_active() {
		if ( empty( No404_Options::get( 'enabled' ) ) ) {
			return false;
		}

		$client = $this->client();

		return $client instanceof No404_Client && $client->is_configured();
	}

	/**
	 * The configured core client.
	 *
	 * @return No404_Client
	 */
	public function client() {
		$blog_id = function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 0;

		// On multisite, rebuild the client when the store changed (the key is per site).
		if ( null !== $this->client && $this->client_blog_id === $blog_id ) {
			return $this->client;
		}

		$settings = No404_Options::all();

		$config = array(
			'api_base'         => $settings['api_base'],
			'api_key'          => $settings['api_key'],
			'timeout_ms'       => $settings['timeout_ms'],
			'cache_ttl'        => $settings['cache_ttl'],
			'force_301'        => ! empty( $settings['force_301'] ),
			'allowed_hosts'    => $this->allowed_hosts(),
			'ignored_prefixes' => $this->ignored_prefixes(),
			'user_agent'       => 'no404-wordpress/' . NO404_VERSION . '; ' . home_url( '/' ),
			// Key for the pseudonymous visitor ID. wp_salt() is derived from this
			// install's secret keys; home_url keeps multisite sites apart. no404
			// never sees it, so it cannot turn an ID back into an IP address.
			'visitor_secret'   => wp_salt( 'no404_visitor' ) . '|' . home_url( '/' ),
		);

		/**
		 * Chance to modify the core client configuration.
		 *
		 * @param array $config The configuration.
		 */
		$config = apply_filters( 'no404_client_config', $config );

		$this->client         = new No404_Client( $config, new No404_WP_Http(), new No404_WP_Cache() );
		$this->client_blog_id = $blog_id;

		return $this->client;
	}

	/**
	 * Hosts allowed as a redirect target.
	 *
	 * no404 builds its target from the site's own root URL, but we still collect
	 * every variant to cover the www / non-www difference and a separate WordPress
	 * address (site_url).
	 *
	 * @return string[]
	 */
	public function allowed_hosts() {
		$hosts = array();

		foreach ( array( home_url( '/' ), site_url( '/' ) ) as $url ) {
			$host = wp_parse_url( $url, PHP_URL_HOST );
			if ( ! is_string( $host ) || '' === $host ) {
				continue;
			}
			$host    = strtolower( $host );
			$hosts[] = $host;
			// Accept both the www and the bare domain.
			$hosts[] = ( 0 === strpos( $host, 'www.' ) ) ? substr( $host, 4 ) : 'www.' . $host;
		}

		/**
		 * Allowed redirect hosts. Open-redirect protection depends on this list —
		 * be careful when extending it.
		 *
		 * @param string[] $hosts Host listesi.
		 */
		$hosts = apply_filters( 'no404_allowed_hosts', array_values( array_unique( array_filter( $hosts ) ) ) );

		return is_array( $hosts ) ? $hosts : array();
	}

	/**
	 * WordPress paths that are never asked about, plus the user's own prefixes.
	 *
	 * @return string[]
	 */
	protected function ignored_prefixes() {
		$core = array(
			'/wp-admin',
			'/wp-json',
			'/wp-content',
			'/wp-includes',
			'/wp-login.php',
			'/wp-cron.php',
			'/xmlrpc.php',
			'/feed',
			'/comments',
			'/trackback',
		);

		// If the installation moved its content directory, include that too.
		$content = wp_parse_url( content_url( '/' ), PHP_URL_PATH );
		if ( is_string( $content ) && '' !== $content && '/' !== $content ) {
			$core[] = rtrim( $content, '/' );
		}

		return array_values( array_unique( array_merge( $core, No404_Options::exclude_prefixes() ) ) );
	}
}

/**
 * Shorthand accessor.
 *
 * @return No404_Plugin
 */
function no404() {
	return No404_Plugin::instance();
}

no404()->boot();
