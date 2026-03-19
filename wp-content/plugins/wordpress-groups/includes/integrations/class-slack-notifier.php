<?php
/**
 * Slack webhook notification integration.
 *
 * Sends rich Slack messages when meetup application statuses change,
 * groups become at-risk, or groups go dormant.
 *
 * @package Groups
 */

namespace Groups\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Slack_Notifier — sends webhook notifications to a configured Slack channel.
 *
 * Non-blocking: failures are logged but never affect application flow.
 */
class Slack_Notifier {

	/**
	 * Network option key for the Slack webhook URL.
	 *
	 * @var string
	 */
	const OPTION_WEBHOOK_URL = 'groups_slack_webhook_url';

	/**
	 * Human-readable labels for meetup statuses.
	 *
	 * @var array<string, string>
	 */
	const STATUS_LABELS = [
		'meetup-pending'     => 'Pending Review',
		'meetup-vetting'     => 'Under Vetting',
		'meetup-feedback'    => 'Awaiting Feedback',
		'meetup-orientation' => 'Orientation',
		'meetup-scheduling'  => 'Scheduling First Event',
		'meetup-active'      => 'Active',
		'meetup-dormant'     => 'Dormant',
		'meetup-suspended'   => 'Suspended',
		'meetup-removed'     => 'Removed',
		'meetup-declined'    => 'Declined',
	];

	/**
	 * Constructor — registers action hooks.
	 */
	public function __construct() {
		add_action( 'groups_meetup_status_transition', [ $this, 'on_status_transition' ], 30, 3 );
		add_action( 'groups_group_at_risk', [ $this, 'on_group_at_risk' ], 10, 2 );
		add_action( 'groups_group_dormant', [ $this, 'on_group_dormant' ], 10, 2 );
	}

	/**
	 * Handle meetup application status transitions.
	 *
	 * @param int    $post_id    The wp_meetup post ID.
	 * @param string $old_status Previous status slug.
	 * @param string $new_status New status slug.
	 */
	public function on_status_transition( int $post_id, string $old_status, string $new_status ): void {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		$group_name = $post->post_title ?: "(Post #{$post_id})";
		$old_label  = self::STATUS_LABELS[ $old_status ] ?? $old_status;
		$new_label  = self::STATUS_LABELS[ $new_status ] ?? $new_status;

		$message = $this->build_status_transition_message( $group_name, $old_label, $new_label, $post_id );

		$this->send( $message );
	}

	/**
	 * Handle at-risk group notifications.
	 *
	 * @param int $post_id       The wp_meetup post ID.
	 * @param int $days_inactive Days since last event.
	 */
	public function on_group_at_risk( int $post_id, int $days_inactive ): void {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		$group_name = $post->post_title ?: "(Post #{$post_id})";

		$message = $this->build_at_risk_message( $group_name, $days_inactive, $post_id );

		$this->send( $message );
	}

	/**
	 * Handle dormant group notifications.
	 *
	 * @param int $post_id       The wp_meetup post ID.
	 * @param int $days_inactive Days since last event.
	 */
	public function on_group_dormant( int $post_id, int $days_inactive ): void {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		$group_name = $post->post_title ?: "(Post #{$post_id})";

		$message = $this->build_dormant_message( $group_name, $days_inactive, $post_id );

		$this->send( $message );
	}

	/**
	 * Build the Slack payload for a status transition.
	 *
	 * @param string $group_name Group display name.
	 * @param string $old_label  Human-readable old status.
	 * @param string $new_label  Human-readable new status.
	 * @param int    $post_id    The wp_meetup post ID.
	 * @return array Slack message payload.
	 */
	public function build_status_transition_message( string $group_name, string $old_label, string $new_label, int $post_id ): array {
		$message = [
			'blocks' => [
				[
					'type' => 'section',
					'text' => [
						'type' => 'mrkdwn',
						'text' => ":arrows_counterclockwise: *Group Status Changed*\n*{$group_name}*",
					],
				],
				[
					'type'   => 'section',
					'fields' => [
						[
							'type' => 'mrkdwn',
							'text' => "*From:*\n{$old_label}",
						],
						[
							'type' => 'mrkdwn',
							'text' => "*To:*\n{$new_label}",
						],
					],
				],
				[
					'type'     => 'context',
					'elements' => [
						[
							'type' => 'mrkdwn',
							'text' => "Post ID: {$post_id}",
						],
					],
				],
			],
		];

		return $this->apply_filter( $message, 'status_transition', $post_id );
	}

	/**
	 * Build the Slack payload for an at-risk group.
	 *
	 * @param string $group_name    Group display name.
	 * @param int    $days_inactive Days since last event.
	 * @param int    $post_id       The wp_meetup post ID.
	 * @return array Slack message payload.
	 */
	public function build_at_risk_message( string $group_name, int $days_inactive, int $post_id ): array {
		$message = [
			'blocks' => [
				[
					'type' => 'section',
					'text' => [
						'type' => 'mrkdwn',
						'text' => ":warning: *Group At Risk*\n*{$group_name}*",
					],
				],
				[
					'type'   => 'section',
					'fields' => [
						[
							'type' => 'mrkdwn',
							'text' => "*Days Inactive:*\n{$days_inactive}",
						],
						[
							'type' => 'mrkdwn',
							'text' => "*Status:*\nAt Risk",
						],
					],
				],
				[
					'type'     => 'context',
					'elements' => [
						[
							'type' => 'mrkdwn',
							'text' => "Post ID: {$post_id} | This group has had no events for {$days_inactive} days.",
						],
					],
				],
			],
		];

		return $this->apply_filter( $message, 'at_risk', $post_id );
	}

	/**
	 * Build the Slack payload for a dormant group.
	 *
	 * @param string $group_name    Group display name.
	 * @param int    $days_inactive Days since last event.
	 * @param int    $post_id       The wp_meetup post ID.
	 * @return array Slack message payload.
	 */
	public function build_dormant_message( string $group_name, int $days_inactive, int $post_id ): array {
		$message = [
			'blocks' => [
				[
					'type' => 'section',
					'text' => [
						'type' => 'mrkdwn',
						'text' => ":red_circle: *Group Now Dormant*\n*{$group_name}*",
					],
				],
				[
					'type'   => 'section',
					'fields' => [
						[
							'type' => 'mrkdwn',
							'text' => "*Days Inactive:*\n{$days_inactive}",
						],
						[
							'type' => 'mrkdwn',
							'text' => "*Status:*\nDormant",
						],
					],
				],
				[
					'type'     => 'context',
					'elements' => [
						[
							'type' => 'mrkdwn',
							'text' => "Post ID: {$post_id} | This group has been transitioned to dormant after {$days_inactive} days of inactivity.",
						],
					],
				],
			],
		];

		return $this->apply_filter( $message, 'dormant', $post_id );
	}

	/**
	 * Apply the groups_slack_notification filter.
	 *
	 * @param array  $message Slack message payload.
	 * @param string $type    Notification type (status_transition, at_risk, dormant).
	 * @param int    $post_id The wp_meetup post ID.
	 * @return array Filtered message payload.
	 */
	private function apply_filter( array $message, string $type, int $post_id ): array {
		/**
		 * Filters the Slack notification payload before sending.
		 *
		 * Returning a falsy value prevents the notification from being sent.
		 *
		 * @param array  $message The Slack message payload.
		 * @param string $type    Notification type: 'status_transition', 'at_risk', or 'dormant'.
		 * @param int    $post_id The wp_meetup post ID.
		 */
		return apply_filters( 'groups_slack_notification', $message, $type, $post_id );
	}

	/**
	 * Send a message to the configured Slack webhook.
	 *
	 * Non-blocking: any failure is logged and silently swallowed so that
	 * the calling code path is never affected.
	 *
	 * @param array|false $message Slack message payload, or false to skip.
	 * @return bool True if the message was sent successfully, false otherwise.
	 */
	public function send( $message ): bool {
		if ( empty( $message ) ) {
			return false;
		}

		$webhook_url = $this->get_webhook_url();
		if ( empty( $webhook_url ) ) {
			return false;
		}

		try {
			$response = wp_remote_post(
				$webhook_url,
				[
					'headers'  => [ 'Content-Type' => 'application/json; charset=utf-8' ],
					'body'     => wp_json_encode( $message ),
					'timeout'  => 5,
					'blocking' => true,
				]
			);

			if ( is_wp_error( $response ) ) {
				error_log( '[Groups Slack] Send failed: ' . $response->get_error_message() );
				return false;
			}

			$code = wp_remote_retrieve_response_code( $response );
			if ( $code < 200 || $code >= 300 ) {
				error_log( "[Groups Slack] Unexpected response code: {$code}" );
				return false;
			}

			return true;
		} catch ( \Throwable $e ) {
			error_log( '[Groups Slack] Exception: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Get the configured Slack webhook URL.
	 *
	 * @return string Webhook URL or empty string if not configured.
	 */
	public function get_webhook_url(): string {
		return (string) get_site_option( self::OPTION_WEBHOOK_URL, '' );
	}
}
