<?php
/*
Plugin Name: Gravity Forms MailChimp Add-On
Plugin URI: http://www.gravityforms.com
Description: Integrates Gravity Forms with MailChimp allowing form submissions to be automatically sent to your MailChimp account
Version: 3.6
Author: rocketgenius
Author URI: http://www.rocketgenius.com
Text Domain: gravityformsmailchimp
Domain Path: /languages

------------------------------------------------------------------------
Copyright 2009 rocketgenius

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
*/

define( 'GF_MAILCHIMP_VERSION', '3.6' );

add_action( 'gform_loaded', array( 'GF_MailChimp_Bootstrap', 'load' ), 5 );

class GF_MailChimp_Bootstrap {

	public static function load(){

		if ( ! method_exists( 'GFForms', 'include_feed_addon_framework' ) ) {
			return;
		}

		require_once( 'class-gf-mailchimp.php' );

		GFAddOn::register( 'GFMailChimp' );
	}
}

function gf_mailchimp(){
	return GFMailChimp::get_instance();
}