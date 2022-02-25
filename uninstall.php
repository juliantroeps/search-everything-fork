<?php
	/**
	 * Search Everything
	 * Plugin unstall file
	 *
	 * @version 8.3.1
	 * @package Search Everything
	 */
	
	// If uninstall not called from WordPress exit
	if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
		exit();
	}
	
	delete_option( 'se_options' );
	delete_option( 'se_meta' );
