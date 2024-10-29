<?php
defined( 'ABSPATH' ) or die( 'No access!' );

class FmArcavisWooCommerceInterface {
	/**
	 * This function Delete all Arcavis data from woocommerce including orders.
	 * TODO: Refactoring -> use Woocommerce functions!
	 */
	public static function DeleteAllData() {
		global $wpdb;

		$products = $wpdb->get_results( 'SELECT ID FROM ' . $wpdb->prefix . "posts WHERE post_type IN ('product','product_variation')" );
		if ( ! empty( $products ) ) {
			foreach ( $products as $product ) {
				wp_delete_attachment( $product->ID, true );
				$attachments = get_attached_media( '', $product->ID );
				foreach ( $attachments as $attachment ) {
					wp_delete_attachment( $attachment->ID, 'true' );
				}
			}
		}

		$wpdb->query(
			'DELETE a,c FROM ' . $wpdb->prefix . 'terms AS a 
                  LEFT JOIN ' . $wpdb->prefix . 'term_taxonomy AS c ON a.term_id = c.term_id
                  LEFT JOIN ' . $wpdb->prefix . "term_relationships AS b ON b.term_taxonomy_id = c.term_taxonomy_id
                  WHERE c.taxonomy = 'product_tag'"
		);
		$wpdb->query(
			'DELETE a,c FROM ' . $wpdb->prefix . 'terms AS a
                  LEFT JOIN ' . $wpdb->prefix . 'term_taxonomy AS c ON a.term_id = c.term_id
                  LEFT JOIN ' . $wpdb->prefix . "term_relationships AS b ON b.term_taxonomy_id = c.term_taxonomy_id
                  WHERE c.taxonomy = 'product_cat'"
		);
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'terms WHERE term_id IN (SELECT term_id FROM ' . $wpdb->prefix . "term_taxonomy WHERE taxonomy LIKE 'pa_%')" );
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . "term_taxonomy WHERE taxonomy LIKE 'pa_%'" );
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'term_relationships WHERE term_taxonomy_id not IN (SELECT term_taxonomy_id FROM ' . $wpdb->prefix . 'term_taxonomy)' );
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'term_relationships WHERE object_id IN (SELECT ID FROM ' . $wpdb->prefix . "posts WHERE post_type IN ('product','product_variation'))" );
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'postmeta WHERE post_id IN (SELECT ID FROM ' . $wpdb->prefix . "posts WHERE post_type IN ('product','product_variation','shop_coupon'))" );
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . "posts WHERE post_type IN ('product','product_variation','shop_coupon')" );
		//$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . "posts WHERE post_type IN ('product','product_variation','shop_coupon','shop_order')" );
		//$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'woocommerce_order_itemmeta' );
		//$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'woocommerce_order_items' );
		$wpdb->query( 'TRUNCATE TABLE ' . $wpdb->prefix . 'arcavis_logs' );
		$wpdb->query( 'TRUNCATE TABLE ' . $wpdb->prefix . 'lastSyncTicks' );
		$wpdb->query( 'TRUNCATE TABLE ' . $wpdb->prefix . 'lastSyncPage' );

		$wpdb->insert(
			$wpdb->prefix . 'lastSyncTicks',
			array(
				'apiName'  => 'articles',
				'lastSync' => '',
				'updated'  => current_time( 'mysql' ),
			)
		);
		$wpdb->insert(
			$wpdb->prefix . 'lastSyncTicks',
			array(
				'apiName'  => 'articlestocks',
				'lastSync' => '',
				'updated'  => current_time( 'mysql' ),
			)
		);
		$wpdb->insert( $wpdb->prefix . 'lastSyncPage', array( 'lastPage' => '1' ) );

		update_option( 'arcavis_first_sync', '' );
	}
}