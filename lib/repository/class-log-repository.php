<?php
class FmLogRepository {
	public $tablename = 'arcavis_logs';

	public function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset_collate = $wpdb->get_charset_collate();
		
		$table_name = $wpdb->prefix . $this->tablename;

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			date datetime NOT NULL,
			level varchar(10) NOT NULL,
			message longtext NULL,
			UNIQUE KEY id (id)
		) $charset_collate;";

		dbDelta( $sql );
	}

	public function logDebug( $msg ) {
		$this->logMessage( 'DEBUG', $msg );
	}

	public function logError( $msg ) {
		$this->logMessage( 'ERROR', $msg );
	}

	public function logInfo( $msg ) {
		$this->logMessage( 'INFO', $msg );
	}

	private function logMessage( $level, $msg ) {
		global $wpdb;
		// if( WP_DEBUG === true || $level!='DEBUG') {
		if ( $level != 'DEBUG' ) {
			$table_name = $wpdb->prefix . $this->tablename;
			$date       = current_time('mysql');
			$wpdb->insert(
				$table_name,
				array(
					'date'    => $date,
					'level'   => $level,
					'message' => $msg,
				)
			);
		}
	}

	public function get_last_log( $messageFilter = '' ) {
		global $wpdb;
		$where = '';
		if ( ! empty( $messageFilter ) ) {
			$where = ' WHERE message like "%' . esc_sql( $messageFilter ) . '%" ';
		}
		$result = $wpdb->get_row( 'SELECT date, level, message FROM ' . $wpdb->prefix . $this->tablename . ' ' . $where . ' ORDER BY date DESC Limit 0,1', OBJECT );
		return $result;
	}
}
