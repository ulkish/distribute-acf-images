<?php
/*
Plugin Name: Distribute ACF Images
Plugin URI:
Description: Allows you to distribute images inside ACF fields while using the Distributor plugin.
Version: 1.0.0
Author: Con Vista Al Mar
Author URI: https://convistaalmar.com.ar/
License: GPL2+
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Invalid request.' );
}

// Check if class already exists
if( ! class_exists( 'Distribute_ACF_Images' ) ) :


class Distribute_ACF_Images {

	var $acf_dt_media = array();

	/**
	 * Add actions
	 */
	function __construct() {
		add_action( 'dt_push_post',       array( $this, 'push_acf_image' ), 10, 4 );
		add_action( 'dt_pull_post',       array( $this, 'pull_acf_image' ), 10, 3 );
		add_action( 'dt_push_post_media', array( $this, 'set_acf_media' ), 10, 6 );
		add_action( 'dt_pull_post_media', array( $this, 'pull_acf_media' ), 10, 6 );
	}


	/**
	 * Main function. Updates new posts to contain the correct image ID.
	 *
	 * @param  int $new_post_id      The newly created post ID.
	 * @param  int $original_post_id The original post ID.
	 * @param  array $args           Not used (The arguments passed into wp_insert_post.)
	 * @param  object $site          The distributor connection being pulled from.
	 */
	function push_acf_image( $new_post_id, $original_post_id, $args, $site ) {
		$destination_blog_id = (is_numeric($site)) ? $site : $site->site->blog_id;
		// Switch to origin to get id
		restore_current_blog();
		$origin_blog_id      = get_current_blog_id();
		$origin_blog_id      = ( $origin_blog_id === $destination_blog_id ) ? $args->site->blog_id : $origin_blog_id;
		// Go back
		switch_to_blog( $destination_blog_id );
		$post                = get_post( $new_post_id );
		$fields              = get_fields( $post->ID );

		if ( $fields ) {

			foreach( $fields as $key => $value ) {

				$field_object = get_field_object( $key, $post->ID );

				foreach ( $field_object as $key => $value ) {
					// If an image is found, ?
					if ( $key == 'type' && $value == 'image' ){
					// If an array is found, look for an image and place it in $image_name
					} elseif ( acf_is_array( $value ) ) {
					   $image_name = $this->find_img_in_array($post->ID, $value);
					}
				}
				if( $field_object['type'] == 'image' ) {
					// If an image name hasn't been found, grab it from the array search
					$field_name        = ( $field_object['_name'] != '' ) ? $field_object['_name'] : $image_name;
					$image_id          = get_post_meta( $new_post_id, $field_name );
					$original_media_id = $image_id[0];

					$meta_key = 'dt_original_media_id';
					$args = array(
						'post_type'      => 'attachment',
						'post_status'    => 'inherit',
						'order'          => 'DESC',
						'posts_per_page' => 1,
						'meta_query'     => array(
							array(
								'key'     => $meta_key,
								'value'   => $original_media_id,
								'compare' => '=',
							)
						)
					);
					$query = new WP_Query( $args );
					$acf_image_id = $query->posts[0]->ID;

					if ( $acf_image_id && get_post( $acf_image_id ) ) {
						// Update the new post with the correct image location
						if ( wp_get_attachment_image( $acf_image_id, 'thumbnail' ) ) {
							update_post_meta(
								$new_post_id,
								$field_name,
								$acf_image_id,
								$original_media_id );
						} else { }
					}
				}
			}
		}

		return false;
	}


	/**
	 * Using push_acf_image for pulling.
	 *
	 * @param  int   $new_post_id Newly created post
	 * @param  array $args        Not used (The arguments passed into wp_insert_post.)
	 * @param  array $post_array  (Not used)
	 */
	function pull_acf_image( $new_post_id, $args, $post_array ) {
		$destination_blog_id = get_current_blog_id();

		if ( (isset( $new_post_id ) && isset( $destination_blog_id ) && isset( $original_post_id ) ) ) {
			$this->push_acf_image(
				$new_post_id,
				$original_post_id,
				$args,
				$destination_blog_id
			);
		}
	}

	/**
	 * Given an array of media, set the media to a new post.
	 *
	 * @param boolean $boolean     Not used. (If Distributor should set the post media)
	 * @param int $new_post_id     Newly created post
	 * @param array $media         List of media items attached to the post, formatted by {@see \Distributor\Utils\prepare_media()}.
	 * @param int $post_id         Original post id.
	 * @param array $args          Not used (The arguments passed into wp_insert_post.)
	 * @param object $site         The distributor connection being pulled from.
	 */
	function set_acf_media ( $boolean, $new_post_id, $media, $post_id, $args, $site ){
		$destination_blog_id = ( is_numeric( $site ) ) ? $site : $site->site->blog_id;
		// Switch to origin to get id
		restore_current_blog();
		$origin_blog_id      = get_current_blog_id();
		$origin_blog_id      = ( $origin_blog_id === $destination_blog_id ) ? $args->site->blog_id : $origin_blog_id;
		// Go back.
		switch_to_blog( $origin_blog_id );
		$media               = \Distributor\Utils\prepare_media( $post_id );
		$fields              = get_fields( $post_id );

		if ( $fields ) {
			foreach( $fields as $key => $value ) {
				$field_object = get_field_object( $key, $post_id );
				foreach ( $field_object as $key => $value ) {
					if ( acf_is_array( $value ) ) {
						$media_acf = $this->find_dt_media_id( $value, $post_id );
					}
				}
				if( ($field_object['type'] == 'image') || ((isset($field_object['media_type'])) && $field_object['media_type'] == 'image') ) {
					if ($field_object['value']) {
						$field_name = $field_object['value'];
					}
					if( isset($field_object['media_type']) && $field_object['media_type'] == 'image' ) {
						$field_name = $field_object['source_url'];
					}
					if ( isset( $field_name ) ) {
						$destination_site_url = parse_url( $field_name ); // destination
						$src_site_url         = parse_url( get_site_url() ); // main
						$field_name           = str_replace(
							$destination_site_url['host'],
							$src_site_url['host'],
							$field_name);
						$image_id = $this->get_image_id( $field_name );
					}
					if ( isset( $image_id ) ) {
						$acf_image             = \Distributor\Utils\format_media_post( get_post( $image_id ) );
						$featured_image_id     = get_post_thumbnail_id( $post_id );
						$acf_image['featured'] = ( $featured_image_id == $image_id ) ? true : false;
						$media[]               = $acf_image;
					}
				}
			}
		}

		$media = array_merge( $media, $media_acf );
		$media = $this->remove_duplicates_in_array( $media,'id' );
		// Go back
		switch_to_blog( $destination_blog_id );
		\Distributor\Utils\set_media( $new_post_id, $media );
	}

	/**
	 * Using set_acf_media to pull media
	 *
	 * @param  boolean $boolean      Not used. (If Distributor should set the post media)
	 * @param  int $new_post_id      Newly created post
	 * @param  array $media          List of media items attached to the post, formatted by {@see \Distributor\Utils\prepare_media()}.
	 * @param  int $original_post_id Original post id.
	 * @param  array $post_array     The arguments passed into wp_insert_post.
	 * @param  object $site          The distributor connection being pulled from.
	 */
	function pull_acf_media( $boolean, $new_post_id, $media, $original_post_id, $post_array, $site ) {
		$destination_blog_id = get_current_blog_id();
		$this->set_acf_media( $boolean,
			$new_post_id,
			$media,
			$original_post_id,
			$site,
			$destination_blog_id );
	}

	/**
	 * Gets image ids by searching by its 'guid'.
	 *
	 * @param  int $image_url     The guid used to query the db.
	 * @return int $attachment[0] The image id.
	 */
	function get_image_id( $image_url ) {
		global $wpdb;

		$attachment = $wpdb->get_col( $wpdb->prepare( "
			SELECT ID FROM $wpdb->posts
			WHERE guid='%s';",
			$image_url ));

		if( $attachment ){
			return $attachment[0];
		}
	}


	/**
	 * Page Builder image search.
	 *
	 * @param  int $post_id   Post ID
	 * @param  array $array   Metavalue of an ACF field.
	 */
	function find_img_in_array( $post_id, $array ){
		global $wpdb;

		foreach ( $array as $key => $value ) {

			if ( $key == 'type' && $value == 'image' ){


				$dt_original_fields = $wpdb->get_results( $wpdb->prepare("
					SELECT meta_key
					FROM $wpdb->postmeta as pm1
					WHERE pm1.meta_key in (
						SELECT SUBSTRING(pm2.meta_key, 2)
						FROM $wpdb->postmeta as pm2
						WHERE  pm2.post_id = %d
						AND pm2.meta_value = '%s'
						)
					AND pm1.post_id = %d",
					$post_id , $array['key'], $post_id ) );



					// Get keys from a post with {id}
					// Check if {value}
					// Return the meta_keys
					// $post_meta_keys = get_post_meta( $post_id );
					// foreach ( $post_meta_keys as $key => $value) {
					// 	$tmp_value = subtr($key, 2);

					// 	if ( $tmp_value === $array['key'] ) {
					// 		$dt_original_fields = $array['key'];
					// 	}
					// }

				// print_r( $dt_original_fields );
				$original_fields = $dt_original_fields;
			}  elseif( acf_is_array( $value ) ){
				$this->find_img_in_array( $post_id, $value );
			}
		}
		if ( isset( $original_fields ) ) {
			foreach ( $original_fields as $field ) {

				$field_name        = $field->meta_key;
				$new_post_id       = $post_id;
				$image_id          = get_post_meta( $new_post_id, $field_name );
				$original_media_id = $image_id[0];
				$meta_key          = 'dt_original_media_id';

				if ($original_media_id >= 1 ) {
					$args = array(
							'post_type'      => 'attachment',
							'post_status'    => 'inherit',
							'order'          => 'DESC',
							'posts_per_page' => 1,
							'meta_query'     => array(
								array(
									'key'     => $meta_key,
									'value'   => $original_media_id,
									'compare' => '=',
								)
							)
						);
					$query        = new WP_Query( $args );
					$acf_image_id = $query->posts[0]->ID;

					if ( $acf_image_id && get_post( $acf_image_id ) ) {

						if ( wp_get_attachment_image( $acf_image_id, 'thumbnail' ) ) {
							update_post_meta(
								$new_post_id,
								$field_name,
								$acf_image_id,
								$original_media_id );
						} else {
							// Do something.
						}
					}
				}
			}
		}
	}

	/**
	 *	Finds correct image ID
	 *
	 * @param  array  $array    An array if meta field names
	 * @param  int $post_id     Original post id.
	 * @param  boolean $found   Image has been found.
	 * @return array $acf_dt_media
	 */
	function find_dt_media_id( $array, $post_id, $found = FALSE ) {
		global $wpdb, $acf_dt_media;

		foreach ( $array as $key => $value ) {
			// If it's an array search deeper.
			if ( acf_is_array( $value ) ) {
				$this->find_dt_media_id( $value, $post_id, TRUE );
			} elseif ( ( (isset($key) && $key=='type') || (isset($key) && $key=='media_type'))
				&& ((isset($value)) && $value=='image')
				&& (isset($array['value']) || isset($array['url']))){

				$field_name = ( isset($array['value']) ) ? $array['value'] : $array['url'];
				if ( (isset($field_object['media_type'])) && $array['media_type'] == 'image' ) {
					$field_name = $array['source_url'];
				}
				if ( isset($field_name) ) {
					$destination_site_url = parse_url( $field_name ); // destination
					$src_site_url         = parse_url( get_site_url() ); // main

					$field_name           = str_replace(
						$destination_site_url['host'],
						$src_site_url['host'],
						$field_name );
				}

				$image_id              = $this->get_image_id( $field_name );
				$acf_image             = \Distributor\Utils\format_media_post( get_post( $image_id ) );
				$featured_image_id     = get_post_thumbnail_id( $post_id );
				$acf_image['featured'] = ( $featured_image_id == $image_id ) ? true : false;
				$acf_dt_media[] = $acf_image;
			}
		}

		if ( ! $found ) {
			return $acf_dt_media;
		}
	}

	/**
	 * Removes duplicate values from a multi-dimensional array.
	 *
	 *
	 * @param  array $array  Array containing duplicates.
	 * @param  string $key   Field name.
	 * @return array $temp_array Cleaned array.
	 */
	function remove_duplicates_in_array( $array, $key ) {
		$temp_array = array();
		$i          = 0;
		$key_array  = array();

		foreach( $array as $val ) {
			if ( ! in_array( $val[$key], $key_array ) ) {
				$key_array[$i]  = $val[$key];
				$temp_array[$i] = $val;
			}
			$i++;
		}
		return $temp_array;
	}

} // End of class.



new Distribute_ACF_Images;// Instantiate our class.


endif;// End of class_exists() check.
