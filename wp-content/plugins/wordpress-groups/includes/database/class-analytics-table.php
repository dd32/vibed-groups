<?php
/**
 * Query builder for the groups_analytics_daily table.
 *
 * @package Groups\Database
 */

namespace Groups\Database;

/**
 * Provides insert and query methods for the network-wide
 * groups_analytics_daily table.
 *
 * All queries use $wpdb->prepare() to prevent SQL injection.
 */
class Analytics_Table {

	/**
	 * Get the full table name.
	 *
	 * @return string
	 */
	private static function table(): string {
		global $wpdb;

		return $wpdb->base_prefix . 'groups_analytics_daily';
	}

	/**
	 * Insert or update a daily metric row.
	 *
	 * Uses INSERT ... ON DUPLICATE KEY UPDATE to upsert based on the
	 * unique (date, blog_id, metric) constraint.
	 *
	 * @param string $date    Date in Y-m-d format.
	 * @param int    $blog_id Blog ID.
	 * @param string $metric  Metric name (e.g. 'members', 'events_held').
	 * @param int    $value   Metric value.
	 * @return int|false Number of rows affected, or false on failure.
	 */
	public static function insert( string $date, int $blog_id, string $metric, int $value ) {
		global $wpdb;

		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (`date`, `blog_id`, `metric`, `value`)
				VALUES (%s, %d, %s, %d)
				ON DUPLICATE KEY UPDATE `value` = %d",
				$date,
				$blog_id,
				$metric,
				$value,
				$value
			)
		);
	}

	/**
	 * Query analytics rows with optional filters.
	 *
	 * @param array $args {
	 *     Optional. Query arguments.
	 *
	 *     @type string $date_from Start date (inclusive), Y-m-d format.
	 *     @type string $date_to   End date (inclusive), Y-m-d format.
	 *     @type int    $blog_id   Filter by blog ID.
	 *     @type string $metric    Filter by metric name.
	 * }
	 * @return array Array of row objects.
	 */
	public static function query( array $args = [] ): array {
		global $wpdb;

		$table  = self::table();
		$where  = [];
		$values = [];

		if ( ! empty( $args['date_from'] ) ) {
			$where[]  = '`date` >= %s';
			$values[] = $args['date_from'];
		}

		if ( ! empty( $args['date_to'] ) ) {
			$where[]  = '`date` <= %s';
			$values[] = $args['date_to'];
		}

		if ( ! empty( $args['blog_id'] ) ) {
			$where[]  = '`blog_id` = %d';
			$values[] = (int) $args['blog_id'];
		}

		if ( ! empty( $args['metric'] ) ) {
			$where[]  = '`metric` = %s';
			$values[] = $args['metric'];
		}

		$sql = "SELECT * FROM {$table}";

		if ( $where ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where );
		}

		$sql .= ' ORDER BY `date` ASC, `blog_id` ASC, `metric` ASC';

		if ( $values ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			return $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
		}

		// No filters — still safe since $sql contains no user input.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $sql );
	}

	/**
	 * Get aggregated metric totals for a single group within a date range.
	 *
	 * @param int    $blog_id   Blog ID.
	 * @param string $date_from Start date (inclusive), Y-m-d format.
	 * @param string $date_to   End date (inclusive), Y-m-d format.
	 * @return array Associative array of metric => total_value.
	 */
	public static function get_summary( int $blog_id, string $date_from, string $date_to ): array {
		global $wpdb;

		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT `metric`, SUM(`value`) AS total
				FROM {$table}
				WHERE `blog_id` = %d AND `date` >= %s AND `date` <= %s
				GROUP BY `metric`
				ORDER BY `metric` ASC",
				$blog_id,
				$date_from,
				$date_to
			)
		);

		$summary = [];
		foreach ( $rows as $row ) {
			$summary[ $row->metric ] = (int) $row->total;
		}

		return $summary;
	}

	/**
	 * Get aggregated metric totals across all groups within a date range.
	 *
	 * @param string $date_from Start date (inclusive), Y-m-d format.
	 * @param string $date_to   End date (inclusive), Y-m-d format.
	 * @return array Associative array of metric => total_value.
	 */
	public static function get_network_summary( string $date_from, string $date_to ): array {
		global $wpdb;

		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT `metric`, SUM(`value`) AS total
				FROM {$table}
				WHERE `date` >= %s AND `date` <= %s
				GROUP BY `metric`
				ORDER BY `metric` ASC",
				$date_from,
				$date_to
			)
		);

		$summary = [];
		foreach ( $rows as $row ) {
			$summary[ $row->metric ] = (int) $row->total;
		}

		return $summary;
	}
}
