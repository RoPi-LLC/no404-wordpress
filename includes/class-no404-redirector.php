<?php
/**
 * 404 yakalayıcı: `template_redirect` üzerinde çalışır, sunucu tarafında
 * gerçek bir 301/302 üretir.
 *
 * ÖNCELİK: {@see No404_Redirector::PRIORITY} kasıtlı olarak yüksektir. Yoast,
 * Rank Math ve Redirection gibi eklentilerin kendi yönlendirme tabloları vardır
 * ve ONLAR ÖNCE ÇALIŞMALI. no404 son çaredir; onlar bir eşleşme bulup
 * yönlendirdiyse buraya hiç gelinmez.
 *
 * @package no404
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class No404_Redirector {

	/** `template_redirect` önceliği — diğer yönlendirme eklentilerinden SONRA. */
	const PRIORITY = 9999;

	/** @var No404_Client */
	protected $client;

	/** @var string[] Bu istek için geçerli izinli host'lar. */
	protected $allowed_hosts = array();

	/**
	 * @param No404_Client $client Çekirdek istemci.
	 * @param string[]     $allowed_hosts İzinli yönlendirme host'ları.
	 */
	public function __construct( No404_Client $client, array $allowed_hosts ) {
		$this->client        = $client;
		$this->allowed_hosts = $allowed_hosts;
	}

	/** Kancayı bağlar. */
	public function register() {
		add_action( 'template_redirect', array( $this, 'maybe_redirect' ), self::PRIORITY );
	}

	/**
	 * 404 ise no404'e sorar ve uygun hedefe yönlendirir.
	 *
	 * FAIL-OPEN: her koşulda sessizce geri döner; sayfa normal render edilir.
	 *
	 * @return void
	 */
	public function maybe_redirect() {
		if ( ! $this->should_handle() ) {
			return;
		}

		$path = $this->client->normalize_path( $this->current_path() );
		if ( '' === $path || '/' === $path ) {
			return;
		}

		/**
		 * Bu isteği no404'e sormadan atlamak için filtre.
		 *
		 * @param bool   $skip Atlansın mı.
		 * @param string $path Normalize edilmiş yol.
		 */
		if ( apply_filters( 'no404_skip_request', false, $path ) ) {
			return;
		}

		$result = $this->client->resolve( $path, $this->referrer() );
		if ( null === $result || empty( $result['redirect'] ) ) {
			return;
		}

		$target = $this->client->validate_target( $result['redirect'], $path );
		if ( '' === $target ) {
			return;
		}

		$status = $this->client->decide_status( $result );

		/**
		 * Yönlendirme hedefini ve durum kodunu son kez değiştirme imkânı.
		 *
		 * @param string $target Hedef URL.
		 * @param int    $status 301 veya 302.
		 * @param array  $result API sonucu.
		 * @param string $path   Kaynak yol.
		 */
		$target = (string) apply_filters( 'no404_redirect_target', $target, $status, $result, $path );
		$status = (int) apply_filters( 'no404_redirect_status', $status, $result, $path );

		if ( '' === $target || ( 301 !== $status && 302 !== $status ) ) {
			return;
		}

		// WordPress'in kendi allowlist'i: harici host'lara kaçışı engeller.
		// Kendi izinli host'larımızı yalnızca bu çağrı boyunca ekliyoruz; filtre
		// hem ön doğrulama hem wp_safe_redirect'in kendi doğrulaması boyunca açık
		// kalmalı, yoksa safe_redirect kendi hedefimizi harici sayıp düşürür.
		add_filter( 'allowed_redirect_hosts', array( $this, 'filter_allowed_hosts' ) );
		$validated = wp_validate_redirect( $target, '' );
		// Zincir yok: tek yönlendirme, sonra çıkış.
		$sent = ( '' !== $validated ) ? wp_safe_redirect( $validated, $status, 'no404' ) : false;
		remove_filter( 'allowed_redirect_hosts', array( $this, 'filter_allowed_hosts' ) );

		if ( $sent ) {
			exit;
		}
	}

	/**
	 * `allowed_redirect_hosts` filtresi.
	 *
	 * @param string[] $hosts Mevcut izinli host'lar.
	 * @return string[]
	 */
	public function filter_allowed_hosts( $hosts ) {
		if ( ! is_array( $hosts ) ) {
			$hosts = array();
		}

		return array_values( array_unique( array_merge( $hosts, $this->allowed_hosts ) ) );
	}

	/**
	 * Bu istek işlenmeli mi?
	 *
	 * @return bool
	 */
	protected function should_handle() {
		if ( ! is_404() ) {
			return false;
		}
		if ( headers_sent() ) {
			return false; // Yönlendirme yapılamaz; sayfayı bozmayalım.
		}

		// Yalnızca gerçek sayfa görüntülemeleri.
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		if ( 'GET' !== $method && 'HEAD' !== $method ) {
			return false;
		}

		if ( is_admin() || is_feed() || is_robots() || is_trackback() || is_preview() ) {
			return false;
		}
		if ( wp_doing_ajax() || wp_doing_cron() ) {
			return false;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			return false;
		}
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return false;
		}
		if ( function_exists( 'is_favicon' ) && is_favicon() ) {
			return false;
		}
		if ( is_customize_preview() ) {
			return false;
		}

		return true;
	}

	/**
	 * İstenen ham yolu döndürür.
	 *
	 * @return string
	 */
	protected function current_path() {
		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return '';
		}

		// Doğrulama çekirdekte (normalize_path + is_ignored_path) yapılır.
		return esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );
	}

	/**
	 * Ziyaretçinin geldiği adres.
	 *
	 * Sunucu tarafında `Referer` başlığı GERÇEK kaynaktır (JS snippet'inin
	 * aksine 404 sayfasının kendisi değil), doğrudan kullanılabilir.
	 *
	 * @return string
	 */
	protected function referrer() {
		if ( empty( $_SERVER['HTTP_REFERER'] ) ) {
			return '';
		}

		return esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
	}
}
