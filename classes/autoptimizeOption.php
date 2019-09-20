<?php
/**
 * Autoptimize options handler.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This class takes care of the set and get of option for standalone and multisite WordPress instances.
 */
class autoptimizeOption {
	/**
	 * Constructor, add filter on saving options.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'check_multisite_on_saving_options' ] );
	}

	/**
	 * Ensure that is_plugin_active_for_network function is declared.
	 */
	public static function maybe_include_plugin_functions() {
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
	}

	/**
	 * Retrieves the option in standalone and multisite instances.
	 *
	 * @param string $option  Name of option to retrieve. Expected to not be SQL-escaped.
	 * @param mixed  $default Optional. Default value to return if the option does not exist.
	 * @return mixed Value set for the option.
	 */
	public static function get_option( $option, $default = false ) {
		// Ensure that is_plugin_active_for_network function is declared.
		self::maybe_include_plugin_functions();

		// This is always a network setting.
		if ( 'autoptimize_enable_site_config' === $option ) {
			return get_network_option( get_main_network_id(), $option );
		}

		// If the plugin is network activated and our per site setting is not on, use the network configuration.
		$configuration_per_site = get_network_option( get_main_network_id(), 'autoptimize_enable_site_config' );
		if ( is_plugin_active_for_network( 'autoptimize/autoptimize.php' ) && 'on' !== $configuration_per_site ) {
			return get_network_option( get_main_network_id(), $option );
		}

		return get_option( $option, $default );
	}

	/**
	 * Saves the option in standalone and multisite instances.
	 *
	 * @param string      $option   Option name. Expected to not be SQL-escaped.
	 * @param mixed       $value    Option value. Must be serializable if non-scalar. Expected to not be SQL-escaped.
	 * @param string|bool $autoload Optional. Whether to load the option when WordPress starts up. For existing options,
	 *                              `$autoload` can only be updated using `update_option()` if `$value` is also changed.
	 *                              Accepts 'yes'|true to enable or 'no'|false to disable. For non-existent options,
	 *                              the default value is 'yes'. Default null.
	 * @return bool False if value was not updated and true if value was updated.
	 */
	public static function update_option( $option, $value, $autoload = null ) {

		// Ensure that is_plugin_active_for_network function is declared.
		self::maybe_include_plugin_functions();
		$blog_id = get_current_blog_id();

		if ( is_plugin_active_for_network( 'autoptimize/autoptimize.php' ) && 1 === $blog_id ) {
			return update_network_option( get_main_network_id(), $option, $value );
		} else {
			return update_option( $option, $value, $autoload );
		}
	}

	/**
	 * Use the pre_update_option filter to check if the option to be saved if from autoptimize and
	 * in that case, take care of multisite case.
	 */
	public function check_multisite_on_saving_options() {
		// Ensure that is_plugin_active_for_network function is declared.
		self::maybe_include_plugin_functions();
		$blog_id = get_current_blog_id();

		if ( is_plugin_active_for_network( 'autoptimize/autoptimize.php' ) && 1 === $blog_id ) {
			add_filter( 'pre_update_option', [ $this, 'update_autoptimize_option_on_network' ], 10, 3 );
		}
	}

	public static function update_autoptimize_option_on_network( $value, $option, $old_value ) {
		if ( strpos( $option, 'autoptimize_' ) === 0 ) {

			// Ensure that is_plugin_active_for_network function is declared.
			self::maybe_include_plugin_functions();
			$blog_id = get_current_blog_id();

			if ( is_plugin_active_for_network( 'autoptimize/autoptimize.php' ) && 1 === $blog_id ) {
				update_network_option( get_main_network_id(), $option, $value );
				// Return old value, to stop update_option logic.
				return $old_value;
			}
		}
		return $value;
	}
}
new autoptimizeOption();
