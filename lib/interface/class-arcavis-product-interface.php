<?php
defined( 'ABSPATH' ) or die( 'No access!' );

 class FmArcavisArcavisProductInterface {
    private $product;

    public function __construct($product = null){
        $this->setProduct($product);
    }
    public function setProduct($product){
        $this->product = $product;
    }


    public function getStatus(){
		if ( $this->product->Status == '0' ) {
			// aktiv
			return 'publish';
		} elseif ( $this->product->Status == '1' ) {
			// auslaufartikel
			return 'publish';
		} elseif ( $this->product->Status == '2' ) {
			// gesperrt
			return 'trash';
        }
        return 'draft';
    }
    public function getDescription(){
        global $wc_arcavis_shop;
        
		if ( isset( $this->product->Description ) ) {
			$description = $this->product->Description;
			if($wc_arcavis_shop->settingsRepository->getAdditionalSettings()->formating->stripAllContentStyles)
                $description = preg_replace( '/ style=("|\')(.*?)("|\')/','',$description );
            if($wc_arcavis_shop->settingsRepository->getAdditionalSettings()->formating->stripAllContentTags)
                $description = strip_tags( $description, '<p><a>' );
            return $description;
        }
        return '';
    }
    public function getTitle(){
        return trim( str_replace( '  ', ' ', str_replace( '&', '&amp;', $this->product->Title ) ) );
    }
    public function getCoreMeta(){
        $salePrice = '';
        if ( $this->product->SalePrice == 0 ) {
			$price = $this->product->Price;
		} else {
			$salePrice = $this->product->SalePrice;
			$price = $salePrice;
		}        

        return array(
            '_sku' => $this->product->ArticleNumber,
            'article_id' => $this->product->Id,
            'arcavis_id' => $this->product->Id,
            '_price' => $price,
            '_regular_price' => $this->product->Price,
            '_sale_price' => $salePrice,
            '_visibility' => 'visible',
        );

    }
}