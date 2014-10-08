<?php
/*
Plugin Name: PWrapper
Description:  Facilities to WP as Framework in class-based fashion
Plugin URI:
Version: v0.3
Author: Thiago Fernandes
Plugin Type: Pods
*/

// -------------------------------------------------------------------------
// Prevent direct access to this file
// -------------------------------------------------------------------------
if ( ! function_exists( 'add_action' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PW_PATH', trailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'PW_URL', trailingslashit( plugin_dir_url( __FILE__ ) ) );

if ( !class_exists( 'Pods_Wrapper' ) )
{
	include_once PW_PATH . 'includes/pw-core.php';

    //initialize plugin
    add_action('plugins_loaded', array('Pods_Wrapper', 'init'));
}
