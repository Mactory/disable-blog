<?php
/**
 * Plugin Name: Disable WordPress Blog
 * Plugin URI: http://joshuadnelson.com
 * Description: A plugin that disables or hides all blog-related elements of your WordPress site.
 * Version: 0.2.0
 * Author: Joshua Nelson
 * Author URI: http://joshuadnelson.com
 * GitHub Plugin URI: https://github.com/joshuadavidnelson/disable-wordpress-blog
 * GitHub Branch: master
 * License: GPL v2.0
 */

/**
 * Prevent direct access to this file.
 *
 * @since 0.1.0
 */
if( !defined( 'ABSPATH' ) ) {
	exit( 'You are not allowed to access this file directly.' );
}

/**
 * Define Constants
 *
 * @since 0.1.0
 */
// For includes and whatnot
if( !defined( 'DWPB_DIR' ) )
	define( 'DWPB_DIR', dirname( __FILE__ ) );

// For calling scripts and so forth
if( !defined( 'DWPB_URL' ) )
	define( 'DWPB_URL', plugins_url( '/' , __FILE__ ) );

// For internationalization
if( !defined( 'DWPB_DOMAIN' ) )
	define( 'DWPB_DOMAIN', 'disable-wordpress-blog' );
	
// To keep track of versions, useful if you need to make updates specific to versions
define( 'DWPB_VERSION', '0.2.0' );

/**
 * Main Plugin Class
 *
 * @since 0.1.0
 */
global $_disable_wordpress_blog;
$_disable_wordpress_blog = new Disable_WordPress_Blog;
class Disable_WordPress_Blog {

	/**
	 * Build the class
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		
		// Plugin Base
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}
	
	/**
	 * Make it so!
	 *
	 * @since 0.1.0
	 */
	public function init() {
		
		// Hooks are useful, here's one
		do_action( 'dwpb_init' );
		
		// Hide Posts Page from Admin Menu
		add_action( 'admin_menu', array( $this, 'remove_menu_pages' ) );
		
		// Disable Feed
		add_action('do_feed', array( $this, 'disable_feed' ), 1);
		add_action('do_feed_rdf', array( $this, 'disable_feed' ), 1);
		add_action('do_feed_rss', array( $this, 'disable_feed' ), 1);
		add_action('do_feed_rss2', array( $this, 'disable_feed' ), 1);
		add_action('do_feed_atom', array( $this, 'disable_feed' ), 1);
		
		// Redirection Admin Page
		add_action( 'admin_init', array( $this, 'redirect_admin_pages' ) );
		
		// Remove Admin Bar Links
		add_action( 'wp_before_admin_bar_render', array( $this, 'remove_admin_bar_links' ) );
		
		// Filter Comments off Admin Page
		add_action( 'pre_get_comments', array( $this, 'comment_filter' ), 10, 1 );
		
		// Remove Dashboard Widgets
		add_action( 'admin_init', array( $this, 'remove_dashboard_widgets' ) );
		
		// Hide items with CSS
		add_action( 'admin_head', array( $this, 'admin_styles' ) );
		
		// Force Reading Settings
		add_action( 'admin_init', array( $this, 'reading_settings' ) );
		
		// Remove Post via Email Settings
		add_filter( 'enable_post_by_email_configuration', '__return_false' );
		
		// Disable Press This Function
		add_action( 'load-press-this.php', array( $this, 'disable_press_this' ) );
		
		// Remove Post Related Widgets
		add_action( 'widgets_init', array( $this, 'remove_widgets' ) );
	}
	
	/**
	 * Remove Post Related Menus
	 *
	 * @since 0.1.0
	 * @link http://wordpress.stackexchange.com/questions/57464/remove-posts-from-admin-but-show-a-custom-post
	 */
	public function remove_menu_pages() {
		if( apply_filters( 'dwpb_remove_menu_pages', true ) ) {
			$pages = apply_filters( 'dwpb_menu_pages_to_remove', array( 'edit.php' ) );
			foreach( $pages as $page ) {
				remove_menu_page( $page );
			}
			$subpages = apply_filters( 'dwpb_menu_pages_to_remove', array( 'options-general.php' => 'options-writing.php' ) );
			foreach( $subpages as $page => $subpage ) {
				remove_submenu_page( $page, $subpage );
			}
		}
	}
	
	/**
	 * Disable Blog feed
	 *
	 * @since 0.1.0
	 * @link http://wpengineer.com/287/disable-wordpress-feed/
	 */
	public function disable_feed() {
		global $post;
		if( apply_filters( 'dwpb_disable_feed', true, $post ) ) {
			if( $post->post_type == 'post' ) {
				$url = home_url();
				$message = apply_filters( 'dwpb_feed_die_message', __('No feed available, please visit our <a href="'. $url .'">homepage</a>!') );
				wp_die( $message );
			}
		}
	}
	
	/**
	 * Redirect blog-related admin pages
	 *
	 * @since 0.1.0
	 * @link http://wordpress.stackexchange.com/questions/52114/admin-page-redirect
	 */
	public function redirect_admin_pages() {
		global $pagenow;
		
		if( !isset( $pagenow ) ) {
			return;
		} elseif( apply_filters( 'dwpb_redirect_admin_pages', false, $pagenow ) ) {
			return;
		}
		
		// Redirect Edit Post to Edit Page
		if( $pagenow == 'edit.php' && ( !isset( $_GET['post_type'] ) || isset( $_GET['post_type'] ) && $_GET['post_type'] == 'post' ) ) {
			$url = admin_url( '/edit.php?post_type=page' );
			$redirect_url = apply_filters( 'dwpb_redirect_edit', $url );
			wp_redirect( $redirect_url, 301 );
			exit;
		}
		
		// Redirect New Post to New Page
		if( $pagenow == 'post-new.php' && ( !isset( $_GET['post_type'] ) || isset( $_GET['post_type'] ) && $_GET['post_type'] == 'post' ) ) {
			$url = admin_url('/post-new.php?post_type=page' );
			$redirect_url = apply_filters( 'dwpb_redirect_post_new', $url );
			wp_redirect( $redirect_url, 301 );
			exit;
		}
		
		// Redirect at edit tags screen
		// If this is a post type other than 'post' that supports categories or tags,
		// then bail. Otherwise it is a taxonomy only used by 'post'
		// Alternatively, if this is either the edit-tags page and a taxonomy is not set
		// and the built-in default 'post_tags' is not supported by other post types
		if( $pagenow == 'edit-tags.php' && ( ( isset( $_GET['taxonomy'] ) && ! $this->is_taxonomy_supported( $_GET['taxonomy'] ) ) || ( !isset( $_GET['taxonomy'] ) && ! $this->is_taxonomy_supported( 'post_tag' ) ) ) ) {
			$url = admin_url( '/index.php' );
			$redirect_url = apply_filters( 'dwpb_redirect_tax_edit', $url );
			wp_redirect( $redirect_url, 301 );
			exit;
		} 
		
		// Redirect posts-only comment queries to comments
		if( $pagenow == 'edit-comments.php' && isset( $_GET['post_type'] ) && $_GET['post_type'] == 'post' ) {
			$url = admin_url( '/edit-comments.php' );
			$redirect_url = apply_filters( 'dwpb_redirect_edit_comments', $url );
			wp_redirect( $redirect_url, 301 );
			exit;
		}
		
		// Redirect writing options to general options
		if( $pagenow == 'options-writing.php' ) {
			$url = admin_url( '/options-general.php' );
			$redirect_url = apply_filters( 'dwpb_redirect_options_writing', $url );
			wp_redirect( $redirect_url, 301 );
			exit;
		}
	}
	
	/**
	 * Remove blog-related admin bar links
	 *
	 * @since 0.1.0
	 * @link http://www.paulund.co.uk/how-to-remove-links-from-wordpress-admin-bar
	 */
	public function remove_admin_bar_links() {
		global $wp_admin_bar;
		
		// If only posts support comments, then remove comment from admin bar
		if( ! $this->post_types_with_feature( 'comments' ) )
		    $wp_admin_bar->remove_menu( 'comments' );
		
		// Remove New Post from Content
		$wp_admin_bar->remove_node( 'new-post' );
	}

	/**
	 * Hide all comments from 'post' post type
	 * 
	 * @since 0.1.0
	 * @param  (wp_query object) $comments
	 */
	public function comment_filter( $comments ){
		global $pagenow;
		
		if( !isset( $pagenow ) )
			return $comments;
		
		// Filter out comments from post
		if( is_admin() && $pagenow == 'edit-comments.php' ) {
			if( $post_types = $this->post_types_with_feature( 'comments' ) ) {
				$comments->query_vars['post_type'] = $post_types;
			}
		}
		
		return $comments;
	}
	
	/**
	 * Remove post-related dashboard widgets
	 *
	 * @since 0.1.0
	 * @link http://www.deluxeblogtips.com/2011/01/remove-dashboard-widgets-in-wordpress.html
	 */
	function remove_dashboard_widgets() {
		remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' ); // recent comments
		remove_meta_box( 'dashboard_incoming_links', 'dashboard', 'normal' );  // incoming links
		remove_meta_box( 'dashboard_quick_press', 'dashboard', 'normal' );  // quick press
		remove_meta_box( 'dashboard_recent_drafts', 'dashboard', 'normal' );  // recent drafts
		remove_meta_box( 'dashboard_activity', 'dashboard', 'normal' );  // recent drafts
	}
	
	/**
	 * Admin styles
	 * 
	 * Hides post and comment count on activity dashboard widget.
	 * 
	 * @since 0.1.0
	 */
	public function admin_styles() { ?>
		<style>
			#dashboard_right_now .post-count,
			#dashboard_right_now .comment-count,
			.nav-menus-php label[for="add-post-hide"],
			.control-section.add-post,
			.options-reading-php table.form-table tr {
			    display: none;
			}
			.options-reading-php table.form-table tr:first-child,
			.options-reading-php table.form-table tr.option-site-visibility {
				display: block;
			}
		</style>
		<?php
	}
	
	/**
	 * Get all the post types that support comments
	 * 
	 * @since 0.2.0
	 * @return array ( $post_types | bolean )
	 */
	public function disable_press_this() {
		wp_die( '"Press This" functionality has been disabled.' );
	}
	
	/**
	 * Set Page for Posts option
	 * 
	 * If the 'show_on_front' option is set to 'posts', then set it to 'page'
	 * and also set the page
	 * 
	 * @since 0.2.0
	 */
	public function reading_settings() {
		if( get_option( 'show_on_front' ) == 'post' ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_for_posts', apply_filters( 'dwpb_page_for_posts', 0 ) );
			update_option( 'page_on_front', apply_filters( 'dwpb_page_on_front', 0 ) );
		}
	}
	
	/**
	 * Remove post related widgets
	 * 
	 * @since 0.2.0
	 */
	public function remove_widgets() {
		// Remove Recent Posts
		unregister_widget( 'WP_Widget_Recent_Posts' );
		
		// Remove Categories Widget
		if( ! $this->is_taxonomy_supported( 'category' ) )
			unregister_widget( 'WP_Widget_Categories' );
		
		// Remove Recent Comments Widget if posts are the only type with comments
		if( ! $this->post_types_with_feature( 'comments' ) )
			unregister_widget( 'WP_Widget_Recent_Comments' );
		
		// Remove Tag Cloud
		if( ! $this->is_taxonomy_supported( 'post_tag' ) )
			unregister_widget( 'WP_Widget_Tag_Cloud' );
		
		// Remove RSS Widget
		unregister_widget( 'WP_Widget_RSS' );
	}
	
	/**
	 * Get all the post types that support comments
	 * 
	 * @since 0.1.0
	 * @return array ( $post_types | bolean )
	 */
	public function post_types_with_feature( $feature ) {
		$post_types = get_post_types( array(), 'names' );
		
		$post_types_with_feature = array();
		foreach( $post_types as $post_type ) {
			if( post_type_supports( $post_type, $feature ) && $post_type != 'post' ) {
				$post_types_with_feature[] = $post_type;
			}
		}
		
		// Return the array if there are any, otherwise false
		if( empty( $post_types_with_feature ) ) {
			return apply_filters( "dwpb_post_types_supporting_{$feature}", false );
		} else {
			return apply_filters( "dwpb_post_types_supporting_{$feature}", $post_types_with_feature );
		}
	}
	
	/**
	 * Get post types that have a specific taxonomy
	 *  (a combination of get_post_types and get_object_taxonomies)
	 * 
	 * @since 0.2.0
	 * 
	 * @see register_post_types(), get_post_types(), get_object_taxonomies()
	 * 
	 * @param string			$taxonomy	Required. The name of the feature to check against
	 * 										post type support.
	 * @param array | string	$args		Optional. An array of key => value arguments to match 
	 *										against the post type objects. Default empty array.
	 * @param string			$output		Optional. The type of output to return.
	 * 										Accepts post type 'names' or 'objects'.
	 *										Default 'names'.
	 * 
	 * @return array | boolean	A list of post type names or objects that have the taxonomy 
	 *							or false if nothing found.
	 */
	public function is_taxonomy_supported( $taxonomy, $args = array(), $output = 'names' ) {
		$post_types = get_post_types( $args, $output );
	
		// We just need the taxonomy name
		if( is_object( $taxonomy ) ){
			$taxonomy = $taxonomy->name;
		
		// If it's not an object or a string, it won't work, so send it back
		} elseif( !is_string( $taxonomy ) ) {
			return false;
		}
	
		// setup the finished product
		$post_types_with_tax = array();
		foreach( $post_types as $post_type ) {
			// If post types are objects
			if( is_object( $post_type ) && $post_type->name != 'post' ) {
				$taxonomies = get_object_taxonomies( $post_type->name, 'names' );
				if( in_array( $taxonomy, $taxonomies ) ) {
					$post_types_with_tax[] = $post_type;
				}
			// If post types are strings
			} elseif( is_string( $post_type ) && $post_type != 'post' ) {
				$taxonomies = get_object_taxonomies( $post_type, 'names' );
				if( in_array( $taxonomy, $taxonomies ) ) {
					$post_types_with_tax[] = $post_type;
				}
			}
		}
		
		// Ability to override the results
		$override = apply_filters( 'dwpb_taxonomy_support', null, $taxonomy, $post_types, $args, $output );
		if( ! is_null( $override ) ) {
			return $override;
		}
	
		// If there aren't any results, return false
		if( empty( $post_types_with_tax ) ) {
			return false;
		} else {
			return $post_types_with_tax;
		}
	}
}
