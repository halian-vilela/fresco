<?php
/*
Plugin Name: Fresco
Plugin URI: http://github.com/chrismccoy
Description: Tiny plugin to enable the responsive commerical FrescoJS lightbox
Author: Chris McCoy
Author URI: http://www.frescojs.com/
Version: 1.0
*/

// fire off a notice if the fresco js and css arent present

add_action('admin_notices', 'fresco_notice');

function fresco_notice() {
	if(!check_files()) {
		echo '<div id="message" class="error"><p><strong>Fresco Javascript and CSS is missing from the plugin directory. Please do so in order to use this plugin.</strong></p></div>';
	}
}

function check_files() {
	$jsfiles = array('/js', '/js/fresco.js', '/css', '/css/fresco.css');
	foreach($jsfiles as $jsfile) {
		if(!is_dir(plugin_dir_path(__FILE__) . $jsfile)) {
			if(!file_exists(plugin_dir_path(__FILE__) . $jsfile)) {
				return false;
			}
		}
	}
	return true;
}

// add jquery, media queries for IE, and fresco.js

add_action('wp_enqueue_scripts', 'fresh_scripts');

function fresh_scripts() {
	wp_deregister_script('jquery');
	wp_enqueue_script('jquery', 'http://ajax.googleapis.com/ajax/libs/jquery/1.8/jquery.min.js');

	global $is_IE;
	if ($is_IE) {
		wp_enqueue_script('css3_mediaqueries', 'http://css3-mediaqueries-js.googlecode.com/svn/trunk/css3-mediaqueries.js');
	}

	wp_enqueue_script('frescojs', plugins_url('js/fresco.js', __FILE__));
	wp_enqueue_style('frescocss', plugins_url('css/fresco.css', __FILE__));
}

// lets remove the awful html that wordpress spits out for the gallery shortcode

remove_shortcode('gallery');

// our own simple and clean xhtml valid output

add_shortcode('gallery', 'fresco_gallery_shortcode');

function fresco_gallery_shortcode() {
  	global $post;
	$attachments = get_children( array('post_parent' => $post->ID, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image') );
	$output = "\n<div class=\"image\">\n";
        foreach ( $attachments as $id => $attachment ) {
        	$full_image = wp_get_attachment_url($id);
    		$thumbnail = wp_get_attachment_image_src($id, 'thumbnail');
    		$title = trim($attachment->post_excerpt) ? wptexturize($attachment->post_excerpt) : $attachment->post_title;
		$output .= "\t<a href=\"$full_image\" class=\"fresco\" data-fresco-caption=\"$title\" data-fresco-group=\"gallery-{$post->ID}\">\n\t<img src=\"$thumbnail[0]\" width=\"100\" height=\"100\" alt=\"$title\" />\n\t</a>\n\n";
	}
	$output .= "</div>\n";
  	return $output;
}

// filter all images with fresco values

add_filter('the_content', 'lightbox_the_content', 12);

function lightbox_the_content($content) {
	global $post;
	$regex = '/(?:\<a.*href="([^"]*)"[^>]*>)[.\W]*(?:\<img.*src="([^"]*)"[^>]*>)/';
	$caption = '/title\s{0,}=\s{0,}["\'](.+?)["\']/is';
	if(preg_match($regex,$content)) {
		preg_match($caption, $content, $lightbox_caption);
		$content = preg_replace($regex, '<a href="$1" class="fresco" data-fresco-caption="'.$lightbox_caption[1].'" data-fresco-group="gallery-'.$post->ID.'"><img src="$2" />',$content);
	}
	return $content;
}

// modify the output to add the fresco values when you click insert into post

add_filter( 'media_send_to_editor', 'lightbox_media_send_to_editor', 11,3);

function lightbox_media_send_to_editor($html,$attachment_id,$attachment) {
	$post =& get_post($attachment_id);
	preg_match('/title\s{0,}=\s{0,}["\'](.+?)["\']/is', $html, $group);
	$html = preg_replace('/(alt|class)=\"[^"]*"\s/','', $html);
	$html = preg_replace('/<a href=("|\')([^"\']+)("|\')>/', '<a href="$2" class="fresco" data-fresco-caption="'.$group[1].'" data-fresco-group="gallery-'.$post->post_parent.'">', $html);
	return $html;
}

// add oembed for image urls

wp_embed_register_handler( 'detect_lightbox', '#^http://.+\.(jpe?g|gif|png)$#i', 'wp_embed_handler_detect_lightbox' , 10, 3);

function wp_embed_handler_detect_lightbox( $matches, $attr, $url, $rawattr ) {
	global $post;
    	if (preg_match('#^http://.+\.(jpe?g|gif|png)$#i', $url)) {
        	$embed = sprintf('<a href="%1$s" class="fresco" data-fresco-caption="'.$post->post_title.'" data-fresco-group="gallery-'.$post->ID.'"><img src="%1$s" /></a>',$matches[0]);
    	} 
	$embed = apply_filters( 'oembed_detect_lightbox', $embed, $matches, $attr, $url, $rawattr );
    	return apply_filters( 'oembed_result', $embed, $url);
}

// remove p tags around images

function filter_ptags_on_images($content) {
    return preg_replace('/<p>\s*(<a .*>)?\s*(<img .* \/>)\s*(<\/a>)?\s*<\/p>/iU', '\1\2\3', $content);
}

add_filter('the_content','filter_ptags_on_images');
