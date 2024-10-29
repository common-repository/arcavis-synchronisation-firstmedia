<?php

class WooCommerce_Arcavis_Sync_Products {
	public $image_background_process;
	public $background_process;
	public $settings;
	public $api;

	public function __construct( FmArcavisSettingsRepository $settingsRepository ) {
		$this->settings = $settingsRepository->settings;
		$this->api      = new FmArcavisApiRepository( $settingsRepository );

		$this->image_background_process = new FmArcavisSyncImageWorker();
		// $this->background_process = new FmArcavisSyncWorker();
		// $this->image_background_process->save()->dispatch();
	}

	// This function is used for running first time sysc or re-sync.
	public function create_products_init() {
		global $wc_arcavis_shop;
		global $wpdb;

		$itemsPerRun = 50;

		try {
			if ( isset( $_POST['ignoreFirstSync'] ) && $_POST['ignoreFirstSync'] == 'yes' ) {
				$products     = $this->api->get_products_by_page( 1, 1 );
				FmLastSyncRepository::updateLastSync('articles', $products->DataAgeTicks, current_time( 'mysql' ) );
				FmLastSyncRepository::updateLastSync('articlestocks', $products->DataAgeTicks, current_time( 'mysql' ) );
				update_option( 'arcavis_first_sync', 'completed' );
				echo json_encode(array('continue' => false));
				exit;
			}
			if ( isset( $_POST['deleteExistingProducts'] ) && $_POST['deleteExistingProducts'] == 'yes' ) {
				FmArcavisWooCommerceInterface::DeleteAllData();
			}
			if ( isset( $_POST['itemsPerRun'] ) ) {
				$itemsPerRun = sanitize_text_field( $_POST['itemsPerRun'] );
			}
			/*
			$first_sync = get_option('arcavis_first_sync');
			if ($first_sync == 'completed') {
			echo "exit";
			exit;
			}*/

			if ( isset( $this->settings->arcavis_link ) && $this->settings->arcavis_link == '' ) {
				echo json_encode(array('continue' => false));
				exit;
			}
			$lastSync = ''; // FmLastSyncRepository::getLastSync('articles');
			if ( $lastSync == '' ) {
				$lastSyncPage = $wc_arcavis_shop->get_last_page();
				$products     = $this->api->get_products_by_page( $lastSyncPage, $itemsPerRun );

				if ( ! empty( $products ) ) {

					foreach ( $products->Result as $product ) {
						FmArcavisSyncProduct::CreateOrUpdateProduct( $product );
					}

					// Run background Process (get images)
					if(!$wc_arcavis_shop->settingsRepository->settings->arcavis_doNotSyncImages)
						$this->image_background_process->save()->dispatch();

					if ( $products->TotalPages <= $lastSyncPage ) {
						FmLastSyncRepository::updateLastSync('articles', $products->DataAgeTicks, current_time( 'mysql' ) );
						FmLastSyncRepository::updateLastSync('articlestocks', $products->DataAgeTicks, current_time( 'mysql' ) );
						update_option( 'arcavis_first_sync', 'completed' );
						echo json_encode(array('continue' => false));
					} else {

						$nextpage    = $lastSyncPage + 1;
						$table_name2 = $wpdb->prefix . 'lastSyncPage';
						$wpdb->insert( $table_name2, array( 'lastPage' => $nextpage ) );

						echo json_encode(array('continue' => true, 'currentpage' => $lastSyncPage, 'maxpage' => $products->TotalPages, 'itemsPerRun' => $itemsPerRun));
					}
				} else {
					echo json_encode(array('continue' => false));
						  exit;
				}
				exit;
			}
		} catch ( Exception $e ) {
			$wc_arcavis_shop->logger->logError( 'create_products_init ' . $e->getMessage() );
			echo $e->getMessage();
			exit;
		}
	}

	/**
	 *
	 * This function is used updating orders/payments in given time interval.
	 *
	 * @param
	 * @return
	 */
	public function update_payments($settingsRepository){
		global $wc_arcavis_shop;
		global $wpdb;
		$output = '';

		try {

			$lastSync = FmLastSyncRepository::getLastSyncTime( 'articles' );
			if('' == $lastSync)
				$lastSync = current_time( 'mysql' );

			$transactionWorker = new FmArcavisArcavisTransactionController( $settingsRepository );
			$ordersRepository = new FmArcavisWcOrdersRepository();
			$ordersWithoutArcavisPayment = $ordersRepository->findAllWithoutArcavisPaymentSince($lastSync);
			$syncedPaymentsCount = 0;

			$outputAppend = '';
			foreach($ordersWithoutArcavisPayment as $order){
				$response = $transactionWorker->arcavis_process_transaction($order->get_id(), '', $order->get_status());
				if ( $response->IsSuccessful === true && $response->Message == 'Success' ) 
					$syncedPaymentsCount++;
				else
					$outputAppend .= 'Failed Payment-Sync for Order ID '.$order->get_id().'<br />';
			}
			
			FmLastSyncRepository::updateLastSync('articles', null, current_time( 'mysql' ) );
			$output .= '<br />'.$syncedPaymentsCount.'/'.count($ordersWithoutArcavisPayment).' Orders without Arcavis-Payment synced (Since '.$lastSync.')'.$outputAppend;
		} catch ( Exception $e ) {
			$wc_arcavis_shop->logger->logError( 'update_payments ' . $e->getMessage() );
		}
		return $output;
	}
	/**
	 *
	 * This function is used updating products in given time interval.
	 *
	 * @param
	 * @return
	 */
	public function update_products() {
		global $wc_arcavis_shop;
		global $wpdb;
		$counter = 0;
		$productsRepo = new FmArcavisWcProductsRepository();

		try {

			$lastSync = FmLastSyncRepository::getLastSync( 'articles' );
			if ( $lastSync != '' ) {
				$products = $this->api->get_products_by_changedTicks( $lastSync );

				if ( isset( $products->DeletedIds ) && ! empty( $products->DeletedIds ) ) {
					$totalNoLongerOnApiProducts = 0;
					foreach( $products->DeletedIds  as $deleteId){
						$prod = new FmArcavisProductInterface( $productsRepo->findByArcavisId($deleteId) );
						$prod->Delete();
						$totalNoLongerOnApiProducts++;					
					}
				}
				if ( ! empty( $products->Result ) ) {
					// Loop through all updated products
					foreach ( $products->Result as $product ) {
						FmArcavisSyncProduct::CreateOrUpdateProduct( $product );
						$counter++;
					}// End of foreach loop
					if(!$wc_arcavis_shop->settingsRepository->settings->arcavis_doNotSyncImages)
						$this->image_background_process->save()->dispatch();
				}//end of checking products are empty or not.

				$stockSync = new FmArcavisSyncStock($wc_arcavis_shop->settingsRepository);
				$stockSync->runAll();
				
				if(! empty($products) && !empty($products->DataAgeTicks))
					FmLastSyncRepository::updateLastSync('articles', $products->DataAgeTicks, current_time( 'mysql' ) );
			} else {
				throw new \Exception( 'Never Synced. (empty $lastsync)' );
			}
		} catch ( Exception $e ) {
			$wc_arcavis_shop->logger->logError( 'update_products ' . $e->getMessage() );
		}
		return $counter.' Products (Since '.$lastSync.')';
	}

	// This function is used to update the articles stock by article ID.
	public function update_article_stock_by_id( $article_id ) {
		$stocks = $this->api->get_product_stock_by_id( $article_id );
		if ( ! empty( $stocks->Result ) ) {
			$total_stock = 0;
			foreach ( $stocks->Result as $stock ) {
				$total_stock += $stock->Stock;
			}

			$productsRepo = new FmArcavisWcProductsRepository();
			$products_id = $productsRepo->findByArcavisId( $article_id );

			if ( ! empty( $products_id ) ) {
				$stockstatus = 'instock';
				if ( $total_stock <= 0 ) {
					$stockstatus = 'outofstock';
				}
				wc_update_product_stock_status($products_id, $stockstatus);
				$wc_prod = wc_get_product($products_id);
				if( $wc_prod->is_type( 'variable' ) )
					update_post_meta( $product_id, '_manage_stock', 'no' );
				else
					update_post_meta( $product_id, '_manage_stock', 'yes' );
				//update_post_meta( $products_id, '_stock', $total_stock );
				wc_update_product_stock(wc_get_product($product_id), $product->Stock);
			}
		} else {
			// MAYBE not stock managment in arcavis?

		}
		unset( $stocks );
	}

	/**
	 * Updates Single Product manually from API
	 *
	 * @param [ID] $woo_product_id
	 * @return Boolean
	 */
	function update_single_product( $woo_product_id ) {
		global $wc_arcavis_shop;

		$article_id = get_post_meta( $woo_product_id, 'article_id', true );
		if ( ! empty( $article_id ) ) {
			$arcavis_product = $this->api->get_product_by_id( $article_id );
			if ( ! empty( $arcavis_product ) ) {
				$arcavis_product = $arcavis_product->Result;
				FmArcavisSyncProduct::CreateOrUpdateProduct( $arcavis_product );
				if(!$wc_arcavis_shop->settingsRepository->settings->arcavis_doNotSyncImages)
					$this->image_background_process->save()->dispatch();
				return true;
			}
		}
		return false;
	}

	

}


