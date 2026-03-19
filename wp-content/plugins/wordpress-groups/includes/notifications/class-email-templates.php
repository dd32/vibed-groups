<?php
/**
 * Email template loader and renderer.
 *
 * Loads HTML email templates from the email-templates directory,
 * substitutes placeholders, and wraps content in the base template.
 *
 * @package Groups\Notifications
 */

namespace Groups\Notifications;

/**
 * Handles loading, rendering, and placeholder substitution for HTML email templates.
 */
class Email_Templates {

	/**
	 * Path to the email templates directory.
	 *
	 * @var string
	 */
	private string $template_dir;

	/**
	 * Cached base template HTML.
	 *
	 * @var string|null
	 */
	private ?string $base_template = null;

	/**
	 * Constructor.
	 *
	 * @param string|null $template_dir Optional. Path to templates directory.
	 *                                  Defaults to the plugin's email-templates/ directory.
	 */
	public function __construct( ?string $template_dir = null ) {
		$this->template_dir = $template_dir ?? dirname( __DIR__, 2 ) . '/email-templates';
	}

	/**
	 * Render a named email template with placeholder data.
	 *
	 * Loads the content template file, substitutes all {{placeholder}} tokens
	 * with values from $data, processes conditional blocks, and wraps the result
	 * in the base template layout.
	 *
	 * @param string $template_name Template filename without path (e.g. 'rsvp-confirmation').
	 * @param array  $data          Associative array of placeholder => value pairs.
	 * @return string Fully rendered HTML email.
	 *
	 * @throws \InvalidArgumentException If the template file does not exist.
	 */
	public function render( string $template_name, array $data = [] ): string {
		$content_html = $this->load_template( $template_name );
		$content_html = $this->process_conditionals( $content_html, $data );
		$content_html = $this->substitute_placeholders( $content_html, $data );

		$base_html = $this->get_base_template();
		$data['content'] = $content_html;
		$base_html = $this->substitute_placeholders( $base_html, $data );

		/**
		 * Filters the fully rendered email HTML.
		 *
		 * @param string $html          The rendered email HTML.
		 * @param string $template_name The template that was rendered.
		 * @param array  $data          The data passed for placeholder substitution.
		 */
		return apply_filters( 'groups_email_rendered', $base_html, $template_name, $data );
	}

	/**
	 * Load a template file and return its contents.
	 *
	 * @param string $template_name Template name (without .html extension).
	 * @return string Template HTML content.
	 *
	 * @throws \InvalidArgumentException If the template file does not exist.
	 */
	private function load_template( string $template_name ): string {
		// Sanitize template name to prevent directory traversal.
		$template_name = sanitize_file_name( $template_name );
		$file_path     = $this->template_dir . '/' . $template_name . '.html';

		if ( ! file_exists( $file_path ) ) {
			throw new \InvalidArgumentException(
				sprintf( 'Email template "%s" not found at %s', $template_name, $file_path )
			);
		}

		return file_get_contents( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}

	/**
	 * Get the base template HTML, loading it once and caching.
	 *
	 * @return string Base template HTML.
	 */
	private function get_base_template(): string {
		if ( null === $this->base_template ) {
			$this->base_template = $this->load_template( 'base' );
		}

		return $this->base_template;
	}

	/**
	 * Substitute {{placeholder}} tokens in HTML with values from data array.
	 *
	 * Any placeholders without matching data keys are left as-is.
	 *
	 * @param string $html The HTML containing placeholders.
	 * @param array  $data Associative array of placeholder => value pairs.
	 * @return string HTML with placeholders replaced.
	 */
	private function substitute_placeholders( string $html, array $data ): string {
		foreach ( $data as $key => $value ) {
			if ( is_string( $value ) || is_numeric( $value ) ) {
				$html = str_replace( '{{' . $key . '}}', (string) $value, $html );
			}
		}

		return $html;
	}

	/**
	 * Process conditional blocks in templates.
	 *
	 * Supports simple conditionals like:
	 *   {{#if_attending}}...content...{{/if_attending}}
	 *   {{#if_waitlisted}}...content...{{/if_waitlisted}}
	 *
	 * A conditional block is shown if the corresponding data key (without the "if_" prefix)
	 * evaluates to a matching value. For example, {{#if_attending}} is shown when
	 * $data['rsvp_status'] equals 'attending' (case-insensitive).
	 *
	 * @param string $html The HTML containing conditional blocks.
	 * @param array  $data The data array for evaluating conditions.
	 * @return string HTML with conditionals resolved.
	 */
	private function process_conditionals( string $html, array $data ): string {
		return preg_replace_callback(
			'/\{\{#if_(\w+)\}\}(.*?)\{\{\/if_\1\}\}/s',
			function ( $matches ) use ( $data ) {
				$condition = $matches[1]; // e.g., "attending" or "waitlisted"

				// Check against rsvp_status for RSVP templates.
				if ( isset( $data['rsvp_status'] ) && strtolower( $data['rsvp_status'] ) === strtolower( $condition ) ) {
					return $matches[2];
				}

				// Check against status for other templates.
				if ( isset( $data['status'] ) && strtolower( $data['status'] ) === strtolower( $condition ) ) {
					return $matches[2];
				}

				// Check for a direct boolean-like data key.
				if ( ! empty( $data[ 'is_' . $condition ] ) || ! empty( $data[ $condition ] ) ) {
					return $matches[2];
				}

				return '';
			},
			$html
		) ?? $html;
	}

	/**
	 * Get all available template names.
	 *
	 * @return array List of template names (without .html extension).
	 */
	public function get_available_templates(): array {
		$files     = glob( $this->template_dir . '/*.html' );
		$templates = [];

		if ( $files ) {
			foreach ( $files as $file ) {
				$name = basename( $file, '.html' );
				if ( 'base' !== $name ) {
					$templates[] = $name;
				}
			}
		}

		return $templates;
	}
}
