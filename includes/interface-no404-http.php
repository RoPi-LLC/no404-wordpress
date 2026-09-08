<?php
/**
 * HTTP taşıma katmanı arayüzü.
 *
 * Çekirdek (No404_Client) taşıma katmanını bilmez; WordPress adaptörü
 * `wp_remote_get` kullanır, testler sahte bir uygulama geçirir. Sınıfın
 * WordPress'siz test edilebilmesini sağlayan sınır budur.
 *
 * @package no404
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface No404_Http_Interface {

	/**
	 * Bir GET isteği yapar. ASLA exception fırlatmaz — hatalar dönüş dizisinde.
	 *
	 * @param string $url        Tam URL.
	 * @param int    $timeout_ms Zaman aşımı (milisaniye).
	 * @param string $user_agent Gönderilecek User-Agent.
	 *
	 * @return array{ok:bool,status:int,body:string,error:string}
	 *               ok=false → taşıma hatası (DNS, timeout, TLS). status 0 olur.
	 *               ok=true  → HTTP yanıtı alındı; status 4xx/5xx olabilir.
	 */
	public function get( $url, $timeout_ms, $user_agent );
}
