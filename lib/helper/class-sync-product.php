<?php
defined( 'ABSPATH' ) or die( 'No access!' );

class FmArcavisSyncProduct {

	public static function CreateOrUpdateProduct( $product ) {
		global $wc_arcavis_shop;
		global $wpdb;

		$wc_arcavis_shop->logger->logDebug( 'Create/Update Product ' . $product->Title );
		$productsRepo = new FmArcavisWcProductsRepository();
		$post_id = $productsRepo->findByArcavisId( $product->Id );

		$arcavisProductInterface = new FmArcavisArcavisProductInterface( $product );
		$productInterface = new FmArcavisProductInterface($post_id);

		$post_id = $productInterface->updateOrCreate( array(
				'title' => $arcavisProductInterface->getTitle(),
				'description' => $arcavisProductInterface->getDescription(),
				'status' => $arcavisProductInterface->getStatus()
			) );

		$productInterface->updateMetas( $arcavisProductInterface->getCoreMeta() );

		// TODO: $product->TaxRate
		/*
		if ($product->TaxRate !== 0) {
			$tax_rate       = $product->TaxRate * 100;
			$tax_rate_str = str_replace('.', '', $tax_rate);
			$tax_name = 'Arcavis-' . $tax_rate_str;
			$tax_slug = 'arcavis-' . $tax_rate_str;

			$taxrates  = WC_Tax::get_tax_classes();
			if (!in_array($tax_name, $taxrates)) {

				// Create tax classs
				$tax_class = WC_Tax::create_tax_class( $tax_name, $tax_slug );

				// Attached the tax_rate to tax_class
				$tax_rate_data = array(
					'tax_rate_country'  => '*',
					'tax_rate_state'    => '*',
					'tax_rate'          => $tax_rate,
					'tax_rate_name'     => $tax_name,
					'tax_rate_priority' => 1,
					'tax_rate_compound' => 1,
					'tax_rate_shipping' => 0,
					'tax_rate_order'    => 0,
					'tax_rate_class'    => $tax_slug,
				);

				$tax_rate_id = WC_Tax::_insert_tax_rate( $tax_rate_data );
			}
			// update
			update_post_meta( $post_id, '_tax_status', 'taxable );
			update_post_meta( $post_id, '_tax_class', $tax_slug );
		}*/

		update_post_meta( $post_id, 'arcavis_hash', $productInterface->getHash( $product ) );
		wp_set_object_terms( $post_id, 'simple', 'product_type' );

		// Update Images
		$forceImageSync = false;
		if(!has_post_thumbnail($post_id) ) $forceImageSync = true;

		$imagesInterface = new FmArcavisProductImageInterface($post_id);
		$imagesInterface->UpdateImages( $product->Images, $forceImageSync );

		// Add Categories
		$categorySync = new FmArcavisSyncCategory();
		$categorySync->UpdateProduct( $post_id, $product );

		// Add Tags
		$old_tags = wp_get_object_terms($post_id, 'product_tag');
		if(null != $old_tags){
			$delete_tags = array();
			foreach($old_tags as $tag){
				$delete_tags[] = $tag->term_id;
			}
			wp_remove_object_terms($post_id, $delete_tags, 'product_tag');
		}
		if ( array_key_exists( 'Tags', $product ) && ! empty( $product->Tags ) ) {
			$tagsInterface = new FmArcavisProductTagsInterface($post_id);
			$tagsInterface->CreateAndAssignTags( $product->Tags );
		}

		// Add Variations
		if ( array_key_exists( 'HasVariations', $product ) ) {
			if ( $product->HasVariations == 'true' ) {
				update_post_meta( $post_id, '_manage_stock', 'no' );
				wp_set_object_terms( $post_id, 'variable', 'product_type' );
				$api        = new FmArcavisApiRepository( new FmArcavisSettingsRepository() );
				$variations = $api->get_product_variation_by_id( $product->Id );
				if ( ! empty( $variations ) ) {
					$attributesInterface = new FmArcavisProductAttributeInterface($post_id);
					$attributesInterface->dropAttributes();
					$variationsInterface = new FmArcavisProductVariationInterface($post_id);
					foreach ( $variations->Result as $key => $variation ) {
						$attributesInterface->createAndAssignAttributes( $variation->Attributes );
						$updated_post_ids = $variationsInterface->createVariations( $variation, $key );
					}
					// delete old variations	
					if(!is_null($variationsInterface))				
						$variationsInterface->insertDefaultAttributes( $post_id, $variations->Result[0]->Attributes );

					
				}
			}
			else {
				update_post_meta( $post_id, '_manage_stock', 'yes' );
			}
		} else {
			// delete variations?

		}
		// Stock
		$stockSync = new FmArcavisSyncStock($wc_arcavis_shop->settingsRepository);
		$product_count = $stockSync->runSingleById( $product->Id );

		// workaround to save wc_product_meta_lookup
		if ( $woo_product ) {
			$woo_product = wc_get_product( $post_id );
			if ( array_key_exists( 'HasVariations', $product ) ) {
				$woo_product->set_attributes($wc_product_attributes);
			}
			$woo_product->save();
		}

		// Action hook for custom extensions of product update
		do_action( 'arcavis_after_product_update', array( $post_id, $product ) );

		// cleanup
		unset( $tags );
		unset( $woo_product );
		unset( $post_id );
		unset( $product );
	}

}