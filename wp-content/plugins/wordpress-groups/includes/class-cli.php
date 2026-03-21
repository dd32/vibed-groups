<?php
/**
 * WP-CLI commands for WordPress Groups.
 *
 * Provides CLI access to common operations:
 *   wp groups list
 *   wp groups aggregate [--date=Y-m-d]
 *   wp groups dormancy-check
 *
 * @package Groups
 */

namespace Groups;

use WP_CLI;
use WP_CLI\Formatter;

defined( 'ABSPATH' ) || exit;

/**
 * Manage WordPress community groups.
 */
class CLI {

	/**
	 * List all group sites.
	 *
	 * Queries wp_meetup posts across all statuses and displays them in a
	 * table with ID, title, status, and linked site ID.
	 *
	 * ## OPTIONS
	 *
	 * [--status=<status>]
	 * : Filter by post status. Default is any.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 *   - count
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp groups list
	 *     wp groups list --status=meetup-active
	 *     wp groups list --format=csv
	 *
	 * @subcommand list
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function list_( $args, $assoc_args ) {
		$query_args = [
			'post_type'      => 'wp_meetup',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		];

		if ( ! empty( $assoc_args['status'] ) ) {
			$query_args['post_status'] = sanitize_text_field( $assoc_args['status'] );
		} else {
			$query_args['post_status'] = 'any';
		}

		$posts = get_posts( $query_args );

		if ( empty( $posts ) ) {
			WP_CLI::warning( 'No groups found.' );
			return;
		}

		$items = [];

		foreach ( $posts as $post ) {
			$site_id = get_post_meta( $post->ID, '_meetup_site_id', true );

			$items[] = [
				'ID'      => $post->ID,
				'title'   => $post->post_title,
				'status'  => $post->post_status,
				'site_id' => $site_id ?: '—',
			];
		}

		$format = $assoc_args['format'] ?? 'table';

		$formatter = new Formatter(
			$assoc_args,
			[ 'ID', 'title', 'status', 'site_id' ]
		);

		$formatter->display_items( $items );
	}

	/**
	 * Run analytics aggregation.
	 *
	 * Triggers the daily analytics aggregation for a specific date.
	 * Defaults to yesterday if no date is provided.
	 *
	 * ## OPTIONS
	 *
	 * [--date=<date>]
	 * : Date to aggregate in Y-m-d format. Defaults to yesterday.
	 *
	 * ## EXAMPLES
	 *
	 *     wp groups aggregate
	 *     wp groups aggregate --date=2025-12-01
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function aggregate( $args, $assoc_args ) {
		$date = $assoc_args['date'] ?? gmdate( 'Y-m-d', strtotime( '-1 day' ) );

		// Validate the date format.
		$parsed = \DateTime::createFromFormat( 'Y-m-d', $date );

		if ( ! $parsed || $parsed->format( 'Y-m-d' ) !== $date ) {
			WP_CLI::error( "Invalid date format: {$date}. Use Y-m-d (e.g. 2025-12-01)." );
		}

		WP_CLI::log( "Running analytics aggregation for {$date}..." );

		Analytics\Aggregator::aggregate_for_date( $date );

		WP_CLI::success( "Analytics aggregation complete for {$date}." );
	}

	/**
	 * Run dormancy detection.
	 *
	 * Checks all active groups for inactivity and flags them as at-risk
	 * or dormant based on their last event date.
	 *
	 * ## EXAMPLES
	 *
	 *     wp groups dormancy-check
	 *
	 * @subcommand dormancy-check
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function dormancy_check( $args, $assoc_args ) {
		WP_CLI::log( 'Running dormancy detection...' );

		$detector = new Analytics\Dormancy_Detector();
		$detector->run();

		WP_CLI::success( 'Dormancy check complete.' );
	}
}
