<?php
/**
 * Abstract dispatcher.
 *
 * @package query-monitor
 */

if ( ! class_exists( 'QM_Dispatcher' ) ) {
abstract class QM_Dispatcher {

	public function __construct( QM_Plugin $qm ) {
		$this->qm = $qm;

		if ( ! defined( 'QM_COOKIE' ) ) {
			define( 'QM_COOKIE', 'query_monitor_' . COOKIEHASH );
		}

		add_action( 'init', array( $this, 'init' ) );

	}

	abstract public function is_active();

	final public function should_dispatch() {

		$e = error_get_last();

		# Don't dispatch if a fatal has occurred:
		if ( ! empty( $e ) && ( $e['type'] & ( E_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR ) ) ) {
			return false;
		}

		/**
		 * Allows users to disable this dispatcher.
		 *
		 * The dynamic portion of the hook name, `$this->id`, refers to the dispatcher ID.
		 *
		 * @since 2.8.0
		 *
		 * @param bool $true Whether or not the dispatcher is enabled.
		 */
		if ( ! apply_filters( "qm/dispatch/{$this->id}", true ) ) {
			return false;
		}

		return $this->is_active();

	}

	public function get_outputters( $outputter_id ) {
		$collectors = QM_Collectors::init();
		$collectors->process();

		/**
		 * Allows users to filter what outputs.
		 *
		 * The dynamic portion of the hook name, `$outputter_id`, refers to the outputter ID.
		 *
		 * @since 2.8.0
		 *
		 * @param QM_Output[]   $outputters Array of outputters.
		 * @param QM_Collectors $collectors List of collectors.
		 */
		$this->outputters = apply_filters( "qm/outputter/{$outputter_id}", array(), $collectors );

		return $this->outputters;
	}

	public function init() {
		if ( ! $this->user_can_view() ) {
			return;
		}

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', 1 );
		}

		add_action( 'send_headers', 'nocache_headers' );
	}

	protected function before_output() {
		// nothing
	}

	protected function after_output() {
		// nothing
	}

	public function user_can_view() {

		if ( ! did_action( 'plugins_loaded' ) ) {
			return false;
		}

		if ( current_user_can( 'view_query_monitor' ) ) {
			return true;
		}

		return self::user_verified();

	}

	public static function user_verified() {
		if ( isset( $_COOKIE[QM_COOKIE] ) ) { // @codingStandardsIgnoreLine
			return self::verify_cookie( wp_unslash( $_COOKIE[QM_COOKIE] ) ); // @codingStandardsIgnoreLine
		}
		return false;
	}

	public static function verify_cookie( $value ) {
		if ( $old_user_id = wp_validate_auth_cookie( $value, 'logged_in' ) ) {
			return user_can( $old_user_id, 'view_query_monitor' );
		}
		return false;
	}

}
}
