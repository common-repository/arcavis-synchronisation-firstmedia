<?php
defined( 'ABSPATH' ) or die( 'No access!' );

class FmArcavisSyncStock {
	public $settingsRepository;
	public $api;
	private $allowedStockStoreIds;

	public function __construct( FmArcavisSettingsRepository $settingsRepository ) {
		$this->settingsRepository = $settingsRepository;
		$this->allowedStockStoreIds = explode(",", $this->settingsRepository->getAdditionalSettings()->allowedStockStoreIds);
		$this->api      = new FmArcavisApiRepository( $settingsRepository );
    }
    
	public function runAll() {
		global $wc_arcavis_shop;
		global $wpdb;
		$counter = 0;

		$lastSync = FmLastSyncRepository::getLastSync( 'articlestocks' );
		$stocks   = $this->api->get_products_stock_by_changedTicks( $lastSync );
		$productsRepo = new FmArcavisWcProductsRepository();
		$useAllStockSources = in_array('all', $this->allowedStockStoreIds);

		if ( ! empty( $stocks ) ) {
			foreach ( $stocks->Result as $stock ) {
				if($useAllStockSources){
					$product_id = $productsRepo->findByArcavisId( $stock->ArticleId );
					$this->updateProductStock($stock->Stock, $product_id);
				}
				else {
					$this->runSingleById($stock->ArticleId);
				}
				$counter++;
			}
			
			FmLastSyncRepository::updateLastSync('articlestocks', $stocks->DataAgeTicks, current_time( 'mysql' ) );
		}
		return $counter;
	}
	public function runSingleById($arcavis_id){
		$productsRepo = new FmArcavisWcProductsRepository();
		$product_id = $productsRepo->findByArcavisId($arcavis_id);
		$stocks = $this->api->get_product_stock_by_id($arcavis_id);

		if ( ! empty( $stocks->Result ) ) {
			$total_stock = $this->checkStockSourceAndGetTotalStock($stocks);
			$this->updateProductStock($total_stock, $product_id);
		}
	}

	private function checkStockSourceAndGetTotalStock($stocks){
		$total_stock = 0;
		foreach ( $stocks->Result as $stock ) {
			if( in_array('all', $this->allowedStockStoreIds) ||
				in_array($stock->StoreId, $this->allowedStockStoreIds) )
			$total_stock += (int)$stock->Stock;
		}
		return $total_stock;
	}

    private function updateProductStock($stock, $product_id){
		if ( ! empty( $product_id ) ) {			
			$wc_prod = wc_get_product($product_id);
			if( $wc_prod->is_type( 'variable' ) )
				$wc_prod->set_manage_stock( false );
			else
				$wc_prod->set_manage_stock( true );
			
			$stockstatus = 'instock';
			if ( $stock <= 0 ) {
				$stockstatus = 'outofstock';
			}
			$wc_prod->set_stock_status($stockstatus);
			wc_update_product_stock($wc_prod, $stock);
		}		
	}
}