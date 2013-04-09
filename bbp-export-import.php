<?php
/*
Plugin Name: bbPress - Export and Import
Plugin URI: http://pippinsplugins.com/bbpress-stats
Description: Export topics / replies from bbPress forums and import them into another
Version: 1.0
Author: Pippin Williamson
Author URI: http://pippinsplugins.com
Contributors: mordauk
*/


class PW_BBP_Export_Import {

	/**
	 * @var bbp Admin Notes instance
	 */

	private static $instance;


	/**
	 * Main class instance
	 *
	 * @since v1.0
	 *
	 * @return the class instance
	 */

	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new PW_BBP_Export_Import;
			self::$instance->includes();
			self::$instance->actions();
			self::$instance->filters();
		}
		return self::$instance;
	}


	/**
	 * Dummy constructor
	 *
	 * @since v1.0
	 *
	 * @return void
	 */

	private function __construct() { /* nothing here */ }


	private function includes() {

		include dirname( __FILE__ ) . '/includes/export.php';
		include dirname( __FILE__ ) . '/includes/import.php';

	}


	/**
	 * Add all actions we need
	 *
	 * @since v1.0
	 *
	 * @return void
	 */

	private function actions() {
		add_action( 'bbp_forum_metabox', array( $this, 'metabox' ) );

		add_action( 'post_edit_form_tag', array( $this, 'multipart_encrypt' ) );
		add_action( 'admin_init', array( $this, 'trigger_export' ) );
		add_action( 'admin_init', array( $this, 'trigger_import' ) );
	}


	/**
	 * Add all filters we need
	 *
	 * @since v1.0
	 *
	 * @return void
	 */

	private function filters() {

	}


	/**
	 * Allow file uploads in the post edit form if editing a forum
	 *
	 * @since v1.0
	 *
	 * @return void
	 */

	public function multipart_encrypt() {
		if( 'forum' === get_post_type( get_the_ID() ) )
			echo ' enctype="multipart/form-data"';
	}


	/**
	 * Add our form to the forum meta box
	 *
	 * @since v1.0
	 *
	 * @return void
	 */

	public function metabox() {

		$url = add_query_arg(
			array(
				'bbp-action'       => 'export',
				'forum'            => get_the_ID(),
				'bbp-export-nonce' => wp_create_nonce( 'bbp-export' )
			)
		);

		echo '<div class="bbp-export">';
			echo '<strong>' . __( 'Export Topics and Replies:', 'bbp-export-import' ) . '</strong>&nbsp;';
			echo '<a href="' . $url . '" title="' . __( 'Export the Topics and Replies of this Forum', 'bbp-export-import' ) . '">' . __( 'Export', 'bbp-export-import' ) . '</a>';
		echo '</div>';

		echo '<div class="bbp-import"><br/>';
			echo '<span>' . __( 'Import Topics / Replies Into this Forum:', 'bbp-export-import' ) . '</span>';
			echo '<input type="file" name="bbp_csv_file"/>';
			echo '<input type="hidden" name="bbp-action" value="import"/>';
			echo '<input type="hidden" name="forum" value="' . get_the_ID() . '"/>';
			wp_nonce_field( 'bbp-import-nonce', 'bbp-import' );
			submit_button( __( 'Run Import', 'bbp-export-import' ), 'secondary', 'bbp-run-import' );
		echo '</div>';
	}


	/**
	 * Trigger the forum's topic/reply export
	 *
	 * @since v1.0
	 *
	 * @return void
	 */

	public function trigger_export() {

		if( ! isset( $_GET['bbp-action'] ) || $_GET['bbp-action'] != 'export' )
			return;

		if( ! wp_verify_nonce( $_GET['bbp-export-nonce'], 'bbp-export' ) )
			return;

		$export = new BBP_Forum_Export;
		$export->set_forum( absint( $_GET['forum'] ) );
		$export->export();
	}


	/**
	 * Trigger the forum's topic/reply import
	 *
	 * @since v1.0
	 *
	 * @return void
	 */

	public function trigger_import() {

		if( ! isset( $_POST['bbp-action'] ) || $_POST['bbp-action'] != 'import' )
			return;

		if( ! isset( $_POST['bbp-run-import'] ) )
			return;

		if( ! wp_verify_nonce( $_POST['bbp-import'], 'bbp-import-nonce' ) )
			return;

		$import = new BBP_Forum_Import;
		$import->set_forum( absint( $_POST['forum'] ) );
		$import->import();
	}

}


/**
 * Load our singleton class
 *
 * @since v1.0
 *
 * @return void
 */

function pw_bbp_export_import() {
	return PW_BBP_Export_Import::instance();
}

// Load the class
pw_bbp_export_import();