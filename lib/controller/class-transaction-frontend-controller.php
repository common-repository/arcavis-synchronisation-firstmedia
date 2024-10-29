<?php
session_start();


class FmArcavisArcavisTransactionFrontendController {
	public $settings;
	public $settingsRepo;

	public function __construct( FmArcavisSettingsRepository $settingRepository ) {
		$this->settings = $settingRepository->settings;
		$this->settingsRepo = $settingRepository;

		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'cartCalculateFees_CheckTransaction' ), PHP_INT_MAX );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueueScripts' ) );
		add_action( 'wp_head', array( $this, 'printJsVariables' ) );
		add_action( 'woocommerce_before_checkout_billing_form', array( $this, 'customise_checkout_field' ) );
		add_action( 'wp_ajax_arcavis_get_applied_voucher_code', array( $this, 'arcavis_get_applied_voucher_code' ) );
		add_action( 'wp_ajax_nopriv_arcavis_get_applied_voucher_code', array( $this, 'arcavis_get_applied_voucher_code' ) );

		// coupons von arcavis
		add_filter( 'woocommerce_coupons_enabled', array( $this, 'hide_coupon_field_on_cart' ) );
		add_filter( 'woocommerce_checkout_place_order', array( $this, 'checkout_place_order' ) );
    }

    public function printJsVariables(){
		?>
	  <script type="text/javascript">
		var website_url = '<?php echo site_url(); ?>'; 
	  </script>
		<?php
    }

    public function enqueueScripts(){
		wp_add_inline_script( 'arcavis_frontend', 'var website_url=' . site_url() );
		wp_enqueue_script( 'arcavis_frontend_js', plugins_url( '../../assets/js/frontend.js', __FILE__ ), 'jQuery', FM_WC_AS_VER, true );
    }
    
    public function cartCalculateFees_CheckTransaction(){
		global $woocommerce;
		global $wc_arcavis_shop;
		global $wpdb;

		if ( ! empty( $_POST ) && ! empty( $_POST['post_data'] ) ) {
			$data      = null;
			parse_str( $_POST['post_data'], $data );
			//$data = FmArcavisSanitizeHelper::sanitizePost($data);
			if ( $data['billing_email'] != '' || $data['arcavis_voucher'] != '' || $data['arcavis_voucher'] == '' ) {
				// fix company
				if ( ! array_key_exists( 'billing_company', $data ) ) {
					$data['billing_company'] = '';
				}

				$request_array = array();
				if ( get_current_user_id() ) {
					$current_user              = wp_get_current_user();
					$request_array['Customer'] = array(
						'CustomerNumber'   => 'WP-' . get_current_user_id(),
						'LanguageId'       => '',
						'IsCompany'        => $data['billing_company'] == '' ? 'false' : 'true',
						'CompanyName'      => $data['billing_company'],
						'Salutation'       => '',
						'SalutationTitle'  => '',
						'Firstname'        => $current_user->user_firstname,
						'Name'             => $current_user->user_lastname,
						'Street'           => $data['billing_address_1'],
						'StreetNumber'     => '',
						'StreetSupplement' => '',
						'PoBox'            => '',
						'Zip'              => $data['billing_postcode'],
						'City'             => $data['billing_city'],
						'CountryIsoCode'   => $data['billing_country'],
						'ContactEmail'     => $current_user->user_email,
						'ContactPhone'     => $data['billing_phone'],
						'ContactMobile'    => '',
						'Birthdate'        => '',
					);
				}

				$cart_total              = preg_replace( '/[^0-9,.]/', '', html_entity_decode( $woocommerce->cart->get_cart_total() ) );
				$cart_total              = $cart_total + $woocommerce->cart->shipping_total;
				$request_array['Amount'] = $cart_total;

				$request_array['Remarks'] = $data['order_comments'];

				$items     = $woocommerce->cart->get_cart();
				$cart_data = array();
				$i         = 1;

				foreach ( $items as $item => $values ) {
					$article_id  = get_post_meta( $values['data']->get_id(), 'article_id', true );
					$_product    = wc_get_product( $values['data']->get_id() );
					$price       = $_product->get_price();
					$cart_data[] = array(
						'ReceiptPosition' => $i,
						'ArticleId'       => $article_id,
						'Title'           => $_product->get_title(),
						'Quantity'        => $values['quantity'],
						'TaxRate'         => '',
						'UnitPrice'       => FmArcavisArcavisTransationEntity::roundAmount($price),
						'Price'           => FmArcavisArcavisTransationEntity::roundAmount($price * $values['quantity']),
					);

					$i++;
				}

				$shipping_data = array();

				$shipping_session = WC()->session->get( 'shipping_for_package_0' );

				if ( ! empty( $shipping_session ) ) {
					$shippingMethod = sanitize_text_field($_POST['shipping_method'][0]);
					if(array_key_exists($shippingMethod, $shipping_session['rates'])) {
						$shipping_data[] = array(
							'ReceiptPosition' => $i,
							'ArticleId'       => 0,
							'Title'           => $shipping_session['rates'][ $_POST['shipping_method'][0] ]->label,
							'Price'           => FmArcavisArcavisTransationEntity::roundAmount($woocommerce->cart->shipping_total),
						);
						$i++;
					}
				}

				$fees = $woocommerce->cart->get_fees();
				$feeConfig = (object) $this->settingsRepo->getAdditionalSettings()->fees;
				foreach ( $fees as $key => $values ) {
					if(0.0 != $values->amount ) {
						$feeId = $feeConfig->default;
						if(is_object($feeConfig) && property_exists($feeConfig, $values->name))
							$feeId = $feeConfig->{$values->name};
						
						$cart_data[] = array(
							'ReceiptPosition' => $i,
							'ArticleId'       => $feeId,
							'Title'           => $values->name,
							'Price'           => FmArcavisArcavisTransationEntity::roundAmount($values->amount),
						);
						$i++;
					}
				}

				$request_array['TransactionArticles'] = array_merge( $cart_data, $shipping_data );
				$vouchers                             = array();
				if ( trim( $data['arcavis_applied_voucher'] ) != '' ) {
					$vouchers[] = array(
						'VoucherId' => $data['arcavis_applied_voucher'],
						'Amount'    => '',
					);
				}

				$request_array['TransactionVouchers'] = $vouchers;

				$data = json_encode( $request_array );
				$wc_arcavis_shop->logger->logDebug( 'PUT Query ' . $data );

				$response = wp_remote_request(
					$this->settings->arcavis_link . '/api/transactions',
					array(
						'method'  => 'PUT',
						'body'    => $data,
						'headers' => array(
							'Authorization' => 'Basic ' . base64_encode( $this->settings->arcavis_username . ':' . $this->settings->arcavis_password ),
							'Content-Type'  => 'application/json',
						),
					)
				);

				$json_response = wp_remote_retrieve_body( $response );
				$response_body = json_decode( $json_response );

				if ( isset( $response_body->Result->AmountOpen ) ) {
					if ( ! empty( $response_body->Result->TransactionVouchers ) ) {

						$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . "applied_vouchers WHERE session_id='" . sanitize_text_field(session_id()) . "' AND discount_type='voucher'" );

						$wpdb->insert(
							$wpdb->prefix . 'applied_vouchers',
							array(
								'session_id'      => sanitize_text_field(session_id()),
								'voucher_code'    => $response_body->Result->TransactionVouchers[0]->VoucherId,
								'discount_amount' => $response_body->Result->TransactionVouchers[0]->Amount,
								'discount_type'   => 'voucher',
							)
						);

					} else {

						$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . "applied_vouchers WHERE session_id='" . session_id() . "' AND discount_type='voucher'" );
					}

					if ( ! empty( $response_body->Result->TransactionVouchers ) ) {
							$voucher_discount = $response_body->Result->TransactionVouchers[0]->Amount;
							$discount         = $response_body->Result->AmountOpen + $voucher_discount;
							$discount         = $cart_total - $discount;
					} else {
						$discount = $cart_total - ( $response_body->Result->AmountOpen );
					}
					// echo $discount;
					if ( $discount != '0' ) {

								  $wpdb->query( 'DELETE FROM ' . $wpdb->prefix . "applied_vouchers WHERE session_id='" . session_id() . "' AND discount_type='discount'" );
								$wpdb->insert(
									$wpdb->prefix . 'applied_vouchers',
									array(
										'session_id'      => session_id(),
										'voucher_code'    => '',
										'discount_amount' => $discount,
										'discount_type'   => 'discount',
									)
								);

					} else {

							$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . "applied_vouchers WHERE session_id='" . session_id() . "' AND discount_type='discount'" );
					}

					$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . "applied_vouchers WHERE session_id='" . session_id() . "'  AND discount_type='response'" );
					$wpdb->insert(
						$wpdb->prefix . 'applied_vouchers',
						array(
							'session_id'           => session_id(),
							'discount_type'        => 'response',
							'transaction_response' => $json_response,
						)
					);
				}
			}
		}

		$applied_disocunt = $wpdb->get_row( 'SELECT * FROM ' . $wpdb->prefix . "applied_vouchers WHERE session_id='" . session_id() . "' AND discount_type='discount'" );
		// Arcavis Discount on checkout page is added from here.
		if ( ! empty( $applied_disocunt ) ) {

			$extra_fee_option_label    = __('Discount', FM_WC_AS_TEXTDOMAIN);
			$extra_fee_option_cost     = '-' . $applied_disocunt->discount_amount;
			$extra_fee_option_type     = 'fixed';
			$extra_fee_option_taxable  = false;
			$extra_fee_option_minorder = '0';
			$extra_fee_option_cost     = round( $extra_fee_option_cost, 2 );
			$woocommerce->cart->add_fee( __( $extra_fee_option_label, 'woocommerce' ), $extra_fee_option_cost, $extra_fee_option_taxable );
		}

		// Voucher Discount on checkout page is added from here.
		$applied_vouchers = $wpdb->get_row( 'SELECT * FROM ' . $wpdb->prefix . "applied_vouchers WHERE session_id='" . session_id() . "' AND discount_type='voucher'" );
		if ( ! empty( $applied_vouchers ) ) {

			$extra_fee_option_label    = __('Voucher', FM_WC_AS_TEXTDOMAIN);
			$extra_fee_option_cost     = '-' . $applied_vouchers->discount_amount;
			$extra_fee_option_type     = 'fixed';
			$extra_fee_option_taxable  = false;
			$extra_fee_option_minorder = '0';
			$extra_fee_option_cost     = round( $extra_fee_option_cost, 2 );
			$woocommerce->cart->add_fee( __( $extra_fee_option_label, 'woocommerce' ), $extra_fee_option_cost, $extra_fee_option_taxable );

		}
    }

	// Returns the applied voucher codes from check transaction call to show to the user
	public function arcavis_get_applied_voucher_code() {
		global $wpdb;
		$applied_vouchers = $wpdb->get_row( 'SELECT * FROM ' . $wpdb->prefix . "applied_vouchers WHERE session_id='" . sanitize_text_field(session_id()) . "' AND discount_type='voucher'" );
		if ( ! empty( $applied_vouchers ) ) {
			echo json_encode( $applied_vouchers );
		} else {
			echo '';
		}
		exit;
	}
	// This function use to Hide default coupon system of woocommerce.
	public function hide_coupon_field_on_cart( $enabled ) {
		if ( is_cart() || is_checkout() ) {
			$enabled = false;
		}
		return $enabled;
	}
	// This function use to add voucher field in checkout form.
	function customise_checkout_field( $checkout ) {
		global $wc_arcavis_shop;
		woocommerce_form_field(
			'arcavis_voucher',
			array(
				'type'        => 'text',
				'class'       => array(
					'my-field-class form-row-wide',
				),
				'label'       => __('Voucher', FM_WC_AS_TEXTDOMAIN),
				'placeholder' => __('Voucher-Code', FM_WC_AS_TEXTDOMAIN),
				'required'    => false,
			),
			$checkout->get_value( 'arcavis_voucher' )
		);

		echo '<div id="user_link_hidden_checkout_field">
                <input type="hidden" class="input-hidden" name="arcavis_applied_voucher" id="arcavis_applied_voucher" value="">
          </div>';
	}

}