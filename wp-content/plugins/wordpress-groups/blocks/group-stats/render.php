<?php
/**
 * Server-side render for the Group Stats block.
 *
 * Displays compact stat cards: Members, Events, and Founded date.
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
 * 4. Founded date — when the site was registered.
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
	<div class="wp-block-groups-group-stats__cards">
		<div class="wp-block-groups-group-stats__card wp-block-groups-group-stats__card--members">
			<span class="wp-block-groups-group-stats__value"><?php echo esc_html( number_format_i18n( $total_members ) ); ?></span>
			<span class="wp-block-groups-group-stats__label"><?php esc_html_e( 'Members', 'wordpress-groups' ); ?></span>
		</div>

		<div class="wp-block-groups-group-stats__card wp-block-groups-group-stats__card--events">
			<span class="wp-block-groups-group-stats__value"><?php echo esc_html( number_format_i18n( $total_upcoming_events + $total_past_events ) ); ?></span>
			<span class="wp-block-groups-group-stats__label">
				<?php
				if ( $total_upcoming_events > 0 ) {
					printf(
						/* translators: 1: upcoming count, 2: past count. */
						esc_html__( '%1$s upcoming · %2$s held', 'wordpress-groups' ),
						esc_html( number_format_i18n( $total_upcoming_events ) ),
						esc_html( number_format_i18n( $total_past_events ) )
					);
				} else {
					esc_html_e( 'Events held', 'wordpress-groups' );
				}
				?>
			</span>
		</div>

		<?php if ( $founded_year ) : ?>
			<div class="wp-block-groups-group-stats__card wp-block-groups-group-stats__card--founded">
				<span class="wp-block-groups-group-stats__value"><?php echo esc_html( $founded_year ); ?></span>
				<span class="wp-block-groups-group-stats__label"><?php esc_html_e( 'Founded', 'wordpress-groups' ); ?></span>
			</div>
		<?php endif; ?>
	</div>
</div>
