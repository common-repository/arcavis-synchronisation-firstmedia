<?php
/*This Class Has functions for Syncronize the products from arcavis.*/
require_once(WP_PLUGIN_DIR  . '/woocommerce/includes/libraries/wp-async-request.php');
require_once(WP_PLUGIN_DIR  . '/woocommerce/includes/libraries/wp-background-process.php');

class FmArcavisSyncWorkerItem{
	var $post_id;
	var $arcavisdata;
}

class FmArcavisSyncWorker extends WP_Background_Process {

	/**
	 * @var string
	 */
	protected $action = 'arcavis_sync_bg';
	
	/**
	 * Cron interval in Minutes
	 */
	protected $cron_interval = 1;

	/**
	 * Task
	 *
	 * Override this method to perform any actions required on each
	 * queue item. Return the modified item for further processing
	 * in the next pass through. Or, return false to remove the
	 * item from the queue.
	 *
	 * @param mixed $item Queue item to iterate over
	 *
	 * @return mixed
	 */
	protected function task( $item ) {
		$post_id = $item->post_id;
		$data = $item->images;
		return false;
	}

  /**
   * Complete
   *
   * Override if applicable, but ensure that the below actions are
   * performed, or, call parent::complete().
   */
	protected function complete() {
		global $wc_arcavis_shop;

		parent::complete();

		$wc_arcavis_shop->logger->logInfo('**arcavis_sync successfully');
	}

}

