<?php
/**
 * Kaldırma temizliği: ayarlar ve önbellek kayıtları silinir.
 *
 * Transient'lar `_transient_no404_%` ve `_transient_timeout_no404_%` olmak üzere
 * İKİ satır olarak durur; ikisi de silinmezse veritabanında yetim satır kalır.
 *
 * @package no404
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Tek bir sitenin verilerini siler.
 *
 * @return void
 */
function no404_uninstall_site() {
	global $wpdb;

	delete_option( 'no404_settings' );
	delete_option( 'no404_cache_generation' );

	// Transient satırları (harici nesne önbelleği varsa zaten DB'de yoktur).
	$like = $wpdb->esc_like( '_transient_no404_' ) . '%';
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange

	$like = $wpdb->esc_like( '_transient_timeout_no404_' ) . '%';
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
}

if ( is_multisite() ) {
	$no404_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( (array) $no404_site_ids as $no404_site_id ) {
		switch_to_blog( (int) $no404_site_id );
		no404_uninstall_site();
		restore_current_blog();
	}
} else {
	no404_uninstall_site();
}
