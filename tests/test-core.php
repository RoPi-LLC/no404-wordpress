<?php
/**
 * no404 core behaviour tests — these run without WordPress.
 * Run with: php test-core.php
 */

define( 'ABSPATH', true ); // Satisfy the direct-access guard.

// The ONLY WordPress function the core touches. This is exactly what the real
// one does (it normalises old PHP's scheme-less URL inconsistency); we define a
// stub so the class can be exercised without WordPress loaded.
if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( $url, $component );
	}
}

$base = __DIR__ . '/../includes/';
require $base . 'interface-no404-http.php';
require $base . 'interface-no404-cache.php';
require $base . 'class-no404-client.php';

/** Fake HTTP: returns queued responses and counts the calls. */
class FakeHttp implements No404_Http_Interface {
	public $calls = 0;
	public $queue = array();
	public $last_url = '';

	public function get( $url, $timeout_ms, $user_agent ) {
		$this->calls++;
		$this->last_url = $url;
		if ( empty( $this->queue ) ) {
			return array( 'ok' => true, 'status' => 200, 'body' => '{"success":true,"found":false,"redirect":null,"score":0,"source":"NONE"}', 'error' => '' );
		}
		return array_shift( $this->queue );
	}
}

/** Fake cache: in memory, TTL ignored (the test is short-lived). */
class FakeCache implements No404_Cache_Interface {
	public $store = array();
	public function get( $key ) { return isset( $this->store[ $key ] ) ? $this->store[ $key ] : null; }
	public function set( $key, $value, $ttl ) { $this->store[ $key ] = $value; }
	public function flush() { $this->store = array(); }
}

function ok_response( $body ) {
	return array( 'ok' => true, 'status' => 200, 'body' => $body, 'error' => '' );
}

$passed = 0;
$failed = 0;

function check( $label, $actual, $expected ) {
	global $passed, $failed;
	$a = var_export( $actual, true );
	$e = var_export( $expected, true );
	if ( $a === $e ) {
		$passed++;
		echo "  PASS  $label\n";
	} else {
		$failed++;
		echo "  FAIL  $label\n         beklenen: $e\n         gelen   : $a\n";
	}
}

function make_client( $http, $cache, $extra = array() ) {
	$config = array_merge(
		array(
			'api_base'      => 'https://no404.tr',
			'api_key'       => 'testkey123',
			'allowed_hosts' => array( 'store.example', 'www.store.example' ),
			'ignored_prefixes' => array( '/wp-admin', '/wp-json' ),
		),
		$extra
	);
	return new No404_Client( $config, $http, $cache );
}

echo "\n=== normalize_path (must match cleanPath on the server) ===\n";
$c = make_client( new FakeHttp(), new FakeCache() );
check( 'trailing slash is dropped', $c->normalize_path( '/product/gold-ring/' ), '/product/gold-ring' );
check( 'double slash collapses', $c->normalize_path( '//product//x' ), '/product/x' );
check( 'query string is dropped', $c->normalize_path( '/product?utm_source=google' ), '/product' );
check( 'fragment is dropped', $c->normalize_path( '/product#section' ), '/product' );
check( 'full URL reduces to a path', $c->normalize_path( 'https://store.example/product/x' ), '/product/x' );
check( 'backslash is normalised', $c->normalize_path( '\\product\\x' ), '/product/x' );
check( 'root is preserved', $c->normalize_path( '/' ), '/' );
check( 'newlines are stripped', $c->normalize_path( "/product\r\n" ), '/product' );

echo "\n=== is_ignored_path (quota protection) ===\n";
check( 'css is skipped', $c->is_ignored_path( '/tema/style.css' ), true );
check( 'png is skipped', $c->is_ignored_path( '/gorsel/foto.PNG' ), true );
check( 'wp-admin is skipped', $c->is_ignored_path( '/wp-admin/edit.php' ), true );
check( 'wp-json is skipped', $c->is_ignored_path( '/wp-json/wp/v2/posts' ), true );
check( 'well-known is skipped', $c->is_ignored_path( '/.well-known/acme' ), true );
check( 'a real product is not skipped', $c->is_ignored_path( '/14-gram-gold-ring-102' ), false );
check( 'a slug containing a dot is not skipped', $c->is_ignored_path( '/product/3.5-mm-cable' ), false );
check( 'prefix does not match by accident', $c->is_ignored_path( '/wp-administration-guide' ), false );

echo "\n=== decide_status (the 301/302 decision) ===\n";
check( 'REDIRECT -> 301', $c->decide_status( array( 'source' => 'REDIRECT', 'score' => 1.0 ) ), 301 );
check( 'CATALOG high score -> 301', $c->decide_status( array( 'source' => 'CATALOG', 'score' => 0.92 ) ), 301 );
check( 'CATALOG exactly at the 0.5 threshold -> 301', $c->decide_status( array( 'source' => 'CATALOG', 'score' => 0.5 ) ), 301 );
check( 'CATALOG low score -> 302', $c->decide_status( array( 'source' => 'CATALOG', 'score' => 0.42 ) ), 302 );
check( 'FALLBACK -> 302', $c->decide_status( array( 'source' => 'FALLBACK', 'score' => 0.0 ) ), 302 );
$forced = make_client( new FakeHttp(), new FakeCache(), array( 'force_301' => true ) );
check( 'FALLBACK -> 301 when force_301 is on', $forced->decide_status( array( 'source' => 'FALLBACK', 'score' => 0.0 ) ), 301 );

echo "\n=== validate_target (open redirect + loop protection) ===\n";
check( 'allowed host passes', $c->validate_target( 'https://store.example/new', '/old' ), 'https://store.example/new' );
check( 'foreign host is rejected', $c->validate_target( 'https://evil.com/x', '/old' ), '' );
check( 'protocol-relative is rejected', $c->validate_target( '//evil.com/x', '/old' ), '' );
check( 'javascript: is rejected', $c->validate_target( 'javascript:alert(1)', '/old' ), '' );
check( 'data: is rejected', $c->validate_target( 'data:text/html,x', '/old' ), '' );
check( 'relative target passes', $c->validate_target( '/new-product', '/old' ), '/new-product' );
check( 'LOOP: same path is rejected', $c->validate_target( 'https://store.example/old', '/old' ), '' );
check( 'LOOP: same relative path is rejected', $c->validate_target( '/old', '/old' ), '' );
check( 'LOOP: a trailing-slash difference is still a loop', $c->validate_target( 'https://store.example/old/', '/old' ), '' );
check( 'header injection is rejected', $c->validate_target( "https://store.example/x\r\nX-Evil: 1", '/old' ), '' );
check( 'empty target is rejected', $c->validate_target( '', '/old' ), '' );

echo "\n=== resolve: does the cache protect the quota? ===\n";
$http  = new FakeHttp();
$cache = new FakeCache();
$cl    = make_client( $http, $cache );
$http->queue = array( ok_response( '{"success":true,"found":true,"redirect":"https://store.example/new","score":0.9,"source":"CATALOG"}' ) );
$r1 = $cl->resolve( '/old-product' );
$r2 = $cl->resolve( '/old-product' );
$r3 = $cl->resolve( '/old-product/' );          // normalize -> ayni anahtar
$r4 = $cl->resolve( '/old-product?utm=abc' );   // sorgu atilir -> ayni anahtar
check( 'ONE API call for the same path', $http->calls, 1 );
check( 'second call comes from the cache', $r2['redirect'], 'https://store.example/new' );
check( 'trailing slash does not split the cache', $r3['redirect'], 'https://store.example/new' );
check( 'a utm parameter does not split the cache', $r4['redirect'], 'https://store.example/new' );

echo "\n=== resolve: NEGATIVE results are cached too ===\n";
$http  = new FakeHttp();
$cache = new FakeCache();
$cl    = make_client( $http, $cache );
$http->queue = array(
	ok_response( '{"success":true,"found":false,"redirect":null,"score":0,"source":"NONE"}' ),
	ok_response( '{"success":true,"found":true,"redirect":"https://store.example/x","score":0.9,"source":"CATALOG"}' ),
);
$cl->resolve( '/nothing-here' );
$cl->resolve( '/nothing-here' );
$cl->resolve( '/nothing-here' );
check( 'an unmatched path is not asked about again', $http->calls, 1 );

echo "\n=== resolve: a static file is NEVER sent to the API ===\n";
$http  = new FakeHttp();
$cl    = make_client( $http, new FakeCache() );
$cl->resolve( '/tema/style.css' );
$cl->resolve( '/wp-admin/x' );
$cl->resolve( '/gorsel/a.jpg' );
check( 'zero calls for a blacklisted path', $http->calls, 0 );

echo "\n=== FAIL-OPEN: the store stays up when no404 is down ===\n";
$http  = new FakeHttp();
$cache = new FakeCache();
$cl    = make_client( $http, $cache );
$http->queue = array( array( 'ok' => false, 'status' => 0, 'body' => '', 'error' => 'cURL timeout' ) );
$r = $cl->resolve( '/old-product' );
check( 'timeout -> null (no redirect)', $r, null );
$r = $cl->resolve( '/another-product' );
check( 'CIRCUIT BREAKER: the second 404 does not reach the API', $http->calls, 1 );
check( 'result is null while the breaker is open', $r, null );

echo "\n=== HTTP error codes ===\n";
foreach ( array( 404, 403, 429, 500 ) as $status ) {
	$http  = new FakeHttp();
	$cl    = make_client( $http, new FakeCache() );
	$http->queue = array( array( 'ok' => true, 'status' => $status, 'body' => '{"success":false,"message":"x"}', 'error' => '' ) );
	check( "HTTP $status -> null", $cl->resolve( '/old' ), null );
}

echo "\n=== A malformed response is swallowed (fail-open) ===\n";
$http = new FakeHttp();
$cl   = make_client( $http, new FakeCache() );
$http->queue = array( ok_response( '<html>this is not JSON</html>' ) );
check( 'HTML body -> null', $cl->resolve( '/old' ), null );

$http = new FakeHttp();
$cl   = make_client( $http, new FakeCache() );
$http->queue = array( ok_response( '{"success":true}' ) );
$r = $cl->resolve( '/old' );
check( 'missing fields do not crash it', is_array( $r ) && null === $r['redirect'], true );

echo "\n=== Request URL construction ===\n";
$http = new FakeHttp();
$cl   = make_client( $http, new FakeCache() );
$cl->resolve( '/14-gram-altin-yüzük', 'https://google.com/search?q=x' );
check(
	'the URL was built correctly',
	$http->last_url,
	'https://no404.tr/api/v1/resolve/testkey123?path=' . rawurlencode( '/14-gram-altin-yüzük' ) . '&ref=' . rawurlencode( 'https://google.com/search?q=x' )
);

echo "\n=== An unconfigured plugin stays silent ===\n";
$http = new FakeHttp();
$cl   = new No404_Client( array( 'api_base' => 'https://no404.tr', 'api_key' => '' ), $http, new FakeCache() );
check( 'null when there is no key', $cl->resolve( '/old' ), null );
check( 'zero calls when there is no key', $http->calls, 0 );

echo "\n----------------------------------------\n";
echo "PASS: $passed   FAIL: $failed\n";
exit( $failed > 0 ? 1 : 0 );
