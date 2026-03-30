<?php
/**
 * Server-side render for the Next Event block.
 *
 * Displays a callout banner linking to the next upcoming scheduled event.
 * Renders nothing if there are no upcoming events.
 *
 * @package Groups\Blocks
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content (empty for server-rendered).
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$next_event_query = new WP_Query( [
	'post_type'      => 'event',
	'post_status'    => 'event-scheduled',
	'posts_per_page' => 1,
	'meta_key'       => '_event_start_utc', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	'orderby'        => 'meta_value',
	'order'          => 'ASC',
] );

if ( ! $next_event_query->have_posts() ) {
	return; // Nothing to render.
}

$next_event_query->the_post();

$next_id        = get_the_ID();
$start_utc      = get_post_meta( $next_id, '_event_start_utc', true );
$event_timezone = get_post_meta( $next_id, '_event_timezone', true );

$next_event_title   = get_the_title();
$next_event_url     = get_permalink();
$next_event_display = '';

if ( $start_utc ) {
	try {
		$tz = $event_timezone ? new \DateTimeZone( $event_timezone ) : wp_timezone();
	} catch ( \Exception $e ) {
		$tz = wp_timezone();
	}

	$next_event_display = wp_date( get_option( 'date_format' ), strtotime( $start_utc ), $tz );
}

wp_reset_postdata();

$wrapper_attributes = get_block_wrapper_attributes( [
	'class' => 'wp-block-groups-next-event',
] );
?>

<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<a href="<?php echo esc_url( $next_event_url ); ?>" class="wp-block-groups-next-event__link">
		<span class="wp-block-groups-next-event__label"><?php esc_html_e( 'Next Event', 'wordpress-groups' ); ?></span>
		<span class="wp-block-groups-next-event__title"><?php echo esc_html( $next_event_title ); ?></span>
		<?php if ( $next_event_display ) : ?>
			<time class="wp-block-groups-next-event__date" datetime="<?php echo esc_attr( $start_utc ); ?>">
				<?php echo esc_html( $next_event_display ); ?>
			</time>
		<?php endif; ?>
		<span class="wp-block-groups-next-event__arrow" aria-hidden="true">&rarr;</span>
	</a>
</div>
