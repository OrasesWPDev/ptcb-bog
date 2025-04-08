<?php
/**
 * Template handling for the Board Member (BOG) post type
 *
 * @package PTCB_BOG
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
	exit;
}

/**
 * PTCB BOG Templates Class
 *
 * Handles loading custom templates and managing permalinks for the 'board-member' post type.
 */
class PTCB_BOG_Templates {

	/**
	 * Constructor - Adds filters and actions for template and permalink handling.
	 */
	public function __construct() {
		// Filter to load our custom single template for 'board-member' posts
		add_filter('single_template', array($this, 'load_bog_template'));

		// Add custom body classes to single 'board-member' posts
		add_filter('body_class', array($this, 'add_bog_body_classes'));

		// Filter the permalink for 'board-member' posts to match our custom structure
		add_filter('post_type_link', array($this, 'bog_permalink_structure'), 10, 2);

		// Add the rewrite rule needed for our custom permalink structure
		// Priority 10 ensures CPT is registered; our CPT mod runs at priority 1
		add_action('init', array($this, 'add_bog_rewrite_rules'), 10);

		// Register the query variable used in our rewrite rule ('board-member')
		add_filter('query_vars', array($this, 'register_query_vars'));

		// Modify the main query when our custom query variable is detected
		add_action('pre_get_posts', array($this, 'pre_get_posts'));

		if (function_exists('ptcb_bog') && defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE) {
			ptcb_bog()->log('PTCB_BOG_Templates initialized', 'info');
		}
	}

	/**
	 * Loads the custom single template for 'board-member' posts.
	 * Looks for 'templates/single-bog.php' within the plugin directory.
	 *
	 * @param string $template The template path currently being considered by WordPress.
	 * @return string The path to the custom template or the original path.
	 */
	public function load_bog_template($template) {
		global $post;

		// Check if we are on a single post view and if the post type is 'board-member'
		if (is_singular('board-member') && is_object($post) && $post->post_type === 'board-member') {

			if (function_exists('ptcb_bog') && defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE) {
				ptcb_bog()->log("Attempting to load template for single board-member post ID: {$post->ID}", 'info');
			}

			// Define the path to the custom template file within this plugin
			$custom_template = PTCB_BOG_PLUGIN_DIR . 'templates/single-bog.php';

			// Check if the custom template file exists
			if (file_exists($custom_template)) {
				if (function_exists('ptcb_bog') && defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE) {
					ptcb_bog()->log("Loading custom BOG template: {$custom_template}", 'info');
				}
				return $custom_template; // Use our template
			} else {
				if (function_exists('ptcb_bog') && defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE) {
					ptcb_bog()->log("Custom BOG template not found at: {$custom_template}. Falling back to default: {$template}", 'warning');
				}
			}
		} elseif (function_exists('ptcb_bog') && defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE && is_single()) {
			// Log if it's a single post but not our CPT (for debugging template issues)
			if (is_object($post)) {
				ptcb_bog()->log("Template filter called for single post, but post type is '{$post->post_type}'. Using default template: {$template}", 'debug');
			} else {
				ptcb_bog()->log("Template filter called for single post, but post object invalid. Using default template: {$template}", 'debug');
			}
		}

		// Return the original template path if it's not a single board member post or custom template doesn't exist
		return $template;
	}

	/**
	 * Adds custom CSS classes to the body tag on single 'board-member' post pages.
	 *
	 * @param array $classes An array of existing body classes.
	 * @return array The modified array of body classes.
	 */
	public function add_bog_body_classes($classes) {
		// Check if we are viewing a single 'board-member' post
		if (is_singular('board-member')) {
			if (function_exists('ptcb_bog') && defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE) {
				ptcb_bog()->log('Adding body classes for single BOG post.', 'info');
			}
			$classes[] = 'ptcb-bog-single';   // Class specific to single BOG posts
			$classes[] = 'ptcb-bog-template'; // General class indicating our template context
			if (function_exists('ptcb_bog') && defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE) {
				ptcb_bog()->log('Added body classes: ptcb-bog-single, ptcb-bog-template', 'debug');
			}
		}
		return $classes;
	}

	/**
	 * Registers the custom query variable used by our rewrite rule.
	 *
	 * @param array $vars Existing public query variables.
	 * @return array Modified query variables array including 'board-member'.
	 */
	public function register_query_vars($vars) {
		$vars[] = 'board-member'; // Use the CPT slug as the query variable
		if (function_exists('ptcb_bog') && defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE) {
			ptcb_bog()->log("Registered query variable: 'board-member'", 'debug');
		}
		return $vars;
	}

	/**
	 * Modifies the main WordPress query when our custom permalink structure is accessed.
	 * This ensures WordPress correctly identifies the request as a single post of our CPT.
	 *
	 * @param WP_Query $query The main WP_Query object (passed by reference).
	 */
	public function pre_get_posts($query) {
		// Only modify the main query on the frontend
		if (is_admin() || !$query->is_main_query()) {
			return;
		}

		// Check if our custom query variable 'board-member' is set in the query
		$bog_slug = $query->get('board-member');

		if (!empty($bog_slug)) {
			if (function_exists('ptcb_bog') && defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE) {
				ptcb_bog()->log("Detected 'board-member' query variable: '{$bog_slug}'. Modifying main query.", 'info');
			}
			// Tell WordPress this is a single post request for the 'board-member' type
			$query->set('post_type', 'board-member');
			// Set the 'name' query var (which WP uses for slugs) to our captured slug
			$query->set('name', $bog_slug);
			// Explicitly set query flags to ensure it's treated as a single post page
			$query->is_single = true;
			$query->is_singular = true;
			// Prevent potential 404s by removing the potentially conflicting query var after use
			// $query->set('board-member', null); // Optional: may help avoid conflicts in some edge cases
		}
	}


	/**
	 * Customizes the permalink structure for 'board-member' posts.
	 * Forces the URL to be '/ptcb-team/board-of-governors/post-slug/'.
	 *
	 * @param string $post_link The default permalink URL.
	 * @param WP_Post $post The post object.
	 * @return string The modified permalink URL.
	 */
	public function bog_permalink_structure($post_link, $post) {
		// Only modify links for the 'board-member' post type
		if ('board-member' === $post->post_type) {
			// Get the post slug (post_name)
			$slug = $post->post_name;

			// Ensure slug is not empty
			if (empty($slug)) {
				if (function_exists('ptcb_bog') && defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE) {
					ptcb_bog()->log("Cannot generate custom permalink for post ID {$post->ID}: Post slug is empty.", 'warning');
				}
				return $post_link; // Return original link if slug is missing
			}

			// Construct the custom permalink using the desired structure
			$custom_link = home_url('/ptcb-team/board-of-governors/' . $slug . '/');

			if (function_exists('ptcb_bog') && defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE) {
				ptcb_bog()->log("Generated custom permalink for post ID {$post->ID}: {$custom_link}", 'debug');
			}

			return $custom_link;
		}
		// Return the original link for all other post types
		return $post_link;
	}

	/**
	 * Adds the rewrite rule necessary for the custom permalink structure.
	 * Maps '/ptcb-team/board-of-governors/any-slug/' to WordPress's query variables.
	 */
	public function add_bog_rewrite_rules() {
		// Regex: Matches 'ptcb-team/board-of-governors/' followed by one or more characters (the slug)
		// ending optionally with a slash '/'. Captures the slug in $matches[1].
		$regex = 'ptcb-team/board-of-governors/([^/]+)/?$';
		// Target: Maps to index.php, setting our custom query var 'board-member' to the captured slug.
		$target = 'index.php?board-member=$matches[1]';

		add_rewrite_rule($regex, $target, 'top'); // 'top' priority ensures it overrides default rules

		if (function_exists('ptcb_bog') && defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE) {
			ptcb_bog()->log("Added BOG rewrite rule: Regex='{$regex}', Target='{$target}'", 'info');
		}

		// Note: Rewrite rules need flushing after adding/changing. This is handled
		// in the main plugin activation/deactivation and the ptcb_bog_modify_post_type check.
		// Avoid flushing rules directly within this hook unless absolutely necessary,
		// as it's an expensive operation.
	}

} // End class PTCB_BOG_Templates