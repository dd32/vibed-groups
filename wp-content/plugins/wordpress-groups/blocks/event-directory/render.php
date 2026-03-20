<?php
/**
 * Server-side render for the Event Directory block.
 *
 * Renders a container for the React-powered event directory.
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
		'class'      => 'wp-block-groups-event-directory',
		'data-nonce' => wp_create_nonce( 'wp_rest' ),
	]
);

?>
<div <?php echo $wrapper_attributes; ?>>
	<div class="wp-block-groups-event-directory__loading" aria-live="polite">
		<p><?php esc_html_e( 'Loading events\u2026', 'wordpress-groups' ); ?></p>
	</div>
</div>
