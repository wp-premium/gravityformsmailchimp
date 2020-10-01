<?php

// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

/**
Plugin Name: Gravity Forms Mailchimp Add-On
Plugin URI: https://gravityforms.com
Description: Integrates Gravity Forms with Mailchimp, allowing form submissions to be automatically sent to your Mailchimp account.
Version: 4.8
Author: Gravity Forms
Author URI: https://gravityforms.com
License: GPL-2.0+
Text Domain: gravityformsmailchimp
Domain Path: /languages

------------------------------------------------------------------------
Copyright 2009-2020 rocketgenius

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
 **/

define( 'GF_MAILCHIMP_VERSION', '4.8' );

// If Gravity Forms is loaded, bootstrap the Mailchimp Add-On.
add_action( 'gform_loaded', array( 'GF_MailChimp_Bootstrap', 'load' ), 5 );

/**
 * Class GF_MailChimp_Bootstrap
 *
 * Handles the loading of the Mailchimp Add-On and registers with the Add-On Framework.
 */
class GF_MailChimp_Bootstrap {

	/**
	 * If the Feed Add-On Framework exists, Mailchimp Add-On is loaded.
	 *
	 * @access public
	 * @static
	 */
	public static function load() {

		if ( ! method_exists( 'GFForms', 'include_feed_addon_framework' ) ) {
			return;
		}

		require_once( 'class-gf-mailchimp.php' );

		GFAddOn::register( 'GFMailChimp' );
	}
}

/**
 * Returns an instance of the GFMailChimp class
 *
 * @see    GFMailChimp::get_instance()
 *
 * @return GFMailChimp
 */
function gf_mailchimp() {
	return GFMailChimp::get_instance();
}
