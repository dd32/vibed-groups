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
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
		REST\Rate_Limiter::register();
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
