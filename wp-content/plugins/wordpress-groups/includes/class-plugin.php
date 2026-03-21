<?php
/**
 * Central plugin orchestrator.
 *
 * @package Groups
 */

namespace Groups;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin class — singleton orchestrator that wires up all sub-components.
 */
class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Plugin
	 */
	public static function get_instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor — private, use get_instance().
	 */
	private function __construct() {
		$this->init_components();
	}

	/**
	 * Initialise plugin components.
	 */
	private function init_components(): void {
		new Post_Types\Event();
		new Post_Types\Venue();
		new Blocks\Block_Registrar();
		new Analytics\Newcomer_Tracker();
		new Analytics\Activity_Logger();
		new Analytics\Aggregator();
		Analytics\Aggregator::schedule_cron();
		new Analytics\Dormancy_Detector();
		Analytics\Dormancy_Detector::schedule_cron();
		new Calendar\ICal_Export();
		new Notifications\Scheduler();
		new Notifications\Announcements();
		new Workflow\Application_Workflow();
		new Workflow\Status_Transition();
		new Workflow\Site_Provisioner();
		new Models\Recurrence_Generator();
		Models\Recurrence_Generator::schedule_cron();
		new Integrations\Slack_Notifier();
		new Integrations\Official_Events_API();
		new Integrations\WordPress_Org_Profile();
		new Admin\Application_Tracker();
		new Admin\Reports();
		new Admin\Event_Duplicator();
		new Workflow\Organizer_Onboarding();
		new Notifications\Rsvp_Notifications();
		new Blocks\Recurrence_Panel();
		new SEO();
		new Geocoder();

		Cache::register_hooks();

		add_action( 'init', [ Models\Membership::class, 'register_roles' ] );
		add_action( 'init', [ $this, 'load_textdomain' ] );
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue_google_fonts' ] );
		REST\Rate_Limiter::register();
	}

	/**
	 * Load plugin text domain for translations.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'wordpress-groups',
			false,
			dirname( plugin_basename( GROUPS_PLUGIN_FILE ) ) . '/languages'
		);
	}

	/**
	 * Conditionally enqueue Google Fonts.
	 *
	 * GDPR note: Loading fonts from fonts.googleapis.com transmits visitor IP
	 * addresses to Google, which may violate GDPR in the EU. This filter allows
	 * site operators to disable external font loading entirely.
	 *
	 * Usage: add_filter( 'groups_load_google_fonts', '__return_false' );
	 *
	 * When disabled, the theme or site should provide its own font stack.
	 * Consider using locally-hosted fonts or the WordPress Webfonts API
	 * (wp_register_webfonts) when available.
	 */
	public function maybe_enqueue_google_fonts(): void {
		/**
		 * Filters whether to load Google Fonts from the external CDN.
		 *
		 * @since 0.1.0
		 *
		 * @param bool $load Whether to load Google Fonts. Default true.
		 */
		if ( ! apply_filters( 'groups_load_google_fonts', true ) ) {
			return;
		}

		// No external Google Fonts are currently enqueued by this plugin.
		// This hook exists so that themes or child plugins that add Google
		// Fonts via this plugin can be toggled off for GDPR compliance.
	}

	/**
	 * Register REST API controllers.
	 */
	public function register_rest_routes(): void {
		$event_controller = new REST\Event_Controller();
		$event_controller->register_routes();

		$membership_controller = new REST\Membership_Controller();
		$membership_controller->register_routes();

		$rsvp_controller = new REST\Rsvp_Controller();
		$rsvp_controller->register_routes();

		$venue_controller = new REST\Venue_Controller();
		$venue_controller->register_routes();

		$directory_controller = new REST\Directory_Controller();
		$directory_controller->register_routes();

		$preferences_controller = new REST\Preferences_Controller();
		$preferences_controller->register_routes();
	}
}
