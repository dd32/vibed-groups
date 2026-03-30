<?php
/**
 * Title: Community Callout
 * Slug: groups-directory/community-callout
 * Categories: groups-directory
 * Description: Full-width CTA section encouraging visitors to start a group.
 */
?>

<!-- wp:group {"align":"full","className":"groups-directory-callout","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80","left":"var:preset|spacing|edge-space","right":"var:preset|spacing|edge-space"}},"elements":{"link":{"color":{"text":"var:preset|color|primary-200"}}}},"backgroundColor":"primary-900","textColor":"neutral-0","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull groups-directory-callout has-neutral-0-color has-primary-900-background-color has-text-color has-background has-link-color" style="padding-top:var(--wp--preset--spacing--80);padding-right:var(--wp--preset--spacing--edge-space);padding-bottom:var(--wp--preset--spacing--80);padding-left:var(--wp--preset--spacing--edge-space)">

	<!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"var:preset|spacing|70"}}}} -->
	<div class="wp-block-columns">

		<!-- wp:column {"width":"60%"} -->
		<div class="wp-block-column" style="flex-basis:60%">

			<!-- wp:heading {"level":2,"fontSize":"heading-2","textColor":"neutral-0","style":{"spacing":{"margin":{"bottom":"var:preset|spacing|30"}}}} -->
			<h2 class="wp-block-heading has-neutral-0-color has-text-color has-heading-2-font-size" style="margin-bottom:var(--wp--preset--spacing--30)">No group in your area?</h2>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"textColor":"primary-200","fontSize":"body-lg"} -->
			<p class="has-primary-200-color has-text-color has-body-lg-font-size">Start your own WordPress community group and help bring WordPress enthusiasts together. We provide the tools and support to help you get started.</p>
			<!-- /wp:paragraph -->

		</div>
		<!-- /wp:column -->

		<!-- wp:column {"width":"40%","verticalAlignment":"center"} -->
		<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:40%">

			<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"left"}} -->
			<div class="wp-block-buttons">
				<!-- wp:button {"backgroundColor":"accent-500","textColor":"neutral-0","style":{"border":{"radius":"8px"},"spacing":{"padding":{"top":"14px","bottom":"14px","left":"28px","right":"28px"}}}} -->
				<div class="wp-block-button"><a class="wp-block-button__link has-neutral-0-color has-accent-500-background-color has-text-color has-background wp-element-button" href="/apply/" style="border-radius:8px;padding-top:14px;padding-right:28px;padding-bottom:14px;padding-left:28px">Apply to Start a Group</a></div>
				<!-- /wp:button -->

				<!-- wp:button {"backgroundColor":"primary-800","textColor":"neutral-0","className":"is-style-outline","style":{"border":{"radius":"8px","width":"2px","color":"var:preset|color|primary-400"},"spacing":{"padding":{"top":"14px","bottom":"14px","left":"28px","right":"28px"}}}} -->
				<div class="wp-block-button is-style-outline"><a class="wp-block-button__link has-neutral-0-color has-primary-800-background-color has-text-color has-background wp-element-button" href="https://make.wordpress.org/community/" style="border-color:var(--wp--preset--color--primary-400);border-width:2px;border-radius:8px;padding-top:14px;padding-right:28px;padding-bottom:14px;padding-left:28px">Learn More</a></div>
				<!-- /wp:button -->
			</div>
			<!-- /wp:buttons -->

		</div>
		<!-- /wp:column -->

	</div>
	<!-- /wp:columns -->

</div>
<!-- /wp:group -->
