<?php
/**
 * Server-side render for the Notification Preferences block.
 *
 * Provides initial preference state so the view script can hydrate.
 * Only renders for logged-in users.
 *
 * @package Groups
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block default content.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_user_logged_in() ) {
	return;
}

$user_id = get_current_user_id();

$notification_types = [
	'event_reminders'    => __( 'Event Reminders', 'wordpress-groups' ),
	'announcements'      => __( 'Announcements', 'wordpress-groups' ),
	'rsvp_confirmations' => __( 'RSVP Confirmations', 'wordpress-groups' ),
];

$descriptions = [
	'event_reminders'    => __( 'Get notified before events you have RSVP\'d to.', 'wordpress-groups' ),
	'announcements'      => __( 'Receive group announcements and updates.', 'wordpress-groups' ),
	'rsvp_confirmations' => __( 'Get confirmation emails when you RSVP to events.', 'wordpress-groups' ),
];

$preferences = [];
foreach ( array_keys( $notification_types ) as $type ) {
	$preferences[ $type ] = \Groups\Notifications\User_Preferences::is_opted_in( $user_id, $type );
}

$wrapper_attributes = get_block_wrapper_attributes( [
	'class'            => 'wp-block-groups-notification-preferences',
	'data-preferences' => wp_json_encode( $preferences ),
	'data-nonce'       => wp_create_nonce( 'wp_rest' ),
] );

?>
<div <?php echo $wrapper_attributes; ?>>
	<h3 class="wp-block-groups-notification-preferences__heading">
		<?php esc_html_e( 'Notification Preferences', 'wordpress-groups' ); ?>
	</h3>
	<p class="wp-block-groups-notification-preferences__description">
		<?php esc_html_e( 'Choose which notifications you would like to receive.', 'wordpress-groups' ); ?>
	</p>
	<div class="wp-block-groups-notification-preferences__list">
		<?php foreach ( $notification_types as $type => $label ) : ?>
			<div
				class="wp-block-groups-notification-preferences__item"
				data-type="<?php echo esc_attr( $type ); ?>"
			>
				<div class="wp-block-groups-notification-preferences__item-info">
					<label
						class="wp-block-groups-notification-preferences__label"
						for="notification-pref-<?php echo esc_attr( $type ); ?>"
					>
						<?php echo esc_html( $label ); ?>
					</label>
					<span class="wp-block-groups-notification-preferences__item-description">
						<?php echo esc_html( $descriptions[ $type ] ); ?>
					</span>
				</div>
				<button
					type="button"
					role="switch"
					id="notification-pref-<?php echo esc_attr( $type ); ?>"
					class="wp-block-groups-notification-preferences__toggle <?php echo $preferences[ $type ] ? 'is-checked' : ''; ?>"
					aria-checked="<?php echo $preferences[ $type ] ? 'true' : 'false'; ?>"
				>
					<span class="wp-block-groups-notification-preferences__toggle-track">
						<span class="wp-block-groups-notification-preferences__toggle-thumb"></span>
					</span>
					<span class="screen-reader-text">
						<?php
						printf(
							/* translators: %s: notification type label */
							esc_html__( 'Toggle %s', 'wordpress-groups' ),
							esc_html( $label )
						);
						?>
					</span>
				</button>
			</div>
		<?php endforeach; ?>
	</div>
	<div class="wp-block-groups-notification-preferences__status" aria-live="polite"></div>
</div>
