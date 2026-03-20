<?php
/**
 * Application Tracker list table for managing wp_meetup applications.
 *
 * Provides a WP_List_Table-based admin UI on the network admin for
 * deputies to review, filter, and act on group applications.
 *
 * @package Groups\Admin
 */

namespace Groups\Admin;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Application Tracker list table.
 */
class Application_Tracker extends \WP_List_Table {

	/**
	 * Valid meetup statuses for filtering.
	 *
	 * @var array<string, string>
	 */
	private const STATUSES = [
		'meetup-pending'     => 'Pending',
		'meetup-vetting'     => 'Vetting',
		'meetup-feedback'    => 'Feedback',
		'meetup-orientation' => 'Orientation',
		'meetup-scheduling'  => 'Scheduling',
		'meetup-active'      => 'Active',
		'meetup-dormant'     => 'Dormant',
	];

	/**
	 * Constructor. Registers the admin menu hook.
	 */
	public function __construct() {
		add_action( 'network_admin_menu', [ $this, 'register_menu_page' ] );
	}

	/**
	 * Register the network admin menu page.
	 */
	public function register_menu_page(): void {
		add_menu_page(
			__( 'Group Applications', 'wordpress-groups' ),
			__( 'Group Applications', 'wordpress-groups' ),
			'manage_options',
			'group-applications',
			[ $this, 'render_page' ],
			'dashicons-groups',
			30
		);
	}

	/**
	 * Check whether the current user has access.
	 *
	 * @return bool
	 */
	public static function current_user_can_access(): bool {
		return is_super_admin() || current_user_can( 'administrator' );
	}

	/**
	 * Render the admin page.
	 */
	public function render_page(): void {
		if ( ! self::current_user_can_access() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wordpress-groups' ) );
		}

		$this->process_bulk_actions();

		// Re-initialise list table internals for rendering.
		$this->init_list_table();
		$this->prepare_items();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Group Applications', 'wordpress-groups' ) . '</h1>';

		echo '<form method="get">';
		echo '<input type="hidden" name="page" value="group-applications" />';
		$this->display_status_filter();
		$this->display();
		echo '</form>';

		echo '</div>';
	}

	/**
	 * Initialise WP_List_Table internals (called before prepare_items).
	 */
	private function init_list_table(): void {
		parent::__construct( [
			'singular' => 'application',
			'plural'   => 'applications',
			'ajax'     => false,
			'screen'   => 'toplevel_page_group-applications-network',
		] );
	}

	/**
	 * Get the list of columns.
	 *
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		return [
			'cb'           => '<input type="checkbox" />',
			'group_name'   => __( 'Group Name', 'wordpress-groups' ),
			'city_country' => __( 'City/Country', 'wordpress-groups' ),
			'organizer'    => __( 'Organizer', 'wordpress-groups' ),
			'status'       => __( 'Status', 'wordpress-groups' ),
			'applied_date' => __( 'Applied Date', 'wordpress-groups' ),
			'deputy'       => __( 'Deputy', 'wordpress-groups' ),
		];
	}

	/**
	 * Get sortable columns.
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	public function get_sortable_columns(): array {
		return [
			'group_name'   => [ 'title', false ],
			'applied_date' => [ 'date', true ],
			'status'       => [ 'status', false ],
		];
	}

	/**
	 * Get bulk actions.
	 *
	 * @return array<string, string>
	 */
	public function get_bulk_actions(): array {
		return [
			'approve'          => __( 'Approve (→ Vetting)', 'wordpress-groups' ),
			'decline'          => __( 'Decline', 'wordpress-groups' ),
			'request_feedback' => __( 'Request Feedback', 'wordpress-groups' ),
		];
	}

	/**
	 * Process bulk actions.
	 */
	public function process_bulk_actions(): void {
		$action = $this->current_action();

		if ( ! $action ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$nonce = isset( $_REQUEST['_wpnonce'] ) ? wp_unslash( $_REQUEST['_wpnonce'] ) : '';

		if ( ! wp_verify_nonce( $nonce, 'bulk-applications' ) ) {
			return;
		}

		$post_ids = isset( $_REQUEST['application'] ) ? array_map( 'intval', (array) $_REQUEST['application'] ) : [];

		if ( empty( $post_ids ) ) {
			return;
		}

		$status_map = [
			'approve'          => 'meetup-vetting',
			'decline'          => 'meetup-declined',
			'request_feedback' => 'meetup-feedback',
		];

		if ( ! isset( $status_map[ $action ] ) ) {
			return;
		}

		$new_status = $status_map[ $action ];

		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );

			if ( ! $post || 'wp_meetup' !== $post->post_type ) {
				continue;
			}

			wp_update_post( [
				'ID'          => $post_id,
				'post_status' => $new_status,
			] );
		}
	}

	/**
	 * Prepare items for the table.
	 */
	public function prepare_items(): void {
		$per_page = 20;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_page = isset( $_REQUEST['paged'] ) ? max( 1, (int) $_REQUEST['paged'] ) : 1;

		$args = [
			'post_type'      => 'wp_meetup',
			'posts_per_page' => $per_page,
			'paged'          => $current_page,
			'post_status'    => 'any',
		];

		// Status filter.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status_filter = isset( $_REQUEST['meetup_status'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['meetup_status'] ) ) : '';

		if ( $status_filter && array_key_exists( $status_filter, self::STATUSES ) ) {
			$args['post_status'] = $status_filter;
		}

		// Sorting.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) : 'date';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order = isset( $_REQUEST['order'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) : 'DESC';

		$args['orderby'] = $orderby;
		$args['order']   = in_array( strtoupper( $order ), [ 'ASC', 'DESC' ], true ) ? strtoupper( $order ) : 'DESC';

		$query = new \WP_Query( $args );

		$this->items = $query->posts;

		$this->set_pagination_args( [
			'total_items' => $query->found_posts,
			'per_page'    => $per_page,
			'total_pages' => $query->max_num_pages,
		] );

		$this->_column_headers = [
			$this->get_columns(),
			[],
			$this->get_sortable_columns(),
		];
	}

	/**
	 * Render the checkbox column.
	 *
	 * @param \WP_Post $item Post object.
	 * @return string
	 */
	public function column_cb( $item ): string {
		return sprintf(
			'<input type="checkbox" name="application[]" value="%d" />',
			$item->ID
		);
	}

	/**
	 * Render the Group Name column with row actions.
	 *
	 * @param \WP_Post $item Post object.
	 * @return string
	 */
	public function column_group_name( $item ): string {
		$actions = [
			'view'     => sprintf(
				'<a href="%s">%s</a>',
				esc_url( get_permalink( $item->ID ) ),
				__( 'View', 'wordpress-groups' )
			),
			'edit'     => sprintf(
				'<a href="%s">%s</a>',
				esc_url( get_edit_post_link( $item->ID ) ),
				__( 'Edit', 'wordpress-groups' )
			),
			'add_note' => sprintf(
				'<a href="#" class="add-note" data-post-id="%d">%s</a>',
				$item->ID,
				__( 'Add Note', 'wordpress-groups' )
			),
		];

		return sprintf(
			'<strong>%s</strong>%s',
			esc_html( $item->post_title ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * Render the City/Country column.
	 *
	 * @param \WP_Post $item Post object.
	 * @return string
	 */
	public function column_city_country( $item ): string {
		$city    = get_post_meta( $item->ID, '_meetup_city', true );
		$country = get_post_meta( $item->ID, '_meetup_country', true );

		if ( $city && $country ) {
			return esc_html( $city . ', ' . $country );
		}

		return esc_html( $city ?: $country ?: '—' );
	}

	/**
	 * Render the Organizer column.
	 *
	 * @param \WP_Post $item Post object.
	 * @return string
	 */
	public function column_organizer( $item ): string {
		$username = get_post_meta( $item->ID, '_meetup_organizer_username', true );

		return $username ? esc_html( $username ) : '—';
	}

	/**
	 * Render the Status column.
	 *
	 * @param \WP_Post $item Post object.
	 * @return string
	 */
	public function column_status( $item ): string {
		$status = $item->post_status;

		return esc_html( self::STATUSES[ $status ] ?? $status );
	}

	/**
	 * Render the Applied Date column.
	 *
	 * @param \WP_Post $item Post object.
	 * @return string
	 */
	public function column_applied_date( $item ): string {
		$date = get_post_meta( $item->ID, '_meetup_application_date', true );

		if ( ! $date ) {
			$date = $item->post_date;
		}

		return esc_html( mysql2date( 'Y-m-d', $date ) );
	}

	/**
	 * Render the Deputy column.
	 *
	 * @param \WP_Post $item Post object.
	 * @return string
	 */
	public function column_deputy( $item ): string {
		$deputy = get_post_meta( $item->ID, '_meetup_deputy_assigned', true );

		return $deputy ? esc_html( $deputy ) : '—';
	}

	/**
	 * Default column renderer.
	 *
	 * @param \WP_Post $item        Post object.
	 * @param string   $column_name Column name.
	 * @return string
	 */
	public function column_default( $item, $column_name ): string {
		return '—';
	}

	/**
	 * Display the status filter dropdown above the table.
	 */
	private function display_status_filter(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current = isset( $_REQUEST['meetup_status'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['meetup_status'] ) ) : '';

		echo '<div class="tablenav top">';
		echo '<div class="alignleft actions">';
		echo '<select name="meetup_status">';
		echo '<option value="">' . esc_html__( 'All Statuses', 'wordpress-groups' ) . '</option>';

		foreach ( self::STATUSES as $slug => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $slug ),
				selected( $current, $slug, false ),
				esc_html( $label )
			);
		}

		echo '</select>';
		submit_button( __( 'Filter', 'wordpress-groups' ), '', 'filter_action', false );
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Message displayed when no items are found.
	 */
	public function no_items(): void {
		esc_html_e( 'No group applications found.', 'wordpress-groups' );
	}

	/**
	 * Get the available statuses.
	 *
	 * @return array<string, string>
	 */
	public static function get_statuses(): array {
		return self::STATUSES;
	}

	/**
	 * Add an internal note to a meetup post.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $note    Note text.
	 * @param int    $user_id User ID who added the note.
	 * @return bool True on success.
	 */
	public static function add_note( int $post_id, string $note, int $user_id = 0 ): bool {
		$post = get_post( $post_id );

		if ( ! $post || 'wp_meetup' !== $post->post_type ) {
			return false;
		}

		$notes = get_post_meta( $post_id, '_meetup_notes', true );

		if ( ! is_array( $notes ) ) {
			$notes = [];
		}

		$notes[] = [
			'note'    => sanitize_textarea_field( $note ),
			'user_id' => $user_id ?: get_current_user_id(),
			'date'    => current_time( 'mysql', true ),
		];

		return (bool) update_post_meta( $post_id, '_meetup_notes', $notes );
	}

	/**
	 * Get internal notes for a meetup post.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public static function get_notes( int $post_id ): array {
		$notes = get_post_meta( $post_id, '_meetup_notes', true );

		return is_array( $notes ) ? $notes : [];
	}
}
