<?php
/**
 * Title: Group Grid
 * Slug: groups-directory/group-grid
 * Categories: groups-directory
 * Description: A grid of featured WordPress community groups.
 */
?>

<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60","left":"var:preset|spacing|edge-space","right":"var:preset|spacing|edge-space"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--edge-space);padding-bottom:var(--wp--preset--spacing--60);padding-left:var(--wp--preset--spacing--edge-space)">

	<!-- wp:heading {"textAlign":"center","level":2,"fontSize":"heading-3","style":{"spacing":{"margin":{"bottom":"var:preset|spacing|40"}},"typography":{"fontWeight":"700"}}} -->
	<h2 class="wp-block-heading has-text-align-center has-heading-3-font-size" style="margin-bottom:var(--wp--preset--spacing--40);font-weight:700">Featured Groups</h2>
	<!-- /wp:heading -->

	<!-- wp:groups/group-directory /-->

</div>
<!-- /wp:group -->
