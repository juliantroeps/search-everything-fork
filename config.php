<?php
	
	/**
	 * Search Everything
	 * Plugin config file
	 *
	 * @version 8.3.0
	 * @package Search Everything
	 */
	
	global $se_options, $se_meta, $se_global_notice_pages;
	
	$se_options = false;
	$se_meta    = false;
	
	$se_global_notice_pages = array( 'plugins.php', 'index.php', 'update-core.php' );
	
	/**
	 * Get admin options
	 *
	 * @return ArrayObject
	 */
	function se_get_options() {
		global $se_options, $se_meta;
		
		if ( $se_options ) {
			return $se_options;
		}
		
		$se_options = get_option( 'se_options', false );
		
		if ( ! $se_options || ! $se_meta || $se_meta['version'] !== SE_VERSION ) {
			se_upgrade();
			$se_meta    = get_option( 'se_meta' );
			$se_options = get_option( 'se_options' );
		}
		
		$se_meta    = new ArrayObject( $se_meta );
		$se_options = new ArrayObject( $se_options );
		
		return $se_options;
	}
	
	/**
	 * Get plugin meza
	 *
	 * @return false
	 */
	function se_get_meta() {
		global $se_meta;
		
		if ( ! $se_meta ) {
			se_get_options();
		}
		
		return $se_meta;
	}
	
	/**
	 * Update plugin meta
	 *
	 * @param $new_meta
	 *
	 * @return bool
	 */
	function se_update_meta( $new_meta ) {
		global $se_meta;
		
		$new_meta = (array) $new_meta;
		
		$r = update_option( 'se_meta', $new_meta );
		
		if ( $r && $se_meta !== false ) {
			$se_meta->exchangeArray( $new_meta );
		}
		
		return $r;
	}
	
	/**
	 * Update admin options
	 *
	 * @param $new_options
	 *
	 * @return bool
	 */
	function se_update_options( $new_options ) {
		global $se_options;
		
		$new_options = (array) $new_options;
		
		$r = update_option( 'se_options', $new_options );
		
		if ( $r && $se_options !== false ) {
			$se_options->exchangeArray( $new_options );
		}
		
		return $r;
	}
	
	/**
	 * Set global notive
	 */
	function se_set_global_notice() {
		// Some url
		$url = 'https://www.juliantroeps.com';
		
		// Plugin meta
		$se_meta = get_option( 'se_meta', false );
		
		$se_meta['se_global_notice'] = array(
			'title'   => 'Searching for your car keys?',
			'message' => 'Well, there are some things our plugin can\'t search for - your car keys, your wallet, a soulmate and <strong>unregistered custom post types</strong> :) <br> It searches for almost everything else, but it also does some other amazing stuff, like ... research. <a href="' . $url . '" target="_blank">Check it out!</a>'
		);
		
		// Update meta
		se_update_meta( $se_meta );
	}
	
	/**
	 * Upgrade helper
	 * We have to be careful, as previously version was not stored in the options!
	 */
	function se_upgrade() {
		$se_meta = get_option( 'se_meta', false );
		$version = false;
		
		if ( $se_meta ) {
			$version = $se_meta['version'];
		}
		
		if ( ! $version ) {
			// Check if se_options exist
			$se_options = get_option( 'se_options', false );
			if ( $se_options ) {
				// Existing users don't have version stored in their db
				se_migrate();
			}
			else {
				se_install();
			}
		}
	}
	
	/**
	 * Migration helper
	 */
	function se_migrate() {
		$se_meta = array(
			'blog_id'                  => false,
			'auth_key'                 => false,
			'version'                  => '7.0.2',
			'first_version'            => '7.0.1',
			'new_user'                 => false,
			'name'                     => '',
			'email'                    => '',
			'show_options_page_notice' => false
		);
		
		update_option( 'se_meta', $se_meta );
		
		//get options and update values to boolean
		$old_options = get_option( 'se_options', false );
		
		if ( $old_options ) {
			$new_options = se_get_default_options();
			
			$boolean_keys = array(
				'se_use_page_search'        => false,
				'se_use_comment_search'     => false,
				'se_use_tag_search'         => false,
				'se_use_tax_search'         => false,
				'se_use_category_search'    => false,
				'se_approved_comments_only' => false,
				'se_approved_pages_only'    => false,
				'se_use_excerpt_search'     => false,
				'se_use_draft_search'       => false,
				'se_use_attachment_search'  => false,
				'se_use_authors'            => false,
				'se_use_cmt_authors'        => false,
				'se_use_metadata_search'    => false,
				'se_use_highlight'          => false,
			);
			$text_keys    = array(
				'se_exclude_categories'      => '',
				'se_exclude_categories_list' => '',
				'se_exclude_posts'           => '',
				'se_exclude_posts_list'      => '',
				'se_highlight_color'         => '',
				'se_highlight_style'         => ''
			);
			
			foreach ( $boolean_keys as $k ) {
				$new_options[ $k ] = ( 'Yes' === $old_options[ $k ] );
			}
			foreach ( $text_keys as $t ) {
				$new_options[ $t ] = $old_options[ $t ];
			}
			update_option( 'se_options', $new_options );
		}
		
		// Moved to meta
		$notice = get_option( 'se_show_we_tried', false );
		if ( $notice ) {
			delete_option( 'se_show_we_tried' );
		}
	}
	
	/**
	 * Installation helper
	 */
	function se_install() {
		$se_meta = array(
			'blog_id'                  => false,
			'api_key'                  => false,
			'auth_key'                 => false,
			'version'                  => SE_VERSION,
			'first_version'            => SE_VERSION,
			'new_user'                 => true,
			'name'                     => '',
			'email'                    => '',
			'show_options_page_notice' => false
		);
		
		$se_options = se_get_default_options();
		
		update_option( 'se_meta', $se_meta );
		update_option( 'se_options', $se_options );
		
		se_set_global_notice();
	}
	
	/**
	 * Set default option
	 *
	 * @return array
	 */
	function se_get_default_options() {
		$se_options = array(
			'se_exclude_categories'      => '',
			'se_exclude_categories_list' => '',
			'se_exclude_posts'           => '',
			'se_exclude_posts_list'      => '',
			'se_use_page_search'         => false,
			'se_use_comment_search'      => true,
			'se_use_tag_search'          => false,
			'se_use_tax_search'          => false,
			'se_use_category_search'     => true,
			'se_approved_comments_only'  => true,
			'se_approved_pages_only'     => false,
			'se_use_excerpt_search'      => false,
			'se_use_draft_search'        => false,
			'se_use_attachment_search'   => false,
			'se_use_authors'             => false,
			'se_use_cmt_authors'         => false,
			'se_use_metadata_search'     => false,
			'se_use_highlight'           => true,
			'se_highlight_color'         => 'orange',
			'se_highlight_style'         => '',
			'se_research_metabox'        => array(
				'visible_on_compose'      => true,
				'external_search_enabled' => false,
				'notice_visible'          => true
			)
		);
		
		return $se_options;
	}

