<?php
/**
 * Title: Front Page Events
 * Slug: groups-directory/front-events
 * Categories: groups-directory
 * Description: Upcoming events section for the front page.
 */
?>

<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70","left":"var:preset|spacing|edge-space","right":"var:preset|spacing|edge-space"}}},"backgroundColor":"neutral-100","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-neutral-100-background-color has-background" style="padding-top:var(--wp--preset--spacing--70);padding-right:var(--wp--preset--spacing--edge-space);padding-bottom:var(--wp--preset--spacing--70);padding-left:var(--wp--preset--spacing--edge-space)">

	<!-- wp:group {"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|50"}}},"layout":{"type":"flex","justifyContent":"space-between","flexWrap":"wrap"}} -->
	<div class="wp-block-group" style="margin-bottom:var(--wp--preset--spacing--50)">

		<!-- wp:heading {"level":2,"fontSize":"heading-3","style":{"typography":{"fontWeight":"700"}}} -->
		<h2 class="wp-block-heading has-heading-3-font-size" style="font-weight:700">Upcoming events</h2>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"fontSize":"small"} -->
		<p class="has-small-font-size"><a href="/events/">Browse all events &rarr;</a></p>
		<!-- /wp:paragraph -->

	</div>
	<!-- /wp:group -->

	<!-- wp:groups/event-directory /-->

</div>
<!-- /wp:group -->
