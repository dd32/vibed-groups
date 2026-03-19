<?php
/**
 * Tests for the Email_Templates class.
 *
 * @package Groups\Tests\Notifications
 */

namespace Groups\Tests\Notifications;

use Groups\Notifications\Email_Templates;
use Groups\Notifications\Email_Notifier;
use WP_UnitTestCase;

/**
 * Test email template rendering, placeholder substitution, and base wrapping.
 */
class Test_Email_Templates extends WP_UnitTestCase {

	/**
	 * Template renderer instance.
	 *
	 * @var Email_Templates
	 */
	private Email_Templates $templates;

	/**
	 * Path to the email templates directory.
	 *
	 * @var string
	 */
	private string $template_dir;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->template_dir = dirname( __DIR__, 3 ) . '/email-templates';
		$this->templates    = new Email_Templates( $this->template_dir );
	}

	/**
	 * Test that the base template exists and contains required structure.
	 */
	public function test_base_template_exists(): void {
		$this->assertFileExists( $this->template_dir . '/base.html' );
	}

	/**
	 * Test that the base template contains the content placeholder.
	 */
	public function test_base_template_has_content_placeholder(): void {
		$base = file_get_contents( $this->template_dir . '/base.html' );
		$this->assertStringContainsString( '{{content}}', $base );
	}

	/**
	 * Test that the base template contains the unsubscribe placeholder.
	 */
	public function test_base_template_has_unsubscribe_placeholder(): void {
		$base = file_get_contents( $this->template_dir . '/base.html' );
		$this->assertStringContainsString( '{{unsubscribe_url}}', $base );
	}

	/**
	 * Test rendering RSVP confirmation for attending status.
	 */
	public function test_render_rsvp_confirmation_attending(): void {
		$data = [
			'user_name'       => 'Alice',
			'event_title'     => 'WordPress Meetup',
			'event_date'      => 'March 20, 2026',
			'event_time'      => '6:00 PM',
			'event_url'       => 'https://events.wordpress.org/melbourne/event/meetup/',
			'venue_name'      => 'Community Hall',
			'group_name'      => 'Melbourne WordPress',
			'rsvp_status'     => 'attending',
			'unsubscribe_url' => 'https://example.com/unsubscribe',
			'email_subject'   => 'RSVP Confirmed',
			'preview_text'    => 'Your RSVP is confirmed.',
		];

		$html = $this->templates->render( 'rsvp-confirmation', $data );

		$this->assertStringContainsString( 'Alice', $html );
		$this->assertStringContainsString( 'WordPress Meetup', $html );
		$this->assertStringContainsString( 'March 20, 2026', $html );
		$this->assertStringContainsString( '6:00 PM', $html );
		$this->assertStringContainsString( 'Community Hall', $html );
		$this->assertStringContainsString( 'Melbourne WordPress', $html );
		$this->assertStringContainsString( 'Your RSVP is Confirmed', $html );
		$this->assertStringNotContainsString( "You're on the Waitlist", $html );
	}

	/**
	 * Test rendering RSVP confirmation for waitlisted status.
	 */
	public function test_render_rsvp_confirmation_waitlisted(): void {
		$data = [
			'user_name'       => 'Bob',
			'event_title'     => 'WordPress Meetup',
			'event_date'      => 'March 20, 2026',
			'event_time'      => '6:00 PM',
			'event_url'       => 'https://events.wordpress.org/melbourne/event/meetup/',
			'venue_name'      => 'Community Hall',
			'group_name'      => 'Melbourne WordPress',
			'rsvp_status'     => 'waitlisted',
			'unsubscribe_url' => 'https://example.com/unsubscribe',
			'email_subject'   => 'RSVP Waitlisted',
			'preview_text'    => 'You are on the waitlist.',
		];

		$html = $this->templates->render( 'rsvp-confirmation', $data );

		$this->assertStringContainsString( 'Bob', $html );
		$this->assertStringContainsString( "You're on the Waitlist", $html );
		$this->assertStringNotContainsString( 'Your RSVP is Confirmed', $html );
		$this->assertStringContainsString( 'waitlist', $html );
	}

	/**
	 * Test rendering event reminder template.
	 */
	public function test_render_event_reminder(): void {
		$data = [
			'user_name'       => 'Charlie',
			'event_title'     => 'Contributor Day',
			'event_date'      => 'April 1, 2026',
			'event_time'      => '10:00 AM',
			'event_url'       => 'https://events.wordpress.org/sydney/event/contributor-day/',
			'venue_name'      => 'Tech Hub',
			'venue_address'   => '123 Main St, Sydney',
			'group_name'      => 'Sydney WordPress',
			'unsubscribe_url' => 'https://example.com/unsubscribe',
			'email_subject'   => 'Event Reminder',
			'preview_text'    => 'Your event is coming up!',
		];

		$html = $this->templates->render( 'event-reminder', $data );

		$this->assertStringContainsString( 'Charlie', $html );
		$this->assertStringContainsString( 'Contributor Day', $html );
		$this->assertStringContainsString( 'April 1, 2026', $html );
		$this->assertStringContainsString( '123 Main St, Sydney', $html );
		$this->assertStringContainsString( 'Your Event is Coming Up', $html );
	}

	/**
	 * Test rendering application status template.
	 */
	public function test_render_application_status(): void {
		$data = [
			'user_name'       => 'Dana',
			'group_name'      => 'Berlin WordPress',
			'status'          => 'Orientation',
			'status_message'  => 'Your application has been approved! Please complete orientation.',
			'dashboard_url'   => 'https://events.wordpress.org/dashboard/',
			'unsubscribe_url' => 'https://example.com/unsubscribe',
			'email_subject'   => 'Application Update',
			'preview_text'    => 'Your application status has changed.',
		];

		$html = $this->templates->render( 'application-status', $data );

		$this->assertStringContainsString( 'Dana', $html );
		$this->assertStringContainsString( 'Berlin WordPress', $html );
		$this->assertStringContainsString( 'Orientation', $html );
		$this->assertStringContainsString( 'approved', $html );
		$this->assertStringContainsString( 'dashboard/', $html );
	}

	/**
	 * Test rendering welcome organizer template.
	 */
	public function test_render_welcome_organizer(): void {
		$data = [
			'user_name'       => 'Eve',
			'group_name'      => 'Tokyo WordPress',
			'site_url'        => 'https://events.wordpress.org/tokyo-user-group/',
			'dashboard_url'   => 'https://events.wordpress.org/tokyo-user-group/dashboard/',
			'unsubscribe_url' => 'https://example.com/unsubscribe',
			'email_subject'   => 'Welcome, Organizer!',
			'preview_text'    => 'Your group is ready.',
		];

		$html = $this->templates->render( 'welcome-organizer', $data );

		$this->assertStringContainsString( 'Eve', $html );
		$this->assertStringContainsString( 'Tokyo WordPress', $html );
		$this->assertStringContainsString( 'events.wordpress.org/tokyo-user-group/', $html );
		$this->assertStringContainsString( 'Create your first event', $html );
		$this->assertStringContainsString( 'Getting Started', $html );
	}

	/**
	 * Test rendering dormancy alert template.
	 */
	public function test_render_dormancy_alert(): void {
		$data = [
			'deputy_name'     => 'Frank',
			'group_name'      => 'Inactive City WP',
			'days_inactive'   => '75',
			'group_url'       => 'https://events.wordpress.org/inactive-city/',
			'dashboard_url'   => 'https://events.wordpress.org/deputy-dashboard/',
			'unsubscribe_url' => 'https://example.com/unsubscribe',
			'email_subject'   => 'Dormancy Alert',
			'preview_text'    => 'A group needs attention.',
		];

		$html = $this->templates->render( 'dormancy-alert', $data );

		$this->assertStringContainsString( 'Frank', $html );
		$this->assertStringContainsString( 'Inactive City WP', $html );
		$this->assertStringContainsString( '75 days', $html );
		$this->assertStringContainsString( 'Dormancy Alert', $html );
		$this->assertStringContainsString( 'deputy-dashboard/', $html );
	}

	/**
	 * Test rendering group announcement template.
	 */
	public function test_render_group_announcement(): void {
		$data = [
			'user_name'          => 'Grace',
			'group_name'         => 'London WordPress',
			'announcement_title' => 'New Venue for Next Month',
			'announcement_body'  => 'We are moving our meetup to a new location starting next month.',
			'group_url'          => 'https://events.wordpress.org/london/',
			'unsubscribe_url'    => 'https://example.com/unsubscribe',
			'email_subject'      => 'Group Announcement',
			'preview_text'       => 'New announcement from London WordPress.',
		];

		$html = $this->templates->render( 'group-announcement', $data );

		$this->assertStringContainsString( 'Grace', $html );
		$this->assertStringContainsString( 'London WordPress', $html );
		$this->assertStringContainsString( 'New Venue for Next Month', $html );
		$this->assertStringContainsString( 'moving our meetup', $html );
	}

	/**
	 * Test that all templates are wrapped in the base template.
	 */
	public function test_all_templates_wrapped_in_base(): void {
		$templates = $this->templates->get_available_templates();

		$this->assertNotEmpty( $templates, 'No content templates found.' );

		foreach ( $templates as $template_name ) {
			$html = $this->templates->render( $template_name, [
				'user_name'          => 'Test',
				'deputy_name'        => 'Test',
				'event_title'        => 'Test Event',
				'event_date'         => 'Jan 1, 2026',
				'event_time'         => '12:00 PM',
				'event_url'          => 'https://example.com',
				'venue_name'         => 'Test Venue',
				'venue_address'      => '123 Test St',
				'group_name'         => 'Test Group',
				'rsvp_status'        => 'attending',
				'status'             => 'Active',
				'status_message'     => 'All good.',
				'dashboard_url'      => 'https://example.com/dashboard',
				'site_url'           => 'https://example.com/site',
				'group_url'          => 'https://example.com/group',
				'days_inactive'      => '90',
				'announcement_title' => 'Test Announcement',
				'announcement_body'  => 'Test body content.',
				'unsubscribe_url'    => 'https://example.com/unsubscribe',
				'email_subject'      => 'Test Subject',
				'preview_text'       => 'Test preview.',
			] );

			// All rendered emails should have the base template structure.
			$this->assertStringContainsString( '<!DOCTYPE html>', $html, "Template {$template_name} missing DOCTYPE." );
			$this->assertStringContainsString( 'WordPress.org', $html, "Template {$template_name} missing WordPress.org branding." );
			$this->assertStringContainsString( 'Community Groups', $html, "Template {$template_name} missing Community Groups text." );
			$this->assertStringContainsString( 'email-preferences', $html, "Template {$template_name} missing unsubscribe link." );
			$this->assertStringContainsString( 'prefers-color-scheme: dark', $html, "Template {$template_name} missing dark mode support." );
		}
	}

	/**
	 * Test that rendering a nonexistent template throws an exception.
	 */
	public function test_render_nonexistent_template_throws(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->templates->render( 'nonexistent-template' );
	}

	/**
	 * Test that unrecognized placeholders are left in the output.
	 */
	public function test_unrecognized_placeholders_left_intact(): void {
		$html = $this->templates->render( 'event-reminder', [
			'user_name'       => 'Test',
			'event_title'     => 'Test Event',
			'email_subject'   => 'Test',
			'preview_text'    => 'Test',
			'unsubscribe_url' => 'https://example.com/unsubscribe',
			// event_date, event_time, venue_name, etc. intentionally omitted.
		] );

		// Omitted placeholders should remain as {{placeholder}}.
		$this->assertStringContainsString( '{{event_date}}', $html );
		$this->assertStringContainsString( '{{event_time}}', $html );
	}

	/**
	 * Test get_available_templates returns expected templates.
	 */
	public function test_get_available_templates(): void {
		$templates = $this->templates->get_available_templates();

		$expected = [
			'application-status',
			'dormancy-alert',
			'event-reminder',
			'group-announcement',
			'rsvp-confirmation',
			'welcome-organizer',
		];

		sort( $templates );
		sort( $expected );

		$this->assertSame( $expected, $templates );
	}

	/**
	 * Test that base template is NOT in the available templates list.
	 */
	public function test_base_not_in_available_templates(): void {
		$templates = $this->templates->get_available_templates();
		$this->assertNotContains( 'base', $templates );
	}

	/**
	 * Test that placeholder values containing HTML are escaped in output.
	 *
	 * Non-HTML-allowed placeholders like group_name, user_name, event_title
	 * should have their HTML entities escaped to prevent XSS.
	 */
	public function test_placeholder_values_are_html_escaped(): void {
		$xss_payload = '<script>alert("xss")</script>';

		$data = [
			'user_name'       => $xss_payload,
			'event_title'     => 'Event with <b>bold</b> & "quotes"',
			'event_date'      => 'Jan 1, 2026',
			'event_time'      => '12:00 PM',
			'event_url'       => 'https://example.com',
			'venue_name'      => 'Test Venue',
			'venue_address'   => '123 Test St',
			'group_name'      => $xss_payload,
			'unsubscribe_url' => 'https://example.com/unsubscribe',
			'email_subject'   => 'Test Subject',
			'preview_text'    => 'Test preview.',
		];

		$html = $this->templates->render( 'event-reminder', $data );

		// Raw script tags must NOT appear in the output.
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringNotContainsString( '</script>', $html );

		// The escaped version should be present.
		$this->assertStringContainsString( '&lt;script&gt;', $html );

		// Ampersands and quotes should be escaped in non-HTML placeholders.
		$this->assertStringContainsString( '&amp;', $html );
	}

	/**
	 * Test that HTML-allowed placeholders preserve safe HTML but strip scripts.
	 *
	 * announcement_body is allowed to contain HTML (via wp_kses_post),
	 * so safe tags like <p> and <strong> should be preserved, but
	 * dangerous tags like <script> should be stripped.
	 */
	public function test_html_allowed_placeholders_strip_scripts_but_keep_safe_html(): void {
		$data = [
			'user_name'          => 'Grace',
			'group_name'         => 'London WordPress',
			'announcement_title' => 'Test',
			'announcement_body'  => '<p>Safe <strong>HTML</strong> content.</p><script>alert("xss")</script>',
			'group_url'          => 'https://events.wordpress.org/london/',
			'unsubscribe_url'    => 'https://example.com/unsubscribe',
			'email_subject'      => 'Group Announcement',
			'preview_text'       => 'Test.',
		];

		$html = $this->templates->render( 'group-announcement', $data );

		// Safe HTML tags should be preserved.
		$this->assertStringContainsString( '<p>Safe <strong>HTML</strong> content.</p>', $html );

		// Script tags should be stripped entirely.
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringNotContainsString( 'alert("xss")', $html );
	}

	/**
	 * Test that email_subject is properly escaped in attribute contexts.
	 *
	 * The email_subject appears in <title> and aria-label attributes in base.html,
	 * which need esc_attr() escaping rather than just esc_html().
	 */
	public function test_email_subject_escaped_in_attribute_contexts(): void {
		$data = [
			'user_name'       => 'Test',
			'event_title'     => 'Test Event',
			'event_date'      => 'Jan 1, 2026',
			'event_time'      => '12:00 PM',
			'event_url'       => 'https://example.com',
			'venue_name'      => 'Test Venue',
			'venue_address'   => '123 Test St',
			'group_name'      => 'Test Group',
			'unsubscribe_url' => 'https://example.com/unsubscribe',
			'email_subject'   => 'Subject with "quotes" & <tags>',
			'preview_text'    => 'Test preview.',
		];

		$html = $this->templates->render( 'event-reminder', $data );

		// In the title tag, the subject should be attribute-safe.
		$this->assertStringNotContainsString( '<title>Subject with "quotes" & <tags></title>', $html );

		// The aria-label should use esc_attr-safe content.
		$this->assertStringNotContainsString( 'aria-label="Subject with "quotes"', $html );
	}

	/**
	 * Test that the unsubscribe URL uses an HMAC token, not the raw email.
	 */
	public function test_unsubscribe_url_uses_token_not_raw_email(): void {
		$email = 'user@example.com';

		$notifier = new Email_Notifier();

		// Use reflection to access the private get_default_unsubscribe_url method.
		$reflection = new \ReflectionMethod( $notifier, 'get_default_unsubscribe_url' );
		$reflection->setAccessible( true );

		$url = $reflection->invoke( $notifier, $email );

		// The raw email should NOT appear in the URL.
		$this->assertStringNotContainsString( 'user@example.com', $url );
		$this->assertStringNotContainsString( 'user%40example.com', $url );

		// The URL should use a token parameter instead of email.
		$this->assertStringContainsString( 'token=', $url );
		$this->assertStringNotContainsString( 'email=', $url );
	}

	/**
	 * Test that generate_unsubscribe_token creates verifiable tokens.
	 */
	public function test_unsubscribe_token_roundtrip(): void {
		$email = 'test@wordpress.org';

		$token = Email_Notifier::generate_unsubscribe_token( $email );

		// Token should be a non-empty string.
		$this->assertNotEmpty( $token );

		// Token should be URL-safe (no +, /, or = characters).
		$this->assertDoesNotMatchRegularExpression( '/[+\/=]/', $token );

		// Verify the token resolves back to the original email.
		$result = Email_Notifier::verify_unsubscribe_token( $token );
		$this->assertSame( $email, $result );
	}

	/**
	 * Test that tampered unsubscribe tokens are rejected.
	 */
	public function test_tampered_unsubscribe_token_is_rejected(): void {
		$token = Email_Notifier::generate_unsubscribe_token( 'legit@example.com' );

		// Tamper with the token.
		$tampered = $token . 'tampered';

		$result = Email_Notifier::verify_unsubscribe_token( $tampered );
		$this->assertFalse( $result );
	}

	/**
	 * Test that an invalid unsubscribe token returns false.
	 */
	public function test_invalid_unsubscribe_token_returns_false(): void {
		$this->assertFalse( Email_Notifier::verify_unsubscribe_token( '' ) );
		$this->assertFalse( Email_Notifier::verify_unsubscribe_token( 'not-a-real-token' ) );
	}
}
