<?php
defined( 'ABSPATH' ) or die( 'No access!' );


class FmArcavisArcavisTransactionController {
	public $settings;
	public $settingsRepo;

	public function __construct( FmArcavisSettingsRepository $settingRepository ) {
		$this->settings = $settingRepository->settings;
		$this->settingsRepo = $settingRepository;
		
		add_action( 'woocommerce_order_status_changed', array( $this, 'arcavis_process_transaction' ), 99, 3 );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'order_process' ), 10, 1 );

	}


	public function order_process( $order_id ) {
		update_post_meta( $order_id, 'session_id_at_checkout', session_id() );
	}

	// This function call the Post Tranasction API of Arcavis.
	public function arcavis_process_transaction( $order_id, $old_status, $new_status ) {
		global $wc_arcavis_shop;
		global $wpdb;
		$session_id_at_checkout = get_post_meta( $order_id, 'session_id_at_checkout', true );

		if ( in_array( $new_status, array( 'on-hold', 'completed', 'processing' ) ) ) {
			// update transaction in arcavis
			$transactions_done_or_not = get_post_meta( $order_id, 'acravis_response', true );
			if ( ! $transactions_done_or_not ) {
				return $this->arcavis_post_transaction( $order_id, $session_id_at_checkout );
			}
		} elseif ( in_array( $new_status, array( 'refunded', 'cancelled' ) ) ) {
			// Cancel order in Arcavis
			$transactions_done_or_not = get_post_meta( $order_id, 'acravis_canceld', true );
			if ( ! $transactions_done_or_not ) {
				return $this->arcavis_delete_transaction( $order_id, $session_id_at_checkout );
			}
		}
	}

	public function arcavis_post_transaction( $order_id, $session_id_at_checkout ) {
		global $wc_arcavis_shop;
		global $wpdb;
		$transactionInterface = new FmArcavisArcavisTransactionInterface($this->settingsRepo, FmArcavisArcavisTransactionInterfaceMode::FromOrder);
		$transactionInterface->setData($order_id);
		$transactionInterface->buildTransaction($session_id_at_checkout);
		$response_body = $transactionInterface->pushTransaction();

		if ( $response_body->IsSuccessful === true && $response_body->Message == 'Success' ) {

			update_post_meta( $order_id, 'acravis_response', json_encode($response_body) );
			setcookie( 'arcavis_response', '', time() - 3600, '/' );
			$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . "applied_vouchers WHERE session_id='" . $session_id_at_checkout . "' AND discount_type='voucher'" );
			$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . "applied_vouchers WHERE session_id='" . $session_id_at_checkout . "' AND discount_type='discount'" );
			$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . "applied_vouchers WHERE session_id='" . $session_id_at_checkout . "' AND discount_type='response'" );

		}
		return $response_body;
	}

	public function arcavis_delete_transaction( $order_id, $session_id_at_checkout ) {
		global $wc_arcavis_shop;

		/* TODO: Cancel Transaction on Arcavis
		$response      = wp_remote_request(
			$this->settings->arcavis_link . '/api/transactions',
			array(
				'method'  => 'DELETE',
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $this->settings->arcavis_username . ':' . $this->settings->arcavis_password ),
					'Content-Type'  => 'application/json',
				),
			)
		);
		$json_response = wp_remote_retrieve_body( $response );
		$response_body = json_decode( $json_response );

		if ( $response_body->IsSuccessful === true && $response_body->Message == 'Success' ) {

			update_post_meta( $order_id, 'acravis_canceld', $json_response );

		} else {
			$wc_arcavis_shop->logger->logError( 'Post Transaction (DELETE) ' . $response_body->Message );
		}*/
	}

}