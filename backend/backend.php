<?php
defined( 'ABSPATH' ) or die( 'No guetsli!' );

/*This File is for managing all settings in the admin area.*/

class FmArcavisBackend {
	private $adminPages;
	private $settingsRepo;

	public function __construct() {

		$this->settingsRepo = new FmArcavisSettingsRepository();
		$this->adminPages   = new FmArcavisAdminPages( $this->settingsRepo );

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_custom_js_css' ) );

		if ( is_admin() ) {
			add_filter( 'plugin_action_links_' . FM_WC_AS_PLUGIN_BASEFILE, array( $this, 'arcavis_add_settings_link' ), 10, 4 );
		}
	}

	public function admin_custom_js_css() {
		global $post, $post_type;
		if ( ( $post_type == 'product' ) && isset( $post ) ) {
			wp_enqueue_script( 'arcavis-product-edit.js', plugins_url( '../backend/assets/js/arcavis-product-edit.js', __FILE__ ), array( 'jquery' ), FM_WC_AS_VER, true );
			echo '<script>
              var arcavis_logo="' . plugins_url( '../backend/assets/images/arcavis_logo.png', __FILE__ ) . '";
              var arcavis_article_id = ' . get_post_meta( $post->ID, 'article_id', true ) . ';
			  var arcavis_resync_link = "' . wp_nonce_url( admin_url( 'edit.php?post_type=product&action=resync_single_product&post=' . $post->ID ) ) . '";
			  var arcavis_doNotSyncDescription = '. ($this->settingsRepo->settings->arcavis_doNotSyncDescription ? 'true': 'false') .';
			  var arcavis_doNotSyncImages = '. ($this->settingsRepo->settings->arcavis_doNotSyncImages ? 'true': 'false') .';
			  var arcavis_doNotSyncCategories = '. ($this->settingsRepo->getAdditionalSettings()->syncExclude->doNotSyncCategories ? 'true': 'false') .';
			  var arcavis_addiotionalConfig = '.json_encode($this->settingsRepo->getAdditionalSettings()).';
              console.log(arcavis_resync_link);
            </script>';
		}
	}

	public function arcavis_add_settings_link( $links ) {
		$settings_link = '<a href="admin.php?page=arcavis-settings">' . __( 'Settings', FM_WC_AS_TEXTDOMAIN ) . '</a>';
		array_push( $links, $settings_link );
		return $links;
	}

}
