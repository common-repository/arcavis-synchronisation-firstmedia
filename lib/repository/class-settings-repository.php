<?php
defined( 'ABSPATH' ) or die( 'No chocolate!' );

class FmArcavisSettingsRepository {

	public $settings;
	private $additionalConfigObject;
	private $defaultAdditionalConfig = array(
		'fees' => array(
			'default' => 0,
		),
		'allowedStockStoreIds' => 'all',
		'allowSalesPriceSet' => false,
		'syncDescriptionInShortDescription' => false,
		'formating' => array(
			'stripAllContentStyles' => false,
			'stripAllContentTags' => false,
		),
		'syncExclude' => array(
			'doNotSyncCategories' => false,
		),
	);

	public function __construct() {
		$this->settings = $this->load();
	}

	public function load() {
		$arcavis_settings = get_option( 'arcavis_settings' );
		if ( $arcavis_settings == false ) {
			// init
			$arcavis_settings = serialize(
				array(
					'arcavis_link'          => '',
					'arcavis_username'      => '',
					'arcavis_password'      => '',
					'arcavis_sync_interval' => 5,
					'arcavis_filter_by_tag' => '',
					'arcavis_doNotSyncDescription' => false,
					'arcavis_doNotSyncImages' => false,
					'arcavis_addtionalConfigJson' => '',
				)
			);
		}
		return $this->applyDefaults( (object) unserialize( $arcavis_settings ) );
	}

	private function applyDefaults($settings){
		if(!isset($settings->arcavis_addtionalConfigJson) || empty($settings->arcavis_addtionalConfigJson))
			$settings->arcavis_addtionalConfigJson = json_encode($this->defaultAdditionalConfig);

		$this->buildAdditionalConfigObject( $settings->arcavis_addtionalConfigJson );
		if(!isset($settings->arcavis_doNotSyncDescription))
			$settings->arcavis_doNotSyncDescription = false;
		if(!isset($settings->arcavis_doNotSyncImages))
			$settings->arcavis_doNotSyncImages = false;

		return $settings;
	}

	private function buildAdditionalConfigObject($json){
		$obj = json_decode($json);
		$this->additionalConfigObject = $this->applyDefaultsToAdditionalConfigObject($obj);
	}
	private function applyDefaultsToAdditionalConfigObject($obj){
		foreach($this->defaultAdditionalConfig as $key => $val){
			if(! property_exists($obj, $key ) )
				$obj->$key = $this->_parseObject($val);
			else {
				if( is_array( $this->defaultAdditionalConfig[$key] ) ){
					foreach($this->defaultAdditionalConfig[$key] as $key => $val){
						if(! property_exists($obj, $key ) )
							$obj->$key = $this->_parseObject($val);
					}
				}
			}
		}
		return $obj;
	}
	private function _parseObject($val){
		if(is_array($val))
			return (object) $val;
		return $val;
	}

	public function getAdditionalSettings(){
		return (object) $this->additionalConfigObject;
	}
	public function getAdditionalSettingsDefaultJson(){
		return json_encode($this->defaultAdditionalConfig);
	}

	public function save() {
		$arcavis_settings = array(
			'arcavis_link'          => rtrim( $this->settings->arcavis_link, '/' ),
			'arcavis_username'      => $this->settings->arcavis_username,
			'arcavis_password'      => $this->settings->arcavis_password,
			'arcavis_sync_interval' => $this->settings->arcavis_sync_interval,
			'arcavis_filter_by_tag' => $this->settings->arcavis_filter_by_tag,
			'arcavis_doNotSyncDescription' => $this->settings->arcavis_doNotSyncDescription,
			'arcavis_addtionalConfigJson' => $this->settings->arcavis_addtionalConfigJson,
			'arcavis_doNotSyncImages' => $this->settings->arcavis_doNotSyncImages,
		);
		update_option( 'arcavis_settings', serialize( $arcavis_settings ), true );
	}
}