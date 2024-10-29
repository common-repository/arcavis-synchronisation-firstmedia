<?php
defined( 'ABSPATH' ) or die( 'No access!' );

class FmArcavisProductAttributeInterface {
    private $post_id;

    public function __construct($post_id = 0){
        $this->setProductId($post_id);
    }
    public function setProductId($post_id){
        $this->post_id = $post_id;
	}
	
	public function dropAttributes(){
		update_post_meta( $this->post_id, '_product_attributes', '' );

		foreach ( wc_get_attribute_taxonomies() as $tax ) {
			$terms = wp_get_object_terms($this->post_id, wc_attribute_taxonomy_name( $tax->attribute_name ), array( 'fields' => 'ids') );
			wp_remove_object_terms($this->post_id, $terms, wc_attribute_taxonomy_name( $tax->attribute_name ) );
		}
	}

	public function createAndAssignAttributes( $variation ) {
		$product_attributes = array();

		foreach ( $variation as $attr ) {
			// Ignore Season
			if ( $attr->Name != 'Season' ) {

				$data = $this->createAttribute( $attr->Name );

				$product_attributes[ $this->getAttributeTaxonomyKey( $attr ) ] = array(
					'name'         => $this->getAttributeTaxonomyKey( $attr ),
					'value'        => '',
					'is_visible'   => '1',
					'is_variation' => '1',
					'is_taxonomy'  => '1',
				);
			}
		}
		update_post_meta( $this->post_id, '_product_attributes', $product_attributes );
	}
    
	public function getAttributeTaxonomyKey( $attr ) {
		return wc_attribute_taxonomy_name( $attr->Name );
	}
	private function createAttribute( $attribute_name ) {
		global $wpdb;
		$return = array();
		try {
			// Create attribute
			$attribute       = array(
				'attribute_label'   => $attribute_name,
				'attribute_name'    => str_replace( ' ', '-', strtolower( $attribute_name ) ),
				'attribute_type'    => 'select',
				'attribute_orderby' => 'menu_order',
				'attribute_public'  => 0,
			);
			$check_existance = $wpdb->get_row( 'SELECT * FROM ' . $wpdb->prefix . "woocommerce_attribute_taxonomies WHERE attribute_name ='" . str_replace( ' ', '-', strtolower( $attribute_name ) ) . "'" );
			if ( empty( $check_existance ) ) {
				$wpdb->insert( $wpdb->prefix . 'woocommerce_attribute_taxonomies', $attribute );
			}
			$return['attribute_slug'] = str_replace( ' ', '-', strtolower( $attribute_name ) );

			// Register the taxonomy
			$name  = wc_attribute_taxonomy_name( $attribute_name );
			$label = $attribute_name;

			delete_transient( 'wc_attribute_taxonomies' );
			clean_taxonomy_cache( $attribute_name );

			global $wc_product_attributes;
			$wc_product_attributes = array();

			foreach ( wc_get_attribute_taxonomies() as $tax ) {
				if ( $name = wc_attribute_taxonomy_name( $tax->attribute_name ) ) {
					$wc_product_attributes[ $name ] = $tax;
				}
			}

			return $return;
		} catch ( Exception $e ) {
			$wc_arcavis_shop->logger->logError( 'CreateAttribute ' . $e->getMessage() );
			return $return;

		}
	}

}