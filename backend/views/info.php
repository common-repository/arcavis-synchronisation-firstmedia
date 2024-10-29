<?php
defined( 'ABSPATH' ) or die( 'No guetsli!' );

global $wc_arcavis_shop;

function get_wp_schedules_output() {
	$arcavis_schedules = [];
	
	$schedules = wp_get_schedules();
	if ( ! empty( $schedules ) ) {
		foreach ( $schedules as $key => $schedule ) {
			if ( strpos( $key, 'arcavis' ) !== false ) {
				$time     = floor( $schedule['interval'] / 60 ); // MINUTES
				$timeUnit = 'min';
				if ( $time > 60 ) {
					$time     = $time / 60;
					$timeUnit = 'h';
				}
				$schedule['time'] = $time;
				$schedule['timeUnit'] = $timeUnit;
				$hooktime = wp_next_scheduled( $key . '_schedule_action' );
				// background process
				$batch_str = '';
				if ( strpos( $key, 'arcavis_sync_images' ) !== false ) {
					global $wpdb;

					$schedule['display'] = 'Arcavis Image Background Worker';
					$hooktime            = wp_next_scheduled( 'wp_arcavis_sync_images_cron' );

					$tasks   = $wpdb->get_results(
						"
							SELECT option_name, option_value
							FROM $wpdb->options
							WHERE option_name LIKE 'wp_arcavis_sync_images%'
						"
					);
					$tasks_output = '';
					foreach ($tasks as $task) {
						$data = unserialize($task->option_value);
						$tasks_output .= ' - ' . $task->option_name . ': ' .count($data) . '<br />';
					}
					$batch_str = ' | open: ' . count($tasks) . '<br />' . $tasks_output;
				} elseif ( strpos( $key, 'arcavis_sync_bg' ) !== false ) {
					global $wpdb;

					$schedule['display'] = 'Arcavis Background Worker';
					$hooktime            = wp_next_scheduled( 'wp_arcavis_sync_bg_cron' );

					$counter   = $wpdb->get_var(
						"
							SELECT Count(*)
							FROM $wpdb->options
							WHERE option_name LIKE 'wp_arcavis_sync_bg%'
						"
					);
					$batch_str = ' | open: ' . $counter;
				}
				$schedule['hooktime'] = $hooktime;
				$schedule['batch_str'] = $batch_str;

				$arcavis_schedules[] = $schedule;
			}
		}
	}
	// output
	$output    = '<ul class="schedules">';
	foreach ($arcavis_schedules as $schedule) {
		$output .= '<li>' . $schedule['display'] . ' (every ' . $schedule['time'] . $schedule['timeUnit'] . ') | next: <span class="dotted">' . output_arcavis_time( $schedule['hooktime'] ) . $schedule['batch_str'] . '</span></li>';
	}
	$output .= '</ul>';
	return $output;
}

function arcavis_ticks_to_time( $ticks ) {
	return floor( ( $ticks - 621355968000000000 ) / 10000000 );
}

function output_arcavis_convert_time( $timeticks ) {
	if ( isset( $timeticks ) && ! empty( $timeticks ) ) {
		return output_arcavis_time( arcavis_ticks_to_time( $timeticks ) );
	} else {
		return 'Never';
	}
}

function output_arcavis_time( $phpticks ) {
	return date( 'Y.m.d - H:i:s', $phpticks );
}

?>
<div class="wrap arcavis info-view">
    <div class="row form-table">
      <div class="col s6">
		<h4>Übersicht</h4>
		<div class="col s12" style="margin-bottom: 10px;">
      		<div class="col s10 z-depth-4 dotted noborder aboutPlugin relative">
				<img src="<?php echo plugin_dir_url( dirname( __FILE__, 1 ) ) . 'assets/images/arcavis_logo.png'; ?>">
				<img src="<?php echo plugin_dir_url( dirname( __FILE__, 1 ) ) . 'assets/images/this-is-us-de.png'; ?>">
				<img src="<?php echo plugin_dir_url( dirname( __FILE__, 1 ) ) . 'assets/images/firstmedia.png'; ?>">
				<div class="col s12">
					<h5>Arcavis-Synchronisation für WooCommerce von FirstMedia.</h5>
					<p>Dieses Plugin ermöglicht die Synchronisation von Produkten, Bildern, Kategorien, Gutscheinen, Bestellungen und Zahlungen zwischen WooCommerce und Arcavis.<br /><br />
					Benötigen Sie Unterstützung oder wünschen Sie eine massgeschneiderte Anpassung? <br /><a href="https://www.firstmedia.swiss/kontakt/" target="_blank">FirstMedia Solutions kontaktieren</a>
					</p>
				</div>
			</div>
		</div>
		<div class="col s6">
     		<div class="col s12">Version</div>
      		<div class="col s12"><?php echo FM_WC_AS_VER; ?></div>
		</div>
		<div class="col s6">
     		<div class="col s12"><?php _e('Arcavis Installations-URL', FM_WC_AS_TEXTDOMAIN); ?></div>
      		<div class="col s12"><?php echo $this->settingsRepo->settings->arcavis_link; ?> <a href="<?php echo $this->settingsRepo->settings->arcavis_link; ?>" target="_blank"><span class="dashicons dashicons-external"></span></a></div>
		</div>
		<div class="col s6">
     		<div class="col s12">Arcavis <?php _e('Username', FM_WC_AS_TEXTDOMAIN); ?></div>
      		<div class="col s12"><?php echo $this->settingsRepo->settings->arcavis_username; ?></div>
		</div>
		<div class="col s6">
     		<div class="col s12">Synchronisationsintervall</div>
      		<div class="col s12"><?php echo $this->settingsRepo->settings->arcavis_sync_interval; ?> min</div>
		</div>
	  </div>
      <div class="col s6">
		<h4>Synchronisation</h4>
		<div class="row">
     		<div class="col s12" style="margin-top: 32px;">Products: Last Sync with changes (Arcavis Server Time)</div>
      		<div class="col s12">
				<?php
				$lasttick = FmLastSyncRepository::getLastSync( 'articles' );
				echo output_arcavis_convert_time( $lasttick );
				?>
				<form action="" method="post" style="display:inline-flex;margin-left:10px;">

				<input class="waves-effect waves-light btn"  name="sync_manual" type="submit" value="Manual Sync"> 
				<input class="waves-effect waves-light btn" style="margin-left:10px" name="sync_daily" type="submit" value="Run daily Sync"> 
				</form>
			</div>
		</div>
		<div class="row">
     		<div class="col s12">Stocks: Last Sync with changes (Arcavis Server Time)</div>
      		<div class="col s12">
			  <?php
					$lasttick = FmLastSyncRepository::getLastSync( 'articlestocks' );
					echo output_arcavis_convert_time( $lasttick );
				?>
				<form action="" method="post" style="display:inline-flex;margin-left:10px;">
					<input class="waves-effect waves-light btn" style="display:inline-flex" name="sync_stocks" type="submit" value="Sync Stocks"> 
				</form>
			</div>
		</div>
		<div class="row">
     		<div class="col s12">Servertime</div>
      		<div class="col s12"><?php echo date( 'Y.m.d - H:i:s', current_time('timestamp') ); ?></div>
		</div>
		<div class="row z-depth-4 dotted noborder relative" style="margin-top: 44px;">
			<img class="title-tooltip" src="<?php echo plugin_dir_url( dirname( __FILE__, 1 ) ) . 'assets/images/last-logs-de.png'; ?>" alt="Last Logs">
     		<div class="col s12"></div>
      		<div class="col s12" style="padding-left: 18px;">
				<?php
				$log = $wc_arcavis_shop->logger->get_last_log();
				if ( ! empty( $log ) ) {
					echo $this->get_log_msg_output( $log );
				}
				?>
				<br />
				<br />
			</div>
		</div>		  
	  </div>
	</div>
	<div class="row" style="margin-top: 2em;"> 
		<h4>Geplante Aufgaben</h4>
		<div class="col s6"> 
			<p class="crontext">
				Bitte stellen Sie sicher, dass der WordPress-Cronjob korrekt konfiguriert ist. Dieser ist zur weiteren erfolgreichen Synchronisation notwendig und sollte alle 5-10 Minuten aufgerufen werden.<br />
				<br />
				Cronjob-URL:
				<span style="display: block; padding: 15px;font-size: 1.3em;background: #f7f7f7;border-radius: 3px;box-shadow: 1px 1px 2px #dbd9d961;"><?php echo site_url(); ?>/wp-cron.php</span>
				<br />
				Der Cronjob sollte manuell vom Server aufgerufen werden und in WordPress deaktiviert werden. Fügen Sie dazu folgende Zeile in die wp-config.php ein:<br />
			<span style="display: block; padding: 15px;font-size: 1.3em;background: #f7f7f7;border-radius: 3px;box-shadow: 1px 1px 2px #dbd9d961;">define('DISABLE_WP_CRON', true);</span>
			</p>
	  	</div>
		<div class="col s6"> 
			<div class="row">
				<div class="col s12"><h5>Nächste Ausführungen</h5></div>
				<div class="col s12"><?php echo get_wp_schedules_output(); ?></div>
			</div>
			<div class="row">
				<div class="col s12">Memory usage</div>
				<div class="col s12"><?php echo SimpleStopWatch::memoryUsageStr(); ?></div>
			</div>
	  	</div>
	</div>
</div>

