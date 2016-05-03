<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class autoptimizeToolbar {

	public function __construct()
	{
		// Load admin toolbar feature
		add_action( 'plugins_loaded', array( $this, 'load_toolbar' ) );
	}

	public function load_toolbar()
	{
		if( current_user_can( 'manage_options' ) ){
			
			// Load custom admin styles and javascript
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
			
			add_action( 'wp_ajax_autoptimize_delete_cache', array( $this, 'delete_cache' ) );

			add_action( 'wp_before_admin_bar_render', array($this, 'add_toolbar') );
		}
	}

	public function add_toolbar()
	{
		global $wp_admin_bar;

		$wp_admin_bar->add_menu( array(
			'id'    => 'autoptimize',
			'title' => __("Autoptimize",'autoptimize')
		));

		$wp_admin_bar->add_menu( array(
			'id'    => 'autoptimize-delete-cache',
			'title' => __("Delete Cache",'autoptimize'),
			'parent'=> 'autoptimize'
		));
	}

	public function delete_cache()
	{
		autoptimizeCache::clearall();
	}

	public function enqueue_scripts()
	{
		wp_enqueue_style( 'autoptimize-toolbar', plugins_url( 'autoptimize/classes/static/css/toolbar.css' ), array(), time(), "all" );

		wp_enqueue_script( 'autoptimize-toolbar', plugins_url( 'autoptimize/classes/static/js/toolbar.js' ), array(), time(), true );
	}

}
?>