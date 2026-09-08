<?php
/**
 * WordPress önbellek adaptörü (transient).
 *
 * Transient'lar site (blog) başınadır; multisite'ta `switch_to_blog` sonrası
 * doğru mağazanın önbelleği kendiliğinden kullanılır — ek iş gerekmez.
 *
 * @package no404
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class No404_WP_Cache implements No404_Cache_Interface {

	/** Tüm transient adlarının ortak öneki (uninstall temizliği buna dayanır). */
	const PREFIX = 'no404_';

	/** Nesil (generation) sayacını tutan option. Artırmak = önbelleği geçersiz kılmak. */
	const GENERATION_OPTION = 'no404_cache_generation';

	/** @var int|null */
	protected $generation = null;

	/**
	 * @param string $key Anahtar.
	 * @return mixed|null
	 */
	public function get( $key ) {
		$value = get_transient( $this->name( $key ) );

		// Transient API "yok" durumunu false ile bildirir; sözleşmemiz null.
		return ( false === $value ) ? null : $value;
	}

	/**
	 * @param string $key   Anahtar.
	 * @param mixed  $value Değer.
	 * @param int    $ttl   Saniye.
	 * @return void
	 */
	public function set( $key, $value, $ttl ) {
		set_transient( $this->name( $key ), $value, max( 1, (int) $ttl ) );
	}

	/**
	 * Önbelleği geçersiz kılar.
	 *
	 * Satır satır silmek yerine nesil sayacını artırırız: harici bir nesne
	 * önbelleği (Redis/Memcached) kurulu olduğunda transient'lar veritabanında
	 * DURMAZ, dolayısıyla `DELETE ... LIKE` çalışmaz. Sayaç her kurulumda çalışır.
	 * Eski kayıtlar kendi süreleri dolunca düşer.
	 *
	 * @return void
	 */
	public function flush() {
		$next = $this->current_generation() + 1;
		update_option( self::GENERATION_OPTION, $next, false );
		$this->generation = $next;
	}

	/**
	 * Anahtarı transient adına çevirir.
	 *
	 * @param string $key Ham anahtar.
	 * @return string
	 */
	protected function name( $key ) {
		return self::PREFIX . $this->current_generation() . '_' . md5( (string) $key );
	}

	/** @return int */
	protected function current_generation() {
		if ( null === $this->generation ) {
			$this->generation = (int) get_option( self::GENERATION_OPTION, 0 );
		}

		return $this->generation;
	}
}
