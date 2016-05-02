<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class autoptimizeToolbar {

	public function __construct(){}

	public function add(){
		add_action( 'wp_before_admin_bar_render', array($this, 'autoptimize_admin_bar_menu') );
	}

	public function autoptimize_admin_bar_menu() {
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
}
?>
