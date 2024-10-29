<?php
defined( 'ABSPATH' ) or die( 'No access!' );

class FmArcavisProductTagsInterface {
    private $post_id;

    public function __construct($post_id = 0){
        $this->setProductId($post_id);
    }
    public function setProductId($post_id){
        $this->post_id = $post_id;
    }
	public function CreateAndAssignTags( array $arcavis_product_tags ) {
        $tags = $this->createTags( $arcavis_product_tags );
        $tags = array_map( 'intval', $tags );
        wp_set_object_terms( $this->post_id, $tags, 'product_tag' );
    }

	private function createTags( array $tags ) {
		$return = array();
		global $wc_arcavis_shop;
		try {
			foreach ( $tags as $tag ) {
				$tag = trim( str_replace( '  ', ' ', str_replace( '&', '&amp;', $tag ) ) );
				if ( $tag != '' ) {
					$tag_exists = term_exists( $tag, 'product_tag' );

					if ( $tag_exists ) {
						$return[] = $tag_exists['term_id'];
					} else {
						$tag_array = wp_insert_term(
							$tag, // the term
							'product_tag', // the taxonomy
							array(
								'slug' => strtolower( str_replace( ' ', '-', $tag ) ),
							)
						);

						$return[] = $tag_array['term_id'];
					}
				}
			}
			return $return;
		} catch ( Exception $e ) {
			$wc_arcavis_shop->logger->logError( 'FmArcavisProductTagsInterface->createTags ' . $e->getMessage() );
			return $return;
		}
	}
}