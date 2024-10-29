<?php
defined( 'ABSPATH' ) or die( 'No guetsli!' );

if ( ! class_exists( 'SimpleStopWatch' ) ) {

	class SimpleStopWatch {
		/**
		 * @var $startTimes array The start times of the StopWatches
		 */
		private static $startTimes = array();
		/**
		 * Start the timer
		 *
		 * @param $timerName string The name of the timer
		 * @return void
		 */
		public static function start( $timerName = 'default' ) {
			self::$startTimes[ $timerName ] = microtime( true );
		}
		/**
		 * Get the elapsed time in seconds
		 *
		 * @param $timerName string The name of the timer to start
		 * @return float The elapsed time since start() was called
		 */
		public static function elapsed( $timerName = 'default' ) {
			return microtime( true ) - self::$startTimes[ $timerName ];
		}

		public static function elapsedInSeconds( $timerName = 'default' ) {
			$elapsed = self::elapsed( $timerName );
			return round( $elapsed, 2 );
		}

		public static function memoryUsageInMb() {
			return round( memory_get_usage() / 1024.0 / 1024.0, 2 );
		}

		public static function memoryUsageStr() {
			return self::memoryUsageInMb() . " Mb \n";
		}
	}
}
