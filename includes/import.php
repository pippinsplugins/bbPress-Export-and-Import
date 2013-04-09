<?php
/**
 * Export Class
 *
 * @package     BBP Export Import
 * @copyright   Copyright (c) 2013, Pippin Williamson
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * BBP Import Class
 *
 * @since 1.0
 */
class BBP_Forum_Import {


	private $forum_id = 0;

	/**
	 * Can we export?
	 *
	 * @access public
	 * @since 1.0
	 * @return bool Whether we can export or not
	 */
	public function can_import() {
		return (bool) apply_filters( 'bbp_export_capability', current_user_can( 'manage_options' ) );
	}


	/**
	 * Set the forum ID we are importing into
	 *
	 * @access public
	 * @since 1.0
	 */
	public function set_forum( $forum_id = 0 ) {
		$this->forum_id = $forum_id;
	}


	private function csv_to_array( $filename = '', $delimiter = ';' ) {
		if( ! file_exists( $filename ) || ! is_readable( $filename ) )
			return FALSE;

		$header = NULL;
		$data = array();
		if ( ( $handle = fopen( $filename, 'r' ) ) !== FALSE ) {
			while ( ($row = fgetcsv( $handle, 1000, $delimiter ) ) !== FALSE ) {

				if( ! $header )
					$header = $row;
				else
					$data[] = array_combine( $header, $row );
			}
			fclose( $handle );
		}
		return $data;
	}


	/**
	 * Perform the export
	 *
	 * @access public
	 * @since 1.0
	 * @uses bbp_Export::csv_rows_out()
	 * @return void
	 */
	public function import() {
		if ( ! $this->can_import() )
			wp_die( __( 'You do not have permission to import data.', 'bbp-export-import' ), __( 'Error', 'bbp-export-import' ) );

		$csv_array = $this->csv_to_array( $_FILES['bbp_csv_file']['tmp_name'], ';');

		foreach( $csv_array as $key => $line ) :

			$post_args = $line;
			$meta_args = array();

			// Setup author date
			if( $post_args['anonymous'] == '1' ) {
				$meta_args['anonymous_email'] = $line['post_author'];
			} else {

				$user = get_user_by( 'email', $post_args['post_author'] );

				if( ! $user ) {
					// The user doesn't exist, so create them
					$user = wp_insert_user( array(
						'user_email' => $post_args['post_author'],
						'user_login' => $post_args['user_login']
					) );
				}
				$post_args['post_author'] = $user->ID;
			}

			// Decode content
			$post_args['post_content'] = html_entity_decode( $post_args['post_content'] );

			$topic_type = bbp_get_topic_post_type();
			$reply_type = bbp_get_reply_post_type();

			// Remove the post args we don't want sent to wp_insert_post
			unset( $post_args['anonymous']  );
			unset( $post_args['user_login'] );

			switch( $line['post_type'] ) :

				case $topic_type :

					// Set the forum parent for topics
					$post_args['post_parent'] = $this->forum_id;
					$meta_args['voice_count'] = $line['voices'];
					$meta_args['reply_count'] = $post_args['reply_count'];

					$topic_id = bbp_insert_topic( $post_args, $meta_args );

					// Subscribe the original poster to the topic
					bbp_add_user_subscription( $post_args['post_author'], $topic_id );

					// Add the topic to the user's favorites
					if( bbp_is_user_favorite( $post_args['post_author'], $topic_id ) )
						bbp_add_user_favorite( $post_args['post_author'], $topic_id );

					// Set topic as resolved if GetShopped Support Forum is active
					if( $post_args['resolved'] == '1' )
						add_post_meta( $topic_id, '_bbps_topic_status', '2' );

					break;

				case $reply_type :

					// Set the forum parent for replies. The topic ID is created above when the replie's topic is first created
					$post_args['post_parent'] = $topic_id;

					$reply_id = bbp_insert_reply( $post_args, $meta_args );

					// Subscribe reply author, if not already
					if( ! bbp_is_user_subscribed( $post_args['post_author'], $topic_id ) )
						bbp_add_user_subscription( $post_args['post_author'], $topic_id );

					// Mark as favorite
					if( bbp_is_user_favorite( $post_args['post_author'], $topic_id ) )
						bbp_add_user_favorite( $post_args['post_author'], $topic_id );

					// Check if the next row is a topic, meaning we have reached the last reply and need to update the last active time
					if( $csv_array[ $key + 1 ]['post_type'] == bbp_get_topic_post_type() )
						bbp_update_forum_last_active_time( $this->forum_id, $post_args['post_date'] );

					break;

			endswitch;


		endforeach;

		// Recount forum topic / reply counts
		bbp_admin_repair_forum_topic_count();
		bbp_admin_repair_forum_reply_count();

		wp_redirect( admin_url( 'post.php?post=' . $this->forum_id . '&action=edit' ) ); exit;

	}
}