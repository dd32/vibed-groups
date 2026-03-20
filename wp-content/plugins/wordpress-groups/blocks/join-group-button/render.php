<?php
/**
 * Server-side render for the Join Group Button block.
 *
 * Provides initial state so the view script can hydrate.
 *
 * @package Groups
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block default content.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$is_logged_in = is_user_logged_in();
$user_id      = get_current_user_id();
$blog_id      = get_current_blog_id();
$is_member    = $is_logged_in && is_user_member_of_blog( $user_id, $blog_id );

if ( $is_logged_in ) {
	$initial_state = $is_member ? 'member' : 'not-member';
} else {
	$initial_state = 'not-logged-in';
}

$login_url = ! $is_logged_in ? wp_login_url( get_permalink() ) : '';

$button_text = match ( $initial_state ) {
	'member'        => __( 'Leave Group', 'wordpress-groups' ),
	'not-member'    => __( 'Join Group', 'wordpress-groups' ),
	'not-logged-in' => __( 'Log in to join', 'wordpress-groups' ),
};

$data_attributes = [
	'class'              => 'wp-block-groups-join-group-button',
	'data-initial-state' => esc_attr( $initial_state ),
];

if ( $login_url ) {
	$data_attributes['data-login-url'] = esc_url( $login_url );
}

$wrapper_attributes = get_block_wrapper_attributes( $data_attributes );
?>
<div <?php echo $wrapper_attributes; ?>>
	<?php if ( 'not-logged-in' === $initial_state && $login_url ) : ?>
		<a
			class="wp-block-groups-join-group-button__btn wp-block-groups-join-group-button__btn--not-logged-in"
			href="<?php echo esc_url( $login_url ); ?>"
			aria-label="<?php echo esc_attr( $button_text ); ?>"
		>
			<span class="wp-block-groups-join-group-button__label"><?php echo esc_html( $button_text ); ?></span>
		</a>
	<?php else : ?>
		<button
			class="wp-block-groups-join-group-button__btn wp-block-groups-join-group-button__btn--<?php echo esc_attr( $initial_state ); ?>"
			type="button"
			aria-label="<?php echo esc_attr( $button_text ); ?>"
		>
			<span class="wp-block-groups-join-group-button__label"><?php echo esc_html( $button_text ); ?></span>
		</button>
	<?php endif; ?>
</div>
