<?php
/**
 * Shortcodes for the Board Member (BOG) post type
 *
 * @package PTCB_BOG
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
	exit;
}

/**
 * PTCB BOG Shortcodes Class
 *
 * Handles registration and processing of shortcodes for displaying BOG members.
 * Includes:
 * - [ptcb_bog] for the grid display.
 * - [bog_breadcrumbs] for navigation breadcrumbs.
 */
class PTCB_BOG_Shortcodes {

	/**
	 * Constructor - Registers shortcodes.
	 */
	public function __construct() {
		// Register the main grid shortcode
		add_shortcode('ptcb_bog', array($this, 'bog_grid_shortcode'));

		// Register the breadcrumbs shortcode
		add_shortcode('bog_breadcrumbs', array($this, 'breadcrumbs_shortcode_callback'));

		if (function_exists('ptcb_bog') && defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE) {
			ptcb_bog()->log('PTCB_BOG_Shortcodes initialized and shortcodes registered: [ptcb_bog], [bog_breadcrumbs]', 'info');
		}
	}

	/**
	 * Processes the [ptcb_bog] shortcode.
	 * Displays Board Members in a responsive grid.
	 *
	 * Attributes:
	 * - columns (int): Number of columns (1-6). Default: 3.
	 * - limit (int): Max number of members to show (-1 for all). Default: -1.
	 * - orderby (string): WP_Query orderby param (e.g., 'menu_order', 'title', 'date', 'rand'). Default: 'menu_order'.
	 * - order (string): Sort order ('ASC' or 'DESC'). Default: 'ASC'.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output for the grid.
	 */
	public function bog_grid_shortcode($atts) {
		if (function_exists('ptcb_bog') && defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE) {
			ptcb_bog()->log('Processing [ptcb_bog] shortcode with attributes: ' . print_r($atts, true), 'info');
		}

		// Set default attributes and sanitize user input
		$atts = shortcode_atts(array(
			'columns' => 3,
			'limit'   => -1,
			'orderby' => 'menu_order',
			'order'   => 'ASC',
		), $atts, 'ptcb_bog'); // Use the correct shortcode tag here

		// Sanitize Columns
		$columns = absint($atts['columns']);
		$columns = max(1, min(6, $columns)); // Ensure columns are between 1 and 6

		// Sanitize Limit
		$limit = intval($atts['limit']);

		// Sanitize Order By
		$valid_orderby = array('menu_order', 'title', 'name', 'date', 'modified', 'rand', 'ID'); // 'name' refers to post_name (slug)
		$orderby = in_array($atts['orderby'], $valid_orderby) ? $atts['orderby'] : 'menu_order';

		// Sanitize Order
		$order = in_array(strtoupper($atts['order']), array('ASC', 'DESC')) ? strtoupper($atts['order']) : 'ASC';

		if (function_exists('ptcb_bog') && defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE) {
			ptcb_bog()->log("Shortcode params sanitized: columns={$columns}, limit={$limit}, orderby={$orderby}, order={$order}", 'debug');
		}

		// Prepare query arguments for board members
		$args = array(
			'post_type'      => 'board-member', // Use the correct CPT slug
			'posts_per_page' => $limit,
			'orderby'        => $orderby,
			'order'          => $order,
			'post_status'    => 'publish',
		);

		if (function_exists('ptcb_bog') && defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE) {
			ptcb_bog()->log('Board Member query arguments: ' . print_r($args, true), 'info');
		}
		$bog_query = new WP_Query($args);

		// Start output buffering
		ob_start();

		if ($bog_query->have_posts()) {
			// Main grid container
			// Add classes for styling, including column count
			echo '<div class="ptcb-bog-grid ptcb-bog-columns-' . esc_attr($columns) . '">';

			$count = 0;
			$total_posts = $bog_query->post_count;

			while ($bog_query->have_posts()) {
				$bog_query->the_post();
				$post_id = get_the_ID();

				if (function_exists('ptcb_bog') && defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE) {
					ptcb_bog()->log("Processing board member ID: {$post_id}, Title: " . get_the_title(), 'debug');
				}

				// Start a new row when needed (at the beginning and every 'columns' items)
				if ($count % $columns === 0) {
					if (function_exists('ptcb_bog') && defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE) {
						ptcb_bog()->log('Starting new row at item index ' . $count, 'debug');
					}
					// Close previous row if it's not the first item
					if ($count > 0) {
						echo '</div><!-- .ptcb-bog-row -->';
					}
					echo '<div class="ptcb-bog-row">'; // Add row class
				}

				// Get data using helper functions for consistency and safety
				$board_title_html = PTCB_BOG_Helpers::the_board_title($post_id, false); // Get HTML, don't echo yet
				$bog_image = PTCB_BOG_Helpers::get_bog_image($post_id, 'medium', array('class' => 'ptcb-bog-thumbnail')); // Specify desired class

				?>
				<div class="ptcb-bog-column ptcb-bog-column-<?php echo esc_attr(($count % $columns) + 1); ?>">
					<div class="ptcb-bog-card">
						<a href="<?php the_permalink(); ?>" class="ptcb-bog-card-link">

							<?php if (!empty($bog_image)): ?>
								<div class="ptcb-bog-card-image">
									<?php echo $bog_image; // Output the image HTML ?>
								</div>
							<?php endif; ?>

							<div class="ptcb-bog-card-content">
								<!-- WordPress Post Title (Member's Name) -->
								<h3 class="ptcb-bog-card-post-title"><?php the_title(); ?></h3>

								<?php if (!empty($board_title_html)): ?>
									<!-- Separator (Optional) -->
									<hr class="ptcb-bog-title-separator">
									<!-- ACF Board Title (Position/Role) -->
									<?php echo $board_title_html; // Output the pre-formatted HTML safely ?>
								<?php endif; ?>
							</div>

						</a>
					</div>
				</div>
				<?php
				$count++;
			} // End while loop

			// Add empty columns to fill the last row if necessary
			if ($count > 0 && $count % $columns !== 0) {
				$empty_columns = $columns - ($count % $columns);
				if (function_exists('ptcb_bog') && defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE) {
					ptcb_bog()->log('Adding ' . $empty_columns . ' empty columns to complete the last row.', 'debug');
				}
				for ($i = 0; $i < $empty_columns; $i++) {
					echo '<div class="ptcb-bog-column ptcb-bog-column-empty"></div>';
				}
			}

			// Close the last row
			echo '</div><!-- .ptcb-bog-row -->';
			echo '</div><!-- .ptcb-bog-grid -->';

			if (function_exists('ptcb_bog') && defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE) {
				ptcb_bog()->log('Completed rendering BOG grid with ' . $count . ' members.', 'info');
			}

		} else {
			// Display a message if no board members were found
			if (function_exists('ptcb_bog') && defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE) {
				ptcb_bog()->log('No board members found to display for [ptcb_bog] shortcode.', 'warning');
			}
			echo '<div class="ptcb-bog-not-found"><p>' . __('No board members found.', 'ptcb-bog') . '</p></div>';
		}

		// Restore original post data
		wp_reset_postdata();

		// Get buffered content
		$output = ob_get_clean();
		if (function_exists('ptcb_bog') && defined('PTCB_BOG_DEBUG_MODE') && PTCB_BOG_DEBUG_MODE) {
			ptcb_bog()->log('Shortcode [ptcb_bog] processing complete. Returning ' . strlen($output) . ' characters of HTML.', 'info');
		}

		// Return the shortcode output
		return $output;
	}


	/**
	 * Breadcrumbs shortcode callback [bog_breadcrumbs]
	 *
	 * Outputs a breadcrumb trail based on the specified structure:
	 * Home / ptcb-team / board-of-governors / Current Member Title
	 *
	 * @since 1.0.0
	 * @return string Breadcrumbs HTML.
	 */
	public function breadcrumbs_shortcode_callback() {
		ob_start();

		$home_url = home_url('/');
		$separator = '<span class="ptcb-breadcrumb-divider"> / </span>'; // Define separator

		// Get the 'ptcb-team' page by its slug
		$team_page = get_page_by_path('ptcb-team');
		$team_url = $team_page ? get_permalink($team_page->ID) : '#'; // Fallback URL
		$team_title = $team_page ? $team_page->post_title : __('PTCB Team', 'ptcb-bog'); // Fallback title

		// Get the 'board-of-governors' page (child of ptcb-team) by its path
		// This is the page where the [ptcb_bog] shortcode is placed.
		$bog_archive_page_path = 'ptcb-team/board-of-governors';
		$bog_archive_page = get_page_by_path($bog_archive_page_path);
		$bog_archive_url = $bog_archive_page ? get_permalink($bog_archive_page->ID) : '#'; // Fallback URL
		$bog_archive_title = $bog_archive_page ? $bog_archive_page->post_title : __('Board of Governors', 'ptcb-bog'); // Fallback title

		echo '<div class="ptcb-bog-breadcrumbs">'; // Use BOG specific class

		// Home link (Could use theme's home text or a generic 'Home')
		echo '<a href="' . esc_url($home_url) . '">' . __('Ptcb', 'ptcb-bog') . '</a>'; // Changed 'Ptcb' to be translatable

		// ptcb-team link
		echo $separator;
		echo '<a href="' . esc_url($team_url) . '">' . esc_html($team_title) . '</a>';

		// Check if we are on a single board member page or the archive page
		if (is_singular('board-member')) {
			// On single page: Link to the BOG archive page, then show current member title
			echo $separator;
			echo '<a href="' . esc_url($bog_archive_url) . '">' . esc_html($bog_archive_title) . '</a>';
			echo $separator;
			echo '<span class="breadcrumb_last">' . esc_html(get_the_title()) . '</span>'; // Current post title (non-linked)
		} else {
			// On the archive/shortcode page itself, show BOG title as the last item (non-linked)
			echo $separator;
			echo '<span class="breadcrumb_last">' . esc_html($bog_archive_title) . '</span>';
		}

		echo '</div>'; // Close breadcrumbs container

		return ob_get_clean();
	}

} // End class PTCB_BOG_Shortcodes