<?php
/**
 * Plugin Name: PTCB BOG
 * Plugin URI: https://github.com/OrasesWPDev/ptcb-bog // Replace with your actual URI if different
 * Description: Custom WordPress plugin for managing Board of Governors (BOG) profiles with ACF Pro integration
 * Version: 1.0.2 // Incremented version
 * Author: Orases // Replace with your actual Author if different
 * Author URI: https://orases.com // Replace with your actual Author URI if different
 *
 * @package PTCB_BOG
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
	exit;
}

// --- Configuration ---
// Set debug mode (true for development, false for production)
define('PTCB_BOG_DEBUG_MODE', true); // Set to false for production
define('PTCB_BOG_VERSION', '1.0.1');
// ---------------------

// --- Plugin Constants ---
define('PTCB_BOG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PTCB_BOG_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PTCB_BOG_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('PTCB_BOG_LOG_DIR', PTCB_BOG_PLUGIN_DIR . 'logs/');
// -----------------------


/**
 * Modify the 'board-member' post type registration arguments very early via the init hook.
 * This sets the custom permalink structure and ensures it takes precedence.
 */
function ptcb_bog_modify_post_type() {
	global $wp_post_types;

	// Target the specific post type 'board-member'
	if (isset($wp_post_types['board-member'])) {

		// Set the desired rewrite slug for single posts
		$wp_post_types['board-member']->rewrite = array(
			'slug'       => 'ptcb-team/board-of-governors', // URL base for single posts
			'with_front' => false, // CRITICAL: Prevent WP prefixing (like /news/)
			'feeds'      => false, // No feeds needed
			'pages'      => true   // Allow pagination if ever needed (usually not for CPT singles)
		);

		// IMPORTANT: Set has_archive to false as a dedicated page will be used for the archive/shortcode display.
		$wp_post_types['board-member']->has_archive = true;

		// Log the modification if debugging is enabled
		if (function_exists('ptcb_bog') && PTCB_BOG_DEBUG_MODE) {
			$log_message = 'Modified board-member CPT via init priority 1. Setting rewrite[slug] to: ' . $wp_post_types['board-member']->rewrite['slug'];
			$log_message .= ', rewrite[with_front] to: ' . ($wp_post_types['board-member']->rewrite['with_front'] ? 'true' : 'false');
			$log_message .= ', has_archive to: ' . ($wp_post_types['board-member']->has_archive ? 'true' : 'false');
			ptcb_bog()->log($log_message, 'info');
		}

		// Flush rewrite rules ONCE after modification to ensure changes take effect.
		// Use a unique option name for this plugin's flush flag, versioned.
		$option_name = 'ptcb_bog_post_type_modified_v' . PTCB_BOG_VERSION;
		if ( get_option( $option_name ) !== 'yes' ) {
			flush_rewrite_rules();
			update_option( $option_name, 'yes' );
			if (function_exists('ptcb_bog') && PTCB_BOG_DEBUG_MODE) {
				ptcb_bog()->log('Flushed rewrite rules after modifying board-member post type (via init hook, option check). Option: ' . $option_name, 'info');
			}
		}
	} elseif (function_exists('ptcb_bog') && PTCB_BOG_DEBUG_MODE) {
		// Log a warning if the post type isn't registered when this attempts to run.
		// This might happen if the CPT is registered later than priority 1.
		ptcb_bog()->log('Attempted to modify rewrite rules at init priority 1, but post type "board-member" was not found/registered yet.', 'warning');
	}
}
// Run *very* early on init (priority 1) to modify the CPT settings before WP fully processes them.
add_action('init', 'ptcb_bog_modify_post_type', 1);


/**
 * Main PTCB BOG Plugin Class (Singleton Pattern)
 */
final class PTCB_BOG {
	private static $instance = null;
	private $loaded_classes = [];

	/**
	 * Get the singleton instance.
	 */
	public static function get_instance() {
		if (is_null(self::$instance)) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor to prevent direct instantiation.
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Setup debug logging directory and files.
	 * Creates log directory and security files if needed.
	 *
	 * @return bool True if logging is possible (dir exists & writable), false otherwise.
	 */
	private function setup_debug_logging() {
		if (!PTCB_BOG_DEBUG_MODE) {
			return false; // Debug mode is off
		}
		if (!file_exists(PTCB_BOG_LOG_DIR)) {
			// Try to create directory recursively
			if (!@mkdir(PTCB_BOG_LOG_DIR, 0755, true)) {
				error_log('PTCB BOG Plugin Error: Failed to create log directory: ' . PTCB_BOG_LOG_DIR);
				return false; // Failed to create directory
			}
			// Add basic security files to the log directory upon creation
			@file_put_contents(PTCB_BOG_LOG_DIR . '.htaccess', "# Prevent direct access\n<FilesMatch \"\.log$\">\nOrder allow,deny\nDeny from all\n</FilesMatch>\n");
			@file_put_contents(PTCB_BOG_LOG_DIR . 'index.html', '<!-- Silence is golden -->');
		}
		// Check if directory is writable
		if (!is_writable(PTCB_BOG_LOG_DIR)) {
			error_log('PTCB BOG Plugin Error: Log directory is not writable: ' . PTCB_BOG_LOG_DIR);
			return false;
		}
		return true; // Directory exists and is writable
	}

	/**
	 * Log messages to a date-stamped file if debug mode is enabled.
	 *
	 * @param string $message The message to log.
	 * @param string $level   Log level (e.g., 'info', 'debug', 'warning', 'error').
	 * @return bool True on success, false on failure.
	 */
	public function log($message, $level = 'info') {
		// Ensure logging is enabled and directory is setup/writable
		if (!defined('PTCB_BOG_DEBUG_MODE') || !PTCB_BOG_DEBUG_MODE || !$this->setup_debug_logging()) {
			return false;
		}
		try {
			// Use a default timezone if WP timezone isn't set (fallback)
			$timezone_string = get_option('timezone_string') ?: 'UTC';
			$date = new DateTime('now', new DateTimeZone($timezone_string));
			$timestamp = $date->format('Y-m-d H:i:s T'); // Include timezone
			$log_message = "[{$timestamp}] [" . strtoupper($level) . "] {$message}" . PHP_EOL;
			$log_file = PTCB_BOG_LOG_DIR . 'ptcb-bog-' . $date->format('Y-m-d') . '.log';

			// Use file locking for safer writing in concurrent requests
			return (bool)@file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
		} catch (Exception $e) {
			error_log("PTCB BOG Logging Exception: " . $e->getMessage()); // Log errors during logging to PHP error log
			return false;
		}
	}

	/**
	 * Register core plugin hooks.
	 */
	private function init_hooks() {
		// Activation/Deactivation hooks
		register_activation_hook(PTCB_BOG_PLUGIN_BASENAME, array($this, 'activation'));
		register_deactivation_hook(PTCB_BOG_PLUGIN_BASENAME, array($this, 'deactivation'));

		// Initialize main plugin components after all plugins are loaded
		add_action('plugins_loaded', array($this, 'init_plugin'));
	}

	/**
	 * Plugin activation routine.
	 */
	public function activation() {
		$this->log('Plugin activating...', 'info');
		// Ensure our CPT modification function runs to potentially register settings/flush rules
		ptcb_bog_modify_post_type();
		// Explicitly flush rewrite rules on activation.
		flush_rewrite_rules();
		// Remove the 'modified' flag to ensure rules are checked/flushed on next load if needed
		delete_option('ptcb_bog_post_type_modified_v' . PTCB_BOG_VERSION);
		$this->log('Plugin activated. Rewrite rules flushed.', 'info');
	}

	/**
	 * Plugin deactivation routine.
	 */
	public function deactivation() {
		$this->log('Plugin deactivating...', 'info');
		// Flush rules on deactivation to remove the custom structure.
		flush_rewrite_rules();
		// Remove the 'modified' flag
		delete_option('ptcb_bog_post_type_modified_v' . PTCB_BOG_VERSION);
		$this->log('Plugin deactivated. Rewrite rules flushed.', 'info');
	}

	/**
	 * Initialize plugin components (called on plugins_loaded).
	 */
	public function init_plugin() {
		if ($this->setup_debug_logging()) {
			$this->log('init_plugin started on plugins_loaded hook.', 'info');
		} else {
			// Log to error log if debug setup failed but was intended
			if (defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE) {
				error_log('PTCB BOG: init_plugin called, but debug logging setup failed.');
			}
		}

		// Load required include files
		$this->load_files();

		// Add hooks that need to run after plugins_loaded
		add_action('admin_init', array($this, 'check_dependencies'));
		add_action('wp_enqueue_scripts', array($this, 'register_assets'));
		// Note: register_override_styles might be redundant if priority/dependencies work
		// Consider removing if main CSS enqueue is sufficient
		add_action('wp_enqueue_scripts', array($this, 'register_override_styles'), 999);

		$this->log('init_plugin completed.', 'info');
	}

	/**
	 * Load required PHP class files.
	 */
	private function load_files() {
		$load_error = false;
		$files_to_load = [
			'templates' => PTCB_BOG_PLUGIN_DIR . 'includes/class-ptcb-bog-templates.php',
			'helpers'   => PTCB_BOG_PLUGIN_DIR . 'includes/class-ptcb-bog-helpers.php',
			'shortcodes'=> PTCB_BOG_PLUGIN_DIR . 'includes/class-ptcb-bog-shortcodes.php',
		];
		$expected_classes = [
			'templates' => 'PTCB_BOG_Templates',
			'helpers'   => 'PTCB_BOG_Helpers',
			'shortcodes'=> 'PTCB_BOG_Shortcodes',
		];

		foreach ($files_to_load as $key => $file_path) {
			if (file_exists($file_path)) {
				include_once $file_path; // Use include_once to prevent fatal errors if already loaded
				if (class_exists($expected_classes[$key])) {
					// Instantiate the class and store it
					$this->loaded_classes[$expected_classes[$key]] = new $expected_classes[$key]();
					$this->log("Loaded and instantiated {$expected_classes[$key]}.", 'debug');
				} else {
					$this->log("Included {$file_path} but class {$expected_classes[$key]} not found!", 'error');
					$load_error = true;
				}
			} else {
				$this->log("Required file not found: {$file_path}", 'error');
				$load_error = true;
			}
		}

		if ($load_error) {
			// Display an admin notice if critical files failed to load
			add_action('admin_notices', function() {
				?>
                <div class="notice notice-error is-dismissible">
                    <p><?php _e('<strong>PTCB BOG Plugin Error:</strong> One or more critical component files are missing or failed to load. Please check the plugin installation and logs.', 'ptcb-bog'); ?></p>
                </div>
				<?php
			});
		}
	}

	/**
	 * Check for dependencies (ACF Pro) and display admin notices if missing AND debug mode is on.
	 */
	public function check_dependencies() {
		// Check for base ACF first
		if ( ! class_exists('acf') ) {
			// Only show notice if debug mode is ON
			if ( defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE ) {
				add_action('admin_notices', array( $this, 'acf_missing_notice' ));
			}
			// Log regardless of debug mode, as it's a dependency issue
			$this->log('Dependency check failed: Advanced Custom Fields (ACF) plugin not found or activated.', 'warning');

			// If base ACF exists, check for Pro (using a reliable check like function_exists)
		} elseif ( ! function_exists('acf_pro_init') ) {
			// Only show notice if debug mode is ON
			if ( defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE ) {
				add_action('admin_notices', array( $this, 'acf_pro_missing_notice' ));
			}
			// Log regardless of debug mode
			$this->log('Dependency check failed: ACF Pro plugin not found or activated (ACF base plugin detected).', 'warning');

			// If both base and Pro seem active
		} else {
			// Only log success if debug mode is ON
			if ( defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE ) {
				$this->log('Dependency check passed: ACF Pro found and active.', 'debug');
			}
		}
	}

	/**
	 * Displays an admin notice if ACF base plugin is missing.
	 */
	public function acf_missing_notice() {
		?>
        <div class="notice notice-error">
            <p><?php _e('<strong>PTCB BOG Plugin Warning:</strong> Requires the Advanced Custom Fields plugin to be installed and activated.', 'ptcb-bog'); ?></p>
        </div>
		<?php
	}

	/**
	 * Displays an admin notice if ACF Pro plugin is missing.
	 */
	public function acf_pro_missing_notice() {
		?>
        <div class="notice notice-warning"> <?php // Changed to warning as base ACF might provide some functionality ?>
            <p><?php _e('<strong>PTCB BOG Plugin Warning:</strong> Requires the PRO version of Advanced Custom Fields for full functionality. Please ensure ACF Pro is installed and activated.', 'ptcb-bog'); ?></p>
        </div>
		<?php
	}

	/**
	 * Register and enqueue frontend CSS and JS assets.
	 */
	public function register_assets() {
		$this->log('Registering assets via wp_enqueue_scripts.', 'debug');

		// Enqueue main CSS file with dynamic versioning based on file modification time
		$css_file = PTCB_BOG_PLUGIN_DIR . 'assets/css/ptcb-bog.css';
		if (file_exists($css_file)) {
			$css_version = filemtime($css_file);
			wp_enqueue_style(
				'ptcb-bog', // Main CSS handle
				PTCB_BOG_PLUGIN_URL . 'assets/css/ptcb-bog.css',
				array(), // Dependencies (e.g., add theme's main style handle if needed)
				$css_version
			);
			$this->log('Enqueued ptcb-bog CSS version ' . $css_version, 'debug');
		} else {
			$this->log('ptcb-bog CSS file not found: ' . $css_file, 'warning');
		}

		// --- Optional JS Enqueue ---
		// Enqueue JS only on single 'board-member' pages (if JS file exists)
		 if (is_singular('board-member')) {
		 	$this->log('Attempting to enqueue JS for single board-member page.', 'debug');
		 	$js_file = PTCB_BOG_PLUGIN_DIR . 'assets/js/ptcb-bog.js'; // Assumed path
		 	if (file_exists($js_file)) {
		 		$js_version = filemtime($js_file);
		 		wp_enqueue_script(
		 			'ptcb-bog', // JS handle
		 			PTCB_BOG_PLUGIN_URL . 'assets/js/ptcb-bog.js',
		 			array('jquery'), // Dependencies
		 			$js_version,
		 			true // Load in footer
		 		);
		 		$this->log('Enqueued ptcb-bog JS version ' . $js_version, 'debug');
		 	} else {
		 		$this->log('ptcb-bog JS file not found: ' . $js_file, 'warning'); // Warning if JS file is expected but absent
		 	}
		 }
		// --- End Optional JS ---
	}

	/**
	 * Register CSS late to potentially override theme styles.
	 * Consider if adjusting priority or dependencies of the main 'ptcb-bog' style is sufficient.
	 */
	public function register_override_styles() {
		// This might be redundant if the main CSS enqueue works correctly with dependencies/priority.
		// If needed, uncomment and potentially adjust dependencies.

		$this->log('Registering potentially overriding styles via wp_enqueue_scripts (late hook).', 'debug');
		$css_file = PTCB_BOG_PLUGIN_DIR . 'assets/css/ptcb-bog.css';
		if (file_exists($css_file)) {
			$css_version = filemtime($css_file);
			// Use a distinct handle if needed in addition to the main enqueue.
			wp_enqueue_style(
				'ptcb-bog-override',
				PTCB_BOG_PLUGIN_URL . 'assets/css/ptcb-bog.css',
				array('ptcb-bog'), // Depends on the main style being loaded first
				$css_version
			);
			$this->log('Enqueued ptcb-bog-override CSS version ' . $css_version, 'debug');
		}
	}

} // End of PTCB_BOG class

/**
 * Filters the sample permalink HTML in the editor for the 'board-member' CPT.
 * Replaces the incorrect base URL shown due to global prefix conflicts.
 *
 * @param string $html    The sample permalink HTML markup.
 * @param int    $post_id Post ID.
 * @param string $title   Post title.
 * @param string $name    Post slug.
 * @param object $post    Post object.
 * @return string Modified HTML.
 */
function ptcb_bog_filter_sample_permalink_html( $html, $post_id, $title, $name, $post ) {
	// Only filter for our specific post type
	if ( isset($post->post_type) && 'board-member' === $post->post_type ) {
		// Get the correct base URL part we want to see
		$correct_base = home_url( '/ptcb-team/board-of-governors/' );

		// Get the incorrect base URL part WordPress might be showing
		// This finds the part before the editable slug in the current HTML
		// Example: finds 'https://.../news/board-member/' in '<a ...>https://.../news/board-member/</a><span id="editable-post-name">slug</span>/'
		preg_match( '/<a[^>]+>([^<]+)<\/a><span[^>]+>/i', $html, $matches );
		if ( isset( $matches[1] ) ) {
			$incorrect_display_url_part = $matches[1]; // e.g., 'https://.../news/board-member/'

			// Get just the path part of the incorrect URL (e.g., '/news/board-member/')
			$incorrect_path = trailingslashit( wp_parse_url( $incorrect_display_url_part, PHP_URL_PATH ) );

			// Get the path part of the correct base (e.g., '/ptcb-team/board-of-governors/')
			$correct_path = trailingslashit( wp_parse_url( $correct_base, PHP_URL_PATH ) );

			// If they don't match, replace the incorrect one in the HTML
			if ($incorrect_path !== $correct_path) {
				// Construct the full correct display URL part (including scheme/host)
				$correct_display_url_part = home_url($correct_path);

				// Replace the incorrect URL part within the <a> tag's text content
				$html = str_replace( '>' . $incorrect_display_url_part . '<', '>' . $correct_display_url_part . '<', $html );

				// Log the replacement if debugging
				if ( function_exists('ptcb_bog') && defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE ) {
					ptcb_bog()->log("Filtered sample permalink HTML. Replaced '{$incorrect_display_url_part}' with '{$correct_display_url_part}'.", 'debug');
				}
			}
		}
	}
	return $html;
}
add_filter( 'get_sample_permalink_html', 'ptcb_bog_filter_sample_permalink_html', 10, 5 );

/**
 * Global accessor function for the PTCB_BOG instance.
 * Ensures only one instance of the plugin class is loaded.
 *
 * @return PTCB_BOG The singleton instance of the main plugin class.
 */
function ptcb_bog() {
	return PTCB_BOG::get_instance();
}

// Initialize the plugin instance.
ptcb_bog();