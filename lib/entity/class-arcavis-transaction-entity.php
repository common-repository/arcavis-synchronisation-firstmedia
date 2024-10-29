<?php
defined( 'ABSPATH' ) or die( 'No access!' );

class FmArcavisArcavisTransationEntity {
    public $customer;
    public $transactionArticles = array();
    public $transactionVouchers = array();
    public $transactionPayments = array();
    
    
    public $transactionShipping = array();
    public $transactionFees = array();
    public $additionalInfo = array();

    public function toArray(){
        $result = array();
        $result['Customer'] = $this->customer;
        $result['TransactionArticles'] = array_merge($this->transactionArticles, $this->transactionShipping, $this->transactionFees);
        $result['TransactionVouchers'] = $this->transactionVouchers;
        $result['TransactionPayments'] = array($this->transactionPayments);

        $result['Amount'] = $this->additionalInfo['Amount'];
        $result['AmountOpen'] = $this->additionalInfo['AmountOpen'];
        $result['Remarks'] = $this->additionalInfo['Remarks'];

        return $result;
    }

    public static function roundAmount($amount){
        $decimals = ( (int) $amount != $amount ) ? (strlen($amount) - strpos($amount, '.')) - 1 : 0;
        if($decimals <= 2)
            return $amount;
        if($amount < 0)
            return round(( ($amount * -1) + 0.000001) * 20) / 20 * -1;
        return round(($amount + 0.000001) * 20) / 20;  
    }
}