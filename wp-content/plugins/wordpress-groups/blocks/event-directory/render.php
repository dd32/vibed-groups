<?php
/**
 * Server-side render for the Event Directory block.
 *
 * Aggregates upcoming events from all sites in the multisite network,
 * sorted by start date. Designed for the central directory site.
 *
 * @package Groups\Blocks
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content (empty for server-rendered).
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$per_page       = ! empty( $attributes['perPage'] ) ? absint( $attributes['perPage'] ) : 10;
$main_site_id   = get_main_site_id();
$current_blog   = get_current_blog_id();
$is_main_site   = ( $current_blog === $main_site_id );
$now_utc        = gmdate( 'Y-m-d H:i:s' );

// Collect upcoming events — from across the network on the main site,
// or from the current site only on group sites.
$all_events = [];

if ( $is_main_site ) {
	// Network-wide aggregation for the directory front page.
	$sites = get_sites( [
		'number'       => 0,
		'site__not_in' => [ $main_site_id ],
		'public'       => 1,
		'archived'     => 0,
		'deleted'      => 0,
	] );

	foreach ( $sites as $site ) {
		switch_to_blog( $site->blog_id );

		if ( ! post_type_exists( 'event' ) ) {
			restore_current_blog();
			continue;
		}

		$group_name = get_bloginfo( 'name' );
		$group_url  = home_url( '/' );

		$events_query = new WP_Query( [
			'post_type'      => 'event',
			'posts_per_page' => $per_page,
			'post_status'    => [ 'event-scheduled', 'event-active', 'publish' ],
			'orderby'        => 'meta_value',
			'meta_key'       => '_event_start_utc', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'order'          => 'ASC',
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				[
					'key'     => '_event_start_utc',
					'value'   => $now_utc,
					'compare' => '>=',
					'type'    => 'DATETIME',
				],
			],
		] );

		foreach ( $events_query->posts as $post ) {
			$event_id  = $post->ID;
			$start_utc = get_post_meta( $event_id, '_event_start_utc', true );
			$timezone  = get_post_meta( $event_id, '_event_timezone', true );
			$venue_id  = (int) get_post_meta( $event_id, '_event_venue_id', true );
			$online    = get_post_meta( $event_id, '_event_online_link', true );

			$date_display = '';
			if ( $start_utc ) {
				try {
					$tz = $timezone ? new DateTimeZone( $timezone ) : wp_timezone();
				} catch ( Exception $e ) {
					$tz = wp_timezone();
				}
				$date_display = wp_date(
					get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
					strtotime( $start_utc ),
					$tz
				);
			}

			$venue_name = '';
			if ( $venue_id ) {
				$venue = get_post( $venue_id );
				if ( $venue && 'venue' === $venue->post_type ) {
					$venue_name = $venue->post_title;
				}
			} elseif ( $online ) {
				$venue_name = __( 'Online', 'wordpress-groups' );
			}

			$all_events[] = [
				'title'        => $post->post_title,
				'permalink'    => get_permalink( $event_id ),
				'start_utc'    => $start_utc,
				'date_display' => $date_display,
				'venue_name'   => $venue_name,
				'group_name'   => $group_name,
				'group_url'    => $group_url,
			];
		}

		restore_current_blog();
	}

	usort( $all_events, function ( $a, $b ) {
		return strcmp( $a['start_utc'], $b['start_utc'] );
	} );

	$all_events = array_slice( $all_events, 0, $per_page );
} else {
	// Single-site query for group sites.
	$events_query = new WP_Query( [
		'post_type'      => 'event',
		'posts_per_page' => $per_page,
		'post_status'    => [ 'event-scheduled', 'event-active', 'publish' ],
		'orderby'        => 'meta_value',
		'meta_key'       => '_event_start_utc', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'order'          => 'ASC',
		'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			[
				'key'     => '_event_start_utc',
				'value'   => $now_utc,
				'compare' => '>=',
				'type'    => 'DATETIME',
			],
		],
	] );

	foreach ( $events_query->posts as $post ) {
		$event_id  = $post->ID;
		$start_utc = get_post_meta( $event_id, '_event_start_utc', true );
		$timezone  = get_post_meta( $event_id, '_event_timezone', true );
		$venue_id  = (int) get_post_meta( $event_id, '_event_venue_id', true );
		$online    = get_post_meta( $event_id, '_event_online_link', true );

		$date_display = '';
		if ( $start_utc ) {
			try {
				$tz = $timezone ? new DateTimeZone( $timezone ) : wp_timezone();
			} catch ( Exception $e ) {
				$tz = wp_timezone();
			}
			$date_display = wp_date(
				get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
				strtotime( $start_utc ),
				$tz
			);
		}

		$venue_name = '';
		if ( $venue_id ) {
			$venue = get_post( $venue_id );
			if ( $venue && 'venue' === $venue->post_type ) {
				$venue_name = $venue->post_title;
			}
		} elseif ( $online ) {
			$venue_name = __( 'Online', 'wordpress-groups' );
		}

		$all_events[] = [
			'title'        => $post->post_title,
			'permalink'    => get_permalink( $event_id ),
			'start_utc'    => $start_utc,
			'date_display' => $date_display,
			'venue_name'   => $venue_name,
			'group_name'   => '',
			'group_url'    => '',
		];
	}
}

$wrapper_attrs = [
	'class'         => 'wp-block-groups-event-directory',
	'data-per-page' => esc_attr( $per_page ),
];

if ( $is_main_site ) {
	$wrapper_attrs['data-network'] = 'true';
}

$wrapper_attributes = get_block_wrapper_attributes( $wrapper_attrs );
?>

<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="wp-block-groups-event-directory__server-render">
		<?php if ( ! empty( $all_events ) ) : ?>
			<ul class="wp-block-groups-event-directory__list">
				<?php foreach ( $all_events as $event ) : ?>
					<li class="wp-block-groups-event-directory__event-item">
						<a href="<?php echo esc_url( $event['permalink'] ); ?>" class="wp-block-groups-event-directory__event-link">
							<?php echo esc_html( $event['title'] ); ?>
						</a>
						<?php if ( $event['date_display'] ) : ?>
							<span class="wp-block-groups-event-directory__event-date">
								<?php echo esc_html( $event['date_display'] ); ?>
							</span>
						<?php endif; ?>
						<span class="wp-block-groups-event-directory__event-group">
							<a href="<?php echo esc_url( $event['group_url'] ); ?>">
								<?php echo esc_html( $event['group_name'] ); ?>
							</a>
						</span>
						<?php if ( $event['venue_name'] ) : ?>
							<span class="wp-block-groups-event-directory__event-venue">
								<?php echo esc_html( $event['venue_name'] ); ?>
							</span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p class="wp-block-groups-event-directory__empty">
				<?php esc_html_e( 'No upcoming events found.', 'wordpress-groups' ); ?>
			</p>
		<?php endif; ?>
	</div>
</div>
