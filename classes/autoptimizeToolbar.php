<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class autoptimizeToolbar {

	public function __construct()
	{
		// Load admin toolbar feature
		add_action( 'wp_loaded', array( $this, 'load_toolbar' ) );
	}

	public function load_toolbar()
	{
		if( current_user_can( 'manage_options' ) )
		{
			// Load custom styles and scripts
			if( is_admin() ) {
				add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
			} else {
				add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
			}
			
			add_action( 'wp_ajax_autoptimize_delete_cache', array( $this, 'delete_cache' ) );

			add_action( 'wp_ajax_autoptimize_flush_plugins_cache', array( $this, 'flush_plugins_cache' ) );

			add_action( 'admin_bar_menu', array($this, 'add_toolbar'), 100 );
		}
	}

	public function add_toolbar()
	{
		global $wp_admin_bar;
		
		if ( !is_user_logged_in() ) return;

		$AOstatArr = autoptimizeCache::stats();

		$max_size = 512 * 1024 * 1024;

		$files = $AOstatArr[0];
		$bytes = $AOstatArr[1];

		$si_prefix = array( 'B', 'KB', 'MB', 'GB' );
		$class = min( (int) log( $bytes , 1024 ), count( $si_prefix ) - 1 );
		$size = sprintf( '%1.2f', $bytes / pow( 1024, $class ) ) . ' ' . $si_prefix[ $class ];

		$color = ( $bytes > $max_size ) ? 'red' : ( ( $bytes > $max_size/1.25 ) ? 'orange' : 'green' );

		$wp_admin_bar->add_node( array(
			'id'    => 'autoptimize',
			'title' => '<span class="ab-icon"></span><span class="ab-label">' . __("Autoptimize",'autoptimize') . '</span>',
			'meta'  => array( 'class' => 'bullet-' . $color )
		));

		$wp_admin_bar->add_node( array(
			'id'    => 'autoptimize-cache-info',
			'title' => '<p>' . __( "Cache Info", 'autoptimize' ) . '</p>' .
				   '<table>' .
				   '<tr><td>' . __( "Size", 'autoptimize' ) . ':</td><td class="size ' . $color . '">' . $size . '</td></tr>' .
				   '<tr><td>' . __( "Files", 'autoptimize' ) . ':</td><td class="files white">' . $files . '</td></tr>' .
				   '</table>',
			'parent'=> 'autoptimize'
		));
		
		$wp_admin_bar->add_node( array(
			'id'    => 'autoptimize-delete-cache',
			'title' => __("Delete Cache",'autoptimize'),
			'parent'=> 'autoptimize'
		));
	}

	public function delete_cache()
	{
		// Delete Autoptimize Cache
		autoptimizeCache::clearall();

		// Search plugins cache management that are active
		$plugins_cache = $this->check_plugins_cache_management();

		if( count( $plugins_cache ) )
		{
			$result = array(
				'title'	=> __( "We found plugins cache management", 'autoptimize' ) . '!',
				'desc'	=> ' <span style="font-weight:600">Autoptimize</span> ' . 
					   __( "found that you are using one or more plugins for cache management", 'autoptimize' ) . ': <br/><br/>' .
					   ' <span style="font-weight:600">' . join( '<br/>', $plugins_cache ) . '</span> ' .
					   '<br /><br />' .
					   __( "Do you want to clean this cache also?", 'autoptimize' ),
				'yes'	=> __( "Yes", 'autoptimize' ),
				'no'	=> __( "No", 'autoptimize' )
			);
		} else {
			$result = array('title' => '');
		}

		echo json_encode( $result );

		wp_die();
	}

	public function flush_plugins_cache()
	{
		$flushed_plugins= array();


		// Cachify
		if ( has_action( 'cachify_flush_cache' ) ) {
			do_action( 'cachify_flush_cache' );
			array_push( $flushed_plugins, 'Cachify' );
		}

		// Comet Cache (alias Zen Cache, alias Quick Cache)
		if ( isset( $GLOBALS['comet_cache'] ) && method_exists( $GLOBALS['comet_cache'], 'clear_cache' ) )
		{
			$GLOBALS['comet_cache']->clear_cache();
			array_push( $flushed_plugins, 'Comet Cache' );
		}
		else if ( isset( $GLOBALS['zencache'] ) && method_exists( $GLOBALS['zencache'], 'clear_cache' ) )
		{
			$GLOBALS['zencache']->clear_cache();
			array_push( $flushed_plugins, 'Zen Cache' );
		}
		else if ( isset( $GLOBALS['quick_cache'] ) && method_exists( $GLOBALS['quick_cache'], 'clear_cache' ) )
		{
			$GLOBALS['quick_cache']->clear_cache();
			array_push( $flushed_plugins, 'Quick Cache' );
		}

		// Hyper Cache
		if ( has_action( 'hyper_cache_clean' ) ) {
			$plugin = HyperCache::$instance;
			$folder = $plugin->get_folder();
			$plugin->remove_dir($folder . '');
			do_action('hyper_cache_flush_all');
			array_push( $flushed_plugins, 'Hyper Cache' );
		}

		// W3 Total Cache
		if ( function_exists( 'w3tc_pgcache_flush' ) ) {
			w3tc_pgcache_flush();
			array_push( $flushed_plugins, 'W3 Total Cache' );
		}

		// WP Fastest Cache
		if ( isset( $GLOBALS['wp_fastest_cache'] ) && method_exists( $GLOBALS['wp_fastest_cache'], 'deleteCache' ) ) {
			$GLOBALS['wp_fastest_cache']->deleteCache();
			array_push( $flushed_plugins, 'WP Fastest Cache' );
		}

		// WP Super Cache
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
			array_push( $flushed_plugins, 'WP Super Cache' );
		}


		if( count( $flushed_plugins ) )
		{
			$result = array(
				'title'	=> __( "Cache cleaning was successful", 'autoptimize' ) . '!',
				'desc'	=> __( "It has successfully completed the cache cleaning of the following plugins cache management", 'autoptimize' ) . ': <br/><br/>' .
					   ' <span style="font-weight:600">' . join( '<br/>', $flushed_plugins ) . '</span> ',
				'ok'	=> __( "Ok", 'autoptimize' )
			);
		} else {
			$result = array('title' => '');
		}

		echo json_encode( $result );

		wp_die();
	}

	public function enqueue_scripts()
	{
		// Toolbar Styles
		wp_enqueue_style( 'autoptimize-toolbar', plugins_url('/static/css/toolbar.css', __FILE__ ), array(), time(), "all" );

		// Toolbar Javascript
		wp_enqueue_script( 'autoptimize-toolbar', plugins_url( '/static/js/toolbar.js', __FILE__ ), array(), time(), true );

		wp_localize_script( 'autoptimize-toolbar', 'autoptimize_ajax_object', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' )
		) );


		// Zebra Dialog Styles
		wp_enqueue_style( 'zebra_dialog', plugins_url('/external/css/zebra_dialog.css', __FILE__ ), array(), time(), "all" );

		// Zebra Dialog JQuery Script
		wp_enqueue_script( 'zebra_dialog', plugins_url( '/external/js/zebra_dialog.js', __FILE__ ), array(), time(), true );
	}

	public function check_plugins_cache_management()
	{
		$plugins = array();

		// Cachify
		if ( has_action( 'cachify_flush_cache' ) ) {
			array_push( $plugins, 'Cachify' );
		}

		// Comet Cache (alias Zen Cache, alias Quick Cache)
		if ( isset( $GLOBALS['comet_cache'] ) && method_exists( $GLOBALS['comet_cache'], 'clear_cache' ) )
		{
			array_push( $plugins, 'Comet Cache' );
		}
		else if ( isset( $GLOBALS['zencache'] ) && method_exists( $GLOBALS['zencache'], 'clear_cache' ) )
		{
			array_push( $plugins, 'Zen Cache' );
		}
		else if ( isset( $GLOBALS['quick_cache'] ) && method_exists( $GLOBALS['quick_cache'], 'clear_cache' ) )
		{
			array_push( $plugins, 'Quick Cache' );
		}

		// Hyper Cache
		if ( has_action( 'hyper_cache_clean' ) ) {
			array_push( $plugins, 'Hyper Cache' );
		}

		// W3 Total Cache
		if ( function_exists( 'w3tc_pgcache_flush' ) ) {
			array_push( $plugins, 'W3 Total Cache' );
		}

		// WP Fastest Cache
		if ( isset( $GLOBALS['wp_fastest_cache'] ) && method_exists( $GLOBALS['wp_fastest_cache'], 'deleteCache' ) ) {
			array_push( $plugins, 'WP Fastest Cache' );
		}

		// WP Super Cache
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			array_push( $plugins, 'WP Super Cache' );
		}

		return $plugins;
	} 

}
?>