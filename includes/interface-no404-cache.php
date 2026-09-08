<?php
/**
 * Önbellek katmanı arayüzü.
 *
 * Kota koruması bu katmana bağlıdır: aynı ölü URL'yi günde 200 kez çeken bir
 * bot 200 değil 1 olay harcamalı. Bu yüzden NEGATİF sonuçlar da cache'lenir.
 *
 * @package no404
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface No404_Cache_Interface {

	/**
	 * @param string $key Anahtar (ham; uygulama kendi öneki/hash'ini ekler).
	 * @return mixed|null Kayıt yoksa veya süresi dolduysa null.
	 */
	public function get( $key );

	/**
	 * @param string $key   Anahtar.
	 * @param mixed  $value Serileştirilebilir değer.
	 * @param int    $ttl   Saniye.
	 * @return void
	 */
	public function set( $key, $value, $ttl );

	/**
	 * Bu eklentiye ait tüm kayıtları siler (ayar değişince çağrılır).
	 *
	 * @return void
	 */
	public function flush();
}
