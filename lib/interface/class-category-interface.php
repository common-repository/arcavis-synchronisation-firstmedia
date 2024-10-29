<?php
defined( 'ABSPATH' ) or die( 'No access!' );

class FmArcavisCategoryInterface {
    private $category1;
    private $category2;
    private $category3;

    public function __construct($category1 = '', $category2 = '', $category3 = ''){
        $this->setCategory($category1, $category2, $category3);
    }
    public function setCategory($category1, $category2, $category3){
        $this->category1 = $category1;
        $this->category2 = $category2;
        $this->category3 = $category3;
    }

	// This function is used to assign categories to articles
	public function Create() {
		$return = array();
		global $wc_arcavis_shop;
		try {
			$category1 = trim( str_replace( '  ', ' ', str_replace( '&', '&amp;', $this->category1 ) ) );
			$category2 = trim( str_replace( '  ', ' ', str_replace( '&', '&amp;', $this->category2 ) ) );
			$category3 = trim( str_replace( '  ', ' ', str_replace( '&', '&amp;', $this->category3 ) ) );

			// MainGroupTitle
			$category1_exist = term_exists( $category1, 'product_cat', 0 );
			if ( $category1_exist ) {
				$return[]       = $category1_exist['term_id'];
				$parent_term_id = $category1_exist['term_id'];
				unset( $category1_exist );
			} else {
				$category1_array = wp_insert_term(
					$category1, // the term
					'product_cat', // the taxonomy
					array(
						'slug' => strtolower( str_replace( ' ', '-', $category1 ) ),
					)
				);

				$return[]       = $category1_array['term_id'];
				$parent_term_id = $category1_array['term_id'];
				unset( $category1_array );
			}

			// TradeGroupTitle
			if ( $category2 != '' ) {
				$category2_exist = term_exists( $category2, 'product_cat', $parent_term_id );
				if ( $category2_exist ) {
					$return[]        = $category2_exist['term_id'];
					$parent_term_id2 = $category2_exist['term_id'];
					unset( $category2_exist );
				} else {
					$category2_array = wp_insert_term(
						$category2, // the term
						'product_cat', // the taxonomy
						array(
							'slug'   => strtolower( str_replace( ' ', '-', $category2 ) ),
							'parent' => $parent_term_id,
						)
					);

					$return[]        = $category2_array['term_id'];
					$parent_term_id2 = $category2_array['term_id'];
					unset( $category2_array );
				}
			}

			// ArticleGroupTitle
			if ( $category3 != '' ) {
				$category3_exist = term_exists( $category3, 'product_cat', $parent_term_id2 );
				if ( $category3_exist ) {
					$return[] = $category3_exist['term_id'];
					unset( $category3_exist );
				} else {
					$category3_array = wp_insert_term(
						$category3, // the term
						'product_cat', // the taxonomy
						array(
							'slug'   => strtolower( str_replace( ' ', '-', $category3 ) ),
							'parent' => $parent_term_id2,
						)
					);
					$return[]        = $category3_array['term_id'];
					unset( $category3_array );
				}
			}

			return $return;
		} catch ( Exception $e ) {
			$wc_arcavis_shop->logger->logError( 'add_category ' . $e->getMessage() );
			return $return;
		}
    }
    
	public function getHash() {
		return md5( FM_WC_AS_VER_HASH . serialize( array( $this->category1, $this->category2, $this->category3 ) ) );
    }
    
	public function hasInitialSync( $post_id ) {
		$current_hash = get_post_meta( $post_id, 'arcavis_category_hash', true );
		return ( $current_hash != '' );
	}
    
	public function hasChanged( $post_id ) {
		$current_hash = get_post_meta( $post_id, 'arcavis_category_hash', true );
		return ( $current_hash !== $this->getHash() );
	}

}