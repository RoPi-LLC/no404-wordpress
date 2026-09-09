<?php
/**
 * Integration test: loads the plugin against STUBBED WordPress functions and
 * runs a 404 request end to end.
 *
 * The point is to catch, without a real WordPress installation:
 *   - calls to undefined or misspelled WordPress functions
 *   - hook registration and the redirect decision behaving correctly
 *   - fail-open being preserved in the wrapper layer too
 *
 * Run with: php tests/test-wordpress.php
 */

// ---------------------------------------------------------------- test harness

/*
 * OUTPUT BUFFERING IS MANDATORY: the redirector checks `headers_sent()` (correct
 * behaviour — you cannot redirect once headers are out). On the CLI the first
 * `echo` makes headers_sent() true and the redirect is never attempted. Buffering
 * reproduces real request conditions.
 */
ob_start();

$GLOBALS['no404_test'] = array( 'pass' => 0, 'fail' => 0 );

function t_check( $label, $actual, $expected ) {
	$a = var_export( $actual, true );
	$e = var_export( $expected, true );
	if ( $a === $e ) {
		$GLOBALS['no404_test']['pass']++;
		echo "  PASS  $label\n";
	} else {
		$GLOBALS['no404_test']['fail']++;
		echo "  FAIL  $label\n         beklenen: $e\n         gelen   : $a\n";
	}
}

// ---------------------------------------------------------------- sahte WordPress

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['wp_actions']    = array();
$GLOBALS['wp_filters']    = array();
$GLOBALS['wp_options']    = array();
$GLOBALS['wp_transients'] = array();
$GLOBALS['wp_redirects']  = array();
$GLOBALS['http_queue']    = array();
$GLOBALS['http_calls']    = 0;
$GLOBALS['wp_state']      = array( 'is_404' => true, 'is_admin' => false );

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['wp_actions'][ $hook ][ $priority ][] = $callback;
	return true;
}
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['wp_filters'][ $hook ][ $priority ][] = $callback;
	return true;
}
function remove_filter( $hook, $callback, $priority = 10 ) {
	if ( ! isset( $GLOBALS['wp_filters'][ $hook ][ $priority ] ) ) {
		return false;
	}
	foreach ( $GLOBALS['wp_filters'][ $hook ][ $priority ] as $i => $cb ) {
		if ( $cb === $callback ) {
			unset( $GLOBALS['wp_filters'][ $hook ][ $priority ][ $i ] );
		}
	}
	return true;
}
function apply_filters( $hook, $value ) {
	$args = array_slice( func_get_args(), 2 );
	if ( empty( $GLOBALS['wp_filters'][ $hook ] ) ) {
		return $value;
	}
	$by_priority = $GLOBALS['wp_filters'][ $hook ];
	ksort( $by_priority );
	foreach ( $by_priority as $callbacks ) {
		foreach ( $callbacks as $cb ) {
			$value = call_user_func_array( $cb, array_merge( array( $value ), $args ) );
		}
	}
	return $value;
}
function do_action( $hook ) {
	if ( empty( $GLOBALS['wp_actions'][ $hook ] ) ) {
		return;
	}
	$by_priority = $GLOBALS['wp_actions'][ $hook ];
	ksort( $by_priority );
	foreach ( $by_priority as $callbacks ) {
		foreach ( $callbacks as $cb ) {
			call_user_func( $cb );
		}
	}
}

function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['wp_options'] ) ? $GLOBALS['wp_options'][ $name ] : $default;
}
function update_option( $name, $value, $autoload = null ) {
	$GLOBALS['wp_options'][ $name ] = $value;
	return true;
}
function delete_option( $name ) {
	unset( $GLOBALS['wp_options'][ $name ] );
	return true;
}

function get_transient( $key ) {
	if ( ! isset( $GLOBALS['wp_transients'][ $key ] ) ) {
		return false;
	}
	$entry = $GLOBALS['wp_transients'][ $key ];
	if ( $entry['expires'] < time() ) {
		unset( $GLOBALS['wp_transients'][ $key ] );
		return false;
	}
	return $entry['value'];
}
function set_transient( $key, $value, $ttl ) {
	$GLOBALS['wp_transients'][ $key ] = array( 'value' => $value, 'expires' => time() + $ttl );
	return true;
}

function home_url( $path = '' ) { return 'https://store.example' . $path; }
function site_url( $path = '' ) { return 'https://store.example' . $path; }
function content_url( $path = '' ) { return 'https://store.example/wp-content' . $path; }
function plugin_dir_path( $file ) { return dirname( $file ) . '/'; }
function plugin_basename( $file ) { return 'no404/' . basename( $file ); }
function plugins_url( $path, $file ) { return 'https://store.example/wp-content/plugins/no404/' . $path; }
function admin_url( $path = '' ) { return 'https://store.example/wp-admin/' . $path; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function get_current_blog_id() { return 1; }
function load_plugin_textdomain() { return true; }
function is_admin() { return $GLOBALS['wp_state']['is_admin']; }
function is_404() { return $GLOBALS['wp_state']['is_404']; }
function is_feed() { return false; }
function is_robots() { return false; }
function is_trackback() { return false; }
function is_preview() { return false; }
function is_customize_preview() { return false; }
function wp_doing_ajax() { return false; }
function wp_doing_cron() { return false; }
function add_settings_error() {}
function __( $text, $domain = '' ) { return $text; }
/**
 * The real `esc_url_raw` returns an EMPTY string for disallowed schemes; if the
 * stub does not reproduce that, protocol validation goes untested.
 */
function esc_url_raw( $url, $protocols = null ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return '';
	}
	if ( is_array( $protocols ) ) {
		$scheme = strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) );
		if ( ! in_array( $scheme, array_map( 'strtolower', $protocols ), true ) ) {
			return '';
		}
	}
	return $url;
}
function wp_unslash( $value ) { return is_string( $value ) ? stripslashes( $value ) : $value; }
function sanitize_text_field( $str ) { return trim( strip_tags( (string) $str ) ); }
function sanitize_textarea_field( $str ) { return trim( strip_tags( (string) $str ) ); }

function wp_validate_redirect( $location, $fallback = '' ) {
	$parts = parse_url( $location );
	if ( empty( $parts['host'] ) ) {
		return ( 0 === strpos( $location, '/' ) && 0 !== strpos( $location, '//' ) ) ? $location : $fallback;
	}
	$allowed = apply_filters( 'allowed_redirect_hosts', array( 'store.example' ) );
	return in_array( strtolower( $parts['host'] ), $allowed, true ) ? $location : $fallback;
}

function wp_redirect( $location, $status = 302, $by = '' ) {
	$GLOBALS['wp_redirects'][] = array( 'location' => $location, 'status' => $status, 'by' => $by );
	return false; // Returning false keeps the test from calling `exit`.
}

// The real WordPress behaviour: validate the target against the allow-list, then
// delegate to wp_redirect. With an empty fallback, a failed validation means NO
// redirect.
function wp_safe_redirect( $location, $status = 302, $by = '' ) {
	$location = wp_validate_redirect( $location, '' );
	if ( '' === $location ) {
		return false;
	}

	return wp_redirect( $location, $status, $by );
}

function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
class WP_Error {
	protected $message;
	public function __construct( $code = '', $message = '' ) { $this->message = $message; }
	public function get_error_message() { return $this->message; }
}

function wp_remote_get( $url, $args = array() ) {
	$GLOBALS['http_calls']++;
	$GLOBALS['last_http_url'] = $url;
	$GLOBALS['last_http_args'] = $args;
	if ( empty( $GLOBALS['http_queue'] ) ) {
		return array( 'response' => array( 'code' => 200 ), 'body' => '{"success":true,"found":false,"redirect":null,"score":0,"source":"NONE"}' );
	}
	return array_shift( $GLOBALS['http_queue'] );
}
function wp_remote_retrieve_response_code( $r ) { return is_array( $r ) ? $r['response']['code'] : 0; }
function wp_remote_retrieve_body( $r ) { return is_array( $r ) ? $r['body'] : ''; }
function wp_remote_retrieve_header( $r, $name ) {
	if ( ! is_array( $r ) || empty( $r['headers'] ) || ! is_array( $r['headers'] ) ) {
		return '';
	}
	foreach ( $r['headers'] as $key => $value ) {
		if ( strtolower( $key ) === strtolower( $name ) ) {
			return $value;
		}
	}
	return '';
}

function api_ok( $body ) {
	return array( 'response' => array( 'code' => 200 ), 'body' => $body );
}

// ---------------------------------------------------------------- load the plugin

$GLOBALS['wp_options']['no404_settings'] = array(
	'enabled'    => 1,
	'api_base'   => 'https://no404.tr',
	'api_key'    => 'no404_TESTKEY',
	'cache_ttl'  => 3600,
	'timeout_ms' => 1500,
	'force_301'  => 0,
);

// Translation registry: in real WordPress this object exists BEFORE plugins load.
// The stub is set up the same way; otherwise the plugin cannot register its path
// and the test would never see it.
class WP_Textdomain_Registry {
	public $custom = array();
	public function set_custom_path( $domain, $path ) {
		$this->custom[ $domain ] = $path;
	}
}
$GLOBALS['wp_textdomain_registry'] = new WP_Textdomain_Registry();

require __DIR__ . '/../no404.php';

echo "\n=== Plugin loaded; are the hooks registered? ===\n";
t_check( 'init hook is registered', isset( $GLOBALS['wp_actions']['init'] ), true );

// load_plugin_textdomain() is not called (Plugin Check warns about it); instead
// the bundled languages/ folder is registered directly. If that line disappears,
// none of the seven bundled .mo files load — and the failure is silent: the UI
// just appears in English in every language, with no warning.
t_check(
	'translations folder was registered',
	isset( $GLOBALS['wp_textdomain_registry']->custom['no404-auto-404-redirect'] ),
	true
);
t_check(
	'registered path is the plugin languages/ folder',
	basename( (string) $GLOBALS['wp_textdomain_registry']->custom['no404-auto-404-redirect'] ),
	'languages'
);
// The name may appear in comments; what we look for is a real CALL, hence the
// token scan.
$no404_textdomain_calls = 0;
foreach ( array_merge( array( __DIR__ . '/../no404.php' ), glob( __DIR__ . '/../includes/*.php' ) ) as $no404_src ) {
	foreach ( token_get_all( file_get_contents( $no404_src ) ) as $no404_tok ) {
		if ( is_array( $no404_tok ) && T_STRING === $no404_tok[0] && 'load_plugin_textdomain' === $no404_tok[1] ) {
			$no404_textdomain_calls++;
		}
	}
}
t_check( 'no load_plugin_textdomain call in the source', $no404_textdomain_calls, 0 );

// Firing `init` must register the redirector.
do_action( 'init' );
t_check( 'template_redirect hook is registered', isset( $GLOBALS['wp_actions']['template_redirect'] ), true );
t_check( 'priority 9999 (after other SEO plugins)', isset( $GLOBALS['wp_actions']['template_redirect'][9999] ), true );

/**
 * Simulates a 404 request.
 *
 * $fresh=true clears the cache AND the circuit breaker. That is mandatory: a
 * transport failure in one scenario trips the breaker, and later scenarios then
 * silently send no request at all — the tests look like they "passed" while
 * verifying nothing.
 */
function simulate( $request_uri, array $queue = array(), $referer = null, $fresh = true ) {
	if ( $fresh ) {
		$GLOBALS['wp_transients'] = array();
	}
	$GLOBALS['http_queue']   = $queue;
	$GLOBALS['wp_redirects'] = array();
	$_SERVER['REQUEST_URI']  = $request_uri;
	$_SERVER['REQUEST_METHOD'] = 'GET';
	if ( null === $referer ) {
		unset( $_SERVER['HTTP_REFERER'] );
	} else {
		$_SERVER['HTTP_REFERER'] = $referer;
	}
	do_action( 'template_redirect' );
	return $GLOBALS['wp_redirects'];
}

echo "\n=== High-scoring catalogue match -> 301 ===\n";
$r = simulate(
	'/old-gold-ring',
	array( api_ok( '{"success":true,"found":true,"redirect":"https://store.example/14-gram-gold-ring","score":0.92,"source":"CATALOG"}' ) )
);
t_check( 'a redirect happened', count( $r ), 1 );
t_check( 'target is correct', $r[0]['location'], 'https://store.example/14-gram-gold-ring' );
t_check( 'status is 301', $r[0]['status'], 301 );
t_check( 'X-Redirect-By: no404', $r[0]['by'], 'no404' );

echo "\n=== Manually defined redirect -> 301 ===\n";
$r = simulate(
	'/campaign-2019',
	array( api_ok( '{"success":true,"found":true,"redirect":"https://store.example/campaigns","score":1,"source":"REDIRECT"}' ) )
);
t_check( 'status is 301', $r[0]['status'], 301 );

echo "\n=== Low-scoring guess -> 302 (reversible) ===\n";
$r = simulate(
	'/uncertain-page',
	array( api_ok( '{"success":true,"found":true,"redirect":"https://store.example/maybe","score":0.35,"source":"CATALOG"}' ) )
);
t_check( 'status is 302', $r[0]['status'], 302 );

echo "\n=== FALLBACK -> 302 ===\n";
$r = simulate(
	'/completely-unrelated',
	array( api_ok( '{"success":true,"found":false,"redirect":"https://store.example/","score":0,"source":"FALLBACK"}' ) )
);
t_check( 'status is 302', $r[0]['status'], 302 );

echo "\n=== No match -> NO redirect (the theme shows its 404) ===\n";
$r = simulate(
	'/nothing',
	array( api_ok( '{"success":true,"found":false,"redirect":null,"score":0,"source":"NONE"}' ) )
);
t_check( 'no redirect', count( $r ), 0 );

echo "\n=== FAIL-OPEN: the page renders even if the API dies ===\n";
$r = simulate( '/old-product-x', array( new WP_Error( 'http_request_failed', 'Operation timed out' ) ) );
t_check( 'no redirect; the error was swallowed', count( $r ), 0 );

echo "\n=== OPEN REDIRECT: a foreign host is rejected ===\n";
$GLOBALS['http_calls'] = 0;
$r = simulate(
	'/trap',
	array( api_ok( '{"success":true,"found":true,"redirect":"https://evil.com/phishing","score":0.99,"source":"CATALOG"}' ) )
);
t_check( 'the API really was asked (the test is not vacuous)', $GLOBALS['http_calls'], 1 );
t_check( 'evil.com was REJECTED', count( $r ), 0 );

echo "\n=== LOOP: a self-redirect is rejected ===\n";
$GLOBALS['http_calls'] = 0;
$r = simulate(
	'/loop',
	array( api_ok( '{"success":true,"found":true,"redirect":"https://store.example/loop","score":0.99,"source":"CATALOG"}' ) )
);
t_check( 'the API really was asked (the test is not vacuous)', $GLOBALS['http_calls'], 1 );
t_check( 'self-redirect was REJECTED', count( $r ), 0 );

echo "\n=== Never runs on a page that is not a 404 ===\n";
$GLOBALS['wp_state']['is_404'] = false;
$GLOBALS['http_calls'] = 0;
$r = simulate( '/normal-page', array( api_ok( '{"success":true,"found":true,"redirect":"https://store.example/x","score":0.9,"source":"CATALOG"}' ) ) );
t_check( 'no redirect', count( $r ), 0 );
t_check( 'the API was never asked', $GLOBALS['http_calls'], 0 );
$GLOBALS['wp_state']['is_404'] = true;

echo "\n=== A static file is not sent to the API ===\n";
$GLOBALS['http_calls'] = 0;
simulate( '/wp-content/uploads/foto.png' );
simulate( '/wp-admin/edit.php' );
simulate( '/tema/style.css' );
t_check( 'zero API calls', $GLOBALS['http_calls'], 0 );

echo "\n=== Cache: the same path is not asked twice (quota) ===\n";
$body = '{"success":true,"found":true,"redirect":"https://store.example/target","score":0.9,"source":"CATALOG"}';
simulate( '/repeated-bot-path', array( api_ok( $body ) ) ); // fresh
$GLOBALS['http_calls'] = 0;
$r  = simulate( '/repeated-bot-path', array(), null, false );
$r2 = simulate( '/repeated-bot-path/', array(), null, false );
t_check( 'ZERO API calls on subsequent requests', $GLOBALS['http_calls'], 0 );
t_check( 'still redirects, from the cache', $r[0]['location'], 'https://store.example/target' );
t_check( 'trailing slash did not split the cache', count( $r2 ), 1 );

echo "\n=== CIRCUIT BREAKER: no hammering after the API goes down ===\n";
simulate( '/breaker-1', array( new WP_Error( 'http_request_failed', 'timed out' ) ) ); // fresh
$GLOBALS['http_calls'] = 0;
$r = simulate( '/breaker-2', array( api_ok( $body ) ), null, false );
t_check( 'the second 404 did not reach the API', $GLOBALS['http_calls'], 0 );
t_check( 'no redirect while the breaker is open', count( $r ), 0 );

echo "\n=== Request: referer is sent, timeout is applied ===\n";
simulate( '/referer-test', array( api_ok( $body ) ), 'https://google.com/search?q=ring' );
t_check(
	'the ref parameter is present',
	false !== strpos( $GLOBALS['last_http_url'], 'ref=' . rawurlencode( 'https://google.com/search?q=ring' ) ),
	true
);
t_check( 'timeout is 1.5 s', $GLOBALS['last_http_args']['timeout'], 1.5 );
t_check( 'a canonical-host redirect is followed', $GLOBALS['last_http_args']['redirection'], 2 );
t_check( 'the API key is in the URL', false !== strpos( $GLOBALS['last_http_url'], '/resolve/no404_TESTKEY?' ), true );

echo "\n=== A POST request is not handled ===\n";
$GLOBALS['http_calls'] = 0;
$_SERVER['REQUEST_URI'] = '/post-test';
$_SERVER['REQUEST_METHOD'] = 'POST';
$GLOBALS['wp_redirects'] = array();
do_action( 'template_redirect' );
t_check( 'POST is skipped', $GLOBALS['http_calls'], 0 );

echo "\n=== Never runs while switched off ===\n";
$GLOBALS['wp_options']['no404_settings']['enabled'] = 0;
$GLOBALS['wp_actions']['template_redirect'] = array();
$plugin = No404_Plugin::instance();
$reflection = new ReflectionProperty( 'No404_Plugin', 'client' );
$reflection->setAccessible( true );
$reflection->setValue( $plugin, null );
$plugin->setup_frontend();
t_check( 'the hook is not registered', empty( $GLOBALS['wp_actions']['template_redirect'] ), true );

echo "\n=== Settings sanitising ===\n";
$clean = No404_Options::sanitize(
	array(
		'enabled'    => '1',
		'api_base'   => 'https://no404.sirketim.com/',
		'api_key'    => '  no404_YeniAnahtar-123_  ',
		'cache_ttl'  => '10',      // alt sinirin altinda
		'timeout_ms' => '999999',  // ust sinirin ustunde
		'force_301'  => '',
	)
);
t_check( 'api_base trailing slash was dropped', $clean['api_base'], 'https://no404.sirketim.com' );
t_check( 'the key was trimmed', $clean['api_key'], 'no404_YeniAnahtar-123_' );
t_check( 'cache_ttl was clamped to the minimum', $clean['cache_ttl'], 60 );
t_check( 'timeout_ms was clamped to the maximum', $clean['timeout_ms'], 10000 );
t_check( 'force_301 is off', $clean['force_301'], 0 );

$GLOBALS['wp_options']['no404_settings']['api_key'] = 'no404_ABCDEFGH1234';
$masked = No404_Options::masked_key();
t_check( 'the masked key does not leak the full key', false === strpos( $masked, 'ABCDEFGH' ), true );
t_check( 'the masked key shows the last 4 characters', substr( $masked, -4 ), '1234' );

$clean = No404_Options::sanitize( array( 'api_key' => $masked, 'api_base' => 'https://no404.tr' ) );
t_check( 'saving the masked value KEEPS the key', $clean['api_key'], 'no404_ABCDEFGH1234' );

$clean = No404_Options::sanitize( array( 'api_key' => '', 'api_base' => 'https://no404.tr' ) );
t_check( 'saving an empty value KEEPS the key', $clean['api_key'], 'no404_ABCDEFGH1234' );

echo "\n=== The default no404 address ===\n";
$defaults = No404_Options::defaults();
t_check( 'the default address is www.no404.tr', $defaults['api_base'], 'https://www.no404.tr' );
t_check( 'matches the constant', No404_Options::DEFAULT_API_BASE, 'https://www.no404.tr' );

// 1.0.0 shipped the bare domain, which only redirects to the www host. Saving it,
// or reading back a database written by 1.0.0, must land on the canonical host.
$clean = No404_Options::sanitize( array( 'api_base' => 'https://no404.tr' ) );
t_check( 'the 1.0.0 address is rewritten on save', $clean['api_base'], 'https://www.no404.tr' );

$clean = No404_Options::sanitize( array( 'api_base' => 'https://no404.tr/' ) );
t_check( 'the trailing slash form is rewritten too', $clean['api_base'], 'https://www.no404.tr' );

$GLOBALS['wp_options']['no404_settings']['api_base'] = 'https://no404.tr';
t_check( 'an upgraded install reads the new address', No404_Options::get( 'api_base' ), 'https://www.no404.tr' );

$GLOBALS['wp_options']['no404_settings']['api_base'] = 'https://no404.sirketim.com';
t_check( 'a self-hosted address is left alone on read', No404_Options::get( 'api_base' ), 'https://no404.sirketim.com' );

// Kullanicinin "yanlis yazdim, geri alayim" yolu: alani bosalt.
$GLOBALS['wp_options']['no404_settings']['api_base'] = 'https://old-adres.example';
$clean = No404_Options::sanitize( array( 'api_base' => '' ) );
t_check( 'an EMPTY field restores the default', $clean['api_base'], 'https://www.no404.tr' );

$clean = No404_Options::sanitize( array( 'api_base' => '   ' ) );
t_check( 'whitespace only also restores the default', $clean['api_base'], 'https://www.no404.tr' );

// Kendi sunucusunda barindiran kullanici korunmali.
$clean = No404_Options::sanitize( array( 'api_base' => 'https://no404.sirketim.com/' ) );
t_check( 'a self-hosted install is not overwritten', $clean['api_base'], 'https://no404.sirketim.com' );

// Invalid input must keep the PREVIOUS value, not fall back to the default —
// no silent overwriting.
$GLOBALS['wp_options']['no404_settings']['api_base'] = 'https://no404.sirketim.com';
$clean = No404_Options::sanitize( array( 'api_base' => 'ftp://yanlis' ) );
t_check( 'an invalid URL keeps the previous value', $clean['api_base'], 'https://no404.sirketim.com' );
$GLOBALS['wp_options']['no404_settings']['api_base'] = 'https://www.no404.tr';

echo "\n=== Excluded paths parsing ===\n";
$GLOBALS['wp_options']['no404_settings']['exclude_paths'] = "/campaign\n old-blog/ \n\n/temporary";
t_check(
	'prefixes were normalised',
	No404_Options::exclude_prefixes(),
	array( '/campaign', '/old-blog', '/temporary' )
);

echo "\n----------------------------------------\n";
echo "PASS: {$GLOBALS['no404_test']['pass']}   FAIL: {$GLOBALS['no404_test']['fail']}\n";

$no404_report = ob_get_clean();
echo $no404_report;

exit( $GLOBALS['no404_test']['fail'] > 0 ? 1 : 0 );
