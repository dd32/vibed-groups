<?php
/**
 * Title: Front Page Hero
 * Slug: groups-directory/front-hero
 * Categories: groups-directory
 * Description: Two-column hero with heading, description, and search.
 */
?>

<!-- wp:group {"align":"full","className":"groups-directory-hero","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|70","left":"var:preset|spacing|edge-space","right":"var:preset|spacing|edge-space"}}},"backgroundColor":"primary-50","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull groups-directory-hero has-primary-50-background-color has-background" style="padding-top:var(--wp--preset--spacing--80);padding-right:var(--wp--preset--spacing--edge-space);padding-bottom:var(--wp--preset--spacing--70);padding-left:var(--wp--preset--spacing--edge-space)">

	<!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"var:preset|spacing|70"}}}} -->
	<div class="wp-block-columns">

		<!-- wp:column {"width":"60%"} -->
		<div class="wp-block-column" style="flex-basis:60%">

			<!-- wp:heading {"level":1,"fontSize":"heading-1","style":{"spacing":{"margin":{"bottom":"var:preset|spacing|40"}}}} -->
			<h1 class="wp-block-heading has-heading-1-font-size" style="margin-bottom:var(--wp--preset--spacing--40)">Meet the WordPress community near you</h1>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"fontSize":"body-lg","textColor":"neutral-600","style":{"spacing":{"margin":{"bottom":"var:preset|spacing|50"}}}} -->
			<p class="has-neutral-600-color has-text-color has-body-lg-font-size" style="margin-bottom:var(--wp--preset--spacing--50)">Join one of hundreds of WordPress groups around the world for meetups, workshops, and learning opportunities with fellow WordPress enthusiasts.</p>
			<!-- /wp:paragraph -->

			<!-- wp:search {"label":"Search groups","showLabel":false,"placeholder":"Search by city, country, or group name\u2026","buttonText":"Search","className":"groups-directory-hero-search"} /-->

		</div>
		<!-- /wp:column -->

		<!-- wp:column {"width":"40%","className":"groups-directory-hero-visual"} -->
		<div class="wp-block-column groups-directory-hero-visual" style="flex-basis:40%">

			<!-- wp:group {"className":"groups-directory-stats-grid","style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"grid","minimumColumnWidth":"140px"}} -->
			<div class="wp-block-group groups-directory-stats-grid">

				<!-- wp:group {"className":"groups-directory-stat-card","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}},"border":{"radius":"12px"}},"backgroundColor":"neutral-0","layout":{"type":"constrained"}} -->
				<div class="wp-block-group groups-directory-stat-card has-neutral-0-background-color has-background" style="border-radius:12px;padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40)">
					<!-- wp:paragraph {"className":"groups-directory-stat-number","style":{"typography":{"fontWeight":"800","lineHeight":"1"}},"fontSize":"heading-2","fontFamily":"heading"} -->
					<p class="groups-directory-stat-number has-heading-font-family has-heading-2-font-size" style="font-weight:800;line-height:1">800+</p>
					<!-- /wp:paragraph -->

					<!-- wp:paragraph {"textColor":"neutral-500","fontSize":"small"} -->
					<p class="has-neutral-500-color has-text-color has-small-font-size">Active groups</p>
					<!-- /wp:paragraph -->
				</div>
				<!-- /wp:group -->

				<!-- wp:group {"className":"groups-directory-stat-card","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}},"border":{"radius":"12px"}},"backgroundColor":"neutral-0","layout":{"type":"constrained"}} -->
				<div class="wp-block-group groups-directory-stat-card has-neutral-0-background-color has-background" style="border-radius:12px;padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40)">
					<!-- wp:paragraph {"className":"groups-directory-stat-number","style":{"typography":{"fontWeight":"800","lineHeight":"1"}},"fontSize":"heading-2","fontFamily":"heading"} -->
					<p class="groups-directory-stat-number has-heading-font-family has-heading-2-font-size" style="font-weight:800;line-height:1">107</p>
					<!-- /wp:paragraph -->

					<!-- wp:paragraph {"textColor":"neutral-500","fontSize":"small"} -->
					<p class="has-neutral-500-color has-text-color has-small-font-size">Countries</p>
					<!-- /wp:paragraph -->
				</div>
				<!-- /wp:group -->

				<!-- wp:group {"className":"groups-directory-stat-card","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}},"border":{"radius":"12px"}},"backgroundColor":"neutral-0","layout":{"type":"constrained"}} -->
				<div class="wp-block-group groups-directory-stat-card has-neutral-0-background-color has-background" style="border-radius:12px;padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40)">
					<!-- wp:paragraph {"className":"groups-directory-stat-number","style":{"typography":{"fontWeight":"800","lineHeight":"1"}},"fontSize":"heading-2","fontFamily":"heading"} -->
					<p class="groups-directory-stat-number has-heading-font-family has-heading-2-font-size" style="font-weight:800;line-height:1">4K+</p>
					<!-- /wp:paragraph -->

					<!-- wp:paragraph {"textColor":"neutral-500","fontSize":"small"} -->
					<p class="has-neutral-500-color has-text-color has-small-font-size">Events this year</p>
					<!-- /wp:paragraph -->
				</div>
				<!-- /wp:group -->

				<!-- wp:group {"className":"groups-directory-stat-card","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}},"border":{"radius":"12px"}},"backgroundColor":"neutral-0","layout":{"type":"constrained"}} -->
				<div class="wp-block-group groups-directory-stat-card has-neutral-0-background-color has-background" style="border-radius:12px;padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40)">
					<!-- wp:paragraph {"className":"groups-directory-stat-number","style":{"typography":{"fontWeight":"800","lineHeight":"1"}},"fontSize":"heading-2","fontFamily":"heading"} -->
					<p class="groups-directory-stat-number has-heading-font-family has-heading-2-font-size" style="font-weight:800;line-height:1">500K+</p>
					<!-- /wp:paragraph -->

					<!-- wp:paragraph {"textColor":"neutral-500","fontSize":"small"} -->
					<p class="has-neutral-500-color has-text-color has-small-font-size">Community members</p>
					<!-- /wp:paragraph -->
				</div>
				<!-- /wp:group -->

			</div>
			<!-- /wp:group -->

		</div>
		<!-- /wp:column -->

	</div>
	<!-- /wp:columns -->

</div>
<!-- /wp:group -->
