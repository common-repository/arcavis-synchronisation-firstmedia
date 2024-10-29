<?php
class FmArcavisApiRepository {
	public $settings;

	public function __construct(FmArcavisSettingsRepository $settingsRepo = null) {
		global $wc_arcavis_shop;
		if(is_null($settingsRepo))
			$this->settings = $wc_arcavis_shop->settingsRepository->settings;
		else
			$this->settings = $settingsRepo->settings;
	}

	/**
	 * get products
	 *
	 * @param string $args
	 * @return void
	 */
	public function get_products( $additional_url_args = '' ) {
		$filter = '';
		if (!empty($this->settings->arcavis_filter_by_tag)) {
			$filter = '&tag=' . $this->settings->arcavis_filter_by_tag;
		}
		return $this->get_data( '/api/articles?mainArticleId=0&inclStock=true&inclTags=true&ignSupp=true&ignIdents=true' . $additional_url_args . $filter );
	}

	/**
	 * Get all changed products from arcavis
	 *
	 * @param long $changedSinceTicks
	 * @return void
	 */
	public function get_products_by_changedTicks( $changedSinceTicks ) {
		return $this->get_products( '&changedSinceTicks=' . $changedSinceTicks );
	}

	/**
	 * Get Products with pageing
	 *
	 * @param integer $currentPage
	 * @param integer $productsOnPage
	 * @return void
	 */
	public function get_products_by_page( $currentPage, $productsOnPage = 25 ) {
		return $this->get_products( '&pageSize=' . $productsOnPage . '&page=' . $currentPage );
	}

	public function get_product_by_id( $product_id ) {
		return $this->get_data( '/api/articles/' . $product_id . '?inclStock=true&inclTags=true' );
	}

	public function get_products_stock_by_changedTicks( $changedSinceTicks ) {
		return $this->get_data( '/api/articlestocks?groupByArticle=true&changedSinceTicks=' . $changedSinceTicks );
	}

	public function get_product_stock_by_id( $product_id ) {
		return $this->get_data( '/api/articlestocks/' . $product_id );
	}

	public function get_product_variation_by_id( $product_id ) {
		return $this->get_data( '/api/articles?mainArticleId=' . $product_id . '&inclStock=true&inclTags=true&ignSupp=true&ignIdents=true' );
	}


	/**
	 * generate request args
	 *
	 * @param [type] $arcavis_settings
	 * @return void
	 */
	private function get_request_args() {
		return array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $this->settings->arcavis_username . ':' . $this->settings->arcavis_password ),
			),
		);
	}

	/**
	 * get data from arcavis
	 *
	 * @param [type] $url
	 * @return void
	 */
	private function get_data( $api_url ) {
		$url = $this->settings->arcavis_link . $api_url;

		$request_args = $this->get_request_args();

		$response = wp_remote_get( $url, $request_args );
		if ( is_array( $response ) && $response['response']['code'] == 200 ) {
			$data = json_decode( wp_remote_retrieve_body( $response ) );
			return $data;
		} else if ( is_array( $response ) ) {
			$http = $response['response'];
			$code = array_key_exists('code', $http) ? $http['code'] : '';
			$msg = array_key_exists('message', $http) ? $http['message'] : '';
			throw new \Exception($code . ': ' . $msg . '(' . $url  .')');
		}
		return false;
	}
}


