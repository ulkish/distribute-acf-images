<?php
/*
Plugin Name: Distribute ACF Images
Plugin URI:
Description: Allows you to distribute images inside ACF fields while using the Distributor plugin.
Version: 1.0.0
Author: hugomoran
Author URI: https://convistaalmar.com.ar/
License: GPL2+
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Invalid request.' );
}

add_action( 'dt_push_post', 'distribute_acf_image', 10, 4 );
add_action( 'dt_pull_post', 'pull_acf_image', 10, 3 );
add_action( 'dt_push_post_media', 'set_acf_media', 10, 6 );
add_action( 'dt_pull_post_media', 'pull_acf_media', 10, 6 );
add_action( 'dt_push_post', 'update_link_types', 10, 4 );

function distribute_acf_image( $new_post_id, $original_post_id, $args, $site ) {

	$destination_blog_id = (is_numeric($site)) ? $site : $site->site->blog_id;

	// Switch to origin to get id
	restore_current_blog();
	$origin_blog_id = get_current_blog_id();
	$origin_blog_id = ($origin_blog_id===$destination_blog_id) ? $args->site->blog_id : $origin_blog_id;

	// Go back
	switch_to_blog( $destination_blog_id );

	$post = get_post( $new_post_id );
	$fields = get_fields($post->ID );

	if ($fields) {

		foreach( $fields as $key => $value ) {

			$field_object = get_field_object( $key, $post->ID );

			foreach ($field_object as $key => $value) {

				if ($key=='type' && $value=='image'){

				} elseif (acf_is_array($value)) {

				   $field_name2 = array_loop($post->ID, $value);
				}
			}

			if( $field_object['type'] == 'image' ) {

				$field_name = ($field_object['_name']!='') ? $field_object['_name'] : $field_name2;
				$image_id = get_post_meta( $new_post_id, $field_name );
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
				$query = new WP_Query($args);
				$acf_image_id = $query->posts[0]->ID;

				if ($acf_image_id && get_post( $acf_image_id ) ) {

					if ( wp_get_attachment_image( $acf_image_id, 'thumbnail' ) ) {
						update_post_meta( $new_post_id, $field_name, $acf_image_id, $original_media_id );
					} else { }
				}
			}
		}
	}

	return false;
}



function pull_acf_image( $new_post_id, $args, $post_array ) {

	$destination_blog_id = get_current_blog_id();

	distribute_acf_image( $new_post_id, $original_post_id, $args, $destination_blog_id );
}


function set_acf_media ($boolean, $new_post_id, $media, $post_id, $args, $site){

	$destination_blog_id = (is_numeric($site)) ? $site : $site->site->blog_id;

	// Switch to origin to get id
	restore_current_blog();
	$origin_blog_id = get_current_blog_id();
	$origin_blog_id = ($origin_blog_id===$destination_blog_id) ? $args->site->blog_id : $origin_blog_id;

	switch_to_blog( $origin_blog_id );

	$media = \Distributor\Utils\prepare_media( $post_id );


	$fields = get_fields($post_id);

	if ($fields) {
		foreach( $fields as $key => $value ):

			$field_object = get_field_object( $key, $post_id);

			foreach ($field_object as $key => $value) {
				if (acf_is_array($value)) {
					$media_acf = array_loop2($value, $post_id);
				}
			}

			if( $field_object['type'] == 'image' || $field_object['media_type'] == 'image' ) {

				$field_name = $field_object['value'];
				if( $field_object['media_type'] == 'image' ) {
					$field_name = $field_object['source_url'];
				}
				if ($field_name!=''){
					$destination_site_url = parse_url($field_name); // destination
					$src_site_url = parse_url(get_site_url()); // main

					$field_name = str_replace($destination_site_url['host'], $src_site_url['host'], $field_name);
				}
				$image_id = get_image_id($field_name);

				$acf_image = \Distributor\Utils\format_media_post( get_post( $image_id ) );
				$featured_image_id = get_post_thumbnail_id( $post_id );

				$acf_image['featured'] = ($featured_image_id == $image_id) ? true : false;
				$media[] = $acf_image;

			}

		endforeach;
	}

	$media = array_merge($media, $media_acf);
	$media = unique_multidim_array($media,'id');

	// Go back
	switch_to_blog( $destination_blog_id );

	\Distributor\Utils\set_media( $new_post_id, $media );
}


function pull_acf_media( $boolean, $new_post_id, $media, $original_post_id, $post_array, $site ) {

	$destination_blog_id = get_current_blog_id();

	set_acf_media( $boolean, $new_post_id, $media, $original_post_id, $site, $destination_blog_id );
}

function get_image_id($image_url) {
	global $wpdb;
	$attachment = $wpdb->get_col($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE guid='%s';", $image_url ));
	if($attachment){
		return $attachment[0];
	}
}


//Page Builder image search
function array_loop($post_id, $array){
	global $wpdb;

	foreach ($array as $key => $value) {

		if ($key=='type' && $value=='image'){

		$dt_original_fields = $wpdb->get_results($wpdb->prepare("
			SELECT meta_key
			FROM $wpdb->postmeta as pm1
			WHERE pm1.meta_key in (SELECT SUBSTRING(pm2.meta_key, 2) FROM $wpdb->postmeta as pm2
			WHERE  pm2.post_id = %d
			AND pm2.meta_value = '%s')
			AND pm1.post_id = %d",
			$post_id , $array['key'], $post_id));

		$original_fields = $dt_original_fields;
		}  elseif(acf_is_array($value)){

			array_loop($post_id, $value);
		}

	}

	$table_name = $wpdb->prefix."postmeta" ;
	foreach ($original_fields as $field) {

		$field_name = $field->meta_key;
		$new_post_id = $post_id;
		$image_id = get_post_meta( $new_post_id, $field_name );


				$original_media_id = $image_id[0];

				$meta_key = 'dt_original_media_id';
				if ($original_media_id>=1){
					$dt_id = $wpdb->get_col($wpdb->prepare("
						SELECT post_id FROM $wpdb->postmeta
						WHERE meta_key ='%s'
						AND meta_value = %d
						ORDER BY meta_id DESC LIMIT 1;",
						$meta_key, $original_media_id));

					$acf_image_id = $dt_id[0];

					if ($acf_image_id && get_post( $acf_image_id ) ) {

						if ( wp_get_attachment_image( $acf_image_id, 'thumbnail' ) ) {
							$wpdb->query( $wpdb->prepare( "
							UPDATE $table_name  SET meta_value = %d
							WHERE meta_value = %d
							AND meta_key = '%s'
							AND post_id = %d",
							$acf_image_id, $original_media_id, $field_name, $new_post_id ));


						} else {

						}
					}
				}

	}
}


$acf_dt_media = array();
function array_loop2($array, $post_id, $deep=FALSE){
	global $wpdb, $acf_dt_media;

	foreach ($array as $key => $value) {

		if(acf_is_array($value)){

			array_loop2($value, $post_id, TRUE);

		} elseif ( ($key=='type' || $key=='media_type') && $value=='image' && ($array['value']!='' || $array['url']!='')){

			$field_name = ($array['value']!='') ? $array['value'] : $array['url'];
			if( $array['media_type'] == 'image' ) {
				$field_name = $array['source_url'];
			}
			if ($field_name!=''){
				$destination_site_url = parse_url($field_name); // destination
				$src_site_url = parse_url(get_site_url()); // main

				$field_name = str_replace($destination_site_url['host'], $src_site_url['host'], $field_name);
			}

			$image_id = get_image_id($field_name);

			$acf_image = \Distributor\Utils\format_media_post( get_post( $image_id ) );
			$featured_image_id = get_post_thumbnail_id( $post_id );

			$acf_image['featured'] = ($featured_image_id == $image_id) ? true : false;


			$acf_dt_media[] = $acf_image;

		}

	}


	if (!$deep){
		return $acf_dt_media;
	}
}


function unique_multidim_array($array, $key) {
	$temp_array = array();
	$i = 0;
	$key_array = array();

	foreach($array as $val) {
		if (!in_array($val[$key], $key_array)) {
			$key_array[$i] = $val[$key];
			$temp_array[$i] = $val;
		}
		$i++;
	}
	return $temp_array;
}




//Page Builder Page link

function update_link_types ($new_post_id, $original_post_id, $args, $site){

	$destination_blog_id = (is_numeric($site)) ? $site : $site->site->blog_id;

	// Switch to origin to get id
	restore_current_blog();
	$origin_blog_id = get_current_blog_id();
	$origin_blog_id = ($origin_blog_id===$destination_blog_id) ? $args->site->blog_id : $origin_blog_id;
	// Go back
	switch_to_blog( $destination_blog_id );

	$meta = get_post_meta($new_post_id);
	foreach ($meta as $key => $value) {

		if(strrpos($key, 'link_type')){

			if ($value[0]=='page') {

				$page_link = str_replace('link_type', 'page_link', $key);
				update_post_meta($new_post_id, $key, 'custom');
				$ob_id = get_post_meta($new_post_id, $page_link, true);
				delete_post_meta($new_post_id, $page_link);
				switch_to_blog( $origin_blog_id );
				$post_destination_link = get_permalink($ob_id);
				switch_to_blog( $destination_blog_id );

				$custom_link = str_replace('link_type', 'custom_link', $key);

				update_post_meta($new_post_id, $custom_link, $post_destination_link);
			}

		}
	}

}
