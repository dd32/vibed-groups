<?php
/**
 * Network admin "Group Reports" page.
 *
 * Displays date-range-filtered analytics tables (events, geographic, growth)
 * with CSV export support. Data is sourced from Analytics_Table.
 *
 * @package Groups\Admin
 */

namespace Groups\Admin;

use Groups\Database\Analytics_Table;

defined( 'ABSPATH' ) || exit;

/**
 * Group Reports admin page for the network dashboard.
 */
class Reports {

	/**
	 * Menu page hook suffix.
	 *
	 * @var string
	 */
	private string $hook_suffix = '';

	/**
	 * Constructor. Registers hooks.
	 */
	public function __construct() {
		add_action( 'network_admin_menu', [ $this, 'register_menu_page' ] );
		add_action( 'admin_init', [ $this, 'handle_csv_export' ] );
	}

	/**
	 * Register the network admin menu page.
	 */
	public function register_menu_page(): void {
		$this->hook_suffix = add_submenu_page(
			'group-applications',
			__( 'Group Reports', 'wordpress-groups' ),
			__( 'Reports', 'wordpress-groups' ),
			'manage_options',
			'group-reports',
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Get the menu hook suffix.
	 *
	 * @return string
	 */
	public function get_hook_suffix(): string {
		return $this->hook_suffix;
	}

	/**
	 * Check whether the current user has access.
	 *
	 * @return bool
	 */
	public static function current_user_can_access(): bool {
		return is_super_admin() || current_user_can( 'manage_options' );
	}

	/**
	 * Get the sanitised date range from the request.
	 *
	 * Defaults to the last 30 days.
	 *
	 * @return array{date_from: string, date_to: string}
	 */
	public static function get_date_range(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$date_from = isset( $_REQUEST['date_from'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['date_from'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$date_to = isset( $_REQUEST['date_to'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['date_to'] ) ) : '';

		// Validate Y-m-d format.
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from ) ) {
			$date_from = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
		}

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to ) ) {
			$date_to = gmdate( 'Y-m-d' );
		}

		return [
			'date_from' => $date_from,
			'date_to'   => $date_to,
		];
	}

	/**
	 * Render the admin page.
	 */
	public function render_page(): void {
		if ( ! self::current_user_can_access() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wordpress-groups' ) );
		}

		$range     = self::get_date_range();
		$date_from = $range['date_from'];
		$date_to   = $range['date_to'];

		$rows = Analytics_Table::query( [
			'date_from' => $date_from,
			'date_to'   => $date_to,
		] );

		$events_data     = self::build_events_table( $rows );
		$geographic_data = self::build_geographic_table( $rows );
		$growth_data     = self::build_growth_table( $rows );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Group Reports', 'wordpress-groups' ) . '</h1>';

		// Date range picker.
		echo '<form method="get" class="groups-date-range-form">';
		echo '<input type="hidden" name="page" value="group-reports" />';
		echo '<label for="date_from">' . esc_html__( 'From:', 'wordpress-groups' ) . '</label> ';
		echo '<input type="date" id="date_from" name="date_from" value="' . esc_attr( $date_from ) . '" /> ';
		echo '<label for="date_to">' . esc_html__( 'To:', 'wordpress-groups' ) . '</label> ';
		echo '<input type="date" id="date_to" name="date_to" value="' . esc_attr( $date_to ) . '" /> ';
		submit_button( __( 'Filter', 'wordpress-groups' ), 'secondary', 'filter_action', false );
		echo '</form>';

		// CSV export link.
		$export_url = wp_nonce_url(
			add_query_arg(
				[
					'page'      => 'group-reports',
					'action'    => 'csv_export',
					'date_from' => $date_from,
					'date_to'   => $date_to,
				],
				network_admin_url( 'admin.php' )
			),
			'groups_csv_export'
		);
		echo '<p><a href="' . esc_url( $export_url ) . '" class="button">';
		echo esc_html__( 'Export CSV', 'wordpress-groups' );
		echo '</a></p>';

		// Events table.
		echo '<h2>' . esc_html__( 'Events Summary', 'wordpress-groups' ) . '</h2>';
		self::render_table(
			[ __( 'Group', 'wordpress-groups' ), __( 'Events Held', 'wordpress-groups' ), __( 'RSVPs', 'wordpress-groups' ), __( 'Attendees', 'wordpress-groups' ) ],
			$events_data
		);

		// Geographic table.
		echo '<h2>' . esc_html__( 'Geographic Distribution', 'wordpress-groups' ) . '</h2>';
		self::render_table(
			[ __( 'Country', 'wordpress-groups' ), __( 'Groups', 'wordpress-groups' ), __( 'Total Members', 'wordpress-groups' ), __( 'Total Events', 'wordpress-groups' ) ],
			$geographic_data
		);

		// Growth table.
		echo '<h2>' . esc_html__( 'Growth Metrics', 'wordpress-groups' ) . '</h2>';
		self::render_table(
			[ __( 'Metric', 'wordpress-groups' ), __( 'Value', 'wordpress-groups' ) ],
			$growth_data
		);

		echo '</div>';
	}

	/**
	 * Build events summary data grouped by blog_id.
	 *
	 * @param array $rows Analytics rows.
	 * @return array Array of table row arrays.
	 */
	public static function build_events_table( array $rows ): array {
		$groups = [];

		foreach ( $rows as $row ) {
			$blog_id = (int) $row->blog_id;

			if ( ! isset( $groups[ $blog_id ] ) ) {
				$groups[ $blog_id ] = [
					'events_held'  => 0,
					'rsvps_total'  => 0,
					'attendees'    => 0,
				];
			}

			if ( 'events_held' === $row->metric ) {
				$groups[ $blog_id ]['events_held'] += (int) $row->value;
			} elseif ( 'rsvps_total' === $row->metric ) {
				$groups[ $blog_id ]['rsvps_total'] += (int) $row->value;
			} elseif ( 'attendees' === $row->metric ) {
				$groups[ $blog_id ]['attendees'] += (int) $row->value;
			}
		}

		$table = [];
		foreach ( $groups as $blog_id => $data ) {
			$blog_details = get_blog_details( $blog_id );
			$name         = $blog_details ? $blog_details->blogname : sprintf( 'Site #%d', $blog_id );

			$table[] = [ $name, $data['events_held'], $data['rsvps_total'], $data['attendees'] ];
		}

		return $table;
	}

	/**
	 * Build geographic distribution data.
	 *
	 * Groups analytics rows by country (from wp_meetup post meta).
	 *
	 * @param array $rows Analytics rows.
	 * @return array Array of table row arrays.
	 */
	public static function build_geographic_table( array $rows ): array {
		$blog_ids = [];
		foreach ( $rows as $row ) {
			$blog_ids[ (int) $row->blog_id ] = true;
		}

		$countries = [];

		foreach ( array_keys( $blog_ids ) as $blog_id ) {
			$country = self::get_country_for_blog( $blog_id );

			if ( ! isset( $countries[ $country ] ) ) {
				$countries[ $country ] = [
					'groups'  => 0,
					'members' => 0,
					'events'  => 0,
				];
			}

			$countries[ $country ]['groups']++;

			foreach ( $rows as $row ) {
				if ( (int) $row->blog_id !== $blog_id ) {
					continue;
				}
				if ( 'members' === $row->metric ) {
					$countries[ $country ]['members'] += (int) $row->value;
				} elseif ( 'events_held' === $row->metric ) {
					$countries[ $country ]['events'] += (int) $row->value;
				}
			}
		}

		ksort( $countries );

		$table = [];
		foreach ( $countries as $country => $data ) {
			$table[] = [ $country, $data['groups'], $data['members'], $data['events'] ];
		}

		return $table;
	}

	/**
	 * Build growth metrics (network-wide totals).
	 *
	 * @param array $rows Analytics rows.
	 * @return array Array of [ label, value ] pairs.
	 */
	public static function build_growth_table( array $rows ): array {
		$totals = [
			'members_joined' => 0,
			'members_left'   => 0,
			'events_created' => 0,
			'newcomers'      => 0,
		];

		foreach ( $rows as $row ) {
			if ( isset( $totals[ $row->metric ] ) ) {
				$totals[ $row->metric ] += (int) $row->value;
			}
		}

		$net_growth = $totals['members_joined'] - $totals['members_left'];

		return [
			[ __( 'Members Joined', 'wordpress-groups' ), $totals['members_joined'] ],
			[ __( 'Members Left', 'wordpress-groups' ), $totals['members_left'] ],
			[ __( 'Net Member Growth', 'wordpress-groups' ), $net_growth ],
			[ __( 'Events Created', 'wordpress-groups' ), $totals['events_created'] ],
			[ __( 'Newcomers', 'wordpress-groups' ), $totals['newcomers'] ],
		];
	}

	/**
	 * Render an HTML table from headers and row data.
	 *
	 * @param array $headers Column headers.
	 * @param array $rows    Table row arrays.
	 */
	public static function render_table( array $headers, array $rows ): void {
		echo '<table class="widefat striped">';
		echo '<thead><tr>';
		foreach ( $headers as $header ) {
			echo '<th>' . esc_html( $header ) . '</th>';
		}
		echo '</tr></thead>';
		echo '<tbody>';

		if ( empty( $rows ) ) {
			echo '<tr><td colspan="' . count( $headers ) . '">';
			echo esc_html__( 'No data available for this date range.', 'wordpress-groups' );
			echo '</td></tr>';
		} else {
			foreach ( $rows as $row ) {
				echo '<tr>';
				foreach ( $row as $cell ) {
					echo '<td>' . esc_html( $cell ) . '</td>';
				}
				echo '</tr>';
			}
		}

		echo '</tbody></table>';
	}

	/**
	 * Handle CSV export request.
	 */
	public function handle_csv_export(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['action'] ) || 'csv_export' !== $_GET['action'] ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['page'] ) || 'group-reports' !== $_GET['page'] ) {
			return;
		}

		if ( ! self::current_user_can_access() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$nonce = isset( $_GET['_wpnonce'] ) ? wp_unslash( $_GET['_wpnonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'groups_csv_export' ) ) {
			return;
		}

		$range = self::get_date_range();

		$rows = Analytics_Table::query( [
			'date_from' => $range['date_from'],
			'date_to'   => $range['date_to'],
		] );

		$csv_rows = self::build_csv_data( $rows );

		self::send_csv( $csv_rows, $range['date_from'], $range['date_to'] );
	}

	/**
	 * Build CSV data from analytics rows.
	 *
	 * @param array $rows Analytics row objects.
	 * @return array Array of arrays suitable for CSV output.
	 */
	public static function build_csv_data( array $rows ): array {
		$csv = [];
		$csv[] = [ 'Date', 'Blog ID', 'Group Name', 'Metric', 'Value' ];

		foreach ( $rows as $row ) {
			$blog_details = get_blog_details( (int) $row->blog_id );
			$name         = $blog_details ? $blog_details->blogname : sprintf( 'Site #%d', $row->blog_id );

			$csv[] = [
				$row->date,
				$row->blog_id,
				$name,
				$row->metric,
				$row->value,
			];
		}

		return $csv;
	}

	/**
	 * Send CSV data as a file download.
	 *
	 * @param array  $csv_rows  Array of row arrays.
	 * @param string $date_from Start date for filename.
	 * @param string $date_to   End date for filename.
	 */
	public static function send_csv( array $csv_rows, string $date_from, string $date_to ): void {
		$filename = sprintf( 'group-reports-%s-to-%s.csv', $date_from, $date_to );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$output = fopen( 'php://output', 'w' );

		foreach ( $csv_rows as $row ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv
			fputcsv( $output, $row );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $output );
		exit;
	}

	/**
	 * Look up the country for a blog by finding its wp_meetup post.
	 *
	 * @param int $blog_id Blog ID.
	 * @return string Country name or 'Unknown'.
	 */
	public static function get_country_for_blog( int $blog_id ): string {
		$posts = get_posts( [
			'post_type'      => 'wp_meetup',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'meta_key'       => '_meetup_site_id',
			'meta_value'     => $blog_id,
		] );

		if ( empty( $posts ) ) {
			return __( 'Unknown', 'wordpress-groups' );
		}

		$country = get_post_meta( $posts[0]->ID, '_meetup_country', true );

		return $country ? $country : __( 'Unknown', 'wordpress-groups' );
	}
}
