<?php

/*
Plugin Name: Amazon S3 Media
Plugin URI:
Description: Use Amazon S3 to store all your uploads (non-database media).
Version: 1.0
Author: Timothy Wood (@codearachnid)
Author URI: http://www.codearachnid.com
Author Email: tim@imaginesimplicity.com
Text Domain: s3media
License: GPLv2 or later

Notes: THIS FILE IS FOR LOADING THE LIBS ONLY

License:

  Copyright 2011 Imagine Simplicity (tim@imaginesimplicity.com)

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License, version 2, as
  published by the Free Software Foundation.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

*/

if ( !defined( 'ABSPATH' ) )
	die( '-1' );

/**
 *  Include required files to get this show on the road
 */
require_once 'wp-s3media.php';

/**
 * Add action 'plugins_loaded' to instantiate main class.
 *
 * @return void
 */
function s3media_load() {
	if ( apply_filters( 's3media_pre_check', class_exists( 's3media' ) && s3media::prerequisites() ) ) {
		add_action( 'init', array( 's3media', 'instance' ), -100, 0 );
	} else {
		// let the user know prerequisites weren't met: Wrong Versions
		add_action( 'admin_head', array( 's3media', 'min_version_fail_notice' ), 0, 0 );
	}
}
add_action( 'plugins_loaded', 's3media_load', 1 ); // high priority so that it's not too late for addon overrides
