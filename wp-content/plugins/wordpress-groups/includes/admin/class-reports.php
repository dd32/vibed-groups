<?php
/**
 * Admin reports page with CSV export.
 *
 * Provides a network admin page for deputies and admins to view
 * tabular reports with date range filtering and CSV download.
 *
 * @package Groups\Admin
 */

namespace Groups\Admin;

use Groups\Database\Analytics_Table;

defined( 'ABSPATH' ) || exit;

/**
 * Network admin reports page.
 */
class Reports {

	/**
	 * Available report types.
	 *
	 * @var array<string, string>
	 */
	private const REPORT_TYPES = [
		'events_per_period'        => 'Events per Period',
		'geographic_distribution'  => 'Geographic Distribution',
		'member_growth'            => 'Member Growth',
	];

	/**
	 * Metrics mapped to each report type.
	 *
	 * @var array<string, string[]>
	 */
	private const REPORT_METRICS = [
		'events_per_period'       => [ 'events_held', 'events_created' ],
		'geographic_distribution' => [ 'events_held', 'members' ],
		'member_growth'           => [ 'members', 'members_joined', 'members_left' ],
	];

	/**
	 * Constructor. Registers hooks.
	 */
	public function __construct() {
		add_action( 'network_admin_menu', [ $this, 'register_menu_page' ] );
		add_action( 'network_admin_menu', [ $this, 'handle_csv_export' ], 5 );
	}

	/**
	 * Register the network admin menu page.
	 */
	public function register_menu_page(): void {
		add_menu_page(
			__( 'Group Reports', 'wordpress-groups' ),
			__( 'Group Reports', 'wordpress-groups' ),
			'manage_options',
			'group-reports',
			[ $this, 'render_page' ],
			'dashicons-chart-area',
			31
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
	 * Get the available report types.
	 *
	 * @return array<string, string>
	 */
	public static function get_report_types(): array {
		return self::REPORT_TYPES;
	}

	/**
	 * Handle CSV export before headers are sent.
	 *
	 * Checks for the export action early in the request lifecycle
	 * so we can send headers and output before any HTML.
	 */
	public function handle_csv_export(): void {
		if ( ! isset( $_GET['page'] ) || 'group-reports' !== $_GET['page'] ) {
			return;
		}

		if ( empty( $_GET['export_csv'] ) ) {
			return;
		}

		if ( ! self::current_user_can_access() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$nonce = isset( $_GET['_wpnonce'] ) ? wp_unslash( $_GET['_wpnonce'] ) : '';

		if ( ! wp_verify_nonce( $nonce, 'groups_report_csv' ) ) {
			return;
		}

		$params = $this->get_request_params();
		$rows   = $this->query_report_data( $params['report_type'], $params['date_from'], $params['date_to'] );

		$this->send_csv( $params['report_type'], $rows );
	}

	/**
	 * Parse and sanitise request parameters.
	 *
	 * @return array{report_type: string, date_from: string, date_to: string}
	 */
	public function get_request_params(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$report_type = isset( $_GET['report_type'] ) ? sanitize_text_field( wp_unslash( $_GET['report_type'] ) ) : 'events_per_period';

		if ( ! array_key_exists( $report_type, self::REPORT_TYPES ) ) {
			$report_type = 'events_per_period';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : gmdate( 'Y-m-d', strtotime( '-30 days' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$date_to = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : gmdate( 'Y-m-d' );

		// Validate date format.
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from ) ) {
			$date_from = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
		}

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to ) ) {
			$date_to = gmdate( 'Y-m-d' );
		}

		return [
			'report_type' => $report_type,
			'date_from'   => $date_from,
			'date_to'     => $date_to,
		];
	}

	/**
	 * Query report data from the analytics table.
	 *
	 * @param string $report_type Report type key.
	 * @param string $date_from   Start date (Y-m-d).
	 * @param string $date_to     End date (Y-m-d).
	 * @return array Array of row objects.
	 */
	public function query_report_data( string $report_type, string $date_from, string $date_to ): array {
		$metrics = self::REPORT_METRICS[ $report_type ] ?? [ 'events_held' ];
		$all_rows = [];

		foreach ( $metrics as $metric ) {
			$rows = Analytics_Table::query( [
				'date_from' => $date_from,
				'date_to'   => $date_to,
				'metric'    => $metric,
			] );

			$all_rows = array_merge( $all_rows, $rows );
		}

		return $all_rows;
	}

	/**
	 * Build the report as a structured array suitable for display or CSV.
	 *
	 * For geographic_distribution, groups rows by blog_id with country meta.
	 * For events_per_period, groups by date.
	 * For member_growth, groups by date with joined/left columns.
	 *
	 * @param string $report_type Report type key.
	 * @param array  $rows        Raw analytics rows.
	 * @return array{headers: string[], rows: array}
	 */
	public function build_report_table( string $report_type, array $rows ): array {
		switch ( $report_type ) {
			case 'geographic_distribution':
				return $this->build_geographic_report( $rows );

			case 'member_growth':
				return $this->build_member_growth_report( $rows );

			case 'events_per_period':
			default:
				return $this->build_events_report( $rows );
		}
	}

	/**
	 * Build the events per period report.
	 *
	 * @param array $rows Raw analytics rows.
	 * @return array{headers: string[], rows: array}
	 */
	private function build_events_report( array $rows ): array {
		$by_date = [];

		foreach ( $rows as $row ) {
			$date = $row->date ?? '';
			if ( ! isset( $by_date[ $date ] ) ) {
				$by_date[ $date ] = [
					'events_held'    => 0,
					'events_created' => 0,
				];
			}

			$metric = $row->metric ?? '';
			if ( isset( $by_date[ $date ][ $metric ] ) ) {
				$by_date[ $date ][ $metric ] += (int) $row->value;
			}
		}

		ksort( $by_date );

		$table_rows = [];
		foreach ( $by_date as $date => $values ) {
			$table_rows[] = [
				'date'           => $date,
				'events_held'    => $values['events_held'],
				'events_created' => $values['events_created'],
			];
		}

		return [
			'headers' => [ 'Date', 'Events Held', 'Events Created' ],
			'rows'    => $table_rows,
		];
	}

	/**
	 * Build the geographic distribution report.
	 *
	 * Groups analytics by blog_id and includes country metadata from
	 * the wp_meetup post linked to each group site.
	 *
	 * @param array $rows Raw analytics rows.
	 * @return array{headers: string[], rows: array}
	 */
	private function build_geographic_report( array $rows ): array {
		$by_blog = [];

		foreach ( $rows as $row ) {
			$blog_id = (int) ( $row->blog_id ?? 0 );
			if ( ! isset( $by_blog[ $blog_id ] ) ) {
				$by_blog[ $blog_id ] = [
					'events_held' => 0,
					'members'     => 0,
				];
			}

			$metric = $row->metric ?? '';
			if ( isset( $by_blog[ $blog_id ][ $metric ] ) ) {
				$by_blog[ $blog_id ][ $metric ] += (int) $row->value;
			}
		}

		$table_rows = [];
		foreach ( $by_blog as $blog_id => $values ) {
			$country = $this->get_group_country( $blog_id );

			$table_rows[] = [
				'blog_id'     => $blog_id,
				'country'     => $country ?: __( 'Unknown', 'wordpress-groups' ),
				'events_held' => $values['events_held'],
				'members'     => $values['members'],
			];
		}

		return [
			'headers' => [ 'Blog ID', 'Country', 'Events Held', 'Members' ],
			'rows'    => $table_rows,
		];
	}

	/**
	 * Build the member growth report.
	 *
	 * @param array $rows Raw analytics rows.
	 * @return array{headers: string[], rows: array}
	 */
	private function build_member_growth_report( array $rows ): array {
		$by_date = [];

		foreach ( $rows as $row ) {
			$date = $row->date ?? '';
			if ( ! isset( $by_date[ $date ] ) ) {
				$by_date[ $date ] = [
					'members'        => 0,
					'members_joined' => 0,
					'members_left'   => 0,
				];
			}

			$metric = $row->metric ?? '';
			if ( isset( $by_date[ $date ][ $metric ] ) ) {
				$by_date[ $date ][ $metric ] += (int) $row->value;
			}
		}

		ksort( $by_date );

		$table_rows = [];
		foreach ( $by_date as $date => $values ) {
			$table_rows[] = [
				'date'           => $date,
				'members'        => $values['members'],
				'members_joined' => $values['members_joined'],
				'members_left'   => $values['members_left'],
			];
		}

		return [
			'headers' => [ 'Date', 'Total Members', 'Members Joined', 'Members Left' ],
			'rows'    => $table_rows,
		];
	}

	/**
	 * Get the country for a group site by looking up the linked wp_meetup post.
	 *
	 * @param int $blog_id Blog ID.
	 * @return string Country name or empty string.
	 */
	private function get_group_country( int $blog_id ): string {
		$meetup_posts = get_posts( [
			'post_type'  => 'wp_meetup',
			'meta_key'   => '_meetup_site_id',
			'meta_value' => $blog_id,
			'fields'     => 'ids',
			'numberposts' => 1,
		] );

		if ( empty( $meetup_posts ) ) {
			return '';
		}

		return (string) get_post_meta( $meetup_posts[0], '_meetup_country', true );
	}

	/**
	 * Send CSV download response and exit.
	 *
	 * @param string $report_type Report type key.
	 * @param array  $rows        Raw analytics rows.
	 */
	private function send_csv( string $report_type, array $rows ): void {
		$report   = $this->build_report_table( $report_type, $rows );
		$filename = 'groups-report-' . $report_type . '-' . gmdate( 'Y-m-d' ) . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$output = fopen( 'php://output', 'w' );

		if ( ! $output ) {
			return;
		}

		// Write headers.
		fputcsv( $output, $report['headers'] );

		// Write data rows.
		foreach ( $report['rows'] as $row ) {
			fputcsv( $output, array_values( $row ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $output );

		exit;
	}

	/**
	 * Render the admin page.
	 */
	public function render_page(): void {
		if ( ! self::current_user_can_access() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wordpress-groups' ) );
		}

		$params = $this->get_request_params();
		$rows   = $this->query_report_data( $params['report_type'], $params['date_from'], $params['date_to'] );
		$report = $this->build_report_table( $params['report_type'], $rows );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Group Reports', 'wordpress-groups' ) . '</h1>';

		// Date range picker and report type selector.
		$this->render_filters( $params );

		// CSV export button.
		$this->render_csv_button( $params );

		// Report table.
		$this->render_table( $report );

		echo '</div>';
	}

	/**
	 * Render the filter form with date range picker and report type selector.
	 *
	 * @param array $params Current request parameters.
	 */
	private function render_filters( array $params ): void {
		$base_url = network_admin_url( 'admin.php?page=group-reports' );

		echo '<form method="get" action="' . esc_url( $base_url ) . '">';
		echo '<input type="hidden" name="page" value="group-reports" />';

		echo '<div class="tablenav top">';
		echo '<div class="alignleft actions">';

		// Report type selector.
		echo '<label for="report_type">' . esc_html__( 'Report:', 'wordpress-groups' ) . ' </label>';
		echo '<select name="report_type" id="report_type">';
		foreach ( self::REPORT_TYPES as $key => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $key ),
				selected( $params['report_type'], $key, false ),
				esc_html( $label )
			);
		}
		echo '</select> ';

		// Date range.
		echo '<label for="date_from">' . esc_html__( 'From:', 'wordpress-groups' ) . ' </label>';
		echo '<input type="date" name="date_from" id="date_from" value="' . esc_attr( $params['date_from'] ) . '" /> ';

		echo '<label for="date_to">' . esc_html__( 'To:', 'wordpress-groups' ) . ' </label>';
		echo '<input type="date" name="date_to" id="date_to" value="' . esc_attr( $params['date_to'] ) . '" /> ';

		submit_button( __( 'Filter', 'wordpress-groups' ), 'secondary', 'filter_action', false );

		echo '</div>';
		echo '</div>';
		echo '</form>';
	}

	/**
	 * Render the CSV export button.
	 *
	 * @param array $params Current request parameters.
	 */
	private function render_csv_button( array $params ): void {
		$csv_url = wp_nonce_url(
			add_query_arg(
				[
					'page'        => 'group-reports',
					'report_type' => $params['report_type'],
					'date_from'   => $params['date_from'],
					'date_to'     => $params['date_to'],
					'export_csv'  => '1',
				],
				network_admin_url( 'admin.php' )
			),
			'groups_report_csv'
		);

		echo '<p>';
		echo '<a href="' . esc_url( $csv_url ) . '" class="button">';
		echo esc_html__( 'Download CSV', 'wordpress-groups' );
		echo '</a>';
		echo '</p>';
	}

	/**
	 * Render the report as an HTML table.
	 *
	 * @param array $report Report data with headers and rows.
	 */
	private function render_table( array $report ): void {
		if ( empty( $report['rows'] ) ) {
			echo '<p>' . esc_html__( 'No data found for the selected date range.', 'wordpress-groups' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped">';
		echo '<thead><tr>';

		foreach ( $report['headers'] as $header ) {
			echo '<th>' . esc_html( $header ) . '</th>';
		}

		echo '</tr></thead>';
		echo '<tbody>';

		foreach ( $report['rows'] as $row ) {
			echo '<tr>';
			foreach ( array_values( $row ) as $cell ) {
				echo '<td>' . esc_html( (string) $cell ) . '</td>';
			}
			echo '</tr>';
		}

		echo '</tbody>';
		echo '</table>';
	}
}
