<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WC_Arcavis_Shop {

	/**
	 * @var WC_Arcavis_Shop - the single instance of the class
	 * @since 1.0.3
	 */

	protected static $_instance = null;

	public $sync                 = null;
	public $transaction_response = null;
	public $settingsRepository;
	public $cron;
	public $logger;
	public $transactionFrontend;

	/* Texts */
	//public $text_entervoucherplaceholder     = 'Gutschein-Nummer';
	// public $text_syncstarted                 = 'Daten werden synchronisiert. Dies wird mehrere Minuten dauern...';
	// public $text_dontreload                  = 'Bitte Seite nicht neu laden';


	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	public function __construct() {
		$this->settingsRepository = new FmArcavisSettingsRepository();
		$this->logger = new FmLogRepository();
		
		$this->define_constant();
		$this->load_required_files();

		$this->cron = new FmArcavisCronController($this->settingsRepository, $this->logger);

		add_action( 'init', array( $this, 'init' ) );
		// add_action( 'plugins_loaded', array( $this, 'init' ) );
		$plugin = dirname( plugin_basename( __FILE__ ), 2 );

		// AJAX WP ADMIN Calls
		add_action( 'wp_ajax_arcavis_start_initial_sync', array( $this, 'arcavis_start_initial_sync' ) );
		add_action( 'wp_ajax_nopriv_arcavis_start_initial_sync', array( $this, 'arcavis_start_initial_sync' ) );
		add_action( 'admin_action_resync_single_product', array( $this, 'resync_single_product' ) );
	}//end __construct()


	public function init() {
		$this->init_class();
		// print_r(wp_get_schedules());
	}

	private function load_required_files() {
		//$this->load_files( WC_AS_INC_ADMIN . 'shop-setting.php' );
		//$this->load_files( WC_AS_INC . 'arcavis-sync-product-images.php' );
		//$this->load_files( WC_AS_INC . 'arcavis-sync-product-woocommerce.php' );
		//$this->load_files( WC_AS_INC . 'arcavis-sync-products-api.php' );
		$this->load_files( WC_AS_INC . 'arcavis-sync-products.php' );
		//$this->load_files( WC_AS_INC . 'payment-gateway.php' );
		//$this->load_files( WC_AS_INC . 'arcavis-transaction.php' );
	}

	private function init_class() {
		$this->sync        = new WooCommerce_Arcavis_Sync_Products( $this->settingsRepository );
		$this->transaction = new FmArcavisArcavisTransactionController( $this->settingsRepository );
		$this->transactionFrontend = new FmArcavisArcavisTransactionFrontendController( $this->settingsRepository );
	}

	public function load_files( $path, $type = 'require' ) {
		foreach ( glob( $path ) as $files ) {
			if ( $type == 'require' ) {
				require_once $files;
			} elseif ( $type == 'include' ) {
				include_once $files;
			}
		}
	}

	public function arcavis_start_initial_sync() {
		global $wc_arcavis_shop;
		$wc_arcavis_shop->logger->logDebug( '**arcavis_start_initial_sync' );
		$this->sync->create_products_init();
	}


	public function arcavis_start_update_stock_sync() {
		global $wc_arcavis_shop;
		$wc_arcavis_shop->logger->logDebug( '**arcavis_start_update_sync_stock' );
		$stockSync = new FmArcavisSyncStock($wc_arcavis_shop->settingsRepository);
		$product_count = $stockSync->runAll();
		$wc_arcavis_shop->logger->logInfo( '**arcavis_update_sync_stock_done (' . $product_count . ' Products)' );
		do_action('arcavis_after_stock_sync');
	}	

	public function resync_single_product( $args ) {
		// Security
		if ( empty( $_REQUEST['post'] ) ) {
			wp_die( 'No product has been supplied!' );
		}

		$product_id = isset( $_REQUEST['post'] ) ? absint( $_REQUEST['post'] ) : '';

		if ( ! empty( $product_id ) ) {
			$this->sync->update_single_product( $product_id );
		}

		wp_redirect( admin_url( 'post.php?action=edit&post=' . $product_id ) );
		exit;
	}

	private function define_constant() {
		$this->define( 'WC_AS_PATH', plugin_dir_path( __FILE__ ) );
		$this->define( 'WC_AS_INC', WC_AS_PATH . 'includes/' );
		$this->define( 'WC_AS_INC_ADMIN', WC_AS_PATH . 'includes/admin/' );
	}

	protected function define( $key, $value ) {
		if ( ! defined( $key ) ) {
			define( $key, $value );
		}
	}

	public function is_request( $type ) {
		switch ( $type ) {
			case 'admin':
				return is_admin();
			case 'ajax':
				return defined( 'DOING_AJAX' );
			case 'cron':
				return defined( 'DOING_CRON' );
			case 'frontend':
				return ( ! is_admin() || defined( 'DOING_AJAX' ) ) && ! defined( 'DOING_CRON' );
		}
	}

	public function get_last_page() {
		global $wpdb;
		$lastSync = '';
		$result   = $wpdb->get_row( 'SELECT * FROM ' . $wpdb->prefix . 'lastSyncPage ORDER BY id DESC LIMIT 0,1', OBJECT );
		if ( ! empty( $result ) ) {
			$lastPage = $result->lastPage;
		}
		return $lastPage;
	}

}//end class

function WC_Arcavis_Shop() {
	return WC_Arcavis_Shop::instance();
}
// Launch the whole plugin
$GLOBALS['wc_arcavis_shop'] = WC_Arcavis_Shop();
