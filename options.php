<?php
	
	/**
	 * Search Everything
	 * Plugin options file
	 *
	 * @version 8.3.1
	 * @package Search Everything
	 */
	
	/**
	 * Everything Admin!
	 */
	class Search_Everything_Admin {
		
		/**
		 * Load localization files
		 */
		function se_localization() {
			load_plugin_textdomain( 'SearchEverything', false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
		}
		
		/**
		 * Class constructor
		 */
		function __construct() {
			// Load language file
			$locale  = get_locale();
			$meta    = se_get_meta();
			$options = se_get_options();
			
			if ( ! empty( $locale ) ) {
				add_action( 'admin_init', array( &$this, 'se_localization' ) );
			}
			
			add_action( 'admin_enqueue_scripts', array( &$this, 'se_register_plugin_scripts_and_styles' ) );
			add_action( 'admin_menu', array( &$this, 'se_add_options_panel' ) );
			
			if ( isset( $_GET['se_notice'] ) && 0 == $_GET['se_notice'] ) {
				$meta['show_options_page_notice'] = false;
				se_update_meta( $meta );
			}
			
			if ( $meta['show_options_page_notice'] ) {
				add_action( 'all_admin_notices', array( &$this, 'se_options_page_notice' ) );
			}
			
			if ( isset( $_GET['se_global_notice'] ) && 0 == $_GET['se_global_notice'] ) {
				$meta['se_global_notice'] = null;
				se_update_meta( $meta );
			}
		}
		
		/**
		 * Register script and styles
		 */
		function se_register_plugin_scripts_and_styles() {
			wp_register_style( 'search-everything', SE_PLUGIN_URL . '/static/css/admin.css', array(), SE_VERSION );
			wp_enqueue_style( 'search-everything' );
			
			add_editor_style( SE_PLUGIN_URL . '/static/css/se-styles.css', array(), SE_VERSION );
			
			wp_register_style( 'search-everything-compose', SE_PLUGIN_URL . '/static/css/se-compose.css', array(), SE_VERSION );
			wp_enqueue_style( 'search-everything-compose' );
			
			wp_register_script( 'search-everything', SE_PLUGIN_URL . '/static/js/searcheverything.js', array(), SE_VERSION );
			wp_enqueue_script( 'search-everything' );
		}
		
		/**
		 * Admin panel
		 */
		function se_add_options_panel() {
			add_options_page( 'Search', 'Search Everything', 'manage_options', 'extend_search', array( &$this, 'se_option_page' ) );
		}
		
		/**
		 * Validation helper
		 *
		 * @param $validation_rules
		 *
		 * @return array
		 */
		function se_validation( $validation_rules ) {
			$regex    = array(
				"color"         => "^(([a-z]+)|(#[0-9a-f]{2,6}))?$",
				"numeric-comma" => "^(\d+(, ?\d+)*)?$",
				"css"           => "^(([a-zA-Z-])+\ *\:[^;]+; *)*$"
			);
			$messages = array(
				"numeric-comma" => __( "incorrect format for field <strong>%s</strong>", 'SearchEverything' ),
				"color"         => __( "field <strong>%s</strong> should be a css color ('red' or '#abc123')", 'SearchEverything' ),
				"css"           => __( "field <strong>%s</strong> doesn't contain valid css", 'SearchEverything' )
			);
			
			// Init errors
			$errors = array();
			
			foreach ( $validation_rules as $field => $rule_name ) {
				$rule = $regex[ $rule_name ];
				if ( ! preg_match( "/$rule/", $_POST[ $field ] ) ) {
					$errors[ $field ] = $messages[ $rule_name ];
				}
			}
			
			return $errors;
		}
		
		/**
		 * Build admin interface
		 */
		function se_option_page() {
			global $wpdb, $table_prefix, $wp_version;
			
			if ( $_POST ) {
				check_admin_referer( 'se-everything-nonce' );
				
				$errors = $this->se_validation( array(
					"highlight_color"         => "color",
					"highlight_style"         => "css",
					"exclude_categories_list" => "numeric-comma",
					"exclude_posts_list"      => "numeric-comma"
				) );
				
				if ( $errors ) {
					$fields = array(
						"highlight_color"         => __( 'Highlight Background Color', 'SearchEverything' ),
						"highlight_style"         => __( 'Full Highlight Style', 'SearchEverything' ),
						"exclude_categories_list" => __( 'Exclude Categories', 'SearchEverything' ),
						"exclude_posts_list"      => __( 'Exclude some post or page IDs', 'SearchEverything' )
					);
					include( se_get_view( 'options_page_errors' ) );
					
					return;
				}
			}
			
			$new_options = array(
				'se_exclude_categories'      => ( isset( $_POST['exclude_categories'] ) && ! empty( $_POST['exclude_categories'] ) ) ? $_POST['exclude_categories'] : '',
				'se_exclude_categories_list' => ( isset( $_POST['exclude_categories_list'] ) && ! empty( $_POST['exclude_categories_list'] ) ) ? $_POST['exclude_categories_list'] : '',
				'se_exclude_posts'           => ( isset( $_POST['exclude_posts'] ) ) ? $_POST['exclude_posts'] : '',
				'se_exclude_posts_list'      => ( isset( $_POST['exclude_posts_list'] ) && ! empty( $_POST['exclude_posts_list'] ) ) ? $_POST['exclude_posts_list'] : '',
				'se_use_page_search'         => ( isset( $_POST['search_pages'] ) && $_POST['search_pages'] ),
				'se_use_comment_search'      => ( isset( $_POST['search_comments'] ) && $_POST['search_comments'] ),
				'se_use_tag_search'          => ( isset( $_POST['search_tags'] ) && $_POST['search_tags'] ),
				'se_use_tax_search'          => ( isset( $_POST['search_taxonomies'] ) && $_POST['search_taxonomies'] ),
				'se_use_category_search'     => ( isset( $_POST['search_categories'] ) && $_POST['search_categories'] ),
				'se_approved_comments_only'  => ( isset( $_POST['appvd_comments'] ) && $_POST['appvd_comments'] ),
				'se_approved_pages_only'     => ( isset( $_POST['appvd_pages'] ) && $_POST['appvd_pages'] ),
				'se_use_excerpt_search'      => ( isset( $_POST['search_excerpt'] ) && $_POST['search_excerpt'] ),
				'se_use_draft_search'        => ( isset( $_POST['search_drafts'] ) && $_POST['search_drafts'] ),
				'se_use_attachment_search'   => ( isset( $_POST['search_attachments'] ) && $_POST['search_attachments'] ),
				'se_use_authors'             => ( isset( $_POST['search_authors'] ) && $_POST['search_authors'] ),
				'se_use_cmt_authors'         => ( isset( $_POST['search_cmt_authors'] ) && $_POST['search_cmt_authors'] ),
				'se_use_metadata_search'     => ( isset( $_POST['search_metadata'] ) && $_POST['search_metadata'] ),
				'se_use_highlight'           => ( isset( $_POST['search_highlight'] ) && $_POST['search_highlight'] ),
				'se_highlight_color'         => ( isset( $_POST['highlight_color'] ) ) ? $_POST['highlight_color'] : '',
				'se_highlight_style'         => ( isset( $_POST['highlight_style'] ) ) ? $_POST['highlight_style'] : '',
			);
			
			// Save method
			if ( isset( $_POST['action'] ) && $_POST['action'] == "save" ) {
				echo "<div class=\"updated fade\" id=\"limitcatsupdatenotice\"><p>" . __( 'Your default search settings have been <strong>updated</strong> by Search Everything. </p><p> What are you waiting for? Go check out the new search results!', 'SearchEverything' ) . "</p></div>";
				se_update_options( $new_options );
			}
			
			// Reset method
			if ( isset( $_POST['action'] ) && $_POST['action'] == "reset" ) {
				echo "<div class=\"updated fade\" id=\"limitcatsupdatenotice\"><p>" . __( 'Your default search settings have been <strong>updated</strong> by Search Everything. </p><p> What are you waiting for? Go check out the new search results!', 'SearchEverything' ) . "</p></div>";
				$default_options = se_get_default_options();
				
				se_update_options( $default_options );
			}
			
			$options = se_get_options();
			$meta    = se_get_meta();
			
			include( se_get_view( 'options_page' ) );
			
		}
		
		/**
		 * Options page admin notice
		 */
		function se_options_page_notice() {
			$screen = get_current_screen();
			
			if ( 'settings_page_extend_search' == $screen->id ) {
				$close_url = admin_url( $screen->parent_file );
				$close_url = add_query_arg( array(
					'page'      => 'extend_search',
					'se_notice' => 0,
				), $close_url );
				
				include( se_get_view( 'options_page_notice' ) );
			}
		}
	}
