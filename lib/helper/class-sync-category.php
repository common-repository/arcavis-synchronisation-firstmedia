<?php
defined( 'ABSPATH' ) or die( 'No access!' );

class FmArcavisSyncCategory {
	public static function UpdateProduct( $post_id, $product ) {
		global $wc_arcavis_shop;

		if( $wc_arcavis_shop->settingsRepository->getAdditionalSettings()->syncExclude->doNotSyncCategories) return;
		$category1 = $product->MainGroupTitle;
		$category2 = $product->TradeGroupTitle;
		$category3 = $product->ArticleGroupTitle;

		$categoryInterface = new FmArcavisCategoryInterface( $category1, $category2, $category3 );

		if ( $categoryInterface->hasChanged( $post_id ) ) {
			$categories = $categoryInterface->Create();
			$categories = array_map( 'intval', $categories );
			wp_set_object_terms( $post_id, $categories, 'product_cat', false );
			update_post_meta( $post_id, 'arcavis_category_hash', $categoryInterface->getHash() );
			unset( $categories );
		}
	}
    

}