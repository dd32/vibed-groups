<?php
/**
 * Server-side render for the Upcoming Events block.
 *
 * Displays upcoming events as visually attractive cards with date badge,
 * title, venue, and attendee count.
 *
 * @package Groups\Blocks
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$count = ! empty( $attributes['count'] ) ? absint( $attributes['count'] ) : 5;

$events_query = new WP_Query( [
	'post_type'      => 'event',
	'posts_per_page' => $count,
	'post_status'    => [ 'event-scheduled', 'publish' ],
	'orderby'        => 'meta_value',
	'meta_key'       => '_event_start_utc', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	'order'          => 'ASC',
	'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		[
			'key'     => '_event_start_utc',
			'value'   => gmdate( 'Y-m-d H:i:s' ),
			'compare' => '>=',
			'type'    => 'DATETIME',
		],
	],
] );

$wrapper_attributes = get_block_wrapper_attributes( [
	'class' => 'wp-block-groups-upcoming-events',
] );
?>

<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php if ( $events_query->have_posts() ) : ?>
		<div class="wp-block-groups-upcoming-events__grid">
			<?php while ( $events_query->have_posts() ) : ?>
				<?php $events_query->the_post(); ?>
				<?php
				$event_id   = get_the_ID();
				$start_utc  = get_post_meta( $event_id, '_event_start_utc', true );
				$end_utc    = get_post_meta( $event_id, '_event_end_utc', true );
				$timezone   = get_post_meta( $event_id, '_event_timezone', true );
				$venue_id   = (int) get_post_meta( $event_id, '_event_venue_id', true );
				$online     = get_post_meta( $event_id, '_event_online_link', true );

				// Format date/time.
				$month_short   = '';
				$day_num       = '';
				$time_display  = '';
				if ( $start_utc ) {
					try {
						$tz = $timezone ? new DateTimeZone( $timezone ) : wp_timezone();
					} catch ( Exception $e ) {
						$tz = wp_timezone();
					}
					$ts          = strtotime( $start_utc );
					$month_short = wp_date( 'M', $ts, $tz );
					$day_num     = wp_date( 'j', $ts, $tz );
					$time_display = wp_date( get_option( 'time_format' ), $ts, $tz );

					if ( $end_utc ) {
						$time_display .= ' – ' . wp_date( get_option( 'time_format' ), strtotime( $end_utc ), $tz );
					}
					if ( $timezone ) {
						$time_display .= ' ' . wp_date( 'T', $ts, $tz );
					}
				}

				// Venue name.
				$venue_name = '';
				if ( $venue_id ) {
					$venue = get_post( $venue_id );
					if ( $venue && 'venue' === $venue->post_type ) {
						$venue_name = $venue->post_title;
					}
				} elseif ( $online ) {
					$venue_name = __( 'Online', 'wordpress-groups' );
				}

				// RSVP count.
				$attending_count = (int) get_comments( [
					'post_id'    => $event_id,
					'status'     => 'approve',
					'meta_key'   => '_rsvp_status', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value' => 'attending', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					'count'      => true,
				] );
				?>
				<a href="<?php the_permalink(); ?>" class="wp-block-groups-upcoming-events__card">
					<div class="wp-block-groups-upcoming-events__date-badge">
						<span class="wp-block-groups-upcoming-events__date-month"><?php echo esc_html( $month_short ); ?></span>
						<span class="wp-block-groups-upcoming-events__date-day"><?php echo esc_html( $day_num ); ?></span>
					</div>
					<div class="wp-block-groups-upcoming-events__card-content">
						<h3 class="wp-block-groups-upcoming-events__title"><?php the_title(); ?></h3>
						<div class="wp-block-groups-upcoming-events__meta">
							<?php if ( $time_display ) : ?>
								<span class="wp-block-groups-upcoming-events__time">
									<?php echo esc_html( $time_display ); ?>
								</span>
							<?php endif; ?>
							<?php if ( $venue_name ) : ?>
								<span class="wp-block-groups-upcoming-events__venue"><?php echo esc_html( $venue_name ); ?></span>
							<?php endif; ?>
						</div>
						<?php if ( $attending_count > 0 ) : ?>
							<span class="wp-block-groups-upcoming-events__rsvp-count">
								<?php
								printf(
									/* translators: %d: Number of attendees. */
									esc_html( _n( '%d attending', '%d attending', $attending_count, 'wordpress-groups' ) ),
									$attending_count
								);
								?>
							</span>
						<?php endif; ?>
					</div>
				</a>
			<?php endwhile; ?>
			<?php wp_reset_postdata(); ?>
		</div>
	<?php else : ?>
		<p class="wp-block-groups-upcoming-events__empty">
			<?php esc_html_e( 'No upcoming events scheduled.', 'wordpress-groups' ); ?>
		</p>
	<?php endif; ?>
</div>
