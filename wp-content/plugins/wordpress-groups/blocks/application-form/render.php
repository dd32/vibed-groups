<?php
/**
 * Server-side render for the Application Form block.
 *
 * Renders a container for the React multi-step form.
 * If the user is not logged in, shows a login prompt instead.
 *
 * @package Groups
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block default content.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$is_logged_in = is_user_logged_in();
$login_url    = wp_login_url( get_permalink() );

$wrapper_attributes = get_block_wrapper_attributes(
	[
		'class'          => 'wp-block-groups-application-form',
		'data-logged-in' => $is_logged_in ? '1' : '0',
		'data-login-url' => esc_url( $login_url ),
		'data-nonce'     => wp_create_nonce( 'wp_rest' ),
	]
);

?>
<div <?php echo $wrapper_attributes; ?>>
	<?php if ( ! $is_logged_in ) : ?>
		<div class="wp-block-groups-application-form__login-prompt">
			<h2><?php esc_html_e( 'Start a WordPress Community Group', 'wordpress-groups' ); ?></h2>
			<p>
				<?php esc_html_e( 'You need to be logged in with your WordPress.org account to submit a group application.', 'wordpress-groups' ); ?>
			</p>
			<a
				class="wp-block-groups-application-form__login-btn"
				href="<?php echo esc_url( $login_url ); ?>"
			>
				<?php esc_html_e( 'Log in to apply', 'wordpress-groups' ); ?>
			</a>
		</div>
	<?php else : ?>
		<div class="wp-block-groups-application-form__loading" aria-live="polite">
			<p><?php esc_html_e( 'Loading application form\u2026', 'wordpress-groups' ); ?></p>
		</div>
	<?php endif; ?>
</div>
