<?php
/**
 * Server-side render for the Group Directory block.
 *
 * Renders a container div for React hydration on the frontend.
 *
 * @package Groups
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block default content.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$wrapper_attributes = get_block_wrapper_attributes(
	[
		'class'      => 'wp-block-groups-group-directory',
		'data-nonce' => wp_create_nonce( 'wp_rest' ),
	]
);

?>
<div <?php echo $wrapper_attributes; ?>>
	<div class="wp-block-groups-group-directory__loading" aria-live="polite">
		<p><?php esc_html_e( 'Loading group directory\u2026', 'wordpress-groups' ); ?></p>
	</div>
</div>
