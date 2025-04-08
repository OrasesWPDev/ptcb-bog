<?php
/**
 * Plugin Name: PTCB BOG
 * Plugin URI: https://github.com/OrasesWPDev/ptcb-bog // Replace with your actual URI if different
 * Description: Custom WordPress plugin for managing Board of Governors (BOG) profiles with ACF Pro integration
 * Version: 1.0.0
 * Author: Orases // Replace with your actual Author if different
 * Author URI: https://orases.com // Replace with your actual Author URI if different
 *
 * @package PTCB_BOG
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
	exit;
}

// CRITICAL CHANGE: Set debug mode (true for development, false for production)
define('PTCB_BOG_DEBUG_MODE', true); // Set to false for production

// Define plugin constants
define('PTCB_BOG_VERSION', '1.0.0');
define('PTCB_BOG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PTCB_BOG_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PTCB_BOG_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('PTCB_BOG_LOG_DIR', PTCB_BOG_PLUGIN_DIR . 'logs/');

/**
 * Modify the 'board-member' post type registration early to set the custom permalink structure.
 */
function ptcb_bog_modify_post_type() {
	global $wp_post_types;

	// Target the specific post type 'board-member'
	if (isset($wp_post_types['board-member'])) {

		// Set the desired rewrite slug for single posts
		$wp_post_types['board-member']->rewrite = array(
			'slug'       => 'ptcb-team/board-of-governors', // URL base for single posts
			'with_front' => false, // Keep false to prevent WP prefixing
			'feeds'      => false,
			'pages'      => true
		);

		// IMPORTANT: Set has_archive to false as a dedicated page will be used for the archive/shortcode display.
		$wp_post_types['board-member']->has_archive = false;

		// Log the modification if debugging is enabled
		if (function_exists('ptcb_bog') && PTCB_BOG_DEBUG_MODE) {
			ptcb_bog()->log('Modified board-member post type rewrite rules. Slug: ' . $wp_post_types['board-member']->rewrite['slug'] . ', Has Archive: false', 'info');
		}

		// Flush rewrite rules once after modification to ensure changes take effect.
		// Use a unique option name for this plugin's flush flag.
		$option_name = 'ptcb_bog_post_type_modified_v' . PTCB_BOG_VERSION; // Versioning the option name
		if ( get_option( $option_name ) !== 'yes' ) {
			flush_rewrite_rules();
			update_option( $option_name, 'yes' );
			if (function_exists('ptcb_bog') && PTCB_BOG_DEBUG_MODE) {
				ptcb_bog()->log('Flushed rewrite rules after modifying board-member post type (via init hook). Option: ' . $option_name, 'info');
			}
		}
	} else {
		// Log a warning if the post type isn't registered when this attempts to run.
		if (function_exists('ptcb_bog') && PTCB_BOG_DEBUG_MODE) {
			ptcb_bog()->log('Attempted to modify rewrite rules, but post type "board-member" was not found at init priority 1.', 'warning');
		}
	}
}
// Run *very* early on init (priority 1) to modify the CPT settings before WP fully processes them.
// This is crucial for the rewrite slug to be correctly recognized.
add_action('init', 'ptcb_bog_modify_post_type', 1);


/**
 * Main PTCB BOG Plugin Class (Singleton Pattern)
 */
final class PTCB_BOG {
	private static $instance = null;
	private $loaded_classes = [];

	public static function get_instance() {
		if (is_null(self::$instance)) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Setup debug logging directory and files.
	 */
	private function setup_debug_logging() {
		if (!PTCB_BOG_DEBUG_MODE) {
			return false;
		}
		if (!file_exists(PTCB_BOG_LOG_DIR)) {
			if (!@mkdir(PTCB_BOG_LOG_DIR, 0755, true)) {
				return false; // Failed to create directory
			}
			// Add basic security files to the log directory
			@file_put_contents(PTCB_BOG_LOG_DIR . '.htaccess', "# Prevent direct access\n<FilesMatch \"\.log$\">\nOrder allow,deny\nDeny from all\n</FilesMatch>\n");
			@file_put_contents(PTCB_BOG_LOG_DIR . 'index.html', '<!-- Silence is golden -->');
		}
		return is_writable(PTCB_BOG_LOG_DIR); // Return true if directory exists and is writable
	}

	/**
	 * Log messages to a date-stamped file if debug mode is enabled.
	 */
	public function log($message, $level = 'info') {
		if (!defined('PTCB_BOG_DEBUG_MODE') || !PTCB_BOG_DEBUG_MODE || !$this->setup_debug_logging()) {
			// Ensure logging is enabled and the directory is writable
			return false;
		}
		try {
			$date = new DateTime('now', new DateTimeZone('America/New_York')); // Adjust timezone if needed
			$timestamp = $date->format('Y-m-d H:i:s');
			$log_message = "[{$timestamp}] [" . strtoupper($level) . "] {$message}" . PHP_EOL;
			$log_file = PTCB_BOG_LOG_DIR . 'ptcb-bog-' . $date->format('Y-m-d') . '.log';
			// Use file locking for safer writing in concurrent requests
			return (bool)@file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
		} catch (Exception $e) {
			error_log("PTCB BOG Logging Error: " . $e->getMessage()); // Log errors during logging to PHP error log
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
		// Ensure our CPT modification function runs to register settings
		ptcb_bog_modify_post_type();
		// Flush rewrite rules to recognize the new permalink structure immediately.
		flush_rewrite_rules();
		// Log activation
		$this->log('Plugin activated. Rewrite rules flushed.', 'info');
		// Remove the 'modified' flag to ensure rules are checked/flushed on next load if needed
		delete_option('ptcb_bog_post_type_modified_v' . PTCB_BOG_VERSION);
	}

	/**
	 * Plugin deactivation routine.
	 */
	public function deactivation() {
		// Flush rules on deactivation to remove the custom structure.
		flush_rewrite_rules();
		// Log deactivation
		$this->log('Plugin deactivated. Rewrite rules flushed.', 'info');
		// Remove the 'modified' flag
		delete_option('ptcb_bog_post_type_modified_v' . PTCB_BOG_VERSION);
	}

	/**
	 * Initialize plugin components (called on plugins_loaded).
	 */
	public function init_plugin() {
		if ($this->setup_debug_logging()) {
			$this->log('init_plugin started on plugins_loaded hook.', 'info');
		}

		// Load required include files
		$this->load_files();

		// Add hooks that need to run after plugins_loaded
		add_action('admin_init', array($this, 'check_dependencies'));
		add_action('wp_enqueue_scripts', array($this, 'register_assets'));
		// Enqueue override styles late if needed (consider if necessary vs. main CSS priority)
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
				include_once $file_path;
				if (class_exists($expected_classes[$key])) {
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
	 * Check for dependencies (ACF Pro) and display admin notices if missing.
	 */
	public function check_dependencies() {
		if (!class_exists('acf')) {
			add_action('admin_notices', array($this, 'acf_missing_notice'));
			$this->log('Dependency check failed: Advanced Custom Fields (ACF) plugin not found.', 'warning');
		} elseif (!class_exists('acf_pro')) {
			// Only show the Pro notice if the base ACF exists but Pro doesn't
			add_action('admin_notices', array($this, 'acf_pro_missing_notice'));
			$this->log('Dependency check failed: ACF Pro plugin not found (ACF base plugin detected).', 'warning');
		}
	}

	public function acf_missing_notice() {
		?>
		<div class="notice notice-error">
			<p><?php _e('<strong>PTCB BOG Plugin Warning:</strong> Requires the Advanced Custom Fields plugin to be installed and activated.', 'ptcb-bog'); ?></p>
		</div>
		<?php
	}

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

		// Enqueue main CSS file with dynamic versioning
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

		// Enqueue JS only on single 'board-member' pages (if JS file exists)
		if (is_singular('board-member')) { // Check for single CPT pages
			$this->log('Attempting to enqueue JS for single board-member page.', 'debug');
			$js_file = PTCB_BOG_PLUGIN_DIR . 'assets/js/ptcb-bog.js'; // Assumed path
			if (file_exists($js_file)) {
				$js_version = filemtime($js_file);
				wp_enqueue_script(
					'ptcb-bog', // JS handle (can be same as CSS if convenient)
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
	}

	/**
	 * Register CSS late to potentially override theme styles.
	 * Note: Consider if simply adjusting the priority or dependencies of the main 'ptcb-bog' style in register_assets is sufficient.
	 */
	public function register_override_styles() {
		$this->log('Registering potentially overriding styles via wp_enqueue_scripts (late hook).', 'debug');
		$css_file = PTCB_BOG_PLUGIN_DIR . 'assets/css/ptcb-bog.css';
		if (file_exists($css_file)) {
			$css_version = filemtime($css_file);
			// Use a distinct handle if you need this loaded *in addition* to the main enqueue.
			// If it's just about loading late, the hook priority might be enough.
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
 * Global accessor function for the PTCB_BOG instance.
 */
function ptcb_bog() {
	return PTCB_BOG::get_instance();
}

// Initialize the plugin instance.
ptcb_bog();
