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
	 * @synopsis [--type=<css|js>]
	 */
	public function clear( $args, $args_assoc ) {
		$clear = '';
		if ( ! empty( $args_assoc ) ) {
			$clear = $args_assoc['type'];
			if ( ! in_array( $clear, array( 'css', 'js' ), true ) ) {
				WP_CLI::error( esc_html__( 'Please choose `css` or `js`.', 'autoptimize' ) );
			}
		}

		if ( empty( $clear ) ) {
			WP_CLI::line( esc_html__( 'Flushing the cache...', 'autoptimize' ) );
			autoptimizeCache::clearall();
			WP_CLI::success( esc_html__( 'Cache flushed.', 'autoptimize' ) );
			return;
		}

		WP_CLI::line( esc_html__( 'Clearing the cache...', 'autoptimize' ) );

		WP_Filesystem();
		global $wp_filesystem;
		$wp_filesystem->rmdir( AUTOPTIMIZE_CACHE_DIR . $clear, true );

		WP_CLI::success( esc_html__( sprintf( '%s cache cleared.', strtoupper( $clear ) ),'autoptimize' ) );
	}

}

WP_CLI::add_command( 'autoptimize', 'autoptimizeCLI' );
