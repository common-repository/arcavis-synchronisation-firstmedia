<?php
defined( 'ABSPATH' ) or die( 'No access!' );

class FmArcavisSyncController {
	public $settings;
	public $api;

	public function __construct( FmArcavisSettingsRepository $settingsRepository ) {
		$this->settings = $settingsRepository->settings;
		$this->api      = new FmArcavisApiRepository( $settingsRepository );
    }
}