<?php
defined( 'ABSPATH' ) or die( 'No access!' );

class FmArcavisProductImageInterface {
    private $post_id;

    public function __construct($post_id = 0){
        $this->setProductId($post_id);
    }
    public function setProductId($post_id){
        $this->post_id = $post_id;
    }
	public function UpdateImages( $images, $overrideDoNotSyncImages = false ) {
        global $wc_arcavis_shop;
        $post_id = $this->post_id;

		if($wc_arcavis_shop->settingsRepository->settings->arcavis_doNotSyncImages && !$overrideDoNotSyncImages) return;

		if ( $this->hasChanged( $images ) ) {
			$diff = $this->getImagesDiff( $images );

			// add new images
			if ( count( $diff->to_add ) > 0 ) {
				// load new images
				$downloaded = $this->downloadImages( $diff->to_add );

				foreach ( $downloaded as $key => $value ) {
					$item_key = array_search( $key, array_column( $diff->imagelist, 'arcavis_id' ) );
					if ( $item_key !== false ) {
						$diff->imagelist[ $item_key ]->id = $value;
					}
				}
				/*
				$item          = new UpdateImageItem();
				$item->images  = $diff->to_add;
				$item->post_id = $post_id;
				$wc_arcavis_shop->sync->image_background_process->push_to_queue( $item );*/
			}

			// remove images
			foreach ( $diff->to_delete as $key => $wp_attachments ) {
				wp_delete_attachment( $key, 'true' );
			}

			// update state
			$thumbnail   = '';
			$gallery_ids = '';
			if ( count( $diff->imagelist ) > 0 ) {
				$thumbnail     = $diff->imagelist[0]->id;
				$gallery_array = array_splice( $diff->imagelist, 1 );
				$gallery_ids   = implode( ',', array_column( $gallery_array, 'id' ) );
				update_post_meta( $post_id, '_thumbnail_id', $thumbnail );
				update_post_meta( $post_id, '_product_image_gallery', $gallery_ids );
			} else {
				delete_post_meta( $post_id, '_thumbnail_id' );
				delete_post_meta( $post_id, '_product_image_gallery' );
			}

			update_post_meta( $post_id, 'arcavis_images_hash', $this->getHash( $images ) );
		}
    }
    
	private function getImagesDiff( $images ) {
		global $wc_arcavis_shop;

		// get post files
		$post_attachments = array(); // $attachmentId => filename
		$wp_attachments   = get_attached_media( '', $this->post_id );
		foreach ( $wp_attachments as $wp_attachment ) {
			$url                                    = wp_get_attachment_url( $wp_attachment->ID );
			$post_attachments[ $wp_attachment->ID ] = strtolower( basename( $url ) );
		}

		// add new images and add not used to delete
		$new_images    = array();
		$imagelist     = array();
		$delete_images = $post_attachments;
		foreach ( $images as $key => $image ) {
			$filname          = strtolower( basename( $image->Value ) );
			$wpattachment_key = false;
			foreach ( $delete_images as $attachment_key => $existing_image ) {
				if ( strpos( $existing_image, $filname ) !== false ) {
					$wpattachment_key = $attachment_key;
				}
			}

			$post_image = (object) array(
				'arcavis_id' => $key,
				'filename'   => $filname,
				'sort'       => $image->Sort,
			);

			if ( $wpattachment_key == false ) {
				// new images
				$new_images[ $key ] = $image;
			} else {
				// used -> remove from deleted
				unset( $delete_images[ $wpattachment_key ] );
				$post_image->id = $wpattachment_key;
			}

			$imagelist[] = $post_image;
		}
		// $wc_arcavis_shop->logger->logInfo('**arcavis_sync_images_diff4 ('. json_encode($new_images) .', '. json_encode($existing_images) .')');

		return (object) array(
			'to_add'    => $new_images,
			'to_delete' => $delete_images,
			'existing'  => $post_attachments,
			'imagelist' => $imagelist,
		);
	}

	private function downloadImages( $images ) {
        $post_id = $this->post_id;
		$downloaded = array();
		if ( ! empty( $post_id ) && count( $images ) > 0 ) {
			global $wc_arcavis_shop;
			// only need these if performing outside of admin environment
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';

			foreach ( $images as $key => $img ) {
				try {
					$id = media_sideload_image( $img->Value, $post_id, '', 'id' );
					// Add Image to gallery if upload is successful
					if ( $id != '' && ! is_wp_error( $id ) ) {
						// success
						$downloaded[ $key ] = $id;
					} else {
						// error
						$wc_arcavis_shop->logger->logError( '**arcavis_sync_images Error on Download (' . $post_id . ': ' . $img->Value . ')' );
					}
				} catch ( Exception $e ) {
					$wc_arcavis_shop->logger->logError( '**arcavis_sync_images ' . $e->getMessage() );
				}
			}
			$wc_arcavis_shop->logger->logInfo( '**arcavis_sync_images successfully (postid: ' . $post_id . ' | Images: ' . count( $downloaded ) . ')' );
			return $downloaded;
		}
	}

	public function getHash( $images ) {
		return md5( FM_WC_AS_VER_HASH . serialize( $images ) );
	}

	public function hasChanged( $images ) {
		$current_hash = get_post_meta( $this->post_id, 'arcavis_images_hash', true );
		return ( $current_hash !== $this->getHash( $images ) );
	}



}