<?php
/**
 * Query builder for the groups_activity_log table.
 *
 * @package Groups\Database
 */

namespace Groups\Database;

/**
 * Provides insert and query methods for the network-wide
 * groups_activity_log table.
 *
 * All queries use $wpdb->prepare() to prevent SQL injection.
 */
class Activity_Log_Table {

	/**
	 * Get the full table name.
	 *
	 * @return string
	 */
	private static function table(): string {
		global $wpdb;

		return $wpdb->base_prefix . 'groups_activity_log';
	}

	/**
	 * Insert an activity log entry.
	 *
	 * @param int    $blog_id   Blog ID.
	 * @param int    $user_id   User ID (0 for system actions).
	 * @param string $action    Action identifier (e.g. 'member_joined', 'event_created').
	 * @param int    $object_id Related object ID (post ID, comment ID, etc.).
	 * @param array  $meta      Optional. Additional metadata, stored as JSON.
	 * @return int|false The inserted row ID, or false on failure.
	 */
	public static function insert( int $blog_id, int $user_id, string $action, int $object_id, array $meta = [] ) {
		global $wpdb;

		$table     = self::table();
		$meta_json = ! empty( $meta ) ? wp_json_encode( $meta ) : null;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$table,
			[
				'blog_id'   => $blog_id,
				'user_id'   => $user_id,
				'action'    => $action,
				'object_id' => $object_id,
				'meta'      => $meta_json,
			],
			[ '%d', '%d', '%s', '%d', '%s' ]
		);

		if ( false === $result ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Query activity log entries with optional filters.
	 *
	 * @param array $args {
	 *     Optional. Query arguments.
	 *
	 *     @type int    $blog_id   Filter by blog ID.
	 *     @type int    $user_id   Filter by user ID.
	 *     @type string $action    Filter by action name.
	 *     @type string $date_from Start date (inclusive), Y-m-d format.
	 *     @type string $date_to   End date (inclusive), Y-m-d format.
	 *     @type int    $limit     Max rows to return. Default 50.
	 *     @type int    $offset    Offset for pagination. Default 0.
	 * }
	 * @return array Array of row objects with meta decoded from JSON.
	 */
	public static function query( array $args = [] ): array {
		global $wpdb;

		$table  = self::table();
		$where  = [];
		$values = [];

		if ( ! empty( $args['blog_id'] ) ) {
			$where[]  = '`blog_id` = %d';
			$values[] = (int) $args['blog_id'];
		}

		if ( ! empty( $args['user_id'] ) ) {
			$where[]  = '`user_id` = %d';
			$values[] = (int) $args['user_id'];
		}

		if ( ! empty( $args['action'] ) ) {
			$where[]  = '`action` = %s';
			$values[] = $args['action'];
		}

		if ( ! empty( $args['date_from'] ) ) {
			$where[]  = '`created_at` >= %s';
			$values[] = $args['date_from'] . ' 00:00:00';
		}

		if ( ! empty( $args['date_to'] ) ) {
			$where[]  = '`created_at` <= %s';
			$values[] = $args['date_to'] . ' 23:59:59';
		}

		$limit  = isset( $args['limit'] ) ? absint( $args['limit'] ) : 50;
		$offset = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;

		$sql = "SELECT * FROM {$table}";

		if ( $where ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where );
		}

		$sql .= ' ORDER BY `created_at` DESC';
		$sql .= ' LIMIT %d OFFSET %d';

		$values[] = $limit;
		$values[] = $offset;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $values ) );

		return array_map( [ static::class, 'decode_row' ], $rows );
	}

	/**
	 * Get recent activity for a specific group.
	 *
	 * @param int $blog_id Blog ID.
	 * @param int $limit   Number of entries to return. Default 20.
	 * @return array Array of row objects with meta decoded from JSON.
	 */
	public static function get_recent( int $blog_id, int $limit = 20 ): array {
		return self::query(
			[
				'blog_id' => $blog_id,
				'limit'   => $limit,
				'offset'  => 0,
			]
		);
	}

	/**
	 * Decode the meta JSON field on a row object.
	 *
	 * @param object $row Database row.
	 * @return object Row with meta decoded.
	 */
	private static function decode_row( object $row ): object {
		if ( ! empty( $row->meta ) ) {
			$row->meta = json_decode( $row->meta, true );
		} else {
			$row->meta = [];
		}

		return $row;
	}
}
