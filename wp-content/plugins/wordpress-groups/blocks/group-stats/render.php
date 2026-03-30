<?php
/**
 * Server-side render for the Group Stats block.
 *
 * Displays a next-event callout (when applicable) followed by compact
 * stat cards: Members, Events (upcoming + held), and Founded date.
 *
 * @package Groups\Blocks
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content (empty for server-rendered).
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

/*
 * 1. Total members.
 */
$member_count  = count_users();
$total_members = isset( $member_count['total_users'] ) ? (int) $member_count['total_users'] : 0;

/*
 * 2. Events held (past).
 */
$past_events_query = new WP_Query( [
	'post_type'              => 'event',
	'post_status'            => 'event-past',
	'posts_per_page'         => 1,
	'no_found_rows'          => false,
	'update_post_meta_cache' => false,
	'update_post_term_cache' => false,
	'fields'                 => 'ids',
] );
$total_past_events = (int) $past_events_query->found_posts;

/*
 * 3. Upcoming events.
 */
$upcoming_events_query = new WP_Query( [
	'post_type'              => 'event',
	'post_status'            => 'event-scheduled',
	'posts_per_page'         => 1,
	'no_found_rows'          => false,
	'update_post_meta_cache' => false,
	'update_post_term_cache' => false,
	'fields'                 => 'ids',
] );
$total_upcoming_events = (int) $upcoming_events_query->found_posts;

/*
 * 4. Next event — the earliest scheduled event.
 */
$next_event_query = new WP_Query( [
	'post_type'      => 'event',
	'post_status'    => 'event-scheduled',
	'posts_per_page' => 1,
	'meta_key'       => '_event_start_utc', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	'orderby'        => 'meta_value',
	'order'          => 'ASC',
] );

$next_event_title   = '';
$next_event_date    = '';
$next_event_display = '';
$next_event_url     = '';

if ( $next_event_query->have_posts() ) {
	$next_event_query->the_post();
	$next_id        = get_the_ID();
	$start_utc      = get_post_meta( $next_id, '_event_start_utc', true );
	$event_timezone = get_post_meta( $next_id, '_event_timezone', true );

	$next_event_title = get_the_title();
	$next_event_url   = get_permalink();

	if ( $start_utc ) {
		try {
			$tz = $event_timezone ? new \DateTimeZone( $event_timezone ) : wp_timezone();
		} catch ( \Exception $e ) {
			$tz = wp_timezone();
		}

		$next_event_date    = $start_utc;
		$next_event_display = wp_date( get_option( 'date_format' ), strtotime( $start_utc ), $tz );
	}
	wp_reset_postdata();
}

/*
 * 5. Founded date — when the site was registered.
 */
$site_id      = get_current_blog_id();
$site_details = get_blog_details( $site_id );
$founded_year = '';

if ( $site_details && ! empty( $site_details->registered ) ) {
	$founded_year = wp_date( 'Y', strtotime( $site_details->registered ) );
}

$wrapper_attributes = get_block_wrapper_attributes( [
	'class' => 'wp-block-groups-group-stats',
] );
?>

<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

	<?php if ( $next_event_title ) : ?>
		<a href="<?php echo esc_url( $next_event_url ); ?>" class="wp-block-groups-group-stats__next-event">
			<span class="wp-block-groups-group-stats__next-event-label"><?php esc_html_e( 'Next Event', 'wordpress-groups' ); ?></span>
			<span class="wp-block-groups-group-stats__next-event-title"><?php echo esc_html( $next_event_title ); ?></span>
			<?php if ( $next_event_display ) : ?>
				<time class="wp-block-groups-group-stats__next-event-date" datetime="<?php echo esc_attr( $next_event_date ); ?>">
					<?php echo esc_html( $next_event_display ); ?>
				</time>
			<?php endif; ?>
			<span class="wp-block-groups-group-stats__next-event-arrow" aria-hidden="true">&rarr;</span>
		</a>
	<?php endif; ?>

	<div class="wp-block-groups-group-stats__cards">
		<div class="wp-block-groups-group-stats__card wp-block-groups-group-stats__card--members">
			<span class="wp-block-groups-group-stats__value"><?php echo esc_html( number_format_i18n( $total_members ) ); ?></span>
			<span class="wp-block-groups-group-stats__label"><?php esc_html_e( 'Members', 'wordpress-groups' ); ?></span>
		</div>

		<div class="wp-block-groups-group-stats__card wp-block-groups-group-stats__card--events">
			<span class="wp-block-groups-group-stats__value">
				<?php
				if ( $total_upcoming_events > 0 ) {
					printf(
						/* translators: 1: Number of upcoming events, 2: Number of past events. */
						esc_html__( '%1$s upcoming · %2$s held', 'wordpress-groups' ),
						esc_html( number_format_i18n( $total_upcoming_events ) ),
						esc_html( number_format_i18n( $total_past_events ) )
					);
				} else {
					printf(
						/* translators: %s: Number of past events. */
						esc_html__( '%s held', 'wordpress-groups' ),
						esc_html( number_format_i18n( $total_past_events ) )
					);
				}
				?>
			</span>
			<span class="wp-block-groups-group-stats__label"><?php esc_html_e( 'Events', 'wordpress-groups' ); ?></span>
		</div>

		<?php if ( $founded_year ) : ?>
			<div class="wp-block-groups-group-stats__card wp-block-groups-group-stats__card--founded">
				<span class="wp-block-groups-group-stats__value"><?php echo esc_html( $founded_year ); ?></span>
				<span class="wp-block-groups-group-stats__label"><?php esc_html_e( 'Founded', 'wordpress-groups' ); ?></span>
			</div>
		<?php endif; ?>
	</div>
</div>
