<?php
defined( 'ABSPATH' ) or die( 'No access!' );

class FmArcavisCronController {
	public $settingsRepository;
	public $logger;
	public $sync;
	public $transaction;

    public function __construct(FmArcavisSettingsRepository $settingsRepository, FmLogRepository $logger) {
		$this->settingsRepository = $settingsRepository;
        $this->logger = $logger;
        
		$this->sync        = new WooCommerce_Arcavis_Sync_Products( $this->settingsRepository );
		$this->transaction = new FmArcavisArcavisTransactionController( $this->settingsRepository );

		// CRON
		add_filter( 'cron_schedules', array( $this, 'arcavis_add_cron_recurrence_interval' ) );
		add_action( 'arcavis_minutes_schedule_action', array( $this, 'arcavis_start_update_sync' ) );
		add_action( 'arcavis_daily_schedule_action', array( $this, 'arcavis_daily_update_sync' ) );
    }

	public function arcavis_start_update_sync() {
		global $wc_arcavis_shop;
		$wc_arcavis_shop->logger->logDebug( '**arcavis_start_update_sync' );
		//@TODO: Replace with SyncChecker
		$product_count = $this->sync->update_products();
		$payments_message = $this->sync->update_payments($this->settingsRepository);
		$wc_arcavis_shop->logger->logInfo( '**arcavis_update_sync_done (' . $product_count . ', '.$payments_message.')' );

		do_action('arcavis_after_minutes_sync');

	}

	public function arcavis_daily_update_sync() {
		$sync_args                        = array();
		$sync_args['fullSyncAllProducts'] = true;
		$sync_args['fullSyncAllProductCategories'] = true;
		$syncer = new ArcavisSyncChecker();
		$syncer->run( $sync_args, true );
		do_action('arcavis_after_daily_sync');
	}
    
	// This function Schedule a cron for give time interval from setting in admin.
	public function arcavis_add_cron_recurrence_interval( $schedules ) {
		$settings  = $this->settingsRepository->settings;

		if ( isset( $settings->arcavis_link ) ) {
			$schedules['arcavis_minutes'] = array(
				'interval' => $settings->arcavis_sync_interval * 60,
				'display'  => 'Arcavis Minutes',
			);
			$schedules['arcavis_daily']   = array(
				'interval' => 24 * 3600,
				'display'  => 'Arcavis Daily',
			);
		}

		return $schedules;
	}
}