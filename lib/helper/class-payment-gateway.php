<?php
defined( 'ABSPATH' ) or die( 'No guetsli!' );

/**
 * Offline Payment Gateway
 *
 * Provides an Offline Payment Gateway; mainly for testing purposes.
 * We load it later to ensure WC is loaded first since we're extending it.
 *
 * @class       WC_Arcavis_Gateway
 * @extends     WC_Payment_Gateway
 * @version     1.0.4
 * @package     WooCommerce/Classes/Payment
 * @author      bet
 * @description Payment gateway for customer account payments
 */
define( 'FM_WC_AS_WOO_PAYMENT_DIR', plugin_dir_path( __FILE__ ) );
add_action( 'plugins_loaded', 'wc_arcavis_gateway_init', 11 );
add_filter( 'woocommerce_payment_gateways', 'add_arcavis_gateway' );

function wc_arcavis_gateway_init() {

	class WC_Arcavis_Gateway extends WC_Payment_Gateway {

		public function __construct() {
			$this->id                 = 'woo_arcavis';
			$this->method_title       = __( 'Arcavis Invoice', FM_WC_AS_TEXTDOMAIN );
			$this->method_description = __( 'Pay by invoice with the Arcavis invoice module', FM_WC_AS_TEXTDOMAIN );
			$this->title              = __( 'Invoice', FM_WC_AS_TEXTDOMAIN );
			$this->has_fields         = false;
			$this->init_form_fields();
			$this->init_settings();
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			$this->form_fields = array(
				'enabled' => array(
					'title'   => __( 'Enable/Disable', FM_WC_AS_TEXTDOMAIN ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable/Disable', FM_WC_AS_TEXTDOMAIN ),
					'default' => 'yes',
				),
			);
		}

		public function process_payment( $order_id ) {
			global $wc_arcavis_shop;
			global $woocommerce;
			global $wpdb;

			$order         = new WC_Order( $order_id );
			$success       = false;
			$error_message = '';
			$error_type    = 'error';

			if ( get_current_user_id() ) {
				$response = $wc_arcavis_shop->transaction->arcavis_post_transaction( $order_id, session_id() );

				if ( $response->IsSuccessful === true ) {
					$order->payment_complete();
					$success = true;
				} else {
					// Translate error message
					if ( $response->Message == 'NotEnoughFunds' ) {
						// Nicht genug guthaben
						$error_message = 'Ihre Kontolimite wurde erreicht';
					} elseif ( WP_DEBUG === true ) {
						// Show service error
						$error_message = $response->Message;
					} else {
						// Something else
						$error_message = 'Die Zahlung war nicht erfolgreich, bitte versuchen Sie ein anderes Zahlmittel';
					}
				}
			} else {
				$error_message = 'Für Zahlung auf Rechnung wird ein Kundenkonto benötigt. Bitte melden Sie sich an, oder aktivieren Sie die Option "Kundenkonto neu anlegen"';
			}

			// Show message
			if ( $success ) {
				// Return thankyou redirect
				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order ),
				);
			} else {
				wc_add_notice( __( 'Error: ', FM_WC_AS_TEXTDOMAIN ) . $error_message, $error_type );
				return;
			}
		}

	} // end \WC_Gateway_Offline class
}

function add_arcavis_gateway( $gateways ) {
	$gateways[] = 'WC_Arcavis_Gateway';
	return $gateways;
}

