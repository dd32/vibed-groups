<?php
/**
 * Tests for the Slack webhook notifier.
 *
 * @package Groups\Tests
 */

use Groups\Integrations\Slack_Notifier;

/**
 * @coversDefaultClass \Groups\Integrations\Slack_Notifier
 */
class Test_Slack_Notifier extends WP_UnitTestCase {

	/**
	 * Slack_Notifier instance under test.
	 *
	 * @var Slack_Notifier
	 */
	private Slack_Notifier $notifier;

	/**
	 * Set up each test.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->notifier = new Slack_Notifier();
	}

	/**
	 * Tear down each test.
	 */
	public function tear_down(): void {
		delete_site_option( Slack_Notifier::OPTION_WEBHOOK_URL );
		remove_all_filters( 'groups_slack_notification' );
		remove_all_filters( 'pre_http_request' );

		parent::tear_down();
	}

	/**
	 * @covers ::build_status_transition_message
	 */
	public function test_status_transition_message_contains_group_name_and_statuses(): void {
		$message = $this->notifier->build_status_transition_message(
			'Melbourne WordPress',
			'Pending Review',
			'Under Vetting',
			42
		);

		$this->assertIsArray( $message );
		$this->assertArrayHasKey( 'blocks', $message );

		$json = wp_json_encode( $message );
		$this->assertStringContainsString( 'Melbourne WordPress', $json );
		$this->assertStringContainsString( 'Pending Review', $json );
		$this->assertStringContainsString( 'Under Vetting', $json );
		$this->assertStringContainsString( 'Status Changed', $json );
	}

	/**
	 * @covers ::build_at_risk_message
	 */
	public function test_at_risk_message_contains_group_name_and_days(): void {
		$message = $this->notifier->build_at_risk_message( 'Sydney WP', 65, 99 );

		$this->assertIsArray( $message );
		$json = wp_json_encode( $message );
		$this->assertStringContainsString( 'Sydney WP', $json );
		$this->assertStringContainsString( '65', $json );
		$this->assertStringContainsString( 'At Risk', $json );
	}

	/**
	 * @covers ::build_dormant_message
	 */
	public function test_dormant_message_contains_group_name_and_days(): void {
		$message = $this->notifier->build_dormant_message( 'Berlin WP', 95, 100 );

		$this->assertIsArray( $message );
		$json = wp_json_encode( $message );
		$this->assertStringContainsString( 'Berlin WP', $json );
		$this->assertStringContainsString( '95', $json );
		$this->assertStringContainsString( 'Dormant', $json );
	}

	/**
	 * @covers ::build_status_transition_message
	 */
	public function test_message_has_sections_fields_and_context_blocks(): void {
		$message = $this->notifier->build_status_transition_message(
			'Test Group',
			'Old',
			'New',
			1
		);

		$block_types = array_column( $message['blocks'], 'type' );
		$this->assertContains( 'section', $block_types, 'Message should contain section blocks.' );
		$this->assertContains( 'context', $block_types, 'Message should contain a context block.' );

		// Verify fields exist in at least one section block.
		$has_fields = false;
		foreach ( $message['blocks'] as $block ) {
			if ( ! empty( $block['fields'] ) ) {
				$has_fields = true;
				break;
			}
		}
		$this->assertTrue( $has_fields, 'At least one section block should contain fields.' );
	}

	/**
	 * @covers ::send
	 */
	public function test_send_returns_false_when_no_webhook_configured(): void {
		delete_site_option( Slack_Notifier::OPTION_WEBHOOK_URL );

		$result = $this->notifier->send( [ 'text' => 'hello' ] );

		$this->assertFalse( $result, 'send() should return false when no webhook URL is configured.' );
	}

	/**
	 * @covers ::send
	 */
	public function test_send_returns_false_for_empty_message(): void {
		update_site_option( Slack_Notifier::OPTION_WEBHOOK_URL, 'https://hooks.slack.com/services/test' );

		$this->assertFalse( $this->notifier->send( [] ), 'send() should return false for empty array.' );
		$this->assertFalse( $this->notifier->send( false ), 'send() should return false for false.' );
	}

	/**
	 * @covers ::send
	 */
	public function test_send_posts_to_webhook_and_returns_true_on_success(): void {
		$webhook = 'https://hooks.slack.com/services/T00/B00/test';
		update_site_option( Slack_Notifier::OPTION_WEBHOOK_URL, $webhook );

		$captured_url  = null;
		$captured_body = null;

		add_filter( 'pre_http_request', function ( $preempt, $args, $url ) use ( &$captured_url, &$captured_body ) {
			$captured_url  = $url;
			$captured_body = $args['body'];

			return [
				'response' => [ 'code' => 200, 'message' => 'OK' ],
				'body'     => 'ok',
			];
		}, 10, 3 );

		$payload = [ 'text' => 'Test message' ];
		$result  = $this->notifier->send( $payload );

		$this->assertTrue( $result, 'send() should return true on successful webhook post.' );
		$this->assertSame( $webhook, $captured_url, 'Should post to the configured webhook URL.' );
		$this->assertSame( wp_json_encode( $payload ), $captured_body, 'Body should be JSON-encoded payload.' );
	}

	/**
	 * @covers ::send
	 */
	public function test_send_returns_false_on_wp_error_without_throwing(): void {
		update_site_option( Slack_Notifier::OPTION_WEBHOOK_URL, 'https://hooks.slack.com/services/T00/B00/test' );

		add_filter( 'pre_http_request', function () {
			return new WP_Error( 'http_request_failed', 'Connection timed out' );
		} );

		$result = $this->notifier->send( [ 'text' => 'hello' ] );

		$this->assertFalse( $result, 'send() should return false on WP_Error without throwing.' );
	}

	/**
	 * @covers ::send
	 */
	public function test_send_returns_false_on_non_200_response(): void {
		update_site_option( Slack_Notifier::OPTION_WEBHOOK_URL, 'https://hooks.slack.com/services/T00/B00/test' );

		add_filter( 'pre_http_request', function () {
			return [
				'response' => [ 'code' => 403, 'message' => 'Forbidden' ],
				'body'     => 'invalid_token',
			];
		} );

		$result = $this->notifier->send( [ 'text' => 'hello' ] );

		$this->assertFalse( $result, 'send() should return false for non-2xx response code.' );
	}

	/**
	 * @covers ::send
	 * @covers ::on_status_transition
	 */
	public function test_filter_allows_modification_of_message(): void {
		$webhook = 'https://hooks.slack.com/services/T00/B00/test';
		update_site_option( Slack_Notifier::OPTION_WEBHOOK_URL, $webhook );

		add_filter( 'groups_slack_notification', function ( $message, $type, $post_id ) {
			$message['text'] = "Custom: {$type} for post {$post_id}";
			return $message;
		}, 10, 3 );

		$captured_body = null;
		add_filter( 'pre_http_request', function ( $preempt, $args ) use ( &$captured_body ) {
			$captured_body = $args['body'];
			return [
				'response' => [ 'code' => 200, 'message' => 'OK' ],
				'body'     => 'ok',
			];
		}, 10, 3 );

		$message = $this->notifier->build_status_transition_message( 'Test', 'Old', 'New', 55 );
		$this->notifier->send( $message );

		$decoded = json_decode( $captured_body, true );
		$this->assertSame( 'Custom: status_transition for post 55', $decoded['text'] );
	}

	/**
	 * @covers ::send
	 */
	public function test_filter_returning_empty_array_prevents_send(): void {
		$webhook = 'https://hooks.slack.com/services/T00/B00/test';
		update_site_option( Slack_Notifier::OPTION_WEBHOOK_URL, $webhook );

		add_filter( 'groups_slack_notification', '__return_empty_array' );

		$http_called = false;
		add_filter( 'pre_http_request', function () use ( &$http_called ) {
			$http_called = true;
			return [
				'response' => [ 'code' => 200, 'message' => 'OK' ],
				'body'     => 'ok',
			];
		} );

		$message = $this->notifier->build_at_risk_message( 'Test', 60, 1 );
		$result  = $this->notifier->send( $message );

		$this->assertFalse( $result, 'send() should return false when filter empties the message.' );
		$this->assertFalse( $http_called, 'HTTP request should not be made when message is empty.' );
	}

	/**
	 * @covers ::on_status_transition
	 */
	public function test_on_status_transition_sends_for_valid_post(): void {
		$webhook = 'https://hooks.slack.com/services/T00/B00/test';
		update_site_option( Slack_Notifier::OPTION_WEBHOOK_URL, $webhook );

		$post_id = self::factory()->post->create( [
			'post_title' => 'Tokyo WordPress Meetup',
			'post_type'  => 'post',
		] );

		$sent = false;
		add_filter( 'pre_http_request', function ( $preempt, $args ) use ( &$sent ) {
			$body = json_decode( $args['body'], true );
			$json = wp_json_encode( $body );
			if ( str_contains( $json, 'Tokyo WordPress Meetup' ) ) {
				$sent = true;
			}
			return [
				'response' => [ 'code' => 200, 'message' => 'OK' ],
				'body'     => 'ok',
			];
		}, 10, 3 );

		$this->notifier->on_status_transition( $post_id, 'meetup-pending', 'meetup-vetting' );

		$this->assertTrue( $sent, 'on_status_transition should send a Slack message containing the group name.' );
	}

	/**
	 * @covers ::on_status_transition
	 */
	public function test_on_status_transition_uses_human_readable_labels(): void {
		$webhook = 'https://hooks.slack.com/services/T00/B00/test';
		update_site_option( Slack_Notifier::OPTION_WEBHOOK_URL, $webhook );

		$post_id = self::factory()->post->create( [ 'post_title' => 'Label Test Group' ] );

		$captured_body = null;
		add_filter( 'pre_http_request', function ( $preempt, $args ) use ( &$captured_body ) {
			$captured_body = $args['body'];
			return [
				'response' => [ 'code' => 200, 'message' => 'OK' ],
				'body'     => 'ok',
			];
		}, 10, 3 );

		$this->notifier->on_status_transition( $post_id, 'meetup-pending', 'meetup-active' );

		$this->assertStringContainsString( 'Pending Review', $captured_body );
		$this->assertStringContainsString( 'Active', $captured_body );
	}

	/**
	 * @covers ::get_webhook_url
	 */
	public function test_get_webhook_url_returns_empty_string_when_not_set(): void {
		delete_site_option( Slack_Notifier::OPTION_WEBHOOK_URL );
		$this->assertSame( '', $this->notifier->get_webhook_url() );
	}

	/**
	 * @covers ::get_webhook_url
	 */
	public function test_get_webhook_url_returns_configured_value(): void {
		$url = 'https://hooks.slack.com/services/T00/B00/xyz';
		update_site_option( Slack_Notifier::OPTION_WEBHOOK_URL, $url );
		$this->assertSame( $url, $this->notifier->get_webhook_url() );
	}
}
