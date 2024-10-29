<?php
defined( 'ABSPATH' ) or die( 'No access!' );

class FmArcavisProductVariationInterface {
    private $post_id;

    public function __construct($post_id = 0){
        $this->setProductId($post_id);
    }
    public function setProductId($post_id){
        $this->post_id = $post_id;
    }
	public function getVariationTaxonomyKey( $attr ) {
		return wc_attribute_taxonomy_name( $attr->Name );
    }
    
	public function createVariations( $variation_data, $index ) {
		global $wc_arcavis_shop;
        $updated_posts = array();
        $product_id = $this->post_id;
		$product = wc_get_product( $this->post_id );

		$productsRepo = new FmArcavisWcProductsRepository();
		$check_product = $productsRepo->findByArcavisId( $variation_data->Id );

		if ( $variation_data->Status == '0' ) {
			$Status = 'publish';
		} elseif ( $variation_data->Status == '1' ) {
			$Status = 'trash';
		} elseif ( $variation_data->Status == '2' ) {
			$Status = 'trash';
		}

		if ( $check_product != '' ) {
			$variation_id   = $check_product;
			$variation_post = array( // Setup the post data for the variation
				'ID'          => $variation_id,
				'post_title'  => $product->get_title(),
				'post_name'   => 'product-' . $product_id . '-variation',
				'post_status' => $Status,
				'post_parent' => $product_id,
				'post_type'   => 'product_variation',
				'guid'        => $product->get_permalink(),
			);
			wp_update_post( $variation_post );
		} else {
			$variation_post = array( // Setup the post data for the variation

				'post_title'  => $product->get_title(),
				'post_name'   => 'product-' . $product_id . '-variation',
				'post_status' => $Status,
				'post_parent' => $product_id,
				'post_type'   => 'product_variation',
				'guid'        => $product->get_permalink(),
			);
			$variation_id   = wp_insert_post( $variation_post ); // Insert the variation
		}
		// Get an instance of the WC_Product_Variation object
		$variation = new WC_Product_Variation( $variation_id );

		// Iterating through the variations attributes
		foreach ( $variation_data->Attributes as $attr ) {
			if ( $attr->Name == 'Season' ) {
				continue;
			}

			$attribute = $attr->Name;
			// translate color and size
			if ( strpos( get_locale(), 'de' ) == 0 ) {
				if ( $attr->Name == 'Color' ) {
					$attribute = 'Farbe';
				} elseif ( $attr->Name == 'Size' ) {
					$attribute = 'Grösse';
				}
			}
			$term_name = $attr->Value;
			$taxonomy  = $this->getVariationTaxonomyKey( $attr ); // The attribute taxonomy

			if ( ! taxonomy_exists( $taxonomy ) ) {
				register_taxonomy(
					$taxonomy,
					'product',
					array(
						'hierarchical' => false,
						'label'        => ucfirst( $attribute ),
						'query_var'    => true,
						'rewrite'      => array( 'slug' => sanitize_title( $attribute ) ), // The base slug
					)
				);
			}

			// Check if the Term name exist and if not we create it.
			if ( ! term_exists( $term_name, $taxonomy ) ) {
				wp_insert_term( $term_name, $taxonomy ); // Create the term
			}

			$term_slug = get_term_by( 'name', $term_name, $taxonomy )->slug; // Get the term slug

			// Get the post Terms names from the parent variable product.
			$post_term_names = wp_get_post_terms( $product_id, $taxonomy );

			// Check if the post term exist and if not we set it in the parent variable product.
			if ( ! in_array( $term_name, wp_list_pluck($post_term_names,'name') ) ) {
				wp_set_post_terms( $product_id, $term_name, $taxonomy, true );
			}
			
			if ( ! in_array( $term_slug, wp_list_pluck($post_term_names,'slug') ) ) {
				foreach($post_term_names as $t){
					if($t->name == $term_name)
						$term_slug == $t->slug;
				}
			}

			// Set/save the attribute data in the product variation
			update_post_meta( $variation_id, 'attribute_' . $taxonomy, $term_slug );
		}

		// Set/save all other data
		// SKU
		if ( ! empty( $variation_data->ArticleNumber ) ) {
			$variation->set_sku( $variation_data->ArticleNumber );
		}

		// Prices
		if ( empty( $variation_data->SalePrice ) ) {
			$variation->set_price( $variation_data->Price );
		} else {
			$variation->set_price( $variation_data->SalePrice );
			$variation->set_sale_price( $variation_data->SalePrice );
		}
		$variation->set_regular_price( $variation_data->Price );

		// Stock
		$variation->set_manage_stock( true );
		$variation->set_stock_quantity( $variation_data->Stock );
		$variation->set_stock_status( '' );
		$variation->set_weight( '' ); // weight (reseting)	
		$variation->save(); // Save the data

		// Article Id (for mapping ordered articles back to arcavis)
		update_post_meta( $variation_id, 'article_id', $variation_data->Id );
		update_post_meta( $variation_id, 'arcavis_id', $variation_data->Id );


		// Stock
		$stockSync = new FmArcavisSyncStock($wc_arcavis_shop->settingsRepository);
		$product_count = $stockSync->runSingleById( $variation_data->Id );

		unset( $product );
	}

	public function insertDefaultAttributes( $products_data ) {
		return;
		if(empty($products_data) || !is_array($products_data)) return;
        $variations_default_attributes = array();
		foreach ( $products_data as $key => $attribute ) {
			$variations_default_attributes[ $this->getVariationTaxonomyKey( $attribute ) ] = get_term_by( 'name', $attribute->Value, $this->getVariationTaxonomyKey( $attribute ) )->slug;
		}
		// Save the variation default attributes to variable product meta data
		update_post_meta( $this->post_id, '_default_attributes', $variations_default_attributes );
	}

}