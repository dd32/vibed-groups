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
	'not-logged-in' => __( 'Log in to RSVP', 'wordpress-groups' ),
	default         => __( 'RSVP', 'wordpress-groups' ),
};

?>
<div <?php echo $wrapper_attributes; ?>>
	<?php if ( 'not-logged-in' === $initial_state && $login_url ) : ?>
		<a
			class="wp-block-groups-rsvp-button__btn wp-block-groups-rsvp-button__btn--not-logged-in"
			href="<?php echo esc_url( $login_url ); ?>"
			aria-label="<?php echo esc_attr( $button_text ); ?>"
		>
			<span class="wp-block-groups-rsvp-button__label"><?php echo esc_html( $button_text ); ?></span>
		</a>
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
