<?php
/**
 * Server-side render for the RSVP Button block.
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

$event_id = ! empty( $attributes['eventId'] ) ? absint( $attributes['eventId'] ) : get_the_ID();

if ( ! $event_id || 'event' !== get_post_type( $event_id ) ) {
	return;
}

$is_logged_in   = is_user_logged_in();
$current_user   = wp_get_current_user();
$user_status    = '';
$attending_count = 0;
$waitlist_count  = 0;

// Count RSVPs by status.
$attending_comments = get_comments(
	[
		'post_id'    => $event_id,
		'type'       => 'groups_rsvp',
		'status'     => 'approve',
		'meta_key'   => '_rsvp_status',
		'meta_value' => 'attending',
		'count'      => true,
	]
);
$attending_count = absint( $attending_comments );

$waitlist_comments = get_comments(
	[
		'post_id'    => $event_id,
		'type'       => 'groups_rsvp',
		'status'     => 'approve',
		'meta_key'   => '_rsvp_status',
		'meta_value' => 'waitlisted',
		'count'      => true,
	]
);
$waitlist_count = absint( $waitlist_comments );

// Get current user's RSVP status.
if ( $is_logged_in ) {
	$user_rsvp = get_comments(
		[
			'post_id' => $event_id,
			'user_id' => $current_user->ID,
			'type'    => 'groups_rsvp',
			'status'  => 'approve',
			'number'  => 1,
		]
	);

	if ( ! empty( $user_rsvp ) ) {
		$user_status = get_comment_meta( $user_rsvp[0]->comment_ID, '_rsvp_status', true );
	}
}

$initial_state = $is_logged_in ? ( $user_status ?: 'not-rsvped' ) : 'not-logged-in';
$login_url      = ! $is_logged_in ? wp_login_url( get_permalink( $event_id ) ) : '';

$data_attributes = [
	'class'               => 'wp-block-groups-rsvp-button',
	'data-event-id'       => esc_attr( $event_id ),
	'data-initial-state'  => esc_attr( $initial_state ),
	'data-attending'      => esc_attr( $attending_count ),
	'data-waitlisted'     => esc_attr( $waitlist_count ),
];

if ( $login_url ) {
	$data_attributes['data-login-url'] = esc_url( $login_url );
}

$wrapper_attributes = get_block_wrapper_attributes( $data_attributes );

$button_text = match ( $initial_state ) {
	'attending'     => __( 'Attending', 'wordpress-groups' ),
	'waitlisted'    => __( 'On Waitlist', 'wordpress-groups' ),
	'not-logged-in' => __( 'RSVP to this Event', 'wordpress-groups' ),
	default         => __( 'RSVP', 'wordpress-groups' ),
};

?>
<div <?php echo $wrapper_attributes; ?>>
	<?php if ( 'not-logged-in' === $initial_state && $login_url ) : ?>
		<div class="wp-block-groups-rsvp-button__guest-prompt">
			<p class="wp-block-groups-rsvp-button__guest-text">
				<?php esc_html_e( 'Want to attend this event?', 'wordpress-groups' ); ?>
			</p>
			<a
				class="wp-block-groups-rsvp-button__btn wp-block-groups-rsvp-button__btn--login"
				href="<?php echo esc_url( $login_url ); ?>"
				aria-label="<?php esc_attr_e( 'Log in to RSVP for this event', 'wordpress-groups' ); ?>"
			>
				<span class="wp-block-groups-rsvp-button__label"><?php esc_html_e( 'Log in to RSVP', 'wordpress-groups' ); ?></span>
			</a>
			<?php
			$register_url = wp_registration_url();
			if ( get_option( 'users_can_register' ) && $register_url ) :
			?>
				<a
					class="wp-block-groups-rsvp-button__btn wp-block-groups-rsvp-button__btn--register"
					href="<?php echo esc_url( add_query_arg( 'redirect_to', urlencode( get_permalink( $event_id ) ), $register_url ) ); ?>"
					aria-label="<?php esc_attr_e( 'Create an account to RSVP', 'wordpress-groups' ); ?>"
				>
					<span class="wp-block-groups-rsvp-button__label"><?php esc_html_e( 'Create Account', 'wordpress-groups' ); ?></span>
				</a>
			<?php endif; ?>
			<div class="wp-block-groups-rsvp-button__guest-divider">
				<span><?php esc_html_e( 'or RSVP as a guest', 'wordpress-groups' ); ?></span>
			</div>
			<form class="wp-block-groups-rsvp-button__guest-form" data-event-id="<?php echo esc_attr( $event_id ); ?>" data-api-url="<?php echo esc_url( rest_url( 'groups/v1/events/' . $event_id . '/rsvps/guest-rsvp' ) ); ?>">
				<input type="text" name="guest_name" placeholder="<?php esc_attr_e( 'Your name', 'wordpress-groups' ); ?>" required aria-label="<?php esc_attr_e( 'Your name', 'wordpress-groups' ); ?>" />
				<input type="email" name="guest_email" placeholder="<?php esc_attr_e( 'Your email', 'wordpress-groups' ); ?>" required aria-label="<?php esc_attr_e( 'Your email', 'wordpress-groups' ); ?>" />
				<button type="submit" class="wp-block-groups-rsvp-button__btn wp-block-groups-rsvp-button__btn--guest">
					<span class="wp-block-groups-rsvp-button__label"><?php esc_html_e( 'RSVP as Guest', 'wordpress-groups' ); ?></span>
				</button>
				<p class="wp-block-groups-rsvp-button__guest-note">
					<?php esc_html_e( 'We\'ll send a confirmation to your email.', 'wordpress-groups' ); ?>
				</p>
			</form>
		</div>
	<?php else : ?>
		<button
			class="wp-block-groups-rsvp-button__btn wp-block-groups-rsvp-button__btn--<?php echo esc_attr( $initial_state ); ?>"
			type="button"
			aria-label="<?php echo esc_attr( $button_text ); ?>"
		>
			<span class="wp-block-groups-rsvp-button__label"><?php echo esc_html( $button_text ); ?></span>
		</button>
	<?php endif; ?>
	<div class="wp-block-groups-rsvp-button__counts" aria-live="polite">
		<span class="wp-block-groups-rsvp-button__attending-count">
			<?php
			printf(
				/* translators: %d: number of attendees */
				esc_html( _n( '%d attending', '%d attending', $attending_count, 'wordpress-groups' ) ),
				absint( $attending_count )
			);
			?>
		</span>
		<?php if ( $waitlist_count > 0 ) : ?>
			<span class="wp-block-groups-rsvp-button__waitlist-count">
				<?php
				printf(
					/* translators: %d: number of people on waitlist */
					esc_html( _n( '%d waitlisted', '%d waitlisted', $waitlist_count, 'wordpress-groups' ) ),
					absint( $waitlist_count )
				);
				?>
			</span>
		<?php endif; ?>
	</div>
</div>
