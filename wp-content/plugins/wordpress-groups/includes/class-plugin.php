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

		add_action( 'init', [ Models\Membership::class, 'register_roles' ] );
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
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
	}
}
