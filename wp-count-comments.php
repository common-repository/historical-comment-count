<?php
/*
Plugin Name: Historical Comment Count
Plugin URI: http://www.truthmedia.com/wordpress/historical-comment-count/
Description: Allows the website owner to track the number of published comments on their blog over time.
Version: 1.20
Author: TruthMedia Internet Group
Author URI: http://truthmedia.com/
Requires: WordPress Version 2.8 and PHP 4.3

Allows the website owner to track the number of published comments on their blog over time.


*/

	/**
	 * Test and see if we have loaded the class objects.  Include the files if we haven't.
	 */	 
	
	if(!class_exists("Object"))	include_once("class/Object.class.php");
	if(!class_exists("CountComments"))	include_once("class/CountComments.class.php");


	/**
	 * If we are able to, create a new instance of the plugin object now.
	 */
	if(!isset($wp_count_comments)) {
		global $table_prefix;
		$wp_count_comments = new CountComments();
		
		$wp_count_comments->path = dirname(__FILE__);
		$wp_count_comments->prefix = $table_prefix;
		$wp_count_comments->url = WP_PLUGIN_URL.'/'.str_replace(basename( __FILE__),"",plugin_basename(__FILE__)); 

		// Add hooks as necessary to connect to WordPress
		add_action('admin_menu', array(&$wp_count_comments, 'wp_admin_init'));
	}
	
?>