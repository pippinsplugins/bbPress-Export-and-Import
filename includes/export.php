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
 * BBP Export Class
 *
 * @since 1.0
 */
class BBP_Forum_Export {


	private $forum_id = 0;

	/**
	 * Can we export?
	 *
	 * @access public
	 * @since 1.0
	 * @return bool Whether we can export or not
	 */
	public function can_export() {
		return (bool) apply_filters( 'bbp_export_capability', current_user_can( 'manage_options' ) );
	}


	/**
	 * Set the forum ID to be exported
	 *
	 * @access public
	 * @since 1.0
	 */
	public function set_forum( $forum_id = 0 ) {
		$this->forum_id = $forum_id;
	}


	/**
	 * Set the export headers
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function headers() {
		ignore_user_abort( true );
		set_time_limit( 0 );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=bbp-export-' . sanitize_file_name( get_the_title( $this->forum_id ) ) . '-' . date( 'm-d-Y' ) . '.csv' );
		header( "Expires: 0" );
	}

	/**
	 * Set the CSV columns
	 *
	 * @access public
	 * @since 1.0
	 * @return array $cols All the columns
	 */
	public function csv_cols() {
		$cols = array(
			'post_type'     => 'post_type',
			'post_author'   => 'post_author',
			'user_login'    => 'user_login',
			'post_parent'   => 'post_parent',
			'post_content'  => 'post_content',
			'post_title'    => 'post_title',
			'post_date_gmt' => 'post_date_gmt',
			'post_date'     => 'post_date',
			'anonymous'     => 'anonymous',
			'voices'        => 'voices',
			'reply_count'   => 'reply_count',
			'resolved'      => 'resolved'
		);
		return $cols;
	}

	/**
	 * Retrieve the CSV columns
	 *
	 * @access public
	 * @since 1.0
	 * @return array $cols Array of the columns
	 */
	public function get_csv_cols() {
		$cols = $this->csv_cols();
		return apply_filters( 'bbp_export_csv_cols', $cols );
	}

	/**
	 * Output the CSV columns
	 *
	 * @access public
	 * @since 1.0
	 * @uses bbp_Export::get_csv_cols()
	 * @return void
	 */
	public function csv_cols_out() {
		$cols = $this->get_csv_cols();
		$i = 1;
		foreach( $cols as $col_id => $column ) {
			echo '"' . $column . '"';
			echo $i == count( $cols ) ? '' : ';';
			$i++;
		}
		echo "\r\n";
	}

	/**
	 * Get the data being exported
	 *
	 * @access public
	 * @since 1.0
	 * @return array $data Data for Export
	 */
	public function get_data() {

		$topics = get_posts(
			array(
				'post_parent' => $this->forum_id,
				'nopaging'    => true,
				'post_type'   => bbp_get_topic_post_type()
			)
		);

		foreach( $topics as $topic ) :

			$topic_anonymous = bbp_is_topic_anonymous( $topic->ID );

			if( $topic_anonymous ) {
				$email = get_post_meta( $topic->ID, '_bbp_anonymous_email', true );
			} else {
				$email = get_the_author_meta( 'email', $topic->post_author );
			}

			$data[] = array(
				'post_type'     => bbp_get_topic_post_type(),
				'post_author'   => $email,
				'user_login'    => get_the_author_meta( 'user_login', $topic->post_author ),
				'post_parent'   => $topic->post_parent,
				'post_content'  => htmlentities( $topic->post_content ),
				'post_title'    => $topic->post_title,
				'post_date_gmt' => $topic->post_date_gmt,
				'post_date'     => $topic->post_date,
				'anonymous'     => $topic_anonymous ? '1' : '0',
				'voices'        => bbp_get_topic_voice_count( $topic->ID ),
				'reply_count'   => bbp_get_topic_post_count( $topic->ID ),
				'resolved'      => function_exists( 'bbps_topic_resolved' ) && bbps_topic_resolved( $topic->ID ) ? '1' : 0
			);

			$replies = get_posts(
				array(
					'post_parent' => $topic->ID,
					'nopaging'    => true,
					'post_type'   => bbp_get_reply_post_type()
				)
			);

			if( $replies ):

				foreach( $replies as $reply ) :

					$reply_anonymous = bbp_is_reply_anonymous( $reply->ID );

					if( $reply_anonymous ) {
						$reply_email = get_post_meta( $topic->ID, '_bbp_anonymous_email', true );
					} else {
						$reply_email = get_the_author_meta( 'email', $reply->post_author );
					}

					$data[] = array(
						'post_type'     => bbp_get_reply_post_type(),
						'post_author'   => $reply_email,
						'user_login'    => get_the_author_meta( 'user_login', $reply->post_author ),
						'post_parent'   => $reply->post_parent,
						'post_content'  => htmlentities( $reply->post_content ),
						'post_title'    => $reply->post_title,
						'post_date_gmt' => $reply->post_date_gmt,
						'post_date'     => $reply->post_date,
						'anonymous'     => $reply_anonymous ? '1' : '0',
						'voices'        => '0',
						'reply_count'   => '0',
						'resolved'      => '0'
					);

				endforeach;

			endif;

		endforeach;

		$data = apply_filters( 'bbp_export_get_data', $data );

		return $data;
	}

	/**
	 * Output the CSV rows
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function csv_rows_out() {
		$data = $this->get_data();

		$cols = $this->get_csv_cols();

		// Output each row
		foreach ( $data as $row ) {
			$i = 1;
			foreach ( $row as $col_id => $column ) {
				// Make sure the column is valid
				if ( array_key_exists( $col_id, $cols ) ) {
					echo '"' . $column . '"';
					echo $i == count( $cols ) ? '' : ';';
				}
				$i++;
			}
			echo "\r\n";
		}
	}

	/**
	 * Perform the export
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function export() {
		if ( ! $this->can_export() )
			wp_die( __( 'You do not have permission to export data.', 'bbp-export-import' ), __( 'Error', 'bbp-export-import' ) );

		// Set headers
		$this->headers();

		// Output CSV columns (headers)
		$this->csv_cols_out();

		// Output CSV rows
		$this->csv_rows_out();

		exit;
	}
}