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
		autoptimizeCache::clearall();
		do_action("autoptimize_action_cachepurged");
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
	}
}
?>