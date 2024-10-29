<?php
defined( 'ABSPATH' ) or die( 'No access!' );

class FmArcavisOrderInterface {
    private $settingsRepo;
    private $order_id;
    private $order;
    private $i = 1;

    public function __construct($settingsRepo, $order_id = 0){
        $this->settingsRepo = $settingsRepo;
        $this->setOrderId($order_id);
    }
    public function setOrderId($order_id){
        $this->order_id = $order_id;
        if($order_id != 0)
            $this->order = new WC_Order( $order_id );
    }

    public function getCustomer(){
        $custNumber = $this->order->get_user_id();
        if($custNumber == 0)
            $custNumber = "GUEST-".$this->order_id;
        
        return array(
            'CustomerNumber'   => 'WP-' . $custNumber,
            'LanguageId'       => 'de',
            'IsCompany'        => $this->order->get_billing_company() == '' ? 'false' : 'true',
            'CompanyName'      => $this->order->get_billing_company(),
            'Salutation'       => '',
            'SalutationTitle'  => '',
            'Firstname'        => $this->order->get_billing_first_name(),
            'Name'             => $this->order->get_billing_last_name(),
            'Street'           => $this->order->get_billing_address_1(),
            'StreetNumber'     => '',
            'StreetSupplement' => '',
            'PoBox'            => '',
            'Zip'              => $this->order->get_billing_postcode(),
            'City'             => $this->order->get_billing_city(),
            'CountryIsoCode'   => $this->order->get_billing_country(),
            'ContactEmail'     => $this->order->get_billing_email(),
            'ContactPhone'     => $this->order->get_billing_phone(),
            'ContactMobile'    => '',
            'Birthdate'        => '',
        );
    }

    public function getArticles(){
        $items = $this->order->get_items();
		$articles = array();
        foreach ( $items as $item => $values ) {
            $article_id  = get_post_meta( $values->get_product_id(), 'article_id', true );
            $_product    = wc_get_product( $values->get_product_id() );
            $price       = $_product->get_price();
            $articles[] = array(
                'ReceiptPosition' => $this->i,
                'ArticleId'       => $article_id,
                'Title'           => $_product->get_title(),
                'Quantity'        => $values->get_quantity(),
                'TaxRate'         => '',
                'UnitPrice'       => FmArcavisArcavisTransationEntity::roundAmount($price),
                'Price'           => FmArcavisArcavisTransationEntity::roundAmount($values->get_total()),
            );

            $this->i++;
        }
		return $articles;
    }

    public function getVouchers($session_id_at_checkout) {
        global $wpdb;
		$arcavis_response_json = $wpdb->get_row( 'SELECT transaction_response FROM ' . $wpdb->prefix . "applied_vouchers WHERE session_id='" . $session_id_at_checkout . "' AND discount_type='response'" );
		$arcavis_response      = json_decode( stripslashes( $arcavis_response_json->transaction_response ) );

		$request_array['TransactionArticles'] = $arcavis_response->Result->TransactionArticles;// array_merge($cart_data,$shipping_data);
		$vouchers                             = array();
		if ( ! empty( $arcavis_response->Result->TransactionVouchers ) ) {
			$vouchers = $arcavis_response->Result->TransactionVouchers;
		}
        return $vouchers;
    }

    public function getPayments(){
        $paymentName = $this->order->get_payment_method_title();
        if("TWINT" == $paymentName)
            $paymentName = 'TWINT Web'; //If TWINT is already setup directly in the account it expects additional transaction Informations. -> Setting Payment Name to TWINT Web solves the Problem
        
        $payments = array(
            'Title'           => $paymentName,
            'Amount'          => FmArcavisArcavisTransationEntity::roundAmount( $this->order->get_total() ),
            'CurrencyIsoCode' => get_woocommerce_currency(),
            'Debit'	=> 0, //0=Customer paid   https://sequens.freshdesk.com/support/solutions/articles/1000158705-post-api-transactions
            'ExchangeRate' => 1, //Exchangerate == 1
        );
        return $payments;
    }
    public function getShipping(){
        $shipping_data[] = array(
            'ReceiptPosition' => $this->i,
            'ArticleId'       => 0,
            'Title'           => $this->order->get_shipping_method(),
            'Price'           => FmArcavisArcavisTransationEntity::roundAmount((double)$this->order->get_shipping_total()),
        );
        $this->i++;
        return $shipping_data;
    }

    public function getFees(){
        $fees = $this->order->get_fees();
        $feesData = array();
        $feeConfig = (object)$this->settingsRepo->getAdditionalSettings()->fees;
        
        foreach ( $fees as $values ) {
            if(0.0 != $values->get_amount() ) {
                $feeId = $feeConfig->default;
                $feeName = $values->get_name();
                if(is_object($feeConfig) && property_exists($feeConfig, $feeName))
                    $feeId = $feeConfig->{$feeName};
                
                $feesData[] = array(
                    'ReceiptPosition' => $this->i,
                    'ArticleId'       => $feeId,
                    'Title'           => $feeName,
                    'Price'           => FmArcavisArcavisTransationEntity::roundAmount((double)$values->get_amount()),
                );
                $this->i++;
            }
        }
        return $feesData;
    }

    public function getAdditionalInfo(){
        $addInfo = array();
		$addInfo['Amount']     = FmArcavisArcavisTransationEntity::roundAmount( $this->order->get_total() );
		$addInfo['AmountOpen'] = FmArcavisArcavisTransationEntity::roundAmount( $this->order->get_total() );
        $addInfo['Remarks']    = $this->order_id;
        return $addInfo;
    }
}
