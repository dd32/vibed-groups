<?php
/**
 * Server-side render for the Deputy Dashboard block.
 *
 * Displays a network overview for super admins: total groups, pending
 * applications, dormant groups count, and recent network activity.
 *
 * Only visible to super administrators.
 *
 * @package Groups\Blocks
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content (empty for server-rendered).
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

// Only visible to super admins.
if ( ! is_super_admin() ) {
	return;
}

// Switch to the main site for wp_meetup queries.
$main_site_id  = get_main_site_id();
$current_site  = get_current_blog_id();
$switched      = false;

if ( $current_site !== $main_site_id ) {
	switch_to_blog( $main_site_id );
	$switched = true;
}

/*
 * 1. Total groups — all wp_meetup posts with active-like statuses.
 */
$total_groups_query = new WP_Query( [
	'post_type'              => 'wp_meetup',
	'post_status'            => [ 'meetup-active', 'meetup-dormant', 'meetup-suspended' ],
	'posts_per_page'         => 1,
	'no_found_rows'          => false,
	'update_post_meta_cache' => false,
	'update_post_term_cache' => false,
	'fields'                 => 'ids',
] );
$total_groups = (int) $total_groups_query->found_posts;

/*
 * 2. Pending applications — wp_meetup posts in the pipeline statuses.
 */
$pending_query = new WP_Query( [
	'post_type'              => 'wp_meetup',
	'post_status'            => [ 'meetup-pending', 'meetup-vetting', 'meetup-feedback', 'meetup-orientation', 'meetup-scheduling' ],
	'posts_per_page'         => 1,
	'no_found_rows'          => false,
	'update_post_meta_cache' => false,
	'update_post_term_cache' => false,
	'fields'                 => 'ids',
] );
$pending_count = (int) $pending_query->found_posts;

/*
 * 3. Dormant groups count.
 */
$dormant_query = new WP_Query( [
	'post_type'              => 'wp_meetup',
	'post_status'            => 'meetup-dormant',
	'posts_per_page'         => 1,
	'no_found_rows'          => false,
	'update_post_meta_cache' => false,
	'update_post_term_cache' => false,
	'fields'                 => 'ids',
] );
$dormant_count = (int) $dormant_query->found_posts;

/*
 * 4. Active groups count.
 */
$active_query = new WP_Query( [
	'post_type'              => 'wp_meetup',
	'post_status'            => 'meetup-active',
	'posts_per_page'         => 1,
	'no_found_rows'          => false,
	'update_post_meta_cache' => false,
	'update_post_term_cache' => false,
	'fields'                 => 'ids',
] );
$active_count = (int) $active_query->found_posts;

if ( $switched ) {
	restore_current_blog();
}

/*
 * 5. Recent activity from the analytics_daily table (last 30 days).
 */
$date_to   = wp_date( 'Y-m-d' );
$date_from = wp_date( 'Y-m-d', strtotime( '-30 days' ) );

$network_summary = \Groups\Database\Analytics_Table::get_network_summary( $date_from, $date_to );

$events_last_30 = isset( $network_summary['events_held'] ) ? (int) $network_summary['events_held'] : 0;
$rsvps_last_30  = isset( $network_summary['total_rsvps'] ) ? (int) $network_summary['total_rsvps'] : 0;

/*
 * 6. Recent activity log entries (network-wide).
 */
$recent_activity = \Groups\Database\Activity_Log_Table::query( [
	'limit' => 10,
] );

/*
 * Build the stat cards.
 */
$cards = [
	[
		'key'   => 'total-groups',
		'icon'  => 'networking',
		'value' => number_format_i18n( $total_groups ),
		'label' => __( 'Total Groups', 'wordpress-groups' ),
	],
	[
		'key'   => 'active-groups',
		'icon'  => 'yes-alt',
		'value' => number_format_i18n( $active_count ),
		'label' => __( 'Active Groups', 'wordpress-groups' ),
	],
	[
		'key'   => 'pending-apps',
		'icon'  => 'editor-help',
		'value' => number_format_i18n( $pending_count ),
		'label' => __( 'Pending Applications', 'wordpress-groups' ),
	],
	[
		'key'   => 'dormant-groups',
		'icon'  => 'warning',
		'value' => number_format_i18n( $dormant_count ),
		'label' => __( 'Dormant Groups', 'wordpress-groups' ),
	],
	[
		'key'   => 'events-30d',
		'icon'  => 'calendar',
		'value' => number_format_i18n( $events_last_30 ),
		'label' => __( 'Events (30 days)', 'wordpress-groups' ),
	],
	[
		'key'   => 'rsvps-30d',
		'icon'  => 'groups',
		'value' => number_format_i18n( $rsvps_last_30 ),
		'label' => __( 'RSVPs (30 days)', 'wordpress-groups' ),
	],
];

/*
 * Map activity action names to human-readable labels.
 */
$action_labels = [
	'member_joined'        => __( 'joined a group', 'wordpress-groups' ),
	'member_left'          => __( 'left a group', 'wordpress-groups' ),
	'role_changed'         => __( 'role changed', 'wordpress-groups' ),
	'member_banned'        => __( 'was banned', 'wordpress-groups' ),
	'rsvp_created'         => __( 'RSVP\'d to an event', 'wordpress-groups' ),
	'rsvp_promoted'        => __( 'promoted from waitlist', 'wordpress-groups' ),
	'event_status_changed' => __( 'event status changed', 'wordpress-groups' ),
	'event_created'        => __( 'created an event', 'wordpress-groups' ),
];

$wrapper_attributes = get_block_wrapper_attributes( [
	'class' => 'wp-block-groups-deputy-dashboard',
] );
?>

<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Generated by get_block_wrapper_attributes(). ?> data-loaded>
	<div class="wp-block-groups-deputy-dashboard__loading" role="status">
		<span class="wp-block-groups-deputy-dashboard__loading-spinner" aria-hidden="true"></span>
		<?php esc_html_e( 'Loading dashboard…', 'wordpress-groups' ); ?>
	</div>
	<h2 class="wp-block-groups-deputy-dashboard__heading">
		<?php esc_html_e( 'Deputy Dashboard', 'wordpress-groups' ); ?>
	</h2>

	<div class="wp-block-groups-deputy-dashboard__cards">
		<?php foreach ( $cards as $card ) : ?>
			<div class="wp-block-groups-deputy-dashboard__card wp-block-groups-deputy-dashboard__card--<?php echo esc_attr( $card['key'] ); ?>">
				<span class="wp-block-groups-deputy-dashboard__card-icon dashicons dashicons-<?php echo esc_attr( $card['icon'] ); ?>" aria-hidden="true"></span>
				<span class="wp-block-groups-deputy-dashboard__card-value"><?php echo esc_html( $card['value'] ); ?></span>
				<span class="wp-block-groups-deputy-dashboard__card-label"><?php echo esc_html( $card['label'] ); ?></span>
			</div>
		<?php endforeach; ?>
	</div>

	<?php
	// At-risk and dormant groups detail.
	if ( $switched ) {
		switch_to_blog( $main_site_id );
	}
	$at_risk_groups = get_posts( [
		'post_type'      => 'wp_meetup',
		'post_status'    => [ 'meetup-active', 'meetup-dormant' ],
		'posts_per_page' => 10,
		'meta_query'     => [
			'relation' => 'OR',
			[ 'key' => '_meetup_at_risk', 'value' => '1' ],
			// Also include dormant.
		],
		'orderby'        => 'title',
		'order'          => 'ASC',
	] );
	// Also get dormant ones directly.
	$dormant_groups = get_posts( [
		'post_type'      => 'wp_meetup',
		'post_status'    => 'meetup-dormant',
		'posts_per_page' => 10,
		'fields'         => 'ids',
	] );
	$at_risk_ids = array_merge(
		wp_list_pluck( $at_risk_groups, 'ID' ),
		$dormant_groups
	);
	$at_risk_ids = array_unique( $at_risk_ids );

	if ( $switched ) {
		restore_current_blog();
	}
	?>

	<?php if ( ! empty( $at_risk_ids ) ) : ?>
		<div class="wp-block-groups-deputy-dashboard__section">
			<h3 class="wp-block-groups-deputy-dashboard__section-title">
				<?php esc_html_e( 'Groups Needing Attention', 'wordpress-groups' ); ?>
			</h3>
			<ul class="wp-block-groups-deputy-dashboard__risk-list">
				<?php
				foreach ( $at_risk_ids as $group_id ) {
					$group_post   = get_post( $group_id );
					if ( ! $group_post ) continue;
					$is_dormant   = 'meetup-dormant' === get_post_status( $group_id );
					$last_event   = get_post_meta( $group_id, '_meetup_last_event_date', true );
					$days_ago     = $last_event ? floor( ( time() - strtotime( $last_event ) ) / DAY_IN_SECONDS ) : '?';
					$badge_class  = $is_dormant ? 'dormant' : 'at-risk';
					$badge_label  = $is_dormant ? __( 'Dormant', 'wordpress-groups' ) : __( 'At Risk', 'wordpress-groups' );
					?>
					<li class="wp-block-groups-deputy-dashboard__risk-item">
						<span class="wp-block-groups-deputy-dashboard__risk-badge wp-block-groups-deputy-dashboard__risk-badge--<?php echo esc_attr( $badge_class ); ?>">
							<?php echo esc_html( $badge_label ); ?>
						</span>
						<strong><?php echo esc_html( $group_post->post_title ); ?></strong>
						<span class="wp-block-groups-deputy-dashboard__risk-meta">
							<?php
							printf(
								/* translators: %s: number of days */
								esc_html__( '%s days since last event', 'wordpress-groups' ),
								esc_html( $days_ago )
							);
							?>
						</span>
					</li>
					<?php
				}
				?>
			</ul>
		</div>
	<?php endif; ?>

	<?php if ( $recent_activity ) : ?>
		<div class="wp-block-groups-deputy-dashboard__section">
			<h3 class="wp-block-groups-deputy-dashboard__section-title">
				<?php esc_html_e( 'Recent Network Activity', 'wordpress-groups' ); ?>
			</h3>
			<ul class="wp-block-groups-deputy-dashboard__activity-list">
				<?php foreach ( $recent_activity as $entry ) : ?>
					<li class="wp-block-groups-deputy-dashboard__activity-item">
						<span class="wp-block-groups-deputy-dashboard__activity-action">
							<?php
							$user_display = '';
							if ( ! empty( $entry->user_id ) ) {
								$activity_user = get_user_by( 'id', $entry->user_id );
								$user_display  = $activity_user ? $activity_user->display_name : __( 'Unknown user', 'wordpress-groups' );
							}

							$action_label = isset( $action_labels[ $entry->action ] )
								? $action_labels[ $entry->action ]
								: esc_html( $entry->action );

							if ( $user_display ) {
								printf(
									'<strong>%s</strong> %s',
									esc_html( $user_display ),
									esc_html( $action_label )
								);
							} else {
								echo esc_html( $action_label );
							}
							?>
						</span>
						<span class="wp-block-groups-deputy-dashboard__activity-meta">
							<?php
							$blog_details = get_blog_details( $entry->blog_id );
							if ( $blog_details ) {
								printf(
									'<span class="wp-block-groups-deputy-dashboard__activity-group">%s</span>',
									esc_html( $blog_details->blogname )
								);
							}
							?>
							<time class="wp-block-groups-deputy-dashboard__activity-time" datetime="<?php echo esc_attr( $entry->created_at ); ?>">
								<?php
								echo esc_html(
									human_time_diff( strtotime( $entry->created_at ), current_time( 'timestamp' ) )
									. ' ' . __( 'ago', 'wordpress-groups' )
								);
								?>
							</time>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>
</div>
