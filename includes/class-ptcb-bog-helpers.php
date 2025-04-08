<?php
/**
 * Helper functions for the PTCB BOG Plugin
 *
 * @package PTCB_BOG
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
	exit;
}

/**
 * PTCB BOG Helpers Class
 *
 * Utility functions for the plugin.
 */
class PTCB_BOG_Helpers {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Log initialization if debug mode is on
		if (function_exists('ptcb_bog') && defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE) {
			ptcb_bog()->log('PTCB_BOG_Helpers initialized', 'info');
		}
	}

	/**
	 * Safely get an ACF field value with logging and fallback.
	 * Checks if ACF function exists.
	 *
	 * @param string $field_name The ACF field name (e.g., 'board_title').
	 * @param int|null $post_id Optional. Post ID to get field from. Defaults to current post.
	 * @param mixed $default Default value if field is empty or ACF is unavailable.
	 * @return mixed Field value or the default.
	 */
	public static function get_acf_field($field_name, $post_id = null, $default = '') {
		if (!function_exists('get_field')) {
			if (function_exists('ptcb_bog') && defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE) {
				ptcb_bog()->log('ACF function get_field() not available when trying to get: ' . $field_name, 'error');
			}
			return $default;
		}

		if (!$post_id) {
			$post_id = get_the_ID();
			if (!$post_id && function_exists('ptcb_bog') && defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE) {
				ptcb_bog()->log('No post ID provided or found for get_acf_field: ' . $field_name, 'warning');
				// In some contexts get_the_ID() might return false, handle gracefully
				return $default;
			}
		}

		if (function_exists('ptcb_bog') && defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE) {
			ptcb_bog()->log('Getting ACF field: ' . $field_name . ' for post ID: ' . $post_id, 'debug');
		}

		$value = get_field($field_name, $post_id);

		// Use strict check for empty to allow '0' or false as valid values if needed, adjust if necessary
		if (empty($value)) {
			if (function_exists('ptcb_bog') && defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE) {
				ptcb_bog()->log('ACF field ' . $field_name . ' is empty for post ID: ' . $post_id . '. Returning default.', 'debug');
			}
			return $default;
		}

		if (function_exists('ptcb_bog') && defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE) {
			ptcb_bog()->log('Successfully retrieved ACF field: ' . $field_name, 'debug');
		}
		return $value;
	}

	/**
	 * Get the 'board_title' ACF field value for a Board Member.
	 *
	 * @param int|null $post_id Optional. Post ID. Defaults to current post.
	 * @return string The board title or an empty string if not found.
	 */
	public static function get_board_title($post_id = null) {
		$current_post_id_log = $post_id ? $post_id : 'current post (' . get_the_ID() . ')';
		if (function_exists('ptcb_bog') && defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE) {
			ptcb_bog()->log('Getting board_title for post ID: ' . $current_post_id_log, 'debug');
		}
		// Use the safe helper function to retrieve the ACF field
		$title = self::get_acf_field('board_title', $post_id, ''); // Field name changed here
		if (function_exists('ptcb_bog') && defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE) {
			ptcb_bog()->log('Board title retrieved: ' . ($title ?: '(empty)'), 'debug');
		}
		return $title;
	}

	/**
	 * Display the Board Title (from ACF field 'board_title') wrapped in HTML.
	 *
	 * @param int|null $post_id Optional. Post ID. Defaults to current post.
	 * @param string $tag Optional. The HTML tag to wrap the title in. Default 'h3'.
	 * @param bool $echo Optional. Whether to echo the HTML (true) or return it (false). Default true.
	 * @return string|void HTML output if $echo is false.
	 */
	public static function the_board_title($post_id = null, $tag = 'h3', $echo = true) { // MODIFIED: Added $tag parameter, default 'h3'
		$current_post_id_log = $post_id ? $post_id : 'current post (' . get_the_ID() . ')';
		if (function_exists('ptcb_bog') && defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE) {
			ptcb_bog()->log('Generating board_title HTML for post ID: ' . $current_post_id_log, 'debug');
		}

		$board_title = self::get_board_title($post_id);

		if (empty($board_title)) {
			if (function_exists('ptcb_bog') && defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE) {
				ptcb_bog()->log('Board title is empty, not outputting HTML.', 'debug');
			}
			return ''; // Return empty string if no title
		}

		// Sanitize the tag
		$allowed_tags = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'p', 'span'];
		$tag = in_array(strtolower($tag), $allowed_tags) ? strtolower($tag) : 'h3'; // Default to h3 if tag not allowed

		// CSS class name updated to be specific to BOG Board Title
		// MODIFIED: Wrap in the specified tag
		$html = '<' . $tag . ' class="ptcb-bog-board-title">' . esc_html($board_title) . '</' . $tag . '>';

		if ($echo) {
			if (function_exists('ptcb_bog') && defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE) {
				ptcb_bog()->log('Echoing board_title HTML.', 'debug');
			}
			echo $html;
		} else {
			if (function_exists('ptcb_bog') && defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE) {
				ptcb_bog()->log('Returning board_title HTML.', 'debug');
			}
			return $html;
		}
	}

	/**
	 * Get the 'company_title' ACF field value for a Board Member.
	 *
	 * @param int|null $post_id Optional. Post ID. Defaults to current post.
	 * @return string The company title or an empty string if not found.
	 */
	public static function get_company_title($post_id = null) {
		$current_post_id_log = $post_id ? $post_id : 'current post (' . get_the_ID() . ')';
		if (function_exists('ptcb_bog') && defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE) {
			ptcb_bog()->log('Getting company_title for post ID: ' . $current_post_id_log, 'debug');
		}
		// Use the safe helper function to retrieve the ACF field
		$title = self::get_acf_field('company_title', $post_id, ''); // Field name is company_title
		if (function_exists('ptcb_bog') && defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE) {
			ptcb_bog()->log('Company title retrieved: ' . ($title ?: '(empty)'), 'debug');
		}
		return $title;
	}

	/**
	 * Display the Company Title (from ACF field 'company_title') wrapped in HTML H4 tags.
	 *
	 * @param int|null $post_id Optional. Post ID. Defaults to current post.
	 * @param string $tag Optional. The HTML tag to wrap the title in. Default 'h4'.
	 * @param bool $echo Optional. Whether to echo the HTML (true) or return it (false). Default true.
	 * @return string|void HTML output if $echo is false.
	 */
	public static function the_company_title($post_id = null, $tag = 'h4', $echo = true) {
		$current_post_id_log = $post_id ? $post_id : 'current post (' . get_the_ID() . ')';
		if (function_exists('ptcb_bog') && defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE) {
			ptcb_bog()->log('Generating company_title HTML for post ID: ' . $current_post_id_log, 'debug');
		}

		$company_title = self::get_company_title($post_id);

		if (empty($company_title)) {
			if (function_exists('ptcb_bog') && defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE) {
				ptcb_bog()->log('Company title is empty, not outputting HTML.', 'debug');
			}
			return ''; // Return empty string if no title
		}

		// Sanitize the tag
		$allowed_tags = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'p', 'span'];
		$tag = in_array(strtolower($tag), $allowed_tags) ? strtolower($tag) : 'h4'; // Default to h4 if tag not allowed

		// CSS class name specific to BOG Company Title
		$html = '<' . $tag . ' class="ptcb-bog-company-title">' . esc_html($company_title) . '</' . $tag . '>';

		if ($echo) {
			if (function_exists('ptcb_bog') && defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE) {
				ptcb_bog()->log('Echoing company_title HTML.', 'debug');
			}
			echo $html;
		} else {
			if (function_exists('ptcb_bog') && defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE) {
				ptcb_bog()->log('Returning company_title HTML.', 'debug');
			}
			return $html;
		}
	}

	/**
	 * Get the featured image HTML for a Board Member post.
	 *
	 * @param int|null $post_id Optional. Post ID. Defaults to current post.
	 * @param string $size Optional. Image size (e.g., 'thumbnail', 'medium', 'large'). Default 'medium'.
	 * @param array $attr Optional. Attributes for the image tag (e.g., ['class' => 'my-class']).
	 * @return string HTML image tag or empty string if no thumbnail.
	 */
	public static function get_bog_image($post_id = null, $size = 'medium', $attr = array()) {
		if (!$post_id) {
			$post_id = get_the_ID();
		}

		if (!$post_id) {
			if (function_exists('ptcb_bog') && defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE) {
				ptcb_bog()->log('No post ID provided or found for get_bog_image.', 'warning');
			}
			return '';
		}

		if (function_exists('ptcb_bog') && defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE) {
			ptcb_bog()->log('Getting BOG image for post ID: ' . $post_id . ' with size: ' . $size, 'debug');
		}

		// Set default CSS class if not provided, or append if it is
		$default_class = 'ptcb-bog-image'; // Updated default class name
		if (!isset($attr['class'])) {
			$attr['class'] = $default_class;
		} else {
			// Append default class ensuring no double spacing
			$attr['class'] = trim($attr['class'] . ' ' . $default_class);
		}

		if (function_exists('ptcb_bog') && defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE) {
			ptcb_bog()->log('Image attributes: ' . print_r($attr, true), 'debug');
		}

		// Get the featured image using WordPress function
		if (has_post_thumbnail($post_id)) {
			if (function_exists('ptcb_bog') && defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE) {
				ptcb_bog()->log('Featured image found for post ID: ' . $post_id, 'debug');
			}
			return get_the_post_thumbnail($post_id, $size, $attr);
		}

		// No image found
		if (function_exists('ptcb_bog') && defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE) {
			ptcb_bog()->log('No featured image found for post ID: ' . $post_id, 'warning');
		}
		return '';
	}

	/**
	 * Get all published Board Member posts using WP_Query.
	 *
	 * @param array $args Optional. Arguments to override default WP_Query args.
	 * @return array Array of WP_Post objects for board members, or empty array if none found.
	 */
	public static function get_bog_members($args = array()) {
		$default_args = array(
			'post_type'      => 'board-member', // Changed post type
			'posts_per_page' => -1,             // Get all posts by default
			'orderby'        => 'menu_order',   // Default ordering
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);

		// Merge provided args with defaults
		$query_args = wp_parse_args($args, $default_args);

		if (function_exists('ptcb_bog') && defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE) {
			ptcb_bog()->log('Querying BOG members with args: ' . print_r($query_args, true), 'info');
		}

		$bog_query = new WP_Query($query_args);

		// Return the posts if found
		if ($bog_query->have_posts()) {
			$count = $bog_query->post_count;
			if (function_exists('ptcb_bog') && defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE) {
				ptcb_bog()->log('Query found ' . $count . ' BOG members.', 'info');
			}
			// It's often better to return the query object itself if the loop needs reset etc.
			// But mimicking the original, we return just the posts array.
			wp_reset_postdata(); // Reset post data after custom query
			return $bog_query->posts;
		}

		// No posts found
		if (function_exists('ptcb_bog') && defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE) {
			ptcb_bog()->log('No BOG members found matching the query.', 'warning');
		}
		wp_reset_postdata(); // Ensure post data is reset even if no posts found
		return array();
	}

	/**
	 * Check if the current page is a single Board Member post view.
	 *
	 * @return bool True if viewing a single 'board-member' post, false otherwise.
	 */
	public static function is_bog_single() {
		// Check for the specific post type 'board-member'
		$is_bog = is_singular('board-member'); // Changed post type
		if (function_exists('ptcb_bog') && defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE) {
			ptcb_bog()->log('Checking if current page is a single BOG post: ' . ($is_bog ? 'Yes' : 'No'), 'debug');
		}
		return $is_bog;
	}

} // End class PTCB_BOG_Helpers