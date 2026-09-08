<?php
/**
 * no404 çekirdek davranış testleri — WordPress olmadan çalışır.
 * Çalıştırma: php test-core.php
 */

define( 'ABSPATH', true ); // Doğrudan erişim korumasını geç.

// Çekirdeğin dokunduğu TEK WordPress fonksiyonu. Gerçeğinin de yaptığı şey
// budur (eski PHP'nin şemasız URL tutarsızlığını normalleştirir); burada
// sahtesini tanımlıyoruz ki sınıf WordPress yüklenmeden de sınanabilsin.
if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( $url, $component );
	}
}

$base = __DIR__ . '/../includes/';
require $base . 'interface-no404-http.php';
require $base . 'interface-no404-cache.php';
require $base . 'class-no404-client.php';

/** Sahte HTTP: sıraya konan yanıtları döner, çağrı sayısını sayar. */
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

/** Sahte cache: bellek içi, TTL yok sayılır (test kısa). */
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
			'allowed_hosts' => array( 'magaza.com', 'www.magaza.com' ),
			'ignored_prefixes' => array( '/wp-admin', '/wp-json' ),
		),
		$extra
	);
	return new No404_Client( $config, $http, $cache );
}

echo "\n=== normalize_path (sunucudaki cleanPath ile aynı olmalı) ===\n";
$c = make_client( new FakeHttp(), new FakeCache() );
check( 'sondaki slash atilir', $c->normalize_path( '/urun/altin-yuzuk/' ), '/urun/altin-yuzuk' );
check( 'cift slash tekleşir', $c->normalize_path( '//urun//x' ), '/urun/x' );
check( 'sorgu dizesi atilir', $c->normalize_path( '/urun?utm_source=google' ), '/urun' );
check( 'fragment atilir', $c->normalize_path( '/urun#bolum' ), '/urun' );
check( 'tam URL yola iner', $c->normalize_path( 'https://magaza.com/urun/x' ), '/urun/x' );
check( 'ters slash duzelir', $c->normalize_path( '\\urun\\x' ), '/urun/x' );
check( 'kok korunur', $c->normalize_path( '/' ), '/' );
check( 'satir sonu temizlenir', $c->normalize_path( "/urun\r\n" ), '/urun' );

echo "\n=== is_ignored_path (kota koruması) ===\n";
check( 'css atlanir', $c->is_ignored_path( '/tema/style.css' ), true );
check( 'png atlanir', $c->is_ignored_path( '/gorsel/foto.PNG' ), true );
check( 'wp-admin atlanir', $c->is_ignored_path( '/wp-admin/edit.php' ), true );
check( 'wp-json atlanir', $c->is_ignored_path( '/wp-json/wp/v2/posts' ), true );
check( 'well-known atlanir', $c->is_ignored_path( '/.well-known/acme' ), true );
check( 'gercek urun atlanmaz', $c->is_ignored_path( '/14-gram-altin-yuzuk-102' ), false );
check( 'nokta iceren slug atlanmaz', $c->is_ignored_path( '/urun/3.5-mm-kablo' ), false );
check( 'onek yanlis eslesmez', $c->is_ignored_path( '/wp-adminler-icin-rehber' ), false );

echo "\n=== decide_status (301/302 karari) ===\n";
check( 'REDIRECT -> 301', $c->decide_status( array( 'source' => 'REDIRECT', 'score' => 1.0 ) ), 301 );
check( 'CATALOG yuksek skor -> 301', $c->decide_status( array( 'source' => 'CATALOG', 'score' => 0.92 ) ), 301 );
check( 'CATALOG esik ustu tam 0.5 -> 301', $c->decide_status( array( 'source' => 'CATALOG', 'score' => 0.5 ) ), 301 );
check( 'CATALOG dusuk skor -> 302', $c->decide_status( array( 'source' => 'CATALOG', 'score' => 0.42 ) ), 302 );
check( 'FALLBACK -> 302', $c->decide_status( array( 'source' => 'FALLBACK', 'score' => 0.0 ) ), 302 );
$forced = make_client( new FakeHttp(), new FakeCache(), array( 'force_301' => true ) );
check( 'force_301 acikken FALLBACK -> 301', $forced->decide_status( array( 'source' => 'FALLBACK', 'score' => 0.0 ) ), 301 );

echo "\n=== validate_target (acik yonlendirme + dongu korumasi) ===\n";
check( 'izinli host gecer', $c->validate_target( 'https://magaza.com/yeni', '/eski' ), 'https://magaza.com/yeni' );
check( 'yabanci host reddedilir', $c->validate_target( 'https://evil.com/x', '/eski' ), '' );
check( 'protokol-goreli reddedilir', $c->validate_target( '//evil.com/x', '/eski' ), '' );
check( 'javascript: reddedilir', $c->validate_target( 'javascript:alert(1)', '/eski' ), '' );
check( 'data: reddedilir', $c->validate_target( 'data:text/html,x', '/eski' ), '' );
check( 'goreli hedef gecer', $c->validate_target( '/yeni-urun', '/eski' ), '/yeni-urun' );
check( 'DONGU: ayni yol reddedilir', $c->validate_target( 'https://magaza.com/eski', '/eski' ), '' );
check( 'DONGU: goreli ayni yol reddedilir', $c->validate_target( '/eski', '/eski' ), '' );
check( 'DONGU: sondaki slash farki da dongudur', $c->validate_target( 'https://magaza.com/eski/', '/eski' ), '' );
check( 'baslik enjeksiyonu reddedilir', $c->validate_target( "https://magaza.com/x\r\nX-Evil: 1", '/eski' ), '' );
check( 'bos hedef reddedilir', $c->validate_target( '', '/eski' ), '' );

echo "\n=== resolve: onbellek kotayi koruyor mu? ===\n";
$http  = new FakeHttp();
$cache = new FakeCache();
$cl    = make_client( $http, $cache );
$http->queue = array( ok_response( '{"success":true,"found":true,"redirect":"https://magaza.com/yeni","score":0.9,"source":"CATALOG"}' ) );
$r1 = $cl->resolve( '/eski-urun' );
$r2 = $cl->resolve( '/eski-urun' );
$r3 = $cl->resolve( '/eski-urun/' );          // normalize -> ayni anahtar
$r4 = $cl->resolve( '/eski-urun?utm=abc' );   // sorgu atilir -> ayni anahtar
check( 'ayni yol icin TEK API cagrisi', $http->calls, 1 );
check( 'ikinci cagri cache den doner', $r2['redirect'], 'https://magaza.com/yeni' );
check( 'sondaki slash cache i bolmez', $r3['redirect'], 'https://magaza.com/yeni' );
check( 'utm parametresi cache i bolmez', $r4['redirect'], 'https://magaza.com/yeni' );

echo "\n=== resolve: NEGATIF sonuc da cache lenir ===\n";
$http  = new FakeHttp();
$cache = new FakeCache();
$cl    = make_client( $http, $cache );
$http->queue = array(
	ok_response( '{"success":true,"found":false,"redirect":null,"score":0,"source":"NONE"}' ),
	ok_response( '{"success":true,"found":true,"redirect":"https://magaza.com/x","score":0.9,"source":"CATALOG"}' ),
);
$cl->resolve( '/hic-yok' );
$cl->resolve( '/hic-yok' );
$cl->resolve( '/hic-yok' );
check( 'bulunamayan yol tekrar sorulmaz', $http->calls, 1 );

echo "\n=== resolve: statik dosya API ye HIC sorulmaz ===\n";
$http  = new FakeHttp();
$cl    = make_client( $http, new FakeCache() );
$cl->resolve( '/tema/style.css' );
$cl->resolve( '/wp-admin/x' );
$cl->resolve( '/gorsel/a.jpg' );
check( 'kara listede sifir cagri', $http->calls, 0 );

echo "\n=== FAIL-OPEN: no404 dustugunde magaza ayakta ===\n";
$http  = new FakeHttp();
$cache = new FakeCache();
$cl    = make_client( $http, $cache );
$http->queue = array( array( 'ok' => false, 'status' => 0, 'body' => '', 'error' => 'cURL timeout' ) );
$r = $cl->resolve( '/eski-urun' );
check( 'timeout -> null (yonlendirme yok)', $r, null );
$r = $cl->resolve( '/baska-urun' );
check( 'DEVRE KESICI: ikinci 404 API ye gitmez', $http->calls, 1 );
check( 'devre kesikken sonuc null', $r, null );

echo "\n=== HTTP hata kodlari ===\n";
foreach ( array( 404, 403, 429, 500 ) as $status ) {
	$http  = new FakeHttp();
	$cl    = make_client( $http, new FakeCache() );
	$http->queue = array( array( 'ok' => true, 'status' => $status, 'body' => '{"success":false,"message":"x"}', 'error' => '' ) );
	check( "HTTP $status -> null", $cl->resolve( '/eski' ), null );
}

echo "\n=== Bozuk yanit yutulur (fail-open) ===\n";
$http = new FakeHttp();
$cl   = make_client( $http, new FakeCache() );
$http->queue = array( ok_response( '<html>bu JSON degil</html>' ) );
check( 'HTML govde -> null', $cl->resolve( '/eski' ), null );

$http = new FakeHttp();
$cl   = make_client( $http, new FakeCache() );
$http->queue = array( ok_response( '{"success":true}' ) );
$r = $cl->resolve( '/eski' );
check( 'eksik alanlar cokmez', is_array( $r ) && null === $r['redirect'], true );

echo "\n=== Istek URL kurulumu ===\n";
$http = new FakeHttp();
$cl   = make_client( $http, new FakeCache() );
$cl->resolve( '/14-gram-altin-yüzük', 'https://google.com/search?q=x' );
check(
	'URL dogru kuruldu',
	$http->last_url,
	'https://no404.tr/api/v1/resolve/testkey123?path=' . rawurlencode( '/14-gram-altin-yüzük' ) . '&ref=' . rawurlencode( 'https://google.com/search?q=x' )
);

echo "\n=== Yapilandirilmamis eklenti sessizdir ===\n";
$http = new FakeHttp();
$cl   = new No404_Client( array( 'api_base' => 'https://no404.tr', 'api_key' => '' ), $http, new FakeCache() );
check( 'anahtar yokken null', $cl->resolve( '/eski' ), null );
check( 'anahtar yokken sifir cagri', $http->calls, 0 );

echo "\n----------------------------------------\n";
echo "PASS: $passed   FAIL: $failed\n";
exit( $failed > 0 ? 1 : 0 );
