<?php
defined( 'ABSPATH' ) or die( 'No access!' );

class FmArcavisInstaller {
	public $logger;

	public function __construct() {
        $this->logger = new FmLogRepository();
    }


	/***
	 * This function will run at the time of plugin activation set up cron, custom database tables..
	 */
	public function activation_tasks() {
		$this->activate_cron_events();
		$this->logger->install();

		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table_name = $wpdb->prefix . 'lastSyncTicks';

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			apiName varchar(255) NOT NULL,
			lastSync bigint(20) NOT NULL,
			updated timestamp DEFAULT CURRENT_TIMESTAMP NOT NULL,
			UNIQUE KEY id (id)
		) $charset_collate;";

		dbDelta( $sql );
		$checkSyncApi = $wpdb->get_results( 'SELECT * FROM ' . $table_name . " WHERE apiName = 'articles' OR apiName = 'vouchers' OR apiName = 'articlestocks'", OBJECT );
		if ( empty( $checkSyncApi ) ) {
			$wpdb->insert(
				$table_name,
				array(
					'apiName'  => 'articles',
					'lastSync' => '',
					'updated'  => current_time( 'mysql' ),
				)
			);
			$wpdb->insert(
				$table_name,
				array(
					'apiName'  => 'vouchers',
					'lastSync' => '',
					'updated'  => current_time( 'mysql' ),
				)
			);
			$wpdb->insert(
				$table_name,
				array(
					'apiName'  => 'articlestocks',
					'lastSync' => '',
					'updated'  => '',
				)
			);
		}

		$table_name2 = $wpdb->prefix . 'lastSyncPage';

		$sql2 = "CREATE TABLE $table_name2 (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			lastPage int(11) NOT NULL,
			UNIQUE KEY id (id)
		) $charset_collate;";

		dbDelta( $sql2 );
		$checkSyncPage = $wpdb->get_results( 'SELECT * FROM ' . $table_name2, OBJECT );
		if ( empty( $checkSyncPage ) ) {
			$wpdb->insert( $table_name2, array( 'lastPage' => '1' ) );
		}

		$table_name3 = $wpdb->prefix . 'applied_vouchers';

		$sql3 = "CREATE TABLE $table_name3 (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			session_id varchar(255) NOT NULL,
			voucher_code varchar(255) NOT NULL,
			discount_amount varchar(255) NOT NULL,
			discount_type varchar(255) NOT NULL,
			transaction_response longtext NOT NULL,
			UNIQUE KEY id (id)
		) $charset_collate;";

		dbDelta( $sql3 );
    }
	public function activate_cron_events() {
		if ( ! wp_next_scheduled( 'arcavis_minutes_schedule_action' ) ) {
			wp_schedule_event( time(), 'arcavis_minutes', 'arcavis_minutes_schedule_action' );
		}
		if ( ! wp_next_scheduled( 'arcavis_daily_schedule_action' ) ) {
			wp_schedule_event( strtotime( '02:20:00' ), 'arcavis_daily', 'arcavis_daily_schedule_action' );
		}
	}
    
    
	// Function will call at deactivation of plugin.
	public function deactivate_tasks() {
		wp_clear_scheduled_hook( 'arcavis_schedule_api_hook' ); // old hook
		wp_clear_scheduled_hook( 'arcavis_minutes_schedule_action' );
		wp_clear_scheduled_hook( 'arcavis_daily_schedule_action' );
	}

}