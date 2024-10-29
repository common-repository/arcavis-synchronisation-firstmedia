<?php
/*This Class Has functions for Syncronize the products from arcavis.*/
require_once(WP_PLUGIN_DIR  . '/woocommerce/includes/libraries/wp-async-request.php');
require_once(WP_PLUGIN_DIR  . '/woocommerce/includes/libraries/wp-background-process.php');

class UpdateImageItem{
	var $images;
	var $post_id;
}

class FmArcavisSyncImageWorker extends WP_Background_Process {

	/**
	 * @var string
	 */
	protected $action = 'arcavis_sync_images';
	
	/**
	 * Cron interval in Minutes
	 */
	protected $cron_interval = 3;

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
		$images = $item->images;
		$post_id = $item->post_id;
		global $wc_arcavis_shop;
		// only need these if performing outside of admin environment
		require_once(ABSPATH . 'wp-admin/includes/media.php');
		require_once(ABSPATH . 'wp-admin/includes/file.php');
		require_once(ABSPATH . 'wp-admin/includes/image.php');

		$list_id = get_post_meta($post_id, '_product_image_gallery', true);
		$first = empty(get_the_post_thumbnail($post_id));

		foreach ($images as $img) {
			try {
				$id = media_sideload_image($img->Value, $post_id,'','id');
				// Add Image to gallery if upload is successful
				if($id != '' && !is_wp_error($id)){
				  //First image will be set as product thumbnail
				  if($first){
					set_post_thumbnail($post_id, $id);
					$first=false;
				  }
				  else{
					$list_id .= $id.',';
				  }
				} else {
					// error
					$wc_arcavis_shop->logger->logError('**arcavis_sync_images '.$e->getMessage());
				}
			} catch (Exception $e) {
				$wc_arcavis_shop->logger->logError('**arcavis_sync_images '.$e->getMessage());
			}
		}

		update_post_meta($post_id, '_product_image_gallery', rtrim($list_id,','));
		$wc_arcavis_shop->logger->logInfo('**arcavis_sync_images successfully (postid: '. $item->post_id .')');
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

		$wc_arcavis_shop->logger->logInfo('**arcavis_sync_images successfully');
	}

}

