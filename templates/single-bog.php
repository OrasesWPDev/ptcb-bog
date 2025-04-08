<?php
/**
 * Template for displaying single Board Member (BOG) posts
 * Loaded via PTCB_BOG_Templates class.
 *
 * @package PTCB_BOG
 * @version 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
	exit;
}

// Include the theme's header (expects Flatsome theme context)
get_header();
?>

	<main id="main" class="<?php echo esc_attr(flatsome_main_classes()); ?>">

		<!-- Header Block Section (Full Width) -->
		<!-- This pulls in the UX Block containing the title and breadcrumbs -->
		<div class="ptcb-bog-section-wrapper ptcb-bog-header">
			<?php
			// Use the specific Block ID for the BOG single header
			echo do_shortcode('[block id="single-board-member-header"]');
			?>
		</div>

		<!-- Start the WordPress Loop -->
		<?php while (have_posts()) : the_post(); ?>

			<!-- BOG Bio Section -->
			<div class="ptcb-bog-bio-section">
				<div class="container"> <!-- Use Flatsome's container for content width -->
					<div class="row"> <!-- Use Flatsome's row -->

						<!-- Featured Image Column (Left) -->
						<div class="large-4 medium-4 small-12 col ptcb-bog-featured-image-column">
							<?php if (has_post_thumbnail()) : ?>
								<div class="ptcb-bog-featured-image">
									<?php
									// Display the featured image - 'large' size is often suitable for main images
									the_post_thumbnail('large', array('class' => 'ptcb-bog-profile-image'));
									?>
								</div>
							<?php else : ?>
								<!-- Optional: Placeholder if no image -->
								<div class="ptcb-bog-no-featured-image">
									<?php // echo __('No image available.', 'ptcb-bog'); ?>
								</div>
							<?php endif; ?>
						</div>

						<!-- Bio Content Column (Right) -->
						<div class="large-8 medium-8 small-12 col ptcb-bog-bio-column">
							<div class="ptcb-bog-bio-content">
								<?php
								// Display the ACF 'board_title' using the helper function
								// This echoes the title wrapped in its div: <div class="ptcb-bog-board-title">...</div>
								PTCB_BOG_Helpers::the_board_title(get_the_ID(), true);

								// Display the main post content (from the WordPress editor)
								$content = get_the_content(); // Get content to check if it's empty

								if (!empty(trim($content))) { // Check if content exists and isn't just whitespace
									the_content();
								} else {
									// Fallback message if no content is entered in the editor
									echo '<p class="ptcb-bog-no-bio">' . __('No biography information is currently available for this member.', 'ptcb-bog') . '</p>';
								}
								?>
							</div>
						</div>

					</div> <!-- /.row -->
				</div> <!-- /.container -->
			</div> <!-- /.ptcb-bog-bio-section -->

		<?php endwhile; // End of the loop. ?>

	</main>

<?php
// Include the theme's footer
get_footer();
?>