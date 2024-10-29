<?php
defined( 'ABSPATH' ) or die( 'No guetsli!' );

/*This File is for managing all settings of the Arcavis in the admin area.*/

class FmArcavisAdminPages {
	public $settingsRepo;
	public $syncChecker;
	private $error = false;
	private $message = '';

	public function __construct( FmArcavisSettingsRepository $settingsRepository ) {
		$this->settingsRepo    = $settingsRepository;
		$this->syncChecker = new ArcavisSyncChecker($settingsRepository);

		add_action( 'admin_menu', array( $this, 'arcavis_setting' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'arcavis_setting_scripts_admin' ) );
	}

	public function arcavis_setting_scripts_admin() {
		wp_enqueue_style( 'aracavis_style', plugin_dir_url( __FILE__ ) . 'assets/css/arcavis.css', null, FM_WC_AS_VER );
		wp_enqueue_script( 'aracavis_script', plugin_dir_url( __FILE__ ) . 'assets/js/arcavis-settings.js', 'jQuery', FM_WC_AS_VER );
		
		global $pagenow;
		if ( $pagenow == 'admin.php' && substr( $_GET['page'], 0, 7 ) === 'arcavis' ) {
			wp_enqueue_script( 'aracavis_forms_script', plugin_dir_url( __FILE__ ) . 'assets/js/arcavis-forms.js', 'jQuery', FM_WC_AS_VER );
			wp_enqueue_style( 'materialize_style', plugin_dir_url( __FILE__ ) . '../vendor/materialize/css/materialize.min.css', null, FM_WC_AS_VER );
			wp_enqueue_script( 'materialize_script', plugin_dir_url( __FILE__ ) . '../vendor/materialize/js/materialize.min.js', 'jQuery', FM_WC_AS_VER );
		}
	}

	public function arcavis_setting() {
		global $wc_arcavis_shop;
		add_menu_page(
			'arcavis',
			'arcavis',
			'manage_options',
			'arcavis-info',
			array(
				$this,
				'arcavis_info_page',
			),
			plugins_url( 'assets/images/arcavis_icon.svg', __FILE__ ),
			'55.6.0'
		);
		add_submenu_page(
			'arcavis-info',
			__( 'Overview', FM_WC_AS_TEXTDOMAIN ),
			__( 'Overview', FM_WC_AS_TEXTDOMAIN ),
			'manage_options',
			'arcavis-info'
		);
		add_submenu_page(
			'arcavis-info',
			__( 'Settings', FM_WC_AS_TEXTDOMAIN ),
			__( 'Settings', FM_WC_AS_TEXTDOMAIN ),
			'manage_options',
			'arcavis-settings',
			array(
				$this,
				'arcavis_setting_page',
			)
		);
		add_submenu_page(
			'arcavis-info',
			__( 'Synchronisation', FM_WC_AS_TEXTDOMAIN ),
			__( 'Synchronisation', FM_WC_AS_TEXTDOMAIN ),
			'manage_options',
			'arcavis-sync',
			array(
				$this,
				'arcavis_sync_page',
			)
		);
		add_submenu_page(
			'arcavis-info',
			__( 'Log', FM_WC_AS_TEXTDOMAIN ),
			__( 'Log', FM_WC_AS_TEXTDOMAIN ),
			'manage_options',
			'arcavis-log',
			array(
				$this,
				'arcavis_log_page',
			)
		);
	}

	public function arcavis_info_page() {
		global $wc_arcavis_shop;

		if ( isset( $_POST['sync_manual'] ) ) {
			$wc_arcavis_shop->cron->arcavis_start_update_sync();
		} else if ( isset( $_POST['sync_stocks'] ) ) {
			$wc_arcavis_shop->arcavis_start_update_stock_sync();
		} else if ( isset( $_POST['sync_daily'] ) ) {
			$wc_arcavis_shop->cron->arcavis_daily_update_sync();
		}

		require_once 'views/info.php';
	}

	public function arcavis_sync_page() {
		global $wc_arcavis_shop;

		$running_options = get_option( $this->syncChecker->syncworker_option_key, false );
		$running = $running_options != false;
		
		if ( isset( $_POST['sync_check'] ) || $running ) {
			$args = $this->getSyncCheckerArgs();
			$this->syncChecker->run( $args );
			if (!$running) {
				$running_options = get_option( $this->syncChecker->syncworker_option_key, false );
				$running = $running_options != false;
			}
		}

		if ($running) {
			$running_options = json_decode($running_options);
			if ( isset( $_POST['sync_cancel'] ) ) {
				delete_option( $this->syncChecker->syncworker_option_key );
				$running = false;
			}
		}
		require_once 'views/sync.php';
	}

	public function arcavis_setting_page() {
		if ( isset( $_POST['save_settings'] ) ) {
			$this->settingsRepo->settings->arcavis_link = rtrim( sanitize_text_field( $_POST["arcavis_link"]),'/');
			$this->settingsRepo->settings->arcavis_username = sanitize_text_field($_POST["arcavis_username"]);
			$this->settingsRepo->settings->arcavis_password = sanitize_text_field($_POST["arcavis_password"]);
			$this->settingsRepo->settings->arcavis_sync_interval = sanitize_text_field($_POST["arcavis_sync_interval"]);
			$this->settingsRepo->settings->arcavis_doNotSyncDescription = sanitize_text_field($_POST["arcavis_doNotSyncDescription"]);
			$this->settingsRepo->settings->arcavis_addtionalConfigJson = stripslashes( $_POST["arcavis_addtionalConfigJson"] );
			$this->settingsRepo->settings->arcavis_doNotSyncImages = sanitize_text_field($_POST["arcavis_doNotSyncImages"]);
			if (isset($_POST['arcavis_filter_webshop'])) {
				$this->settingsRepo->settings->arcavis_filter_by_tag = 'WebShop';
			} else {
				$this->settingsRepo->settings->arcavis_filter_by_tag = '';
			}
			$this->settingsRepo->save();
			if(!$this->testArcavisConnection()){
				$this->error = true;
				$this->message = __('Die Einstellungen wurden gespeichert, es konnte aber keine Verbindung zur Arcavis-API hergestellt werden. Bitte prüfen Sie die Konfiguration.<br /><br />Hinweis: Der Benutzer muss ein Benutzer mit der Rolle WebAPI sein, nicht ein Adminbenutzer.', FM_WC_AS_TEXTDOMAIN);
			}
		}		
		
		require_once 'views/settings.php';
	}
	private function testArcavisConnection(){
		try {
			$apiRepos = new FmArcavisApiRepository($this->settingsRepo);
			$apiRepos->get_products('&pageSize=1&page=1');
		}
		catch(Exception $ex){
			return false;
		}
		return true;
	}

	public function arcavis_log_page() {
		global $wpdb;
		$logs = $wpdb->get_results( 'SELECT date, level, message FROM ' . $wpdb->prefix . 'arcavis_logs ORDER BY date DESC Limit 0,50' );
		require_once 'views/log.php';
	}

	public function get_log_msg_level_output( $level, $includeText = false ) {
		if ( $level == 'DEBUG' ) {
			$output = '<span style="color:#7b7b7b" class="dashicons dashicons-code-standards"></span>';
		} elseif ( $level == 'ERROR' ) {
			$output = '<span style="color:#dc3232" class="dashicons dashicons-welcome-comments"></span>';
		} else {
			// INFO
			$output = '<span style="color:#0073aa" class="dashicons dashicons-info"></span>';
		}

		// convert to statelabel (add text)
		if ( $includeText ) {
			$output .= ' ' . $level;
		}

		return $output;
	}

	public function get_log_msg_output( $log ) {
		$levelicon = $this->get_log_msg_level_output( $log->level );
		return $levelicon . '<strong> ' . $log->date . ' ' . $log->level . '</strong><br />' . $log->message;
	}

	private function getSyncCheckerArgs() {
		$sync_args = array();
		if ( isset( $_POST['itemsPerRun'] ) ) {
			$sync_args['itemsPerRun'] = sanitize_text_field($_POST['itemsPerRun']);
		}
		// options
		if ( isset( $_POST['deleteDuplicates'] ) ) {
			$sync_args['deleteDuplicates'] = sanitize_text_field($_POST['deleteDuplicates']) == 'on';
		}
		if ( isset( $_POST['writeMissingProducts'] ) ) {
			$sync_args['writeMissingProducts'] = sanitize_text_field($_POST['writeMissingProducts']) == 'on';
		}
		if ( isset( $_POST['deleteOldProducts'] ) ) {
			$sync_args['deleteOldProducts'] = sanitize_text_field($_POST['deleteOldProducts']) == 'on';
		}
		if ( isset( $_POST['deleteAllWooCommerceProducts'] ) ) {
			$sync_args['deleteAllWooCommerceProducts'] = sanitize_text_field($_POST['deleteAllWooCommerceProducts']) == 'on';
		}
		if ( isset( $_POST['deleteAllArcavisProducts'] ) ) {
			$sync_args['deleteAllArcavisProducts'] = sanitize_text_field($_POST['deleteAllArcavisProducts']) == 'on';
		}
		if ( isset( $_POST['fullSyncAllProducts'] ) ) {
			$sync_args['fullSyncAllProducts'] = sanitize_text_field($_POST['fullSyncAllProducts']) == 'on';
		}
		if ( isset( $_POST['fullSyncAllProductCategories'] ) ) {
			$sync_args['fullSyncAllProductCategories'] = sanitize_text_field($_POST['fullSyncAllProductCategories']) == 'on';
		}
		if ( isset( $_POST['syncChangedProducts'] ) ) {
			$sync_args['syncChangedProducts'] = sanitize_text_field($_POST['syncChangedProducts']) == 'on';
		}
		if ( isset( $_POST['syncCheckImages'] ) ) {
			$sync_args['syncCheckImages'] = sanitize_text_field($_POST['syncCheckImages']) == 'on';
		}
		if ( isset( $_POST['syncImages'] ) ) {
			$sync_args['syncImages'] = sanitize_text_field($_POST['syncImages']) == 'on';
		}
		if ( isset( $_POST['countProductsWithoutThumbnail'] ) ) {
			$sync_args['countProductsWithoutThumbnail'] = sanitize_text_field($_POST['countProductsWithoutThumbnail']) == 'on';
		}
		if ( isset( $_POST['syncSingleProductsWithoutThumbnail'] ) ) {
			$sync_args['syncSingleProductsWithoutThumbnail'] = sanitize_text_field($_POST['syncSingleProductsWithoutThumbnail']) == 'on';
		}
		if ( isset( $_POST['syncPayments'] ) ) {
			$sync_args['syncPayments'] = sanitize_text_field($_POST['syncPayments']) == 'on';
		}
		if ( isset( $_POST['syncPaymentsSinceLastSync'] ) ) {
			$sync_args['syncPaymentsSinceLastSync'] = sanitize_text_field($_POST['syncPaymentsSinceLastSync']) == 'on';
		}
		if ( isset( $_POST['syncAllPayments'] ) ) {
			$sync_args['syncAllPayments'] = sanitize_text_field($_POST['syncAllPayments']) == 'on';
		}
		if ( isset( $_POST['mergeWooCommerceAndArcavisProducts'] ) ) {
			$sync_args['mergeWooCommerceAndArcavisProducts'] = sanitize_text_field($_POST['mergeWooCommerceAndArcavisProducts']) == 'on';
		}
		if ( isset( $_POST['mergeWooAndArcavisProductsById'] ) ) {
			$sync_args['mergeWooAndArcavisProductsById'] = sanitize_text_field($_POST['mergeWooAndArcavisProductsById']) == 'on';
		}
		return $sync_args;
	}
}


