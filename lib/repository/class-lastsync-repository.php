<?php
class FmLastSyncRepository {

	public function install() {
		/* TODO */
	}

    public static function getLastSync($api){
		global $wpdb;
		$lastSync = '';
		$result   = $wpdb->get_row( 'SELECT * FROM ' . $wpdb->prefix . "lastSyncTicks WHERE apiName='" . $api . "'", OBJECT );
		if ( ! empty( $result ) ) {
			if ( $result->lastSync == 0 ) {
				$lastSync = '';
			} else {
				$lastSync = $result->lastSync;
			}
		}
		return $lastSync;
    }
	public static function getLastSyncTime( $api ) {
		global $wpdb;
		$lastSync = '';
		$result   = $wpdb->get_row( 'SELECT * FROM ' . $wpdb->prefix . "lastSyncTicks WHERE apiName='" . $api . "'", OBJECT );
		if ( ! empty( $result ) ) {
			if ( $result->updated == 0 ) {
				$lastSync = '';
			} else {
				$lastSync = $result->updated;
			}
		}
		return $lastSync;
    }
    public static function updateLastSync($api, $ticks = null, $time) {
		global $wpdb;
        if(is_null($ticks) || empty($ticks))
            return $wpdb->update( $wpdb->prefix . 'lastSyncTicks', array( 'updated' => $time ), array( 'apiName' => $api ) );
        else
            return $wpdb->update( $wpdb->prefix . 'lastSyncTicks', array( 'lastSync' => $ticks, 'updated' => $time ), array( 'apiName' => $api ) );
    }
}
