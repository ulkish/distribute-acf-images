<?php
/**
 * Distribute ACF Images
 *
 * Plugin Name: Distribute ACF Images
 * Plugin URI:
 * Description: This is an addon for Distributor plugin.
 * Version:     1.0
 * Author:      Tipit.net
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Invalid request.' );
}

add_action( 'dt_push_post', 'distribute_acf_image', 10, 4 );
function distribute_acf_image( $new_post_id, $original_post_id, $args, $site ) {

    $destination_blog_id = (is_numeric($site)) ? $site : $site->site->blog_id;

    // Switch to origin to get id
    restore_current_blog();
    $origin_blog_id = get_current_blog_id();
    $origin_blog_id = ($origin_blog_id === $destination_blog_id) ? $args->site->blog_id : $origin_blog_id;

    // Go back
    switch_to_blog( $destination_blog_id );

    $post = get_post( $new_post_id );
    $fields = get_fields( $post->ID );

    if ($fields) {

        foreach( $fields as $key => $value ) {

            $field_object = get_field_object( $key, $post->ID );

            if( $field_object['type'] == 'image' ) {

                $field_name = $field_object['_name'];

                $image_id = get_post_meta( $new_post_id, $field_name );

                $original_media_id = $image_id[0];

                $meta_key = 'dt_original_media_id';


                $args = array(
                    'post_type'  => 'attachment',
                    'post_status' => 'inherit',
                    'order'      => 'DESC',
                    'posts_per_page' => 1,
                    'meta_query' => array(
                        array(
                            'key' => $meta_key,
                            'value' => $original_media_id,
                            'compare' => '=',
                        )
                    )
                );

                $query = new WP_Query($args);
                $acf_image_id = $query->posts[0]->ID;


                if ($acf_image_id && get_post( $acf_image_id ) ) {
                    if ( wp_get_attachment_image( $acf_image_id, 'thumbnail' ) ) {

                        update_post_meta( $new_post_id, $field_name, $acf_image_id, $original_media_id );

                        } else {

                        }
                    }
            }
        }
    }
    return false;
}


add_action( 'dt_pull_post', 'pull_acf_image', 10, 3 );
function pull_acf_image( $new_post_id, $args, $post_array ) {

    $destination_blog_id = get_current_blog_id();
    distribute_acf_image( $new_post_id, $original_post_id, $args, $destination_blog_id );
}


add_action( 'dt_push_post_media', 'set_acf_media', 10, 6 );
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
	    foreach( $fields as $key => $value ) {

			$field_object = get_field_object( $key, $post_id);

			if( $field_object['type'] == 'image' ) {
				$field_name = $field_object['value'];
				if ($field_name!=''){
					$destination_site_url = parse_url( $field_name ); // destination
					$src_site_url = parse_url(get_site_url()); // main

					$field_name = str_replace( $destination_site_url['host'], $src_site_url['host'], $field_name );
				}
				$image_id = get_image_id( $field_name );

				$acf_image = \Distributor\Utils\format_media_post( get_post( $image_id ) );
				$featured_image_id = get_post_thumbnail_id( $post_id );

				$acf_image['featured'] = ( $featured_image_id == $image_id ) ? true : false;
				$media[] = $acf_image;
			}
		}
	}

	// Go back
	switch_to_blog( $destination_blog_id );

	\Distributor\Utils\set_media( $new_post_id, $media );
}

add_action( 'dt_pull_post_media', 'pull_acf_media', 10, 6 );
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
