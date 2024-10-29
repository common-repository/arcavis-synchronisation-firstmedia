<?php
defined( 'ABSPATH' ) or die( 'No access!' );

 class FmArcavisApiProductInterface {
    private $arcavis_id;
    private $api;

    public function __construct($arcavis_id = null, $api){
        $this->setArcavisId($arcavis_id);
        $this->api = $api;
    }
    public function setArcavisId($arcavis_id){
        $this->arcavis_id = $arcavis_id;
    }

    public function hasProduct(){
        $data = $this->api->get_product_by_id($this->arcavis_id);
        if($data === false) return false;
        if($data->IsSuccessful) return true;
        return false;
    }
}