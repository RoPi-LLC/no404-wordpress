<?php
/**
 * Uninstall cleanup: removes the settings and the cache entries.
 *
 * A transient is stored as TWO rows — `_transient_no404_%` and
 * `_transient_timeout_no404_%`. Delete only one of them and orphaned rows are
 * left behind in the database.
 *
 * @package no404
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Deletes the data of a single site.
 *
 * @return void
 */
function no404_uninstall_site() {
	global $wpdb;

	delete_option( 'no404_settings' );
	delete_option( 'no404_cache_generation' );
	// Setup wizard progress (1.1.0). Its transients — the one-shot activation
	// redirect and the per-user step notice — start with `no404_` and go below.
	delete_option( 'no404_wizard' );

	// Transient rows (with an external object cache these are not in the DB anyway).
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
