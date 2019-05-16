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
class autoptimizeOption
{
	/**
	 * Constructor, add filter on saving options.
	 */
	public function __construct()
    {
		add_action('init', [$this, 'check_multisite_on_saving_options']);
	}

	/**
	 * Retrieves the option in standalone and multisite instances.
	 * 
	 * @param string $option  Name of option to retrieve. Expected to not be SQL-escaped.
	 * @param mixed  $default Optional. Default value to return if the option does not exist.
	 * @return mixed Value set for the option.
	 */
    public static function get_option( $option, $default = false )
    {
		if ( is_multisite() && is_plugin_active_for_network( 'autoptimize/autoptimize.php' ) ) {
			return get_network_option( get_main_network_id(), $option );
		} else {
			return get_option( $option, $default );
		}
    }

	/**
	 * Saves the option in standalone and multisite instances.
	 * 
	 * @param string      $option   Option name. Expected to not be SQL-escaped.
	 * @param mixed       $value    Option value. Must be serializable if non-scalar. Expected to not be SQL-escaped.
	 * @param string|bool $autoload Optional. Whether to load the option when WordPress starts up. For existing options,
	 *                              `$autoload` can only be updated using `update_option()` if `$value` is also changed.
     *			                    Accepts 'yes'|true to enable or 'no'|false to disable. For non-existent options,
     *				                the default value is 'yes'. Default null.
     * @return bool False if value was not updated and true if value was updated.
	 */
    public static function update_option( $option, $value, $autoload = null )
    {
		if ( is_multisite() && is_plugin_active_for_network( 'autoptimize/autoptimize.php' ) ) {
			return update_network_option( get_main_network_id(), $option, $value );
		} else {
			return update_option( $option, $value, $autoload );
		}
    }

}
new autoptimizeOption();
