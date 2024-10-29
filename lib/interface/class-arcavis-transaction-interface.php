<?php
defined( 'ABSPATH' ) or die( 'No access!' );

abstract class FmArcavisArcavisTransactionInterfaceMode {
    const FromCart = 0;
    const FromOrder = 1;
}
class FmArcavisArcavisTransactionInterface {
    private $settingsRepo;
    private $transaction;
    private $mode;

    private $dataInterface;
    private $data;

    public function __construct($settingsRepo, $mode = FmArcavisArcavisTransactionInterfaceMode::FromOrder ){
        $this->settingsRepo = $settingsRepo;
        $this->setMode($mode);
    }
    public function setTransaction($transaction){
        $this->transaction = $transaction;
    }
    public function setMode($mode){
        $this->mode = $mode;
        if(FmArcavisArcavisTransactionInterfaceMode::FromOrder == $this->mode) 
            $this->dataInterface = new FmArcavisOrderInterface($this->settingsRepo);
    }
    public function setData($data){
        $this->data = $data;
        if(FmArcavisArcavisTransactionInterfaceMode::FromOrder == $this->mode)
            $this->dataInterface->setOrderId($data);
    }
    public function buildTransaction($session_id_at_checkout){
        $transaction = new FmArcavisArcavisTransationEntity();
        $transaction->customer = $this->dataInterface->getCustomer();
        $transaction->transactionArticles = $this->dataInterface->getArticles();
        $transaction->transactionShipping = $this->dataInterface->getShipping();
        $transaction->transactionFees = $this->dataInterface->getFees();
        $transaction->transactionVouchers = $this->dataInterface->getVouchers($session_id_at_checkout);
        $transaction->transactionPayments = $this->dataInterface->getPayments();
        $transaction->additionalInfo = $this->dataInterface->getAdditionalInfo();
        $this->transaction = $transaction;
        $this->checkForDoubleVouchersInFeesAndVouchers();
    }
    private function checkForDoubleVouchersInFeesAndVouchers(){
        // Checks Registered Fees for Applied Vouchers, in case they were applied two times 
        // (Once in Vouchers and Once in Fees as a negative fee)
        foreach($this->transaction->transactionVouchers as $voucher) {
            foreach($this->transaction->transactionFees as $key => $fee){
                if($voucher->Amount == ($fee['Price'] * -1) )
                    unset($this->transaction->transactionFees[$key]);
            }
        }
    }
    public function pushTransaction(){
        global $wc_arcavis_shop;
        $data = json_encode( $this->transaction->toArray() );

		$response      = wp_remote_request(
			$this->settingsRepo->settings->arcavis_link . '/api/transactions',
			array(
				'method'  => 'POST',
				'body'    => $data,
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $this->settingsRepo->settings->arcavis_username . ':' . $this->settingsRepo->settings->arcavis_password ),
					'Content-Type'  => 'application/json',
				),
			)
		);
		$json_response = wp_remote_retrieve_body( $response );
        $response_body = json_decode( $json_response );
        
		if ( $response_body->IsSuccessful === false && $response_body->Message != 'Success' ) {
			$wc_arcavis_shop->logger->logError( 'Post Transaction: API: ' . $response_body->Message . ' | ' . $data . ' | Additional Confing: ' . json_encode( $this->settingsRepo->getAdditionalSettings() ) );
        }
        return $response_body;
    }
}