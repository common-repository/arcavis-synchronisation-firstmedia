<?php
defined( 'ABSPATH' ) or die( 'No guetsli!' );

/**
 * Plugin Name:           Arcavis-Synchronisation - FirstMedia
 * Plugin URI:            https://www.firstmedia.swiss/angebot/webentwicklung-e-commerce-webhosting/
 * Description:           Synchronisiert Produkte von Arcavis in WooCommerce
 * Version:               2.2.21
 * Author:                FirstMedia Solutions GmbH
 * Author URI:            https://www.firstmedia.swiss
 * Text Domain:           fm-wc-arcavis
 * Domain Path:           /i18n
 * WC requires at least:  3.7
 * WC tested up to:       5.5
 */

define( 'FM_WC_AS_VER', '2.2.21' );
define( 'FM_WC_AS_VER_HASH', '2.0.9' );
define( 'FM_WC_AS_PLUGIN_BASEFILE', plugin_basename(__FILE__) );
define( 'FM_WC_AS_TEXTDOMAIN', 'fm-wc-arcavis');

function fmArcavis_deactivate_old_plugin_error_notice(){
	echo '<div class="notice notice-error">
		<p>'.__('Bitte deaktivieren Sie die alte Version der Arcavis-Synchronisation. Neu wird die Synchronisation vom FirstMedia Arcavis durchgeführt.').' <a href="'.get_admin_url().'plugins.php">'.__('Plugins verwalten').'</a></p>
	</div>';
}
function fmArcavis_display_woocommerce_needed_error(){
	echo '<div class="notice notice-error">
		<p>'.__('WooCommerce wird benötigt, damit die Arcavis-Synchronisation verwendet werden kann. Bitte aktivieren Sie WooCommerce.').' <a href="'.get_admin_url().'plugins.php">'.__('Plugins verwalten').'</a></p>
	</div>';
}
if ( in_array( 'wc-arcavis-shop/woocommerce-arcavis-shop.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ){
	add_action('admin_notices', 'fmArcavis_deactivate_old_plugin_error_notice' );
	return;
}
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ){
	add_action('admin_notices', 'fmArcavis_display_woocommerce_needed_error' );
	return;
}

require_once 'backend/load.php';
require_once 'lib/load.php';
require_once 'class-installer.php';
require_once 'arcavis_woocommerce-master/woocommerce-arcavis-shop.php';

class FmArcavisPlugin {
	public $backend;

	function __construct() {
		$this->backend = new FmArcavisBackend();

		// Plugin setup
		register_activation_hook( __FILE__, array( $this, 'plugin_activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'plugin_deactivate' ) );

		add_action( 'plugins_loaded', array( $this, 'load_translation' ) );
		if('' == FmLastSyncRepository::getLastSync( 'articles' ))
			add_action('admin_notices', array( $this, 'start_initial_sync_notice' ) );
	}

	public function plugin_activate() {
		$installer = new FmArcavisInstaller();
		$installer->activation_tasks();
	}

	public function plugin_deactivate() {
		$installer = new FmArcavisInstaller();
		$installer->deactivate_tasks();
	}

	public function load_translation() {
		load_plugin_textdomain( FM_WC_AS_TEXTDOMAIN, false, dirname( plugin_basename( __FILE__ ) ) . '/i18n/' );
	}

	public function start_initial_sync_notice(){
		if ( !isset($_GET['page']) || substr( $_GET['page'], 0, 7 ) != 'arcavis' ) 
			 echo '<div class="notice notice-warning">
				 <p>'.__('Die Arcavis-Erstsynchronisierung wurde noch nicht durchgeführt.').' <a href="'.get_admin_url().'admin.php?page=arcavis-settings">'.__('Jetzt konfigurieren und starten').'</a></p>
			 </div>';
	}
}
new FmArcavisPlugin();
