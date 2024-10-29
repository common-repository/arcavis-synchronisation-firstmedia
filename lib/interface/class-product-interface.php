<?php
defined( 'ABSPATH' ) or die( 'No access!' );

class FmArcavisProductInterface {
    private $post_id;

    public function __construct($post_id = null){
        $this->setProductId($post_id);
    }
    public function setProductId($post_id){
        $this->post_id = $post_id;
	}
	public function updateOrCreate($product){
		if( empty( $this->post_id ) || $this->post_id === false)
			return $this->create($product);
		else
			return $this->update($product);
	}
	private function create($product){
		global $wc_arcavis_shop;
		$_data   = array(
			'post_author'  => 1,
			'post_title'   => $product['title'],
			'post_status'  => $product['status'],
			'post_type'    => 'product',
		);
		if($wc_arcavis_shop->settingsRepository->getAdditionalSettings()->syncDescriptionInShortDescription)
			$_data['post_excerpt'] = $product['description'];
		else
			$_data['post_content'] = $product['description'];
		$this->setProductId(wp_insert_post( $_data ));
		return $this->post_id;
	}
	private function update($product){
		global $wc_arcavis_shop;
		
		$product_data = array(
			'ID'           => $this->post_id,
			'post_title'   => $product['title'],
			'post_type'    => 'product',
			'post_status'  => $product['status'],
		);

		if(! $wc_arcavis_shop->settingsRepository->settings->arcavis_doNotSyncDescription || strlen(get_post($this->post_id)->post_content) == 0 ) {
			//If DoNOT-Sync Description is inactive -> sync description, ALSO sync description if empty	
			if($wc_arcavis_shop->settingsRepository->getAdditionalSettings()->syncDescriptionInShortDescription) {
				if($product['description'] == get_post($this->post_id)->post_content)
					$product_data['post_content'] = '';
				$product_data['post_excerpt'] = $product['description'];
			}
			else
				$product_data['post_content'] = $product['description'];
		}
		wp_update_post( $product_data );
		return $this->post_id;
	}
	public function updateMetas($meta){
		foreach($meta as $key => $val){
			update_post_meta( $this->post_id, $key, $val );
		}
	}
	public function Delete() {
		/*$wc_product = wc_get_product( $this->post_id );
		if ( $wc_product ) {
			*/
			$arg = array(
				'ID' => $this->post_id,
				'post_status' => 'draft'
			);
            wp_update_post( $arg );
			//wp_delete_attachment( $post_id, true );
			//$wc_product->delete( true );
		/*}
		unset( $wc_product );*/
    }
	public function getArcavisId(  ) {
		return get_post_meta( $this->post_id, 'article_id', true );
	}
    public function getHash( $product ) {
		return md5( FM_WC_AS_VER_HASH . serialize( $product ) );
	}
	public function hasChanged( $new_product ) {
		$current_hash = get_post_meta( $this->post_id, 'arcavis_hash', true );
		return ( $current_hash !== $this->getHash( $new_product ) );
	}

}