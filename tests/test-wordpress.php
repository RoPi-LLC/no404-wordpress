<?php
/**
 * Entegrasyon testi: eklentiyi SAHTE WordPress fonksiyonlarıyla yükler ve
 * bir 404 isteğini uçtan uca çalıştırır.
 *
 * Amaç, gerçek bir WordPress kurulumu olmadan şunları yakalamak:
 *   - tanımsız/yanlış yazılmış WordPress fonksiyon çağrıları
 *   - kanca kaydı ve yönlendirme kararının doğru işlemesi
 *   - fail-open davranışının sarmalayıcı katmanda da korunması
 *
 * Çalıştırma: php tests/test-wordpress.php
 */

// ---------------------------------------------------------------- test altyapısı

/*
 * ÇIKTI TAMPONU ŞART: yönlendirici `headers_sent()` kontrolü yapar (doğru
 * davranış — başlık gönderilmişse yönlendiremeyiz). CLI'da ilk `echo` ile
 * headers_sent() true olur ve yönlendirme hiç denenmez. Tamponlayarak
 * gerçek istek koşullarını taklit ediyoruz.
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

function home_url( $path = '' ) { return 'https://magaza.com' . $path; }
function site_url( $path = '' ) { return 'https://magaza.com' . $path; }
function content_url( $path = '' ) { return 'https://magaza.com/wp-content' . $path; }
function plugin_dir_path( $file ) { return dirname( $file ) . '/'; }
function plugin_basename( $file ) { return 'no404/' . basename( $file ); }
function plugins_url( $path, $file ) { return 'https://magaza.com/wp-content/plugins/no404/' . $path; }
function admin_url( $path = '' ) { return 'https://magaza.com/wp-admin/' . $path; }
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
 * Gerçek `esc_url_raw` izin verilmeyen şemalarda BOŞ string döner; sahte sürüm
 * bunu taklit etmezse protokol doğrulaması test edilmemiş olur.
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
	$allowed = apply_filters( 'allowed_redirect_hosts', array( 'magaza.com' ) );
	return in_array( strtolower( $parts['host'] ), $allowed, true ) ? $location : $fallback;
}

function wp_redirect( $location, $status = 302, $by = '' ) {
	$GLOBALS['wp_redirects'][] = array( 'location' => $location, 'status' => $status, 'by' => $by );
	return false; // false döndürüyoruz ki test `exit` etmesin.
}

// WordPress'teki gerçek davranış: hedefi allowlist'e karşı doğrular, sonra
// wp_redirect'e devreder. Boş fallback ile doğrulama düşerse yönlendirme YOK.
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

function api_ok( $body ) {
	return array( 'response' => array( 'code' => 200 ), 'body' => $body );
}

// ---------------------------------------------------------------- eklentiyi yükle

$GLOBALS['wp_options']['no404_settings'] = array(
	'enabled'    => 1,
	'api_base'   => 'https://no404.tr',
	'api_key'    => 'no404_TESTKEY',
	'cache_ttl'  => 3600,
	'timeout_ms' => 1500,
	'force_301'  => 0,
);

// Çeviri kaydı: gerçek WordPress'te bu nesne eklentiler yüklenmeden ÖNCE kurulur.
// Sahtesini de öyle kuruyoruz, yoksa eklenti yolu bildiremez ve test bunu göremez.
class WP_Textdomain_Registry {
	public $custom = array();
	public function set_custom_path( $domain, $path ) {
		$this->custom[ $domain ] = $path;
	}
}
$GLOBALS['wp_textdomain_registry'] = new WP_Textdomain_Registry();

require __DIR__ . '/../no404.php';

echo "\n=== Eklenti yuklendi, kancalar bagli mi? ===\n";
t_check( 'init kancasi kayitli', isset( $GLOBALS['wp_actions']['init'] ), true );

// load_plugin_textdomain() çağrılmıyor (Plugin Check uyarısı); bunun yerine
// paketteki languages/ klasörü doğrudan kayda bildiriliyor. Bu satır düşerse
// pakete gömülü 7 dilin .mo dosyası HİÇ yüklenmez ve hata sessizce olur —
// arayüz her dilde İngilizce görünür, uyarı çıkmaz.
t_check(
	'ceviri klasoru kayda bildirildi',
	isset( $GLOBALS['wp_textdomain_registry']->custom['no404-auto-404-redirect'] ),
	true
);
t_check(
	'bildirilen yol eklentinin languages/ klasoru',
	basename( (string) $GLOBALS['wp_textdomain_registry']->custom['no404-auto-404-redirect'] ),
	'languages'
);
// Yorumlarda adı geçebilir; ARANAN gerçek çağrıdır, o yüzden token taraması.
$no404_textdomain_calls = 0;
foreach ( array_merge( array( __DIR__ . '/../no404.php' ), glob( __DIR__ . '/../includes/*.php' ) ) as $no404_src ) {
	foreach ( token_get_all( file_get_contents( $no404_src ) ) as $no404_tok ) {
		if ( is_array( $no404_tok ) && T_STRING === $no404_tok[0] && 'load_plugin_textdomain' === $no404_tok[1] ) {
			$no404_textdomain_calls++;
		}
	}
}
t_check( 'kaynakta load_plugin_textdomain cagrisi yok', $no404_textdomain_calls, 0 );

// `init` tetiklenince yönlendirici bağlanmalı.
do_action( 'init' );
t_check( 'template_redirect kancasi kayitli', isset( $GLOBALS['wp_actions']['template_redirect'] ), true );
t_check( 'oncelik 9999 (diger SEO eklentilerinden sonra)', isset( $GLOBALS['wp_actions']['template_redirect'][9999] ), true );

/**
 * Bir 404 isteğini simüle eder.
 *
 * $fresh=true önbelleği VE devre kesiciyi temizler. Bu şart: bir senaryoda
 * oluşan taşıma hatası devre kesiciyi tetikler ve sonraki senaryolar sessizce
 * hiç istek göndermez — testler de "geçmiş" görünür ama aslında hiçbir şeyi
 * doğrulamamış olur.
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

echo "\n=== Yuksek skorlu katalog eslesmesi -> 301 ===\n";
$r = simulate(
	'/eski-altin-yuzuk',
	array( api_ok( '{"success":true,"found":true,"redirect":"https://magaza.com/14-gram-altin-yuzuk","score":0.92,"source":"CATALOG"}' ) )
);
t_check( 'yonlendirme yapildi', count( $r ), 1 );
t_check( 'hedef dogru', $r[0]['location'], 'https://magaza.com/14-gram-altin-yuzuk' );
t_check( 'durum 301', $r[0]['status'], 301 );
t_check( 'X-Redirect-By: no404', $r[0]['by'], 'no404' );

echo "\n=== Elle tanimli yonlendirme -> 301 ===\n";
$r = simulate(
	'/kampanya-2019',
	array( api_ok( '{"success":true,"found":true,"redirect":"https://magaza.com/kampanyalar","score":1,"source":"REDIRECT"}' ) )
);
t_check( 'durum 301', $r[0]['status'], 301 );

echo "\n=== Dusuk skorlu tahmin -> 302 (geri alinabilir) ===\n";
$r = simulate(
	'/belirsiz-sayfa',
	array( api_ok( '{"success":true,"found":true,"redirect":"https://magaza.com/olabilir","score":0.35,"source":"CATALOG"}' ) )
);
t_check( 'durum 302', $r[0]['status'], 302 );

echo "\n=== FALLBACK -> 302 ===\n";
$r = simulate(
	'/tamamen-alakasiz',
	array( api_ok( '{"success":true,"found":false,"redirect":"https://magaza.com/","score":0,"source":"FALLBACK"}' ) )
);
t_check( 'durum 302', $r[0]['status'], 302 );

echo "\n=== Eslesme yok -> yonlendirme YOK (tema 404 gosterir) ===\n";
$r = simulate(
	'/hicbir-sey',
	array( api_ok( '{"success":true,"found":false,"redirect":null,"score":0,"source":"NONE"}' ) )
);
t_check( 'yonlendirme yok', count( $r ), 0 );

echo "\n=== FAIL-OPEN: API cokse bile sayfa render edilir ===\n";
$r = simulate( '/eski-urun-x', array( new WP_Error( 'http_request_failed', 'Operation timed out' ) ) );
t_check( 'yonlendirme yok, hata yutuldu', count( $r ), 0 );

echo "\n=== ACIK YONLENDIRME: yabanci host reddedilir ===\n";
$GLOBALS['http_calls'] = 0;
$r = simulate(
	'/tuzak',
	array( api_ok( '{"success":true,"found":true,"redirect":"https://evil.com/phishing","score":0.99,"source":"CATALOG"}' ) )
);
t_check( 'API gercekten soruldu (test bos gecmiyor)', $GLOBALS['http_calls'], 1 );
t_check( 'evil.com REDDEDILDI', count( $r ), 0 );

echo "\n=== DONGU: kendine yonlendirme reddedilir ===\n";
$GLOBALS['http_calls'] = 0;
$r = simulate(
	'/dongu',
	array( api_ok( '{"success":true,"found":true,"redirect":"https://magaza.com/dongu","score":0.99,"source":"CATALOG"}' ) )
);
t_check( 'API gercekten soruldu (test bos gecmiyor)', $GLOBALS['http_calls'], 1 );
t_check( 'kendine yonlendirme REDDEDILDI', count( $r ), 0 );

echo "\n=== 404 olmayan sayfada hic calismaz ===\n";
$GLOBALS['wp_state']['is_404'] = false;
$GLOBALS['http_calls'] = 0;
$r = simulate( '/normal-sayfa', array( api_ok( '{"success":true,"found":true,"redirect":"https://magaza.com/x","score":0.9,"source":"CATALOG"}' ) ) );
t_check( 'yonlendirme yok', count( $r ), 0 );
t_check( 'API ye hic sorulmadi', $GLOBALS['http_calls'], 0 );
$GLOBALS['wp_state']['is_404'] = true;

echo "\n=== Statik dosya API ye sorulmaz ===\n";
$GLOBALS['http_calls'] = 0;
simulate( '/wp-content/uploads/foto.png' );
simulate( '/wp-admin/edit.php' );
simulate( '/tema/style.css' );
t_check( 'sifir API cagrisi', $GLOBALS['http_calls'], 0 );

echo "\n=== Onbellek: ayni yol tekrar sorulmaz (kota) ===\n";
$body = '{"success":true,"found":true,"redirect":"https://magaza.com/hedef","score":0.9,"source":"CATALOG"}';
simulate( '/tekrar-eden-bot-yolu', array( api_ok( $body ) ) ); // fresh
$GLOBALS['http_calls'] = 0;
$r  = simulate( '/tekrar-eden-bot-yolu', array(), null, false );
$r2 = simulate( '/tekrar-eden-bot-yolu/', array(), null, false );
t_check( 'sonraki isteklerde SIFIR API cagrisi', $GLOBALS['http_calls'], 0 );
t_check( 'cache den yine yonlendiriyor', $r[0]['location'], 'https://magaza.com/hedef' );
t_check( 'sondaki slash cache i bolmedi', count( $r2 ), 1 );

echo "\n=== DEVRE KESICI: API dustukten sonra bombardiman yok ===\n";
simulate( '/kesici-1', array( new WP_Error( 'http_request_failed', 'timed out' ) ) ); // fresh
$GLOBALS['http_calls'] = 0;
$r = simulate( '/kesici-2', array( api_ok( $body ) ), null, false );
t_check( 'ikinci 404 API ye gitmedi', $GLOBALS['http_calls'], 0 );
t_check( 'devre kesikken yonlendirme yok', count( $r ), 0 );

echo "\n=== Istek: referer gonderiliyor, timeout uygulaniyor ===\n";
simulate( '/referer-testi', array( api_ok( $body ) ), 'https://google.com/search?q=yuzuk' );
t_check(
	'ref parametresi var',
	false !== strpos( $GLOBALS['last_http_url'], 'ref=' . rawurlencode( 'https://google.com/search?q=yuzuk' ) ),
	true
);
t_check( 'timeout 1.5 sn', $GLOBALS['last_http_args']['timeout'], 1.5 );
t_check( 'API anahtari URL de', false !== strpos( $GLOBALS['last_http_url'], '/resolve/no404_TESTKEY?' ), true );

echo "\n=== POST istegi islenmez ===\n";
$GLOBALS['http_calls'] = 0;
$_SERVER['REQUEST_URI'] = '/post-testi';
$_SERVER['REQUEST_METHOD'] = 'POST';
$GLOBALS['wp_redirects'] = array();
do_action( 'template_redirect' );
t_check( 'POST atlanir', $GLOBALS['http_calls'], 0 );

echo "\n=== Kapaliyken hic calismaz ===\n";
$GLOBALS['wp_options']['no404_settings']['enabled'] = 0;
$GLOBALS['wp_actions']['template_redirect'] = array();
$plugin = No404_Plugin::instance();
$reflection = new ReflectionProperty( 'No404_Plugin', 'client' );
$reflection->setAccessible( true );
$reflection->setValue( $plugin, null );
$plugin->setup_frontend();
t_check( 'kanca bagli degil', empty( $GLOBALS['wp_actions']['template_redirect'] ), true );

echo "\n=== Ayar temizleme (sanitize) ===\n";
$clean = No404_Options::sanitize(
	array(
		'enabled'    => '1',
		'api_base'   => 'https://no404.tr/',
		'api_key'    => '  no404_YeniAnahtar-123_  ',
		'cache_ttl'  => '10',      // alt sinirin altinda
		'timeout_ms' => '999999',  // ust sinirin ustunde
		'force_301'  => '',
	)
);
t_check( 'api_base sondaki slash atildi', $clean['api_base'], 'https://no404.tr' );
t_check( 'anahtar trim edildi', $clean['api_key'], 'no404_YeniAnahtar-123_' );
t_check( 'cache_ttl alt sinira cekildi', $clean['cache_ttl'], 60 );
t_check( 'timeout_ms ust sinira cekildi', $clean['timeout_ms'], 10000 );
t_check( 'force_301 kapali', $clean['force_301'], 0 );

$GLOBALS['wp_options']['no404_settings']['api_key'] = 'no404_ABCDEFGH1234';
$masked = No404_Options::masked_key();
t_check( 'maskeli anahtar tam anahtari sizdirmaz', false === strpos( $masked, 'ABCDEFGH' ), true );
t_check( 'maskeli anahtar son 4 haneyi gosterir', substr( $masked, -4 ), '1234' );

$clean = No404_Options::sanitize( array( 'api_key' => $masked, 'api_base' => 'https://no404.tr' ) );
t_check( 'maskeli deger kaydedilirse anahtar KORUNUR', $clean['api_key'], 'no404_ABCDEFGH1234' );

$clean = No404_Options::sanitize( array( 'api_key' => '', 'api_base' => 'https://no404.tr' ) );
t_check( 'bos deger kaydedilirse anahtar KORUNUR', $clean['api_key'], 'no404_ABCDEFGH1234' );

echo "\n=== Varsayilan no404 adresi ===\n";
$defaults = No404_Options::defaults();
t_check( 'varsayilan adres no404.tr', $defaults['api_base'], 'https://no404.tr' );
t_check( 'sabit ile ayni', No404_Options::DEFAULT_API_BASE, 'https://no404.tr' );

// Kullanicinin "yanlis yazdim, geri alayim" yolu: alani bosalt.
$GLOBALS['wp_options']['no404_settings']['api_base'] = 'https://eski-adres.example';
$clean = No404_Options::sanitize( array( 'api_base' => '' ) );
t_check( 'alan BOS birakilirsa varsayilana doner', $clean['api_base'], 'https://no404.tr' );

$clean = No404_Options::sanitize( array( 'api_base' => '   ' ) );
t_check( 'sadece bosluk da varsayilana doner', $clean['api_base'], 'https://no404.tr' );

// Kendi sunucusunda barindiran kullanici korunmali.
$clean = No404_Options::sanitize( array( 'api_base' => 'https://no404.sirketim.com/' ) );
t_check( 'kendi kurulumu ezilmez', $clean['api_base'], 'https://no404.sirketim.com' );

// Gecersiz girdi varsayilani DEGIL, eski degeri korumali (sessizce tasima yapmayalim).
$GLOBALS['wp_options']['no404_settings']['api_base'] = 'https://no404.sirketim.com';
$clean = No404_Options::sanitize( array( 'api_base' => 'ftp://yanlis' ) );
t_check( 'gecersiz URL eski degeri korur', $clean['api_base'], 'https://no404.sirketim.com' );
$GLOBALS['wp_options']['no404_settings']['api_base'] = 'https://no404.tr';

echo "\n=== Haric tutulan yollar ayristirmasi ===\n";
$GLOBALS['wp_options']['no404_settings']['exclude_paths'] = "/kampanya\n eski-blog/ \n\n/gecici";
t_check(
	'onekler normalize edildi',
	No404_Options::exclude_prefixes(),
	array( '/kampanya', '/eski-blog', '/gecici' )
);

echo "\n----------------------------------------\n";
echo "PASS: {$GLOBALS['no404_test']['pass']}   FAIL: {$GLOBALS['no404_test']['fail']}\n";

$no404_report = ob_get_clean();
echo $no404_report;

exit( $GLOBALS['no404_test']['fail'] > 0 ? 1 : 0 );
