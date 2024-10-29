<?php
class FmArcavisWcProductsRepository {

	public function findAllPublishedWithArcavisId($exclude = null){
        $args = array(
			'numberposts' => -1,
			'post_type'   => array( 'product', 'product_variation' ),
			'post_status' => array( 'publish' ),
			'meta_query'  => array(
				array(
					'key'     => 'article_id',
					'value'   => '',
					'compare' => '!=',
				),
			),
		);
		if(!is_null($exclude)) {
			$args["exclude"] = $exclude;
			$args["post__not_in"] = $exclude;
		}
		$products = get_posts( $args );
		if ( ! empty( $products ) ) {
			return $products;
		}
		return false;		
	}

	public function findByName($name){
        $args = array(
			'numberposts' => -1,
			'post_type'   => array( 'product', 'product_variation' ),
			'post_status' => array( 'publish', 'draft', 'auto-draft', 'future', 'private', 'inherit', 'trash' ),
			'title'  => trim($name),
		);
		$products = get_posts( $args );
		if ( ! empty( $products ) ) {
			return $products[0];
		}
		return false;		
	}

	public function findBySku($id){
        $args = array(
			'numberposts' => -1,
			'post_type'   => array( 'product', 'product_variation' ),
			'post_status' => array( 'publish', 'draft', 'auto-draft', 'future', 'private', 'inherit', 'trash' ),
			'meta_query' => array(
				array(
					'key' => '_sku',
					'value' => trim($id),
					'compare' => '='
				)
			),
		);
		$products = get_posts( $args );
		if ( ! empty( $products ) ) {
			return $products[0];
		}
		return false;		
	}

	public function findByArcavisId($article_id){
		global $wpdb;
		$query   = $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta where meta_key = 'article_id' and meta_value = '%d'", $article_id );
		$product = $wpdb->get_col( $query );
		if ( ! empty( $product ) ) {
			return (int) $product[0];
		}
		return false;
	}

	public function findAllByArticleId( $article_id, $compare = 'IN' ) {
		return array(
			'numberposts' => -1,
			'post_type'   => array( 'product', 'product_variation' ),
			'post_status' => array( 'publish', 'draft', 'auto-draft', 'future', 'private', 'inherit', 'trash' ),
			'meta_query'  => array(
				array(
					'key'     => 'article_id',
					'value'   => $article_id,
					'compare' => $compare,
				),
			),
		);
	}

	public function findAllDuplicates() {
		global $wpdb;
		$duplicate_product_ids = array();

		$sql = "SELECT  $wpdb->postmeta.post_id,
                    GROUP_CONCAT(DISTINCT $wpdb->postmeta.post_id SEPARATOR ',') AS posts_id,
                    $wpdb->postmeta.meta_key,
                    $wpdb->postmeta.meta_value,
                    Count($wpdb->postmeta.meta_value) as counter
            FROM $wpdb->postmeta
            WHERE $wpdb->postmeta.meta_key = 'article_id'
            GROUP BY $wpdb->postmeta.meta_value
            HAVING Count($wpdb->postmeta.meta_value) > 1";

		$duplicate_meta_values = $wpdb->get_results( $sql, OBJECT );

		// loop over all meta value groups (same meta value)
		foreach ( $duplicate_meta_values as $meta_value_group ) {
			// loop over all post ids
			foreach ( explode( ',', $meta_value_group->posts_id ) as $post_id ) {
				$duplicate_product_ids[] = $post_id;
			}
		}
		return $duplicate_product_ids;
	}
}