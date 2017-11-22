<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class autoptimizeCLI extends WP_CLI_Command {

	/**
	 * Clears the cache.
	 *
	 * @subcommand clear
	 */
	public function clear( $args, $args_assoc ) {
		WP_CLI::line( esc_html__( 'Flushing the cache...', 'autoptimize' ) );
		autoptimizeCache::clearall();
		WP_CLI::success( esc_html__( 'Cache flushed.', 'autoptimize' ) );
	}

}

WP_CLI::add_command( 'autoptimize', 'autoptimizeCLI' );
