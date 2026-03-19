<?php
/**
 * Dormancy detector — flags groups with no recent events as at-risk
 * or dormant via a daily WP-Cron check.
 *
 * @package Groups\Analytics
 */

namespace Groups\Analytics;

use Groups\Notifications\Email_Notifier;

defined( 'ABSPATH' ) || exit;

/**
 * Monitors wp_meetup posts for inactivity and manages the at-risk → dormant
 * lifecycle via scheduled cron checks.
 */
class Dormancy_Detector {

	/**
	 * Cron hook name.
	 *
	 * @var string
	 */
	const CRON_HOOK = 'groups_dormancy_check';

	/**
	 * Days of inactivity before a group is flagged at-risk.
	 *
	 * @var int
	 */
	const AT_RISK_DAYS = 60;

	/**
	 * Days of inactivity before a group transitions to dormant.
	 *
	 * @var int
	 */
	const DORMANT_DAYS = 90;

	/**
	 * Post meta key for the last event date.
	 *
	 * @var string
	 */
	const LAST_EVENT_META = '_meetup_last_event_date';

	/**
	 * Post meta key for at-risk flag.
	 *
	 * @var string
	 */
	const AT_RISK_META = '_meetup_at_risk';

	/**
	 * Post meta key for the assigned deputy.
	 *
	 * @var string
	 */
	const DEPUTY_META = '_meetup_deputy_assigned';

	/**
	 * Constructor. Registers hooks.
	 */
	public function __construct() {
		add_action( self::CRON_HOOK, [ $this, 'run' ] );
	}

	/**
	 * Schedule the daily cron event if not already scheduled.
	 *
	 * Should be called during plugin initialisation.
	 */
	public static function schedule_cron(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Unschedule the cron event.
	 *
	 * Called on plugin deactivation.
	 */
	public static function unschedule_cron(): void {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	/**
	 * Run the dormancy check across all active groups.
	 */
	public function run(): void {
		$posts = get_posts( [
			'post_type'      => 'wp_meetup',
			'post_status'    => 'meetup-active',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		] );

		foreach ( $posts as $post_id ) {
			self::check_group( (int) $post_id );
		}
	}

	/**
	 * Check a single group for dormancy.
	 *
	 * Evaluates the group's last event date and applies the at-risk or
	 * dormant status as appropriate.
	 *
	 * @param int $post_id The wp_meetup post ID.
	 * @return string|null 'dormant', 'at_risk', or null if no action taken.
	 */
	public static function check_group( int $post_id ): ?string {
		$post = get_post( $post_id );

		if ( ! $post || 'wp_meetup' !== $post->post_type ) {
			return null;
		}

		// Skip groups that are not active.
		if ( 'meetup-active' !== $post->post_status ) {
			return null;
		}

		$last_event_date = self::get_last_event_date( $post_id );

		if ( ! $last_event_date ) {
			// No event date recorded — use activation date or post date as fallback.
			$activation_date = get_post_meta( $post_id, '_meetup_activation_date', true );
			$last_event_date = $activation_date ?: $post->post_date_gmt;
		}

		$last_event_time = strtotime( $last_event_date );

		if ( false === $last_event_time ) {
			return null;
		}

		$days_inactive = (int) floor( ( current_time( 'timestamp', true ) - $last_event_time ) / DAY_IN_SECONDS );

		if ( $days_inactive >= self::DORMANT_DAYS ) {
			return self::mark_dormant( $post_id, $days_inactive );
		}

		if ( $days_inactive >= self::AT_RISK_DAYS ) {
			return self::mark_at_risk( $post_id, $days_inactive );
		}

		// Group is active and healthy — clear at-risk flag if previously set.
		if ( get_post_meta( $post_id, self::AT_RISK_META, true ) ) {
			delete_post_meta( $post_id, self::AT_RISK_META );
		}

		return null;
	}

	/**
	 * Get the last event date for a group.
	 *
	 * Reads from post meta first. If not set, attempts to query the
	 * group's site for the most recent event.
	 *
	 * @param int $post_id The wp_meetup post ID.
	 * @return string|null Date string or null if not available.
	 */
	private static function get_last_event_date( int $post_id ): ?string {
		$date = get_post_meta( $post_id, self::LAST_EVENT_META, true );

		if ( $date ) {
			return $date;
		}

		// Attempt to query the group's site for the most recent event.
		$site_id = get_post_meta( $post_id, '_meetup_site_id', true );

		if ( ! $site_id || ! is_multisite() ) {
			return null;
		}

		$site_id = (int) $site_id;

		switch_to_blog( $site_id );

		$latest_event = get_posts( [
			'post_type'      => 'event',
			'post_status'    => [ 'event-past', 'event-active', 'event-scheduled', 'publish' ],
			'posts_per_page' => 1,
			'orderby'        => 'meta_value',
			'meta_key'       => '_event_start_utc',
			'order'          => 'DESC',
			'fields'         => 'ids',
		] );

		$event_date = null;

		if ( $latest_event ) {
			$event_date = get_post_meta( $latest_event[0], '_event_start_utc', true );

			if ( $event_date ) {
				// Cache the date back on the meetup post.
				restore_current_blog();
				update_post_meta( $post_id, self::LAST_EVENT_META, $event_date );
				return $event_date;
			}
		}

		restore_current_blog();

		return null;
	}

	/**
	 * Mark a group as at-risk.
	 *
	 * @param int $post_id        The wp_meetup post ID.
	 * @param int $days_inactive  Number of days since last event.
	 * @return string 'at_risk'
	 */
	private static function mark_at_risk( int $post_id, int $days_inactive ): string {
		// Only notify if not already flagged.
		$already_flagged = (bool) get_post_meta( $post_id, self::AT_RISK_META, true );

		update_post_meta( $post_id, self::AT_RISK_META, 1 );

		/**
		 * Fires when a group is flagged as at-risk due to inactivity.
		 *
		 * @param int $post_id       The wp_meetup post ID.
		 * @param int $days_inactive Days since last event.
		 */
		do_action( 'groups_group_at_risk', $post_id, $days_inactive );

		if ( ! $already_flagged ) {
			self::notify_deputy( $post_id, $days_inactive );
		}

		return 'at_risk';
	}

	/**
	 * Mark a group as dormant by transitioning its status.
	 *
	 * @param int $post_id        The wp_meetup post ID.
	 * @param int $days_inactive  Number of days since last event.
	 * @return string 'dormant'
	 */
	private static function mark_dormant( int $post_id, int $days_inactive ): string {
		wp_update_post( [
			'ID'          => $post_id,
			'post_status' => 'meetup-dormant',
		] );

		// Clear the at-risk flag since the group is now dormant.
		delete_post_meta( $post_id, self::AT_RISK_META );

		/**
		 * Fires when a group transitions to dormant due to inactivity.
		 *
		 * @param int $post_id       The wp_meetup post ID.
		 * @param int $days_inactive Days since last event.
		 */
		do_action( 'groups_group_dormant', $post_id, $days_inactive );

		self::notify_deputy( $post_id, $days_inactive );

		return 'dormant';
	}

	/**
	 * Send a dormancy alert email to the assigned deputy.
	 *
	 * @param int $post_id        The wp_meetup post ID.
	 * @param int $days_inactive  Number of days since last event.
	 */
	private static function notify_deputy( int $post_id, int $days_inactive ): void {
		$deputy_username = get_post_meta( $post_id, self::DEPUTY_META, true );

		if ( ! $deputy_username ) {
			return;
		}

		$deputy = get_user_by( 'login', $deputy_username );

		if ( ! $deputy || ! $deputy->user_email ) {
			return;
		}

		$post       = get_post( $post_id );
		$group_name = $post ? $post->post_title : __( 'Unknown Group', 'wordpress-groups' );

		$notifier = new Email_Notifier();
		$notifier->send(
			$deputy->user_email,
			sprintf(
				/* translators: %s: Group name. */
				__( 'Dormancy Alert: %s needs attention', 'wordpress-groups' ),
				$group_name
			),
			'dormancy-alert',
			[
				'deputy_name'   => $deputy->display_name,
				'group_name'    => $group_name,
				'days_inactive' => (string) $days_inactive,
				'group_url'     => get_edit_post_link( $post_id, 'raw' ) ?: admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
				'dashboard_url' => admin_url( 'admin.php?page=groups-deputy-dashboard' ),
			]
		);
	}
}
