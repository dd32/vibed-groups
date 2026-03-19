<?php
/**
 * Email notification sender.
 *
 * Renders templates and sends HTML emails via wp_mail().
 *
 * @package Groups\Notifications
 */

namespace Groups\Notifications;

/**
 * Sends branded HTML email notifications using the template system.
 */
class Email_Notifier {

	/**
	 * Email template renderer.
	 *
	 * @var Email_Templates
	 */
	private Email_Templates $templates;

	/**
	 * Constructor.
	 *
	 * @param Email_Templates|null $templates Optional. Template renderer instance.
	 */
	public function __construct( ?Email_Templates $templates = null ) {
		$this->templates = $templates ?? new Email_Templates();
	}

	/**
	 * Send an HTML email using a named template.
	 *
	 * Renders the template with the provided data, sets the content type to HTML,
	 * and sends via wp_mail().
	 *
	 * @param string       $to       Recipient email address.
	 * @param string       $subject  Email subject line.
	 * @param string       $template Template name (e.g. 'rsvp-confirmation').
	 * @param array        $data     Associative array of placeholder => value pairs.
	 * @param array|string $headers  Optional. Additional headers.
	 * @return bool Whether the email was sent successfully.
	 */
	public function send( string $to, string $subject, string $template, array $data = [], array|string $headers = [] ): bool {
		// Ensure email_subject and preview_text have defaults.
		$data['email_subject'] = $data['email_subject'] ?? $subject;
		$data['preview_text']  = $data['preview_text'] ?? $subject;

		// Provide a default unsubscribe URL if not set.
		if ( empty( $data['unsubscribe_url'] ) ) {
			$data['unsubscribe_url'] = $this->get_default_unsubscribe_url( $to );
		}

		$html = $this->templates->render( $template, $data );

		// Set content type to HTML via filter.
		$set_html_content_type = static function (): string {
			return 'text/html';
		};

		add_filter( 'wp_mail_content_type', $set_html_content_type );

		// Set From header if not already provided.
		$from_name  = apply_filters( 'groups_email_from_name', 'WordPress.org Community' );
		$from_email = apply_filters( 'groups_email_from_address', 'noreply@wordpress.org' );

		if ( is_array( $headers ) ) {
			$has_from = false;
			foreach ( $headers as $header ) {
				if ( stripos( $header, 'from:' ) === 0 ) {
					$has_from = true;
					break;
				}
			}
			if ( ! $has_from ) {
				$headers[] = sprintf( 'From: %s <%s>', $from_name, $from_email );
			}
		}

		/**
		 * Fires before an email notification is sent.
		 *
		 * @param string $to       Recipient email.
		 * @param string $subject  Email subject.
		 * @param string $template Template name.
		 * @param array  $data     Template data.
		 */
		do_action( 'groups_before_email_send', $to, $subject, $template, $data );

		try {
			$sent = wp_mail( $to, $subject, $html, $headers );
		} finally {
			remove_filter( 'wp_mail_content_type', $set_html_content_type );
		}

		/**
		 * Fires after an email notification is sent.
		 *
		 * @param bool   $sent     Whether the email was sent successfully.
		 * @param string $to       Recipient email.
		 * @param string $subject  Email subject.
		 * @param string $template Template name.
		 */
		do_action( 'groups_after_email_send', $sent, $to, $subject, $template );

		return $sent;
	}

	/**
	 * Generate a default unsubscribe URL for a given email address.
	 *
	 * Uses an HMAC token instead of exposing the raw email in the URL.
	 *
	 * @param string $email Recipient email address.
	 * @return string Unsubscribe URL.
	 */
	private function get_default_unsubscribe_url( string $email ): string {
		$token = self::generate_unsubscribe_token( $email );

		/**
		 * Filters the default unsubscribe URL.
		 *
		 * @param string $url   The default unsubscribe URL.
		 * @param string $email The recipient email address.
		 */
		return apply_filters(
			'groups_email_unsubscribe_url',
			add_query_arg(
				[
					'action' => 'groups_email_preferences',
					'token'  => $token,
				],
				home_url( '/email-preferences/' )
			),
			$email
		);
	}

	/**
	 * Generate an HMAC-based unsubscribe token for an email address.
	 *
	 * The token encodes the email address securely using wp_hash() so that
	 * the raw email is not exposed in the unsubscribe URL query string.
	 *
	 * @param string $email The email address to generate a token for.
	 * @return string The HMAC token (base64url-encoded email + hash).
	 */
	public static function generate_unsubscribe_token( string $email ): string {
		$hash = wp_hash( 'unsubscribe:' . $email, 'nonce' );

		// Encode both email and hash together so the server can verify.
		$payload = base64_encode( $email . '|' . $hash );

		// Make URL-safe by replacing +/= characters.
		return rtrim( strtr( $payload, '+/', '-_' ), '=' );
	}

	/**
	 * Verify and extract the email address from an unsubscribe token.
	 *
	 * @param string $token The unsubscribe token to verify.
	 * @return string|false The email address if valid, false otherwise.
	 */
	public static function verify_unsubscribe_token( string $token ): string|false {
		// Restore base64 encoding.
		$payload = base64_decode( strtr( $token, '-_', '+/' ) );

		if ( false === $payload ) {
			return false;
		}

		$parts = explode( '|', $payload, 2 );

		if ( count( $parts ) !== 2 ) {
			return false;
		}

		[ $email, $hash ] = $parts;

		$expected_hash = wp_hash( 'unsubscribe:' . $email, 'nonce' );

		if ( ! hash_equals( $expected_hash, $hash ) ) {
			return false;
		}

		return $email;
	}
}
