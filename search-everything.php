<?php
	/*
	Plugin Name: Search Everything
	Plugin URI: http://wordpress.org/plugins/search-everything/
	Description: Adds search functionality without modifying any template pages: Activate, Configure and Search. Options Include: search highlight, search pages, excerpts, attachments, drafts, comments, tags and custom fields (metadata). Also offers the ability to exclude specific pages and posts. Does not search password-protected content.
	Version: 8.3.1
	Author: Julian Troeps, Sovrn, zemanta
	Author URI: https://www.juliantroeps.com
	*/
	
	/**
	 * Search Everything
	 * Plugin main file
	 *
	 * @version 8.3.1
	 * @package Search Everything
	 */
	
	// Define the plugin version
	define( 'SE_VERSION', '8.3.1' );
	
	// Plugin file constant
	if ( ! defined( 'SE_PLUGIN_FILE' ) ) {
		define( 'SE_PLUGIN_FILE', plugin_basename( __FILE__ ) );
	}
	
	// Plugin name constant
	if ( ! defined( 'SE_PLUGIN_NAME' ) ) {
		define( 'SE_PLUGIN_NAME', trim( dirname( plugin_basename( __FILE__ ) ), '/' ) );
	}
	
	// Plugin directory constant
	if ( ! defined( 'SE_PLUGIN_DIR' ) ) {
		define( 'SE_PLUGIN_DIR', WP_PLUGIN_DIR . '/' . SE_PLUGIN_NAME );
	}
	
	// Plugin url constant
	if ( ! defined( 'SE_PLUGIN_URL' ) ) {
		define( 'SE_PLUGIN_URL', plugins_url() . '/' . SE_PLUGIN_NAME );
	}
	
	include_once( SE_PLUGIN_DIR . '/config.php' );
	include_once( SE_PLUGIN_DIR . '/options.php' );
	
	/**
	 * Initialize the plugin class
	 */
	function se_initialize_plugin() {
		$SE = new Search_Everything();
	}
	
	add_action( 'wp_loaded', 'se_initialize_plugin' );
	
	/**
	 * Helper function to get a certain view
	 *
	 * @param $view
	 *
	 * @return string
	 */
	function se_get_view( $view ) {
		return SE_PLUGIN_DIR . "/views/$view.php";
	}
	
	/**
	 * Global notices
	 */
	function se_global_notice() {
		global $pagenow, $se_global_notice_pages;
		
		// Diplay notice only for admins
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		
		// Get the plugins meta data
		$se_meta = se_get_meta();
		
		// Closing url
		$close_url = admin_url( 'options-general.php' );
		
		// Update the url with query args
		$close_url = add_query_arg( array( 'page' => 'extend_search', 'se_global_notice' => 0, ), $close_url );
		
		// Check if notice is present
		$show_notice = isset( $se_meta['se_global_notice'] ) && $se_meta['se_global_notice'];
		
		// Show notice template
		if ( $show_notice && in_array( $pagenow, $se_global_notice_pages ) ) {
			include( se_get_view( 'global_notice' ) );
		}
	}
	
	add_action( 'all_admin_notices', 'se_global_notice' );
	
	/**
	 *
	 */
	class Search_Everything {
		
		var $logging = false;
		var $options;
		var $wp_ver23;
		var $wp_ver25;
		var $wp_ver28;
		var $ajax_request;
		private $query_instance;
		
		function __construct( $ajax_query = false ) {
			global $wp_version;
			
			// Ajax request
			$this->ajax_request = (bool) $ajax_query;
			
			// Version checker (backwards compatibility)
			$this->wp_ver23 = ( $wp_version >= '2.3' );
			$this->wp_ver25 = ( $wp_version >= '2.5' );
			$this->wp_ver28 = ( $wp_version >= '2.8' );
			
			// Get the options
			$this->options = se_get_options();
			
			// Initialize instance based on setting
			if ( $this->ajax_request ) {
				$this->init_ajax( $ajax_query );
			}
			else {
				$this->init();
			}
		}
		
		/**
		 * Initilaize ajax request
		 *
		 * @param $query
		 */
		function init_ajax( $query ) {
			$this->search_hooks();
		}
		
		/**
		 * Init function
		 *
		 * @return bool|void
		 */
		function init() {
			// Check if user is admin
			if ( current_user_can( 'manage_options' ) ) {
				$SEAdmin = new Search_Everything_Admin();
			}
			
			// Disable Search-Everything, because posts_join is not working properly
			// in Wordpress-backend's Ajax functions (for example in wp_link_query from
			// compose screen (article search when inserting links))
			if ( basename( $_SERVER["SCRIPT_NAME"] ) == "admin-ajax.php" ) {
				return true;
			}
			
			// Invoke search hookis
			$this->search_hooks();
			
			// Highlight content
			if ( $this->options['se_use_highlight'] ) {
				add_filter( 'the_content', array( $this, 'se_postfilter' ), 11 );
				add_filter( 'the_title', array( $this, 'se_postfilter' ), 11 );
				add_filter( 'the_excerpt', array( $this, 'se_postfilter' ), 11 );
			}
		}
		
		/**
		 * Search Everything hooks
		 */
		function search_hooks() {
			// Add filters based upon option settings
			if ( $this->options['se_use_tag_search'] || $this->options['se_use_category_search'] || $this->options['se_use_tax_search'] ) {
				
				// Add filter for posts_joins
				add_filter( 'posts_join', array( $this, 'se_terms_join' ) );
				
				// Log tag search
				if ( $this->options['se_use_tag_search'] ) {
					$this->se_log( "searching tags" );
				}
				
				// Log category search
				if ( $this->options['se_use_category_search'] ) {
					$this->se_log( "searching categories" );
				}
				
				// Log taxonomy search
				if ( $this->options['se_use_tax_search'] ) {
					$this->se_log( "searching custom taxonomies" );
				}
			}
			
			// Page search
			if ( $this->options['se_use_page_search'] ) {
				// Add filter
				add_filter( 'posts_where', array( $this, 'se_search_pages' ) );#
				
				// Log page search
				$this->se_log( "searching pages" );
			}
			
			// Excerpt search @todo - the heck?
			if ( $this->options['se_use_excerpt_search'] ) {
				// Log page search
				$this->se_log( "searching excerpts" );
			}
			
			// Comment search
			if ( $this->options['se_use_comment_search'] ) {
				// Add filter
				add_filter( 'posts_join', array( $this, 'se_comments_join' ) );
				
				// Log comment search
				$this->se_log( "searching comments" );
				
				// Highlight content
				if ( $this->options['se_use_highlight'] ) {
					add_filter( 'comment_text', array( $this, 'se_postfilter' ) );
				}
			}
			
			// Draft search
			if ( $this->options['se_use_draft_search'] ) {
				// Add filter
				add_filter( 'posts_where', array( $this, 'se_search_draft_posts' ) );
				
				// Log draft search
				$this->se_log( "searching drafts" );
			}
			
			// Attachement search
			if ( $this->options['se_use_attachment_search'] ) {
				// Add filter
				add_filter( 'posts_where', array( $this, 'se_search_attachments' ) );
				
				// Log attachement search
				$this->se_log( "searching attachments" );
			}
			
			// Meta search
			if ( $this->options['se_use_metadata_search'] ) {
				// Add filter
				add_filter( 'posts_join', array( $this, 'se_search_metadata_join' ) );
				
				// Log meta search
				$this->se_log( "searching metadata" );
			}
			
			// Excluding posts
			if ( $this->options['se_exclude_posts_list'] != '' ) {
				// Log excluding posts
				$this->se_log( "searching excluding posts" );
			}
			
			// Excluding categories
			if ( $this->options['se_exclude_categories_list'] != '' ) {
				// Add filter
				add_filter( 'posts_join', array( $this, 'se_exclude_categories_join' ) );
				
				// Log excluding categories search
				$this->se_log( "searching excluding categories" );
			}
			
			// Author search
			if ( $this->options['se_use_authors'] ) {
				// Add filter
				add_filter( 'posts_join', array( $this, 'se_search_authors_join' ) );
				
				// Log author search
				$this->se_log( "searching authors" );
			}
			
			// Add filter for search_where
			add_filter( 'posts_search', array( $this, 'se_search_where' ), 10, 2 );
			
			// Add filter for no_revisions
			add_filter( 'posts_where', array( $this, 'se_no_revisions' ) );
			
			// Add filter for destinct search
			add_filter( 'posts_request', array( $this, 'se_distinct' ) );
			
			// Add filter for no_future
			add_filter( 'posts_where', array( $this, 'se_no_future' ) );
			
			// Add filter for query log
			add_filter( 'posts_request', array( $this, 'se_log_query' ), 10, 2 );
		}
		
		/**
		 * Creates the list of search
		 * keywords from the 's' parameters.
		 *
		 * @return array
		 */
		function se_get_search_terms() {
			// Search query
			$s = isset( $this->query_instance->query_vars['s'] ) ? $this->query_instance->query_vars['s'] : '';
			
			// Sentence
			$sentence = isset( $this->query_instance->query_vars['sentence'] ) && $this->query_instance->query_vars['sentence'];
			
			// Init variables
			$search_terms = array();
			
			if ( ! empty( $s ) ) {
				// added slashes screw with quote grouping when done early, so done later
				$s = stripslashes( $s );
				
				// Check if sentence
				if ( $sentence ) {
					$search_terms = array( $s );
				}
				else {
					preg_match_all( '/".*?("|$)|((?<=[\\s",+])|^)[^\\s",+]+/', $s, $matches );
					
					// Filter the array and remove new lines
					$search_terms = array_filter( array_map( function ( $a ) { return trim( $a, "\"'\n\r " ); }, $matches[0] ) );
				}
			}
			
			return $search_terms;
		}
		
		/**
		 * Add where clause to the search query
		 *
		 * @param $where
		 * @param $wp_query
		 *
		 * @return mixed|string
		 */
		function se_search_where( $where, $wp_query ) {
			// Return if not search
			if ( ! $wp_query->is_search() && ! $this->ajax_request ) {
				return $where;
			}
			
			// Set the wp query instance
			$this->query_instance = &$wp_query;
			
			// Set the search query
			$search_query = $this->se_search_default();
			
			//add filters based upon option settings
			if ( $this->options['se_use_tag_search'] ) {
				$search_query .= $this->se_build_search_tag();
			}
			
			if ( $this->options['se_use_category_search'] || $this->options['se_use_tax_search'] ) {
				$search_query .= $this->se_build_search_categories();
			}
			
			if ( $this->options['se_use_metadata_search'] ) {
				$search_query .= $this->se_build_search_metadata();
			}
			
			if ( $this->options['se_use_excerpt_search'] ) {
				$search_query .= $this->se_build_search_excerpt();
			}
			
			if ( $this->options['se_use_comment_search'] ) {
				$search_query .= $this->se_build_search_comments();
			}
			
			if ( $this->options['se_use_authors'] ) {
				$search_query .= $this->se_search_authors();
			}
			
			if ( $search_query != '' ) {
				// Let's use _OUR_ query instead of WP's, as we have posts already included in our query as well(assuming it's not empty which we check for)
				$where = " AND ((" . $search_query . ")) ";
			}
			
			// Remove excluded posts
			if ( $this->options['se_exclude_posts_list'] != '' ) {
				$where .= $this->se_build_exclude_posts();
			}
			
			// Remove excluded categories
			if ( $this->options['se_exclude_categories_list'] != '' ) {
				$where .= $this->se_build_exclude_categories();
			}
			
			// Log
			$this->se_log( "global where: " . $where );
			
			return $where;
		}
		
		/**
		 * Search for terms in default locations
		 * like title and content replacing the old
		 * search terms seems to be the best way to
		 * avoid issue with multiple terms
		 *
		 * @return string
		 */
		function se_search_default() {
			global $wpdb;
			
			// Ceck for exact value
			$not_exact = empty( $this->query_instance->query_vars['exact'] );
			
			// Init variables
			$search_sql_query = '';
			$seperator        = '';
			
			// Get the terms
			$terms = $this->se_get_search_terms();
			
			// if it's not a sentance add other terms
			if ( count( $terms ) > 0 ) {
				// Start the query string
				$search_sql_query .= '(';
				
				// Loop through terms
				foreach ( $terms as $term ) {
					$search_sql_query .= $seperator;
					
					$esc_term = $wpdb->prepare( "%s", $not_exact ? "%" . $term . "%" : $term );
					
					$like_title = "($wpdb->posts.post_title LIKE $esc_term)";
					$like_post  = "($wpdb->posts.post_content LIKE $esc_term)";
					
					$search_sql_query .= "($like_title OR $like_post)";
					
					$seperator = ' AND ';
				}
				
				// End the query string
				$search_sql_query .= ')';
			}
			
			// Return the query string
			return $search_sql_query;
		}
		
		/**
		 * Exclude post revisions
		 *
		 * @param $where
		 *
		 * @return mixed|string
		 */
		function se_no_revisions( $where ) {
			global $wpdb;
			
			if ( ! empty( $this->query_instance->query_vars['s'] ) ) {
				if ( ! $this->wp_ver28 ) {
					$where = 'AND (' . substr( $where, strpos( $where, 'AND' ) + 3 ) . ") AND $wpdb->posts.post_type != 'revision'";
				}
				
				$where = ' AND (' . substr( $where, strpos( $where, 'AND' ) + 3 ) . ') AND post_type != \'revision\'';
			}
			
			return $where;
		}
		
		/**
		 * Exclude future posts fix provided by Mx
		 *
		 * @param $where
		 *
		 * @return mixed|string
		 */
		function se_no_future( $where ) {
			global $wpdb;
			
			if ( ! empty( $this->query_instance->query_vars['s'] ) ) {
				if ( ! $this->wp_ver28 ) {
					$where = 'AND (' . substr( $where, strpos( $where, 'AND' ) + 3 ) . ") AND $wpdb->posts.post_status != 'future'";
				}
				
				$where = 'AND (' . substr( $where, strpos( $where, 'AND' ) + 3 ) . ') AND post_status != \'future\'';
			}
			
			return $where;
		}
		
		/**
		 * Logs search into a file
		 *
		 * @param $msg
		 *
		 * @return bool
		 */
		function se_log( $msg ) {
			if ( $this->logging ) {
				// Get the log file
				$fp = fopen( SE_PLUGIN_DIR . "logfile.log", "a+" );
				
				// Error if writing not possible
				if ( ! $fp ) {
					_e( 'Unable to write to log file!', 'SearchEverything' );
				}
				
				// get the date
				$date = date( "Y-m-d H:i:s " );
				
				// Source string
				$source = "search_everything plugin: ";
				
				// Write the log entry
				fwrite( $fp, "\n\n" . $date . "\n" . $source . "\n" . $msg );
				
				// Close the file
				fclose( $fp );
			}
			
			return true;
		}
		
		/**
		 * Duplicate fix provided by Tiago.Pocinho
		 *
		 * @param $query
		 *
		 * @return array|mixed|string|string[]
		 */
		function se_distinct( $query ) {
			if ( ! empty( $this->query_instance->query_vars['s'] ) ) {
				if ( ! strstr( $query, 'DISTINCT' ) ) {
					$query = str_replace( 'SELECT', 'SELECT DISTINCT', $query );
				}
			}
			
			return $query;
		}
		
		/**
		 * Search pages (except password-protected pages provided by loops)
		 *
		 * @param $where
		 *
		 * @return array|mixed|string|string[]
		 */
		function se_search_pages( $where ) {
			if ( ! empty( $this->query_instance->query_vars['s'] ) ) {
				$where = str_replace( '"', '\'', $where );
				
				if ( $this->options['se_approved_pages_only'] ) {
					$where = str_replace( "post_type = 'post'", " AND 'post_password = '' AND ", $where );
				}
				else {
					$where = str_replace( 'post_type = \'post\' AND ', '', $where );
				}
			}
			
			// Log pages
			$this->se_log( "pages where: " . $where );
			
			return $where;
		}
		
		/**
		 * Create the search excerpts query
		 *
		 * @return string
		 */
		function se_build_search_excerpt() {
			global $wpdb;
			
			$vars = $this->query_instance->query_vars;
			
			// Get the search var
			$s = $vars['s'];
			
			// get the search terms
			$search_terms = $this->se_get_search_terms();
			
			// check if exact
			$exact = isset( $vars['exact'] ) ? $vars['exact'] : '';
			
			// Init variables
			$search = '';
			
			if ( ! empty( $search_terms ) ) {
				// Building search query
				$searchand = '';
				
				// Loop through terms
				foreach ( $search_terms as $term ) {
					// Prepeare the term
					$term = $wpdb->prepare( "%s", $exact ? $term : "%$term%" );
					
					// Search query part
					$search .= "{$searchand}($wpdb->posts.post_excerpt LIKE $term)";
					
					// Set the "AND"
					$searchand = ' AND ';
				}
				
				// Sentence term
				$sentence_term = $wpdb->prepare( "%s", $exact ? $s : "%$s%" );
				
				// Check result count
				if ( count( $search_terms ) > 1 && $search_terms[0] != $sentence_term ) {
					$search = "($search) OR ($wpdb->posts.post_excerpt LIKE $sentence_term)";
				}
				
				// Update with "OR"
				if ( ! empty( $search ) ) {
					$search = " OR ({$search}) ";
				}
			}
			
			return $search;
		}
		
		/**
		 * Search drafts
		 *
		 * @param $where
		 *
		 * @return array|mixed|string|string[]
		 */
		function se_search_draft_posts( $where ) {
			global $wpdb;
			
			if ( ! empty( $this->query_instance->query_vars['s'] ) ) {
				$where = str_replace( '"', '\'', $where );
				
				if ( ! $this->wp_ver28 ) {
					$where = str_replace( " AND (post_status = 'publish'", " AND ((post_status = 'publish' OR post_status = 'draft')", $where );
				}
				else {
					$where = str_replace( " AND ($wpdb->posts.post_status = 'publish'", " AND ($wpdb->posts.post_status = 'publish' OR $wpdb->posts.post_status = 'draft'", $where );
				}
				
				$where = str_replace( " AND (post_status = 'publish'", " AND (post_status = 'publish' OR post_status = 'draft'", $where );
			}
			
			// Log draft search
			$this->se_log( "drafts where: " . $where );
			
			return $where;
		}
		
		/**
		 * Search attachments
		 *
		 * @param $where
		 *
		 * @return array|mixed|string|string[]
		 */
		function se_search_attachments( $where ) {
			global $wpdb;
			
			if ( ! empty( $this->query_instance->query_vars['s'] ) ) {
				$where = str_replace( '"', '\'', $where );
				
				if ( ! $this->wp_ver28 ) {
					$where = str_replace( " AND (post_status = 'publish'", " AND (post_status = 'publish' OR post_type = 'attachment'", $where );
					$where = str_replace( "AND post_type != 'attachment'", "", $where );
				}
				else {
					$where = str_replace( " AND ($wpdb->posts.post_status = 'publish'", " AND ($wpdb->posts.post_status = 'publish' OR $wpdb->posts.post_type = 'attachment'", $where );
					$where = str_replace( "AND $wpdb->posts.post_type != 'attachment'", "", $where );
				}
			}
			
			// Log attachment search
			$this->se_log( "attachments where: " . $where );
			
			return $where;
		}
		
		/**
		 * Create the comments data query
		 *
		 * @return string
		 */
		function se_build_search_comments() {
			global $wpdb;
			
			// Get query cars
			$vars = $this->query_instance->query_vars;
			
			// Get the search var
			$s = $vars['s'];
			
			// get the search terms
			$search_terms = $this->se_get_search_terms();
			
			// check if exact
			$exact = isset( $vars['exact'] ) ? $vars['exact'] : '';
			
			// Init variables
			$search = '';
			
			if ( ! empty( $search_terms ) ) {
				// Building search query on comments content
				$searchand     = '';
				$searchContent = '';
				
				// Loop through terms
				foreach ( $search_terms as $term ) {
					$term = $wpdb->prepare( "%s", $exact ? $term : "%$term%" );
					
					if ( $this->wp_ver23 ) {
						$searchContent .= "{$searchand}(cmt.comment_content LIKE $term)";
					}
					
					$searchand = ' AND ';
				}
				
				$sentense_term = $wpdb->prepare( "%s", $s );
				
				if ( count( $search_terms ) > 1 && $search_terms[0] != $sentense_term ) {
					if ( $this->wp_ver23 ) {
						$searchContent = "($searchContent) OR (cmt.comment_content LIKE $sentense_term)";
					}
				}
				
				$search = $searchContent;
				
				// Building search query on comments author
				if ( $this->options['se_use_cmt_authors'] ) {
					$searchand      = '';
					$comment_author = '';
					
					foreach ( $search_terms as $term ) {
						$term = $wpdb->prepare( "%s", $exact ? $term : "%$term%" );
						if ( $this->wp_ver23 ) {
							$comment_author .= "{$searchand}(cmt.comment_author LIKE $term)";
						}
						$searchand = ' AND ';
					}
					
					$sentence_term = $wpdb->prepare( "%s", $s );
					
					if ( count( $search_terms ) > 1 && $search_terms[0] != $sentence_term ) {
						if ( $this->wp_ver23 ) {
							$comment_author = "($comment_author) OR (cmt.comment_author LIKE $sentence_term)";
						}
					}
					
					$search = "($search) OR ($comment_author)";
				}
				
				if ( $this->options['se_approved_comments_only'] ) {
					$comment_approved = "AND cmt.comment_approved =  '1'";
					$search           = "($search) $comment_approved";
				}
				
				if ( ! empty( $search ) ) {
					$search = " OR ({$search}) ";
				}
			}
			
			// Log comment search
			$this->se_log( "comments sql: " . $search );
			
			return $search;
		}
		
		/**
		 * Build the author search
		 *
		 * @return string
		 */
		function se_search_authors() {
			global $wpdb;
			
			// Get the search var
			$s = $this->query_instance->query_vars['s'];
			
			// get the search terms
			$search_terms = $this->se_get_search_terms();
			
			// check if exact
			$exact = isset( $this->query_instance->query_vars['exact'] ) && $this->query_instance->query_vars['exact'];
			
			// Init variables
			$search    = '';
			$searchand = '';
			
			if ( ! empty( $search_terms ) ) {
				// Building search query
				foreach ( $search_terms as $term ) {
					$term = $wpdb->prepare( "%s", $exact ? $term : "%$term%" );
					
					$search .= "{$searchand}(u.display_name LIKE $term)";
					
					$searchand = ' OR ';
				}
				$sentence_term = $wpdb->prepare( "%s", $s );
				if ( count( $search_terms ) > 1 && $search_terms[0] != $sentence_term ) {
					$search .= " OR (u.display_name LIKE $sentence_term)";
				}
				
				if ( ! empty( $search ) )
					$search = " OR ({$search}) ";
				
			}
			
			$this->se_log( "user where: " . $search );
			
			return $search;
		}
		
		/**
		 * Create the search metadata query
		 *
		 * @return string
		 */
		function se_build_search_metadata() {
			global $wpdb;
			
			// Get the search var
			$s = $this->query_instance->query_vars['s'];
			
			// get the search terms
			$search_terms = $this->se_get_search_terms();
			
			// check if exact
			$exact = isset( $this->query_instance->query_vars['exact'] ) && $this->query_instance->query_vars['exact'];
			
			// Init variables
			$search = '';
			
			if ( ! empty( $search_terms ) ) {
				// Building search query
				$searchand = '';
				
				foreach ( $search_terms as $term ) {
					$term = $wpdb->prepare( "%s", $exact ? $term : "%$term%" );
					
					if ( $this->wp_ver23 ) {
						$search .= "{$searchand}(m.meta_value LIKE $term)";
					}
					else {
						$search .= "{$searchand}(meta_value LIKE $term)";
					}
					
					$searchand = ' AND ';
				}
				
				$sentence_term = $wpdb->prepare( "%s", $s );
				
				if ( count( $search_terms ) > 1 && $search_terms[0] != $sentence_term ) {
					if ( $this->wp_ver23 ) {
						$search = "($search) OR (m.meta_value LIKE $sentence_term)";
					}
					else {
						$search = "($search) OR (meta_value LIKE $sentence_term)";
					}
				}
				
				if ( ! empty( $search ) ) {
					$search = " OR ({$search}) ";
				}
			}
			
			// Log meta search
			$this->se_log( "meta where: " . $search );
			
			return $search;
		}
		
		/**
		 * Create the search tag query
		 *
		 * @return string
		 */
		function se_build_search_tag() {
			global $wpdb;
			
			// Get query cars
			$vars = $this->query_instance->query_vars;
			
			// Get the search var
			$s = $vars['s'];
			
			// get the search terms
			$search_terms = $this->se_get_search_terms();
			
			// check if exact
			$exact = isset( $vars['exact'] ) ? $vars['exact'] : '';
			
			// Init variables
			$search = '';
			
			if ( ! empty( $search_terms ) ) {
				// Building search query
				$searchand = '';
				
				foreach ( $search_terms as $term ) {
					$term = $wpdb->prepare( "%s", $exact ? $term : "%$term%" );
					
					if ( $this->wp_ver23 ) {
						$search .= "{$searchand}(tter.name LIKE $term)";
					}
					
					$searchand = ' OR ';
				}
				
				$sentence_term = $wpdb->prepare( "%s", $s );
				
				if ( count( $search_terms ) > 1 && $search_terms[0] != $sentence_term ) {
					if ( $this->wp_ver23 ) {
						$search = "($search) OR (tter.name LIKE $sentence_term)";
					}
				}
				
				if ( ! empty( $search ) ) {
					$search = " OR ({$search}) ";
				}
			}
			
			// Log tag search
			$this->se_log( "tag where: " . $search );
			
			return $search;
		}
		
		/**
		 * Create the search categories query
		 *
		 * @return string
		 */
		function se_build_search_categories() {
			global $wpdb;
			
			// Get query cars
			$vars = $this->query_instance->query_vars;
			
			// Get the search var
			$s = $vars['s'];
			
			// get the search terms
			$search_terms = $this->se_get_search_terms();
			
			// check if exact
			$exact = isset( $vars['exact'] ) ? $vars['exact'] : '';
			
			// Init variables
			$search = '';
			
			if ( ! empty( $search_terms ) ) {
				// Building search query for categories slug.
				$searchand  = '';
				$searchSlug = '';
				$term       = null;
				
				foreach ( $search_terms as $term ) {
					$term       = $wpdb->prepare( "%s", $exact ? $term : "%" . sanitize_title_with_dashes( $term ) . "%" );
					$searchSlug .= "{$searchand}(tter.slug LIKE $term)";
					$searchand  = ' AND ';
				}
				
				$term = $wpdb->prepare( "%s", $exact ? $term : "%" . sanitize_title_with_dashes( $s ) . "%" );
				
				if ( count( $search_terms ) > 1 && $search_terms[0] != $s ) {
					$searchSlug = "($searchSlug) OR (tter.slug LIKE $term)";
				}
				if ( ! empty( $searchSlug ) ) {
					$search = " OR ({$searchSlug}) ";
				}
				
				// Building search query for categories description.
				$searchand  = '';
				$searchDesc = '';
				
				foreach ( $search_terms as $term ) {
					$term       = $wpdb->prepare( "%s", $exact ? $term : "%$term%" );
					$searchDesc .= "{$searchand}(ttax.description LIKE $term)";
					$searchand  = ' AND ';
				}
				
				$sentence_term = $wpdb->prepare( "%s", $s );
				
				if ( count( $search_terms ) > 1 && $search_terms[0] != $sentence_term ) {
					$searchDesc = "($searchDesc) OR (ttax.description LIKE $sentence_term)";
				}
				
				if ( ! empty( $searchDesc ) ) {
					$search = $search . " OR ({$searchDesc}) ";
				}
			}
			
			// Log categories search
			$this->se_log( "categories where: " . $search );
			
			return $search;
		}
		
		/**
		 * Create the posts' exclusion query
		 *
		 * @return string
		 */
		function se_build_exclude_posts() {
			global $wpdb;
			
			$exclude_query = '';
			
			if ( ! empty( $this->query_instance->query_vars['s'] ) ) {
				// Get list from settings
				$excluded_post_list = trim( $this->options['se_exclude_posts_list'] );
				
				if ( $excluded_post_list != '' ) {
					$excluded_post_list = array();
					
					foreach ( explode( ',', (string) $excluded_post_list ) as $post_id ) {
						$excluded_post_list[] = (int) $post_id;
					}
					
					// Create comma-separated string
					$excl_list = implode( ',', $excluded_post_list );
					
					// Update query string
					$exclude_query = ' AND (' . $wpdb->posts . '.ID NOT IN ( ' . $excl_list . ' ))';
				}
				
				// Log exclude query
				$this->se_log( "ex posts where: " . $exclude_query );
			}
			
			return $exclude_query;
		}
		
		/**
		 * Create the categories' exclusion query
		 *
		 * @return string
		 */
		function se_build_exclude_categories() {
			
			$exclude_query = '';
			
			if ( ! empty( $this->query_instance->query_vars['s'] ) ) {
				// Ge the cats from the settings
				$excluded_cat_list = trim( $this->options['se_exclude_categories_list'] );
				
				if ( $excluded_cat_list != '' ) {
					$excluded_cat_list = array();
					
					foreach ( explode( ',', (string) $excluded_cat_list ) as $cat_id ) {
						$excluded_cat_list[] = (int) $cat_id;
					}
					
					// Create comma-separated string
					$excl_list = implode( ',', $excluded_cat_list );
					
					if ( $this->wp_ver23 ) {
						$exclude_query = " AND ( ctax.term_id NOT IN ( " . $excl_list . " ) OR (wp_posts.post_type IN ( 'page' )))";
					}
					else {
						$exclude_query = ' AND (c.category_id NOT IN ( ' . $excl_list . ' ) OR (wp_posts.post_type IN ( \'page\' )))';
					}
				}
				
				// Log category exclusion
				$this->se_log( "ex category where: " . $exclude_query );
			}
			
			return $exclude_query;
		}
		
		/**
		 * Join for excluding categories - Deprecated in 2.3
		 *
		 * @param $join
		 *
		 * @return mixed|string
		 */
		function se_exclude_categories_join( $join ) {
			global $wpdb;
			
			if ( ! empty( $this->query_instance->query_vars['s'] ) ) {
				
				if ( $this->wp_ver23 ) {
					$join .= " LEFT JOIN $wpdb->term_relationships AS crel ON ($wpdb->posts.ID = crel.object_id) LEFT JOIN $wpdb->term_taxonomy AS ctax ON (ctax.taxonomy = 'category' AND crel.term_taxonomy_id = ctax.term_taxonomy_id) LEFT JOIN $wpdb->terms AS cter ON (ctax.term_id = cter.term_id) ";
				}
				else {
					$join .= "LEFT JOIN $wpdb->post2cat AS c ON $wpdb->posts.ID = c.post_id";
				}
			}
			
			// Log categories join
			$this->se_log( "category join: " . $join );
			
			return $join;
		}
		
		/**
		 * Join for searching comments
		 *
		 * @param $join
		 *
		 * @return mixed|string
		 */
		function se_comments_join( $join ) {
			global $wpdb;
			
			if ( ! empty( $this->query_instance->query_vars['s'] ) ) {
				if ( $this->wp_ver23 ) {
					$join .= " LEFT JOIN $wpdb->comments AS cmt ON ( cmt.comment_post_ID = $wpdb->posts.ID ) ";
				}
				else {
					if ( $this->options['se_approved_comments_only'] ) {
						$comment_approved = " AND comment_approved =  '1'";
					}
					else {
						$comment_approved = '';
					}
					
					$join .= "LEFT JOIN $wpdb->comments ON ( comment_post_ID = ID " . $comment_approved . ") ";
				}
			}
			
			// Log comments join
			$this->se_log( "comments join: " . $join );
			
			return $join;
		}
		
		/**
		 * Join for searching authors
		 *
		 * @param $join
		 *
		 * @return mixed|string
		 */
		function se_search_authors_join( $join ) {
			global $wpdb;
			
			if ( ! empty( $this->query_instance->query_vars['s'] ) ) {
				$join .= " LEFT JOIN $wpdb->users AS u ON ($wpdb->posts.post_author = u.ID) ";
			}
			
			// Log author join
			$this->se_log( "authors join: " . $join );
			
			return $join;
		}
		
		/**
		 * Join for searching metadata
		 *
		 * @param $join
		 *
		 * @return mixed|string
		 */
		function se_search_metadata_join( $join ) {
			global $wpdb;
			
			// Check if search term is present
			if ( ! empty( $this->query_instance->query_vars['s'] ) ) {
				
				if ( $this->wp_ver23 ) {
					$join .= " LEFT JOIN $wpdb->postmeta AS m ON ($wpdb->posts.ID = m.post_id) ";
				}
				else {
					$join .= " LEFT JOIN $wpdb->postmeta ON $wpdb->posts.ID = $wpdb->postmeta.post_id ";
				}
			}
			
			// Log
			$this->se_log( "metadata join: " . $join );
			
			return $join;
		}
		
		/**
		 * Join for searching tags
		 *
		 * @param $join
		 *
		 * @return mixed|string
		 */
		function se_terms_join( $join ) {
			global $wpdb;
			
			// Check if search term is present
			if ( ! empty( $this->query_instance->query_vars['s'] ) ) {
				// Define variables
				$on = array();
				
				// Category Search
				if ( $this->options['se_use_category_search'] ) {
					$on[] = "ttax.taxonomy = 'category'";
				}
				
				// Tag Search
				if ( $this->options['se_use_tag_search'] ) {
					$on[] = "ttax.taxonomy = 'post_tag'";
				}
				
				// Custom Taxonomy Search
				if ( $this->options['se_use_tax_search'] ) {
					// Get all taxonomies
					$all_taxonomies = get_taxonomies();
					
					// Filter WordPress taxonomies
					$filter_taxonomies = array( 'post_tag', 'category', 'nav_menu', 'link_category' );
					
					// Loop throught teaxonomies
					foreach ( $all_taxonomies as $taxonomy ) {
						if ( in_array( $taxonomy, $filter_taxonomies ) ) {
							continue;
						}
						
						// Add to the SQL query string
						$on[] = "ttax.taxonomy = '" . addslashes( $taxonomy ) . "'";
					}
				}
				
				// Build our final string
				$on = ' ( ' . implode( ' OR ', $on ) . ' ) ';
				
				// Update SQL query
				$join .= " LEFT JOIN $wpdb->term_relationships AS trel ON ($wpdb->posts.ID = trel.object_id) LEFT JOIN $wpdb->term_taxonomy AS ttax ON ( " . $on . " AND trel.term_taxonomy_id = ttax.term_taxonomy_id) LEFT JOIN $wpdb->terms AS tter ON (ttax.term_id = tter.term_id) ";
			}
			
			// Log query
			$this->se_log( "tags join: " . $join );
			
			// Return SQL
			return $join;
		}
		
		/**
		 * Highlight the searched terms into Title,
		 * excerpt and content in the search result page.
		 *
		 * @param $postcontent
		 *
		 * @return array|mixed|string|string[]|null
		 */
		function se_postfilter( $postcontent ) {
			// Get the search var
			$search = isset( $this->query_instance->query_vars['s'] ) ? $this->query_instance->query_vars['s'] : '';
			
			if ( ! is_admin() && is_search() && $search != '' ) {
				// Get the hihlight options
				$highlight_color = $this->options['se_highlight_color'];
				$highlight_style = $this->options['se_highlight_style'];
				
				// Get the search terms
				$search_terms = $this->se_get_search_terms();
				
				// Loop through the search terms
				foreach ( $search_terms as $term ) {
					// Don't try to highlight this one
					if ( preg_match( '/\>/', $term ) ) {
						continue;
					}
					
					//  Quote regular expression characters
					$term = preg_quote( $term );
					
					// Highlight the search term if checked
					if ( $highlight_color != '' ) {
						$postcontent = preg_replace( '"(?<!\<)(?<!\w)(\pL*' . $term . '\pL*)(?!\w|[^<>]*>)"iu', '<span class="search-everything-highlight-color" style="background-color:' . $highlight_color . '">$1</span>', $postcontent );
					}
					else {
						$postcontent = preg_replace( '"(?<!\<)(?<!\w)(\pL*' . $term . '\pL*)(?!\w|[^<>]*>)"iu', '<span class="search-everything-highlight" style="' . $highlight_style . '">$1</span>', $postcontent );
					}
				}
			}
			
			return $postcontent;
		}
		
		function se_log_query( $query, $wp_query ) {
			if ( $wp_query->is_search )
				$this->se_log( $query );
			
			return $query;
		}
	}
	
	/**
	 * Callback for the ajay search above
	 */
	function search_everything_callback() {
		// Get the search query
		$s = $_GET['s'];
		
		// Check if empty
		$is_query = ! empty( $_GET['s'] );
		
		// Init variables
		$result = array();
		
		if ( $is_query ) {
			// Init results
			$result = array( 'own' => array() );
			
			// Set params
			$params = array( 's' => $s );
			
			// Create new SE instance
			$SE = new Search_Everything( true );
			
			// Check for exact
			if ( ! empty( $_GET['exact'] ) ) {
				$params['exact'] = true;
			}
			
			// Set post count
			$params["showposts"] = 5;
			
			// Prepare query
			$post_query = new WP_Query( $params );
			
			// Loop through the results
			while ( $post_query->have_posts() ) {
				$post_query->the_post();
				
				$result['own'][] = get_post();
			}
			
			// Reset post data
			$post_query->reset_postdata();
		}
		
		// Return the JSON
		print json_encode( $result );
		
		// End here
		die();
	}
	
	add_action( 'wp_ajax_search_everything', 'search_everything_callback' );
