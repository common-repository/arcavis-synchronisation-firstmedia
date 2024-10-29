<?php
class FmArcavisWcOrdersRepository {

    public function findAllWithoutArcavisPaymentSince($since = '') {
        $args = array(
            'limit'        => -1,
            'status' => array('on-hold','completed','processing'),
            'meta_key'     => 'acravis_response',
            'meta_compare' => 'NOT EXISTS',
            'date_paid' => '>'.$since,
        );
        return wc_get_orders( $args );
    }

}