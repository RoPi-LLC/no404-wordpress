<?php
/**
 * Plugin Name:       no404 – Auto 404 Redirect
 * Plugin URI:        https://no404.tr
 * Description:       Automatically redirects visitors who hit a 404 to the closest matching live URL on your site, using a real server-side 301.
 * Version:           1.0.0
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

define( 'NO404_VERSION', '1.0.0' );
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
 * Eklentinin tekil (singleton) örneği.
 *
 * Multisite: her `switch_to_blog` sonrası ayarlar yeniden okunmalıdır, bu yüzden
 * istemci blog kimliğine göre önbelleklenir.
 */
final class No404_Plugin {

	/** @var No404_Plugin|null */
	protected static $instance = null;

	/** @var No404_Client|null */
	protected $client = null;

	/** @var int|null İstemcinin oluşturulduğu blog. */
	protected $client_blog_id = null;

	/** @return No404_Plugin */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Kancaları bağlar.
	 *
	 * Kaynak dil İngilizcedir; yerelleştirmeler `languages/` altındaki
	 * kataloglardan ve translate.wordpress.org'dan gelir.
	 */
	public function boot() {
		$this->register_translations_path();

		if ( is_admin() ) {
			require_once NO404_PLUGIN_DIR . 'includes/class-no404-admin.php';
			$admin = new No404_Admin();
			$admin->register();
		}

		// Ön yüz kancası `init`e ertelenir: eklenti yüklenirken `home_url()` henüz
		// güvenilir değildir (multisite / alan adı eşleme eklentileri sonra devreye girer).
		if ( ! is_admin() ) {
			add_action( 'init', array( $this, 'setup_frontend' ) );
		}
	}

	/** Ön yüz yönlendiricisini bağlar (yalnızca ayarlar uygunsa). */
	public function setup_frontend() {
		if ( ! $this->is_active() ) {
			return;
		}

		$redirector = new No404_Redirector( $this->client(), $this->allowed_hosts() );
		$redirector->register();
	}

	/**
	 * Pakete gömülü çeviri klasörünü WordPress'in katalog kaydına bildirir.
	 *
	 * `load_plugin_textdomain()` ÇAĞIRMIYORUZ: WordPress 4.6'dan beri çeviriler
	 * ilk `__()` çağrısında kendiliğinden yüklenir ve o fonksiyonu çağırmak
	 * WordPress.org Plugin Check tarafından uyarı sayılır.
	 *
	 * Ama kendiliğinden yükleme yalnızca `WP_LANG_DIR/plugins/` altına bakar —
	 * yani translate.wordpress.org'dan İNEN çevirilere. Eklentinin KENDİ
	 * `languages/` klasörü o listede yoktur (bkz. WP_Textdomain_Registry::
	 * get_paths_for_domain), çünkü oraya yalnızca load_plugin_textdomain()
	 * yol ekler. Sonuç: çağrıyı silip başka bir şey yapmazsak paketle gelen
	 * .mo dosyaları HİÇ yüklenmez ve eklenti her dilde İngilizce görünür.
	 *
	 * Bu yüzden yolu kayda doğrudan bildiriyoruz. Kayıt önce
	 * `WP_LANG_DIR/plugins/` bakar, sonra buraya düşer; yani wp.org'dan inen
	 * daha yeni çeviri her zaman paketteki kopyayı EZER — istediğimiz sıra bu.
	 *
	 * `WP_Textdomain_Registry` WordPress 6.1 ile geldi; yoksa sessizce geçilir
	 * ve eklenti kaynak diline (İngilizce) düşer.
	 */
	private function register_translations_path() {
		if ( ! isset( $GLOBALS['wp_textdomain_registry'] ) || ! $GLOBALS['wp_textdomain_registry'] instanceof WP_Textdomain_Registry ) {
			return;
		}

		$GLOBALS['wp_textdomain_registry']->set_custom_path( 'no404-auto-404-redirect', NO404_PLUGIN_DIR . 'languages' );
	}

	/** @return bool Yönlendirme açık ve yapılandırılmış mı? */
	public function is_active() {
		if ( empty( No404_Options::get( 'enabled' ) ) ) {
			return false;
		}

		$client = $this->client();

		return $client instanceof No404_Client && $client->is_configured();
	}

	/**
	 * Yapılandırılmış çekirdek istemci.
	 *
	 * @return No404_Client
	 */
	public function client() {
		$blog_id = function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 0;

		// Multisite'ta mağaza değiştiyse istemciyi yeniden kur (anahtar site başına).
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
		);

		/**
		 * Çekirdek istemci yapılandırmasını değiştirme imkânı.
		 *
		 * @param array $config Yapılandırma.
		 */
		$config = apply_filters( 'no404_client_config', $config );

		$this->client         = new No404_Client( $config, new No404_WP_Http(), new No404_WP_Cache() );
		$this->client_blog_id = $blog_id;

		return $this->client;
	}

	/**
	 * Yönlendirme hedefinde izin verilen host'lar.
	 *
	 * no404 hedefi sitenin kendi kök URL'sinden kurar; yine de www/non-www
	 * farkı ve ayrı WordPress adresi (site_url) için hepsini toplarız.
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
			// www ↔ kök alan adı ikisi de kabul edilsin.
			$hosts[] = ( 0 === strpos( $host, 'www.' ) ) ? substr( $host, 4 ) : 'www.' . $host;
		}

		/**
		 * İzinli yönlendirme host'ları. Açık yönlendirme (open redirect) koruması
		 * buna dayanır — genişletirken dikkatli olun.
		 *
		 * @param string[] $hosts Host listesi.
		 */
		$hosts = apply_filters( 'no404_allowed_hosts', array_values( array_unique( array_filter( $hosts ) ) ) );

		return is_array( $hosts ) ? $hosts : array();
	}

	/**
	 * Hiç sorulmayacak WordPress yolları + kullanıcının tanımladıkları.
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

		// Kurulum kendi içerik dizinini taşımışsa onu da ekle.
		$content = wp_parse_url( content_url( '/' ), PHP_URL_PATH );
		if ( is_string( $content ) && '' !== $content && '/' !== $content ) {
			$core[] = rtrim( $content, '/' );
		}

		return array_values( array_unique( array_merge( $core, No404_Options::exclude_prefixes() ) ) );
	}
}

/**
 * Kısayol erişim fonksiyonu.
 *
 * @return No404_Plugin
 */
function no404() {
	return No404_Plugin::instance();
}

no404()->boot();
