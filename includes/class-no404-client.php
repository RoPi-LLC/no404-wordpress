<?php
/**
 * no404 — platform-bağımsız çekirdek (davranış sözleşmesi).
 *
 * SINIF GÖVDESİ WordPress API'sine BAĞLI DEĞİLDİR: platformun HTTP ve önbellek
 * katmanları birer arayüzün (`No404_Http_Interface`, `No404_Cache_Interface`)
 * arkasındadır. `tests/test-core.php` bu sayede WordPress olmadan çalışır ve
 * eşleştirme/önbellek/301-302 mantığını izole doğrular. Buraya `wp_*` çağrısı
 * EKLEME — platforma özel her şey adaptörlere ait.
 *
 * Sorumlulukları:
 *   - İstek kurma ve kısa zaman aşımı (fail-open)
 *   - Yerel önbellek (kota koruması, negatifler dahil)
 *   - Statik/yönetim yollarını hiç sormama (kara liste)
 *   - 301/302 kararı (source + score)
 *   - Hedef doğrulama: açık yönlendirme ve döngü koruması
 *
 * @package no404
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class No404_Client {

	/** Önbellek şeması sürümü — yapı değişirse artır, eski kayıtlar es geçilir. */
	const CACHE_SCHEMA = 'v1';

	/** Bu skorun üstündeki CATALOG eşleşmeleri kalıcı (301) sayılır. */
	const HIGH_CONFIDENCE_SCORE = 0.5;

	/** Varsayılan zaman aşımı (ms). Mağazanın 404 sayfasını bekletmeyecek kadar kısa. */
	const DEFAULT_TIMEOUT_MS = 1500;

	/** Varsayılan sonuç ömrü (sn). */
	const DEFAULT_CACHE_TTL = 3600;

	/** API erişilemezken yeniden denemeden önce beklenecek süre (sn). */
	const OUTAGE_TTL = 60;

	/** Kota/limit dolduğunda beklenecek süre (sn). Aylık kota tükendiyse boşuna sormayalım. */
	const QUOTA_TTL = 300;

	/** Yapılandırma hatasında (geçersiz anahtar, pasif abonelik) bekleme (sn). */
	const CONFIG_ERROR_TTL = 300;

	/** API'nin kabul ettiği azami yol uzunluğu (ingestHitSchema ile aynı). */
	const MAX_PATH_LENGTH = 2048;

	/** Devre kesici (circuit breaker) anahtarı. */
	const OUTAGE_KEY = 'outage';

	/**
	 * Katalogda karşılığı olmayan, boşa kota yakan uzantılar.
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
	 * Hiç sorulmayacak yol önekleri. Platform sarmalayıcısı kendi öneklerini ekler.
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

	/** @var string API kökü, sondaki slash olmadan. */
	protected $api_base = '';

	/** @var string */
	protected $api_key = '';

	/** @var int */
	protected $timeout_ms = self::DEFAULT_TIMEOUT_MS;

	/** @var int */
	protected $cache_ttl = self::DEFAULT_CACHE_TTL;

	/** @var bool Tüm yönlendirmeleri 301 yap (varsayılan: kapalı). */
	protected $force_301 = false;

	/** @var string[] Yönlendirme hedefinde izin verilen host'lar (küçük harf). */
	protected $allowed_hosts = array();

	/** @var string İstemci kimliği (User-Agent). */
	protected $user_agent = 'no404-plugin';

	/**
	 * @param array                 $config Ayarlar.
	 * @param No404_Http_Interface  $http   HTTP adaptörü.
	 * @param No404_Cache_Interface $cache  Önbellek adaptörü.
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

	/** Eklenti çalışabilir durumda mı (anahtar + kök URL var mı)? */
	public function is_configured() {
		return '' !== $this->api_key && '' !== $this->api_base;
	}

	/**
	 * Bir 404 yolunu çözer.
	 *
	 * FAIL-OPEN: hiçbir koşulda exception fırlatmaz. no404 yavaşlarsa, düşerse
	 * veya bozuk yanıt dönerse `null` döner ve mağaza kendi 404'ünü render eder.
	 *
	 * @param string $path     404 dönen yol.
	 * @param string $referrer Ziyaretçinin geldiği adres (opsiyonel).
	 *
	 * @return array|null found/redirect/score/source, ya da yönlendirme yoksa null.
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

			// Devre kesici: API erişilemez/kota dolu iken her 404'te tekrar denemeyiz.
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
		} catch ( Throwable $e ) { // PHP 7+ fatal koruması.
			return null;
		}
	}

	/**
	 * HTTP yanıtını sonuca çevirir ve gerekli önbellekleri yazar.
	 *
	 * @param array  $response HTTP adaptörü çıktısı.
	 * @param string $key      Bu yola ait önbellek anahtarı.
	 *
	 * @return array|null
	 */
	protected function handle_response( array $response, $key ) {
		// Taşıma hatası (timeout, DNS, TLS) → devre kes, mağazayı bekletme.
		if ( empty( $response['ok'] ) ) {
			$this->cache->set( self::OUTAGE_KEY, 1, self::OUTAGE_TTL );
			return null;
		}

		$status = isset( $response['status'] ) ? (int) $response['status'] : 0;

		if ( 429 === $status ) {
			// Rate limit veya aylık kota. İkisi de kısa vadede değişmez.
			$this->cache->set( self::OUTAGE_KEY, 1, self::QUOTA_TTL );
			return null;
		}
		if ( 403 === $status || 404 === $status ) {
			// Geçersiz anahtar, duraklatılmış site, pasif abonelik → yapılandırma sorunu.
			$this->cache->set( self::OUTAGE_KEY, 1, self::CONFIG_ERROR_TTL );
			return null;
		}
		if ( $status >= 500 ) {
			$this->cache->set( self::OUTAGE_KEY, 1, self::OUTAGE_TTL );
			return null;
		}

		$payload = $this->decode( isset( $response['body'] ) ? $response['body'] : '' );

		if ( 200 !== $status || null === $payload || empty( $payload['success'] ) ) {
			// 422 (geçersiz yol) dahil: bu YOL için tekrar sormanın anlamı yok.
			$this->cache->set( $key, $this->empty_result(), $this->cache_ttl );
			return null;
		}

		$result = array(
			'found'    => ! empty( $payload['found'] ),
			'redirect' => isset( $payload['redirect'] ) && is_string( $payload['redirect'] ) ? $payload['redirect'] : null,
			'score'    => isset( $payload['score'] ) ? (float) $payload['score'] : 0.0,
			'source'   => isset( $payload['source'] ) && is_string( $payload['source'] ) ? $payload['source'] : 'NONE',
		);

		// Negatif sonuç da cache'lenir — kotayı asıl bu korur.
		$this->cache->set( $key, $result, $this->cache_ttl );

		return $result;
	}

	/**
	 * Yönlendirme HTTP durum kodunu belirler.
	 *
	 * 301 tarayıcıda ve Google'da KALICI olarak cache'lenir; tahmine dayalı bir
	 * eşleşmeyi 301 vermek, katalog sonradan düzelse bile geri alınamaz.
	 *
	 * @param array $result resolve() çıktısı.
	 * @return int 301 veya 302.
	 */
	public function decide_status( array $result ) {
		if ( $this->force_301 ) {
			return 301;
		}

		$source = isset( $result['source'] ) ? $result['source'] : 'NONE';
		$score  = isset( $result['score'] ) ? (float) $result['score'] : 0.0;

		if ( 'REDIRECT' === $source ) {
			return 301; // İnsan tanımlamış, kesin.
		}
		if ( 'CATALOG' === $source && $score >= self::HIGH_CONFIDENCE_SCORE ) {
			return 301;
		}

		return 302; // Düşük skorlu CATALOG ve FALLBACK → geri alınabilir kalsın.
	}

	/**
	 * Yönlendirme hedefini doğrular: açık yönlendirme ve döngü koruması.
	 *
	 * @param string $redirect     API'nin döndüğü hedef.
	 * @param string $current_path Şu anki (404 dönen) yol, normalize edilmiş.
	 *
	 * @return string Güvenli URL, ya da reddedilirse boş string.
	 */
	public function validate_target( $redirect, $current_path ) {
		if ( ! is_string( $redirect ) || '' === trim( $redirect ) ) {
			return '';
		}

		// Başlık enjeksiyonu: kontrol karakterleri asla geçmez.
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

		// Şemasız/host'suz göreli hedef → kendi sitemiz kabul edilir.
		if ( empty( $parts['host'] ) ) {
			// "//evil.com" protokol-göreli; göreli olmayan girdiler de reddedilir.
			if ( 0 !== strpos( $redirect, '/' ) || 0 === strpos( $redirect, '//' ) ) {
				return '';
			}
			if ( $this->normalize_path( $redirect ) === $current_path ) {
				return ''; // Döngü.
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

		// Döngü: izinli host üzerinde aynı yola yönlendirme.
		if ( $this->normalize_path( isset( $parts['path'] ) ? $parts['path'] : '/' ) === $current_path ) {
			return '';
		}

		return $redirect;
	}

	/**
	 * Bu yol hiç sorulmamalı mı?
	 *
	 * @param string $path Normalize edilmiş yol.
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
	 * URL'yi bileşenlerine ayırır.
	 *
	 * WordPress `parse_url()` yerine `wp_parse_url()` ister: eski PHP sürümleri
	 * şemasız (`//host/yol`) girdilerde tutarsız sonuç veriyordu, wp_parse_url
	 * bunu normalleştirir.
	 *
	 * Sarmalayıcının tek sebebi `tests/test-core.php`: çekirdek, WordPress
	 * yüklenmeden de çalışabilmeli. Test kendi `wp_parse_url` sahtesini tanımlar,
	 * yani burada yedek yola gerek yok — fonksiyon her koşulda vardır.
	 *
	 * @param string $url Ayrıştırılacak URL.
	 * @return array|false|null Bileşenler, ya da ayrıştırılamadıysa false/null.
	 */
	private static function parse_url_parts( $url ) {
		return wp_parse_url( $url );
	}

	/**
	 * Yolu kanonik biçime getirir.
	 *
	 * no404 sunucusundaki `cleanPath` ile AYNI kuralları uygular; böylece yerel
	 * önbellek anahtarı ile sunucunun gördüğü yol örtüşür. Sorgu dizesi atılır —
	 * sunucu zaten yalnızca `pathname` alıyor, tutarsak `?utm_source=...`
	 * yüzünden önbellek parçalanır ve boşuna kota harcanır.
	 *
	 * @param string $raw Ham yol veya tam URL.
	 * @return string "/" ile başlayan yol, ya da boş string.
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

		// Sorgu ve fragment at.
		$raw = substr( $raw, 0, strcspn( $raw, '?#' ) );

		// Ters slash'ı slash say (WHATWG URL http(s) şemasında böyle davranır).
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
	 * Ayar ekranındaki "bağlantıyı test et" için tanılama çağrısı.
	 *
	 * Önbelleği ve devre kesiciyi ATLAR — kullanıcı gerçek durumu görmeli.
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
			max( 5000, $this->timeout_ms ), // Testte kullanıcı bekleyebilir.
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
	 * İstek URL'sini kurar.
	 *
	 * @param string $path     Normalize edilmiş yol.
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
	 * Yol için önbellek anahtarı. API anahtarı değişirse eski kayıtlar düşer.
	 *
	 * @param string $path Normalize edilmiş yol.
	 * @return string
	 */
	protected function cache_key( $path ) {
		return self::CACHE_SCHEMA . ':' . substr( md5( $this->api_key ), 0, 8 ) . ':' . md5( $path );
	}

	/** @return array Eşleşme bulunamadı sonucu (negatif önbellek için). */
	protected function empty_result() {
		return array(
			'found'    => false,
			'redirect' => null,
			'score'    => 0.0,
			'source'   => 'NONE',
		);
	}

	/**
	 * JSON çözer; bozuk gövde asla exception'a dönüşmez.
	 *
	 * @param string $body Ham gövde.
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
