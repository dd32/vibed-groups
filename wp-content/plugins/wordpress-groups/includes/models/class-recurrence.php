<?php
/**
 * Recurrence model — rules for recurring events.
 *
 * @package Groups\Models
 */

namespace Groups\Models;

defined( 'ABSPATH' ) || exit;

/**
 * Handles recurrence rule storage, retrieval, and occurrence calculation.
 */
class Recurrence {

	/**
	 * Post meta key for the recurrence rule.
	 *
	 * @var string
	 */
	const META_KEY = '_event_recurrence_rule';

	/**
	 * Valid frequency values.
	 *
	 * @var string[]
	 */
	const VALID_FREQUENCIES = [
		'weekly',
		'biweekly',
		'monthly-day',
		'monthly-date',
	];

	/**
	 * Save a recurrence rule to event post meta.
	 *
	 * Validates the rule before saving. The rule is stored as a JSON string.
	 *
	 * @param int   $event_id The event post ID.
	 * @param array $rule {
	 *     Recurrence rule.
	 *
	 *     @type string $frequency     One of: weekly, biweekly, monthly-day, monthly-date. Required.
	 *     @type int    $day_of_week   Day of week (0 = Sunday, 6 = Saturday). Required for weekly, biweekly, monthly-day.
	 *     @type int    $day_of_month  Day of month (1-31). Required for monthly-date.
	 *     @type string $end_date      End date in Y-m-d format. Optional.
	 * }
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function save_rule( int $event_id, array $rule ): true|\WP_Error {
		$validated = self::validate_rule( $rule );

		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		update_post_meta( $event_id, self::META_KEY, wp_json_encode( $validated ) );

		return true;
	}

	/**
	 * Get the recurrence rule for an event.
	 *
	 * @param int $event_id The event post ID.
	 * @return array|null The rule array, or null if no rule is set.
	 */
	public static function get_rule( int $event_id ): ?array {
		$raw = get_post_meta( $event_id, self::META_KEY, true );

		if ( empty( $raw ) ) {
			return null;
		}

		$rule = json_decode( $raw, true );

		if ( ! is_array( $rule ) ) {
			return null;
		}

		return $rule;
	}

	/**
	 * Check whether an event has a recurrence rule.
	 *
	 * @param int $event_id The event post ID.
	 * @return bool True if the event has a recurrence rule.
	 */
	public static function is_recurring( int $event_id ): bool {
		return null !== self::get_rule( $event_id );
	}

	/**
	 * Calculate the next N occurrences for a recurrence rule.
	 *
	 * @param array  $rule       The recurrence rule array.
	 * @param string $start_date Start date in Y-m-d format.
	 * @param int    $count      Number of occurrences to generate. Default 10.
	 * @return \DateTime[] Array of DateTime objects for each occurrence.
	 */
	public static function calculate_occurrences( array $rule, string $start_date, int $count = 10 ): array {
		$frequency = $rule['frequency'] ?? '';
		$end_date  = isset( $rule['end_date'] ) ? new \DateTime( $rule['end_date'] ) : null;

		$occurrences = [];
		$current     = new \DateTime( $start_date );

		for ( $i = 0; $i < $count; $i++ ) {
			if ( $end_date && $current > $end_date ) {
				break;
			}

			$occurrences[] = clone $current;

			switch ( $frequency ) {
				case 'weekly':
					$current->modify( '+1 week' );
					break;

				case 'biweekly':
					$current->modify( '+2 weeks' );
					break;

				case 'monthly-day':
					$current = self::next_monthly_day( $current, (int) $rule['day_of_week'] );
					break;

				case 'monthly-date':
					$current = self::next_monthly_date( $current, (int) $rule['day_of_month'] );
					break;
			}
		}

		return $occurrences;
	}

	/**
	 * Validate a recurrence rule array.
	 *
	 * @param array $rule The rule to validate.
	 * @return array|\WP_Error The sanitised rule on success, WP_Error on failure.
	 */
	private static function validate_rule( array $rule ): array|\WP_Error {
		if ( empty( $rule['frequency'] ) || ! in_array( $rule['frequency'], self::VALID_FREQUENCIES, true ) ) {
			return new \WP_Error(
				'invalid_frequency',
				__( 'Recurrence frequency must be one of: weekly, biweekly, monthly-day, monthly-date.', 'wordpress-groups' )
			);
		}

		$sanitised = [
			'frequency' => $rule['frequency'],
		];

		// Validate day_of_week for frequencies that need it.
		if ( in_array( $rule['frequency'], [ 'weekly', 'biweekly', 'monthly-day' ], true ) ) {
			if ( ! isset( $rule['day_of_week'] ) || ! is_numeric( $rule['day_of_week'] ) ) {
				return new \WP_Error(
					'missing_day_of_week',
					__( 'day_of_week is required for this frequency.', 'wordpress-groups' )
				);
			}

			$day_of_week = (int) $rule['day_of_week'];

			if ( $day_of_week < 0 || $day_of_week > 6 ) {
				return new \WP_Error(
					'invalid_day_of_week',
					__( 'day_of_week must be between 0 (Sunday) and 6 (Saturday).', 'wordpress-groups' )
				);
			}

			$sanitised['day_of_week'] = $day_of_week;
		}

		// Validate day_of_month for monthly-date.
		if ( 'monthly-date' === $rule['frequency'] ) {
			if ( ! isset( $rule['day_of_month'] ) || ! is_numeric( $rule['day_of_month'] ) ) {
				return new \WP_Error(
					'missing_day_of_month',
					__( 'day_of_month is required for monthly-date frequency.', 'wordpress-groups' )
				);
			}

			$day_of_month = (int) $rule['day_of_month'];

			if ( $day_of_month < 1 || $day_of_month > 31 ) {
				return new \WP_Error(
					'invalid_day_of_month',
					__( 'day_of_month must be between 1 and 31.', 'wordpress-groups' )
				);
			}

			$sanitised['day_of_month'] = $day_of_month;
		}

		// Validate optional end_date.
		if ( ! empty( $rule['end_date'] ) ) {
			$date = \DateTime::createFromFormat( 'Y-m-d', $rule['end_date'] );

			if ( ! $date || $date->format( 'Y-m-d' ) !== $rule['end_date'] ) {
				return new \WP_Error(
					'invalid_end_date',
					__( 'end_date must be a valid date in Y-m-d format.', 'wordpress-groups' )
				);
			}

			$sanitised['end_date'] = $rule['end_date'];
		}

		return $sanitised;
	}

	/**
	 * Calculate the next occurrence of a specific weekday-of-month pattern.
	 *
	 * Given a current date (e.g., the first Tuesday of April), find the same
	 * ordinal weekday in the next month (e.g., the first Tuesday of May).
	 *
	 * @param \DateTime $current     The current occurrence date.
	 * @param int       $day_of_week Target day of week (0 = Sunday, 6 = Saturday).
	 * @return \DateTime The next monthly-day occurrence.
	 */
	private static function next_monthly_day( \DateTime $current, int $day_of_week ): \DateTime {
		// Determine the ordinal week of the current date (1st, 2nd, 3rd, etc.).
		$day_number = (int) $current->format( 'j' );
		$ordinal    = (int) ceil( $day_number / 7 );

		$day_names = [ 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' ];
		$day_name  = $day_names[ $day_of_week ];

		// Move to next month.
		$next = clone $current;
		$next->modify( 'first day of next month' );

		// Find the Nth occurrence of the target weekday in that month.
		$ordinal_map = [ 1 => 'first', 2 => 'second', 3 => 'third', 4 => 'fourth', 5 => 'fifth' ];
		$ordinal_str = $ordinal_map[ $ordinal ] ?? 'fourth';

		$next->modify( "{$ordinal_str} {$day_name} of this month" );

		// If the calculated date is not in the expected month (e.g., "fifth Tuesday"
		// overflows), fall back to the fourth occurrence.
		$expected_month = (int) ( clone $current )->modify( 'first day of next month' )->format( 'n' );

		if ( (int) $next->format( 'n' ) !== $expected_month ) {
			$next = clone $current;
			$next->modify( 'first day of next month' );
			$next->modify( "fourth {$day_name} of this month" );
		}

		return $next;
	}

	/**
	 * Calculate the next monthly-date occurrence.
	 *
	 * If the target day doesn't exist in the next month (e.g., 31st in February),
	 * the last day of that month is used instead.
	 *
	 * @param \DateTime $current       The current occurrence date.
	 * @param int       $day_of_month  Target day of month (1-31).
	 * @return \DateTime The next monthly-date occurrence.
	 */
	private static function next_monthly_date( \DateTime $current, int $day_of_month ): \DateTime {
		$next = clone $current;
		$next->modify( 'first day of next month' );

		$days_in_month = (int) $next->format( 't' );
		$target_day    = min( $day_of_month, $days_in_month );

		$next->setDate(
			(int) $next->format( 'Y' ),
			(int) $next->format( 'n' ),
			$target_day
		);

		return $next;
	}
}
