<?php

define( 'WC_AS_MEMORY_LIMIT_MB', 200 );

class ArcavisSyncChecker {
	public $syncworker_option_key = 'arcavis_sync_worker';
	private $settingsRepository;

	public function __construct($settingsRepository = null) {
		if(is_null($settingsRepository))
			$this->settingsRepository = new FmArcavisSettingsRepository();
		else
			$this->settingsRepository = $settingsRepository;

		add_action( 'wp_ajax_arcavis_sync', array( $this, 'ajax_run' ) );
	}

	public function run( $args, $nopaging = false ) {
		global $wc_arcavis_shop;
		if ( $nopaging ) {
			$output = $this->check_sync_state( $args );
		} else {
			$output = $this->check_sync_state_paged( $args );
		}
		if ( strlen( $output ) < 25 ) {
			$wc_arcavis_shop->logger->logInfo( '**arcavis_sync_check ' . $output );
		} elseif ( strpos( $output, 'ERROR' ) !== false ) {
			$wc_arcavis_shop->logger->logError( '**arcavis_sync_check_done ' . $output );
		} else {
			$wc_arcavis_shop->logger->logInfo( '**arcavis_sync_check_done ' . $output );
		}
	}

	public function is_running() {
		return get_option( $this->syncworker_option_key, false ) != false;
	}

	public function ajax_run() {
		// Security
		if ( ! check_ajax_referer( 'arcavis-sync-security-nonce', 'security' ) ) {
			wp_send_json_error( 'Invalid security token sent.' );
			wp_die();
		}

		if ( $this->is_running() ) {
			$this->run( null );
			$options = get_option( $this->syncworker_option_key, false );
			if ( $options !== false ) {
				$options = json_decode( $options );
				wp_send_json(
					array(
						'percentage' => round( ( $options->page / $options->totalPages ) * 100, 1 ),
						'info'       => 'Page ' . $options->page . ' of ' . $options->totalPages,
						'meminfo'    => SimpleStopWatch::memoryUsageStr(),
					)
				);
				return;
			}
		}
		wp_send_json( 'done' );
		return;
	}

	public function check_sync_state( $args ) {
		global $wc_arcavis_shop;

		// args
		$deleteDuplicates             = isset( $args['deleteDuplicates'] ) ? $args['deleteDuplicates'] : false;
		$writeMissingProducts         = isset( $args['writeMissingProducts'] ) ? $args['writeMissingProducts'] : false;
		$deleteOldProducts            = isset( $args['deleteOldProducts'] ) ? $args['deleteOldProducts'] : false;
		$deleteAllWooCommerceProducts = isset( $args['deleteAllWooCommerceProducts'] ) ? $args['deleteAllWooCommerceProducts'] : false;
		$deleteAllArcavisProducts = isset( $args['deleteAllArcavisProducts'] ) ? $args['deleteAllArcavisProducts'] : false;
		$fullSyncAllProducts          = isset( $args['fullSyncAllProducts'] ) ? $args['fullSyncAllProducts'] : false;
		$fullSyncAllProductCategories = isset( $args['fullSyncAllProductCategories'] ) ? $args['fullSyncAllProductCategories'] : false;
		$syncChangedProducts          = isset( $args['syncChangedProducts'] ) ? $args['syncChangedProducts'] : false;
		$syncCheckImages              = isset( $args['syncCheckImages'] ) ? $args['syncCheckImages'] : false;
		$syncImages                   = isset( $args['syncImages'] ) ? $args['syncImages'] : false;
		$syncPayments     = isset( $args['syncPayments'] ) ? $args['syncPayments'] : false;
		$syncPaymentsSinceLastSync  = isset( $args['syncPaymentsSinceLastSync'] ) ? $args['syncPaymentsSinceLastSync'] : false;
		$syncAllPayments  = isset( $args['syncAllPayments'] ) ? $args['syncAllPayments'] : false;
		$mergeWooCommerceAndArcavisProducts = isset( $args['mergeWooCommerceAndArcavisProducts'] ) ? $args['mergeWooCommerceAndArcavisProducts'] : false;
		$mergeWooAndArcavisProductsById = isset( $args['mergeWooAndArcavisProductsById'] ) ? $args['mergeWooAndArcavisProductsById'] : false;
		$lookForProductsNoLongerOnApi = isset( $args['lookForProductsNoLongerOnApi'] ) ? $args['lookForProductsNoLongerOnApi'] : false;

		$output                   = '';
		$productIds               = array();
		$missingProductArticles   = '';
		$missingProductCount      = 0;
		$totalProductCount        = 0;
		$totalWooCommerceProducts = 0;
		$syncImagesDiffs          = 0;
		$syncedProducts           = array();

		SimpleStopWatch::start();


		$productsRepo = new FmArcavisWcProductsRepository();
		
		if ( $syncPayments || $syncPaymentsSinceLastSync || $syncAllPayments) {
			$transactionWorker = new FmArcavisArcavisTransactionController( $this->settingsRepository );
			$ordersRepository = new FmArcavisWcOrdersRepository();
			$syncedPaymentsCount = 0;
			
			$lastSyncUpdated = date('Y-m-d H:i:s', strtotime('-2 weeks'));
			if($syncPaymentsSinceLastSync) $lastSyncUpdated = FmLastSyncRepository::getLastSyncTime( 'articles' );
			if($syncAllPayments) $lastSyncUpdated = '';
			$ordersWithoutArcavisPayment = $ordersRepository->findAllWithoutArcavisPaymentSince($lastSyncUpdated);

			foreach($ordersWithoutArcavisPayment as $order){
				$response = $transactionWorker->arcavis_process_transaction($order->get_id(), '', $order->get_status());
				if ( $response->IsSuccessful === true && $response->Message == 'Success' ) 
					$syncedPaymentsCount++;
			}
			$output .= '<br />'.$syncedPaymentsCount.' von '.count($ordersWithoutArcavisPayment).' Bestellungen ohne Arcavis-Zahlung synchronisiert';
		}

		//delete All WooCommerce-Products
		if ( $deleteAllWooCommerceProducts ){
			FmArcavisWooCommerceInterface::DeleteAllData();
			$output .= '<br /> All WooCommerce Data deleted.';
		}
		$products = $wc_arcavis_shop->sync->api->get_products();

		if ( ! empty( $products ) ) {

			$output .= '<br />' . count( $products->Result ) . ' Products on API. (' . SimpleStopWatch::elapsedInSeconds() . 's)';

			$this->remove_duplicate_products( ! $deleteDuplicates );

			SimpleStopWatch::start();
			foreach ( $products->Result as $product ) {
				// Memory surveillance
				if ( SimpleStopWatch::memoryUsageInMb() > WC_AS_MEMORY_LIMIT_MB ) {
					return 'Cancelation high memory usage (' . SimpleStopWatch::memoryUsageStr() . ')';
				}

				$post_id = $productsRepo->findByArcavisId( $product->Id );
				if ( ! empty( $post_id ) ) {
					$productIds[] = $post_id;
				}
				if ( $post_id == false ) {
					$missingProductCount ++;
					$missingProductArticles .= $product->ArticleNumber . ', ';

					if ( $writeMissingProducts ) {
						FmArcavisSyncProduct::CreateOrUpdateProduct( $product );
						$syncedProducts[ $product->Id ] = 1;
					}
				}
				if ( $fullSyncAllProducts ) {
					FmArcavisSyncProduct::CreateOrUpdateProduct( $product );
					$syncedProducts[ $product->Id ] = 1;
				}
				if ( $fullSyncAllProductCategories ) {
					if ( ! empty( $post_id ) ) {
						$categorySync = new FmArcavisSyncCategory();
						$categorySync->UpdateProduct( $post_id, $product );
					}
				}

				if ( $syncChangedProducts ) {
					$productInterface = new FmArcavisProductInterface($post_id);
					if ( $productInterface->hasChanged( $product ) ) {
						FmArcavisSyncProduct::CreateOrUpdateProduct( $product );
						$syncedProducts[ $product->Id ] = 1;
					}
				}
				
				$imagesInterface = new FmArcavisProductImageInterface($post_id);
				if ( $syncCheckImages || $syncImages ) {
					if ( $imagesInterface->hasChanged( $product->Images ) ) {
						$syncImagesDiffs++;
					}
					if ( $syncImages ) {
						$imagesInterface->UpdateImages( $product->Images );
					}
				}

				$totalProductCount ++;
			}

			if($lookForProductsNoLongerOnApi){
				$totalNoLongerOnApiProducts = 0;
				foreach( $products->DeletedIds  as $deleteId){
					$prod = new FmArcavisProductInterface( $productsRepo->findByArcavisId($deleteId) );
					$prod->Delete();
					$totalNoLongerOnApiProducts++;					
				}
				
				$output                  .= '<br />Removed' . $totalNoLongerOnApiProducts . ' Products that are no longer on the api.';
			}

			// Run image background process
			if(!$wc_arcavis_shop->settingsRepository->settings->arcavis_doNotSyncImages)
				$wc_arcavis_shop->sync->image_background_process->save()->dispatch();

			$totalWooCommerceProducts = count( get_posts( $productsRepo->findAllByArticleId( '', '!=' ) ) );
			$output                  .= '<br />' . $missingProductCount . ' of ' . $totalProductCount . ' Arcavis Products missing.' . '(' . SimpleStopWatch::elapsedInSeconds() . 's)';
			$output                  .= '<br />' . $totalWooCommerceProducts . ' WooCommerce Products found.';
			
			// delete not needed products
			$productsToDeleteArgs            = $productsRepo->findAllByArticleId( '', '!=' );
			$productsToDeleteArgs['exclude'] = $productIds;
			$productsToDelete                = get_posts( $productsToDeleteArgs );
			$output                         .= '<br />' . count( $productsToDelete ) . ' WooCommerce Products are not on the API.';
			if ( $deleteOldProducts ) {
				foreach ( $productsToDelete as $posts ) {
					$productInterface = new FmArcavisProductInterface($posts->ID);
					$productInterface->Delete();
				}
			}
			$output .= '<br />' . count( $syncedProducts ) . ' Products synced.';

			// output
			if ( $syncCheckImages || $syncImages ) {
				$output .= '<br />' . $syncImagesDiffs . ' Images diffs.';
			}

			// memory
			$output .= '<br />Memory usage: ' . SimpleStopWatch::memoryUsageStr();

			return $output;
		} else {
			if ( is_wp_error( $products ) ) {
				return 'WP_ERROR (' . $products->get_error_message() . ')';
			}
			return 'Unknown_ERROR';
		}
		return 'No Products';
	}

	public function check_sync_state_paged( $args ) {
		global $wc_arcavis_shop;
		$output                          = '';

		$options = get_option( $this->syncworker_option_key, false );

		if ( $options == false ) {
			// init
			$options = new stdClass();
			// args
			$options->page_size       = isset( $args['itemsPerRun'] ) ? (int) $args['itemsPerRun'] : 50;
			$options->page            = 1;
			$options->totalPages      = 1;
			$options->args            = (object) array(
				'deleteDuplicates'                   => isset( $args['deleteDuplicates'] ) ? $args['deleteDuplicates'] : false,
				'writeMissingProducts'               => isset( $args['writeMissingProducts'] ) ? $args['writeMissingProducts'] : false,
				'deleteOldProducts'                  => isset( $args['deleteOldProducts'] ) ? $args['deleteOldProducts'] : false,
				'deleteAllWooCommerceProducts'       => isset( $args['deleteAllWooCommerceProducts'] ) ? $args['deleteAllWooCommerceProducts'] : false,
				'deleteAllArcavisProducts'       => isset( $args['deleteAllArcavisProducts'] ) ? $args['deleteAllArcavisProducts'] : false,
				'fullSyncAllProducts'                => isset( $args['fullSyncAllProducts'] ) ? $args['fullSyncAllProducts'] : false,
				'fullSyncAllProductCategories'       => isset( $args['fullSyncAllProductCategories'] ) ? $args['fullSyncAllProductCategories'] : false,
				'syncChangedProducts'                => isset( $args['syncChangedProducts'] ) ? $args['syncChangedProducts'] : false,
				'syncCheckImages'                    => isset( $args['syncCheckImages'] ) ? $args['syncCheckImages'] : false,
				'syncImages'                         => isset( $args['syncImages'] ) ? $args['syncImages'] : false,
				'countProductsWithoutThumbnail'      => isset( $args['countProductsWithoutThumbnail'] ) ? $args['countProductsWithoutThumbnail'] : false,
				'syncSingleProductsWithoutThumbnail' => isset( $args['syncSingleProductsWithoutThumbnail'] ) ? $args['syncSingleProductsWithoutThumbnail'] : false,
				'syncPayments' => isset( $args['syncPayments'] ) ? $args['syncPayments'] : false,
				'syncPaymentsSinceLastSync' => isset( $args['syncPaymentsSinceLastSync'] ) ? $args['syncPaymentsSinceLastSync'] : false,
				'syncAllPayments' => isset( $args['syncAllPayments'] ) ? $args['syncAllPayments'] : false,
				'mergeWooCommerceAndArcavisProducts' => isset( $args['mergeWooCommerceAndArcavisProducts'] ) ? $args['mergeWooCommerceAndArcavisProducts'] : false,
				'mergeWooAndArcavisProductsById' => isset( $args['mergeWooAndArcavisProductsById'] ) ? $args['mergeWooAndArcavisProductsById'] : false,
			);
			// no api
			$options->products        = array();
			$options->syncedProducts  = array();
			$options->missingProducts = array();
			$options->counters        = (object) array(
				'missingProductCount' => 0,
				'apiProducts'         => 0,
				'imageDiffs'          => 0,
				'syncedProducts'      => 0,
				'syncedPayments' => 0,
				'syncedPaymentsSince' => '',
				'ordersWithoutArcavisResponse' => 0,
				'mergedWooCommerceAndArcavisProducts' => 0,
				'mergedProductsLog' => '',
			);
			$options->runningTime     = 0;
		} else {
			$options                 = json_decode( $options );
			$options->syncedProducts = (array) $options->syncedProducts;
			$options->page++;
		}

		// $missingProductArticles   = '';
		// $missingProductCount      = 0;
		// $totalProductCount        = 0;
		/*
		$totalWooCommerceProducts = 0;
		$syncImagesDiffs          = 0;
		$syncedProducts           = array();*/

		if ( $options->args->countProductsWithoutThumbnail || $options->args->syncSingleProductsWithoutThumbnail ) {
			return $this->run_single_products_paged( $options );
		}

		$productsRepo = new FmArcavisWcProductsRepository();

		SimpleStopWatch::start();
		if ( $options->page == 1 && ($options->args->syncPayments || $options->args->syncPaymentsSinceLastSync || $options->args->syncAllPayments)) {
			$transactionWorker = new FmArcavisArcavisTransactionController( $this->settingsRepository );
			$ordersRepository = new FmArcavisWcOrdersRepository();
			$lastSyncUpdated = date('Y-m-d H:i:s', strtotime('-2 weeks'));
			if($options->args->syncPaymentsSinceLastSync) $lastSyncUpdated = FmLastSyncRepository::getLastSyncTime( 'articles' );
			if($options->args->syncAllPayments) $lastSyncUpdated = '';
			$ordersWithoutArcavisPayment = $ordersRepository->findAllWithoutArcavisPaymentSince($lastSyncUpdated);
			$options->counters->ordersWithoutArcavisResponse = count($ordersWithoutArcavisPayment);
			$options->counters->syncedPaymentsSince = $lastSyncUpdated;

			foreach($ordersWithoutArcavisPayment as $order){
				$response = $transactionWorker->arcavis_process_transaction($order->get_id(), '', $order->get_status());
				if ( $response->IsSuccessful === true && $response->Message == 'Success' ) 
					$options->counters->syncedPayments++;
			}
			$output .= '<br />'.$options->counters->syncedPayments.' von '.$options->counters->ordersWithoutArcavisResponse.' Bestellungen ohne Arcavis-Zahlung synchronisiert (Bestellungen nach '.$options->counters->syncedPaymentsSince.')';
		}

		//delete All WooCommerce-Products
		if ( $options->page == 1 && $options->args->deleteAllWooCommerceProducts ){
			FmArcavisWooCommerceInterface::DeleteAllData();
			$output .= '<br /> All WooCommerce Data deleted.';
		}
		//delete All Arcavis-Products
		if ( $options->page == 1 && $options->args->deleteAllArcavisProducts ){
			$productsToDeleteArgs            = $productsRepo->findAllByArticleId( '', '!=' );
			$productsToDelete                = get_posts( $productsToDeleteArgs );
			$output                         .= '<br />' . count( $productsToDelete ) . ' Arcavis-Products deleted from WooCommerce before import.';
			foreach ( $productsToDelete as $posts ) {
				$productInterface = new FmArcavisProductInterface($posts->ID);
				$productInterface->Delete();
			}
		}
		$response = $wc_arcavis_shop->sync->api->get_products_by_page( $options->page, $options->page_size );

		if ( ! empty( $response ) ) {
			$options->totalPages = $response->TotalPages;

			if ( $options->page == 1 ) {
				$this->remove_duplicate_products( ! $options->args->deleteDuplicates );
			}

			SimpleStopWatch::start();
			$productsRepository = new FmArcavisWcProductsRepository();
			foreach ( $response->Result as $product ) {
				// Memory surveillance
				if ( SimpleStopWatch::memoryUsageInMb() > WC_AS_MEMORY_LIMIT_MB ) {
					return 'Cancelation high memory usage (' . SimpleStopWatch::memoryUsageStr() . ')';
				}

				if($options->args->mergeWooCommerceAndArcavisProducts || $options->args->mergeWooAndArcavisProductsById ) {
					$woocommerceProduct= null;
					if($options->args->mergeWooCommerceAndArcavisProducts)
						$woocommerceProduct = $productsRepository->findByName($product->Title);
					elseif($options->args->mergeWooAndArcavisProductsById)
						$woocommerceProduct = $productsRepository->findBySku($product->ArticleNumber);

					$wooProductHtml = 'Ohne';
					if($woocommerceProduct) {
						$wooArcavisID = get_post_meta( $woocommerceProduct->ID, 'article_id', true );
						$wooSku = get_post_meta( $woocommerceProduct->ID, '_sku', true );
						if(!$wooArcavisID) {
							update_post_meta( $woocommerceProduct->ID, 'article_id', $product->Id );
							$options->counters->mergedWooCommerceAndArcavisProducts++;
							$options->products[] = $woocommerceProduct->ID;
							$wooProductHtml = $woocommerceProduct->post_title.' ('.$wooSku.', Woo-ID '.$woocommerceProduct->ID.')';
						}
						else{
							$wooProductHtml = 'Bereits verknüpft<br />'.$woocommerceProduct->post_title.' ('.$wooSku.', Woo-ID '.$woocommerceProduct->ID.')';
						}
					}
					$options->counters->mergedProductsLog .= '<div class="col s12"><div class="col s6">'.$product->Title.'('.$product->ArticleNumber.')</div><div class="col s6">'.$wooProductHtml.'</div></div>';
				}

				$productsRepo = new FmArcavisWcProductsRepository();
				$post_id = $productsRepo->findByArcavisId( $product->Id );
				if ( ! empty( $post_id ) ) {
					$options->products[] = $post_id;
				}
				if ( $post_id == false ) {
					$options->counters->missingProductCount ++;
					$options->missingProducts[] = $product->ArticleNumber;

					if ( $options->args->writeMissingProducts ) {
						FmArcavisSyncProduct::CreateOrUpdateProduct( $product );
						$options->syncedProducts[ $product->Id ] = 1;
					}
				}
				if ( $options->args->fullSyncAllProducts ) {
					FmArcavisSyncProduct::CreateOrUpdateProduct( $product );
					$options->syncedProducts[ $product->Id ] = 1;
				}
				if ( $options->args->fullSyncAllProductCategories ) {
					if ( ! empty( $post_id ) ) {
						$categorySync = new FmArcavisSyncCategory();
						$categorySync->UpdateProduct( $post_id, $product );
					}
				}

				if ( $options->args->syncChangedProducts ) {
					$productInterface = new FmArcavisProductInterface($post_id);
					if ( $productInterface->hasChanged( $product ) ) {
						FmArcavisSyncProduct::CreateOrUpdateProduct( $product );
						$options->syncedProducts[ $product->Id ] = 1;
					}
				}

				$imagesInterface = new FmArcavisProductImageInterface($post_id);
				if ( $options->args->syncCheckImages || $options->args->syncImages ) {
					if ( $imagesInterface->hasChanged( $product->Images ) ) {
						$options->counters->imageDiffs++;
					}
					if ( $options->args->syncImages ) {
						$imagesInterface->UpdateImages( $product->Images );
					}
				}

				$options->counters->apiProducts++;
			}

			// Run image background process
			if(!$wc_arcavis_shop->settingsRepository->settings->arcavis_doNotSyncImages)
				$wc_arcavis_shop->sync->image_background_process->save()->dispatch();

			if ( $options->page < $response->TotalPages ) {
				$options->runningTime += SimpleStopWatch::elapsedInSeconds();
				// run again
				update_option( $this->syncworker_option_key, json_encode( $options ) );
				return 'Page ' . $options->page . ' of ' . $options->totalPages;
			} else {
				// delete not needed products
				$productsToDeleteArgs            = $productsRepo->findAllByArticleId( '', '!=' );
				$productsToDeleteArgs['exclude'] = $options->products;
				$productsToDelete                = get_posts( $productsToDeleteArgs );
				$output                         .= '<br />' . count( $productsToDelete ) . ' WooCommerce Products are not on the API.';
				if ( $options->args->deleteOldProducts ) {
					foreach ( $productsToDelete as $posts ) {
						$productInterface = new FmArcavisProductInterface($posts->ID);
						$productInterface->Delete();
					}
				}

				$totalWooCommerceProducts = count( get_posts( $productsRepo->findAllByArticleId( '', '!=' ) ) );
				$output                  .= '<br />' . $options->counters->missingProductCount . ' of ' . $options->counters->apiProducts . ' Arcavis Products missing.' . '(' . $options->runningTime . 's)';
				$output                  .= '<br />' . $totalWooCommerceProducts . ' WooCommerce Products found.';
				$output                  .= '<br />' . count( $options->syncedProducts ) . ' Products synced.';
				if ( $options->args->syncCheckImages || $options->args->syncImages ) {
					$output .= '<br />' . $options->counters->imageDiffs . ' Images diffs.';
					$output .= ($options->args->syncImages) ? ' (done)' : '';
				}
				if($options->args->syncPayments || $options->args->syncPaymentsSinceLastSync || $options->args->syncAllPayments)
					$output .= '<br />'.$options->counters->syncedPayments.' von '.$options->counters->ordersWithoutArcavisResponse.' Bestellungen ohne Arcavis-Zahlung synchronisiert (Bestellungen nach '.$options->counters->syncedPaymentsSince.')';
				if($options->args->mergeWooCommerceAndArcavisProducts || $options->args->mergeWooAndArcavisProductsById )
					$output .= '<br />'.$options->counters->mergedWooCommerceAndArcavisProducts.' Produkte von WooCommerce mit Arcavis verknüpft. <a class="details-link" href="javascript:jQuery(\'.merged-products-log\').toggle();">Details anzeigen</a><div class="row merged-products-log" style="display: none;"><div class="col s12"><div class="col s6">Arcavis-Produkt</div><div class="col s6">WooCommerce</div></div>'.$options->counters->mergedProductsLog.'</div>';
				$output .= '<br />Memory usage: ' . SimpleStopWatch::memoryUsageStr();

				delete_option( $this->syncworker_option_key );
				return $output;
			}
		} else {
			if ( is_wp_error( $products ) ) {
				return 'WP_ERROR (' . $products->get_error_message() . ')';
			}
			return 'Unknown_ERROR';
		}
		return 'No Products';
	}

	public function run_single_products_paged( $options ) {
		global $wc_arcavis_shop;

		// get products
		if ( $options->page == 1 ) {
			$args = $productsRepo->findAllByArticleId( '', '!=' );
			// only with empty thumbnail
			$args['meta_query']['relation'] = 'AND';
			$args['meta_query'][]           = array(
				'relation' => 'OR',
				array(
					'key'     => '_thumbnail_id',
					'value'   => '',
					'compare' => '==',
				),
				array(
					'key'     => '_thumbnail_id',
					'compare' => 'NOT EXISTS',
				),
			);
			$args['fields']                 = 'ids';

			$options->posts      = get_posts( $args );
			$options->totalPages = round( count( $options->posts ) / $options->page_size, 0 );
			if ($options->args->countProductsWithoutThumbnail) {
				// no run
				$options->totalPages = 0;
			}
		} else {
			$options->page++;
		}

		SimpleStopWatch::start();

		for ( $index = ( $options->page - 1 ) * $options->page_size; $index < $options->page * $options->page_size; $index++ ) {
			if ( key_exists( $index, $options->posts ) ) {
				$product_id = $options->posts[ $index ];
				if ( ! empty( $product_id ) ) {
					$wc_arcavis_shop->sync->update_single_product( $product_id );
				}
			}
		}

		if ( $options->page < $options->totalPages ) {
			$options->runningTime += SimpleStopWatch::elapsedInSeconds();
			// run again
			update_option( $this->syncworker_option_key, json_encode( $options ) );
			return 'Page ' . $options->page . ' of ' . $options->totalPages;
		} else {
			if ( $options->args->countProductsWithoutThumbnail ) {
				$output = '<br />' . count( $options->posts ) . ' Products without thumbnail found!';
			} else {
				$output = '<br />' . count( $options->posts ) . ' Products synced without thumbnail!';
			}
			delete_option( $this->syncworker_option_key );
			return $output;
		}
	}

	public function remove_duplicate_products( $dry_run = true ) {
		SimpleStopWatch::start();
		// check if there are duplicates
		$productsRepo = new FmArcavisWcProductsRepository();
		$duplicates_product_ids = $productsRepo->findAllDuplicates();
		if ( count( $duplicates_product_ids ) > 0 && ! $dry_run ) {
			foreach ( $duplicates_product_ids as $post_id ) {
				$productInterface = new FmArcavisProductInterface($post_id);
				$productInterface->Delete();
			}
		}
		return '<br />' . count( $duplicates_product_ids ) . ' duplicated WooCommerce Products found. (' . SimpleStopWatch::elapsedInSeconds() . 's)';
	}
}
