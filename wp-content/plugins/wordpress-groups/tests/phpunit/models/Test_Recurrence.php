<?php
/**
 * Tests for the Recurrence model.
 *
 * @package Groups\Tests
 */

use Groups\Models\Recurrence;

/**
 * @coversDefaultClass \Groups\Models\Recurrence
 */
class Test_Recurrence extends WP_UnitTestCase {

	/**
	 * Helper to create a simple post to use as an event.
	 *
	 * @return int Post ID.
	 */
	private function create_event_post(): int {
		return self::factory()->post->create( [ 'post_type' => 'event' ] );
	}

	/**
	 * @covers ::save_rule
	 * @covers ::get_rule
	 */
	public function test_save_and_get_rule_round_trip(): void {
		$event_id = $this->create_event_post();
		$rule     = [
			'frequency'   => 'weekly',
			'day_of_week' => 2,
		];

		$result = Recurrence::save_rule( $event_id, $rule );

		$this->assertTrue( $result );

		$retrieved = Recurrence::get_rule( $event_id );

		$this->assertIsArray( $retrieved );
		$this->assertSame( 'weekly', $retrieved['frequency'] );
		$this->assertSame( 2, $retrieved['day_of_week'] );
	}

	/**
	 * @covers ::save_rule
	 */
	public function test_save_rule_with_end_date(): void {
		$event_id = $this->create_event_post();
		$rule     = [
			'frequency'   => 'weekly',
			'day_of_week' => 3,
			'end_date'    => '2026-12-31',
		];

		$result    = Recurrence::save_rule( $event_id, $rule );
		$retrieved = Recurrence::get_rule( $event_id );

		$this->assertTrue( $result );
		$this->assertSame( '2026-12-31', $retrieved['end_date'] );
	}

	/**
	 * @covers ::save_rule
	 */
	public function test_save_rule_rejects_invalid_frequency(): void {
		$event_id = $this->create_event_post();
		$result   = Recurrence::save_rule( $event_id, [ 'frequency' => 'daily' ] );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_frequency', $result->get_error_code() );
	}

	/**
	 * @covers ::save_rule
	 */
	public function test_save_rule_rejects_missing_day_of_week(): void {
		$event_id = $this->create_event_post();
		$result   = Recurrence::save_rule( $event_id, [ 'frequency' => 'weekly' ] );

		$this->assertWPError( $result );
		$this->assertSame( 'missing_day_of_week', $result->get_error_code() );
	}

	/**
	 * @covers ::save_rule
	 */
	public function test_save_rule_rejects_invalid_day_of_week(): void {
		$event_id = $this->create_event_post();
		$result   = Recurrence::save_rule( $event_id, [
			'frequency'   => 'weekly',
			'day_of_week' => 7,
		] );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_day_of_week', $result->get_error_code() );
	}

	/**
	 * @covers ::save_rule
	 */
	public function test_save_rule_rejects_missing_day_of_month(): void {
		$event_id = $this->create_event_post();
		$result   = Recurrence::save_rule( $event_id, [ 'frequency' => 'monthly-date' ] );

		$this->assertWPError( $result );
		$this->assertSame( 'missing_day_of_month', $result->get_error_code() );
	}

	/**
	 * @covers ::save_rule
	 */
	public function test_save_rule_rejects_invalid_day_of_month(): void {
		$event_id = $this->create_event_post();
		$result   = Recurrence::save_rule( $event_id, [
			'frequency'    => 'monthly-date',
			'day_of_month' => 32,
		] );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_day_of_month', $result->get_error_code() );
	}

	/**
	 * @covers ::save_rule
	 */
	public function test_save_rule_rejects_invalid_end_date(): void {
		$event_id = $this->create_event_post();
		$result   = Recurrence::save_rule( $event_id, [
			'frequency'   => 'weekly',
			'day_of_week' => 1,
			'end_date'    => 'not-a-date',
		] );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_end_date', $result->get_error_code() );
	}

	/**
	 * @covers ::get_rule
	 */
	public function test_get_rule_returns_null_when_no_rule(): void {
		$event_id = $this->create_event_post();

		$this->assertNull( Recurrence::get_rule( $event_id ) );
	}

	/**
	 * @covers ::is_recurring
	 */
	public function test_is_recurring_returns_false_without_rule(): void {
		$event_id = $this->create_event_post();

		$this->assertFalse( Recurrence::is_recurring( $event_id ) );
	}

	/**
	 * @covers ::is_recurring
	 */
	public function test_is_recurring_returns_true_with_rule(): void {
		$event_id = $this->create_event_post();
		Recurrence::save_rule( $event_id, [
			'frequency'   => 'weekly',
			'day_of_week' => 4,
		] );

		$this->assertTrue( Recurrence::is_recurring( $event_id ) );
	}

	/**
	 * @covers ::calculate_occurrences
	 */
	public function test_weekly_recurrence_generates_correct_dates(): void {
		$rule = [
			'frequency'   => 'weekly',
			'day_of_week' => 2, // Tuesday.
		];

		$occurrences = Recurrence::calculate_occurrences( $rule, '2026-04-07', 4 );

		$this->assertCount( 4, $occurrences );
		$this->assertSame( '2026-04-07', $occurrences[0]->format( 'Y-m-d' ) );
		$this->assertSame( '2026-04-14', $occurrences[1]->format( 'Y-m-d' ) );
		$this->assertSame( '2026-04-21', $occurrences[2]->format( 'Y-m-d' ) );
		$this->assertSame( '2026-04-28', $occurrences[3]->format( 'Y-m-d' ) );
	}

	/**
	 * @covers ::calculate_occurrences
	 */
	public function test_biweekly_recurrence_generates_correct_dates(): void {
		$rule = [
			'frequency'   => 'biweekly',
			'day_of_week' => 3, // Wednesday.
		];

		$occurrences = Recurrence::calculate_occurrences( $rule, '2026-04-01', 4 );

		$this->assertCount( 4, $occurrences );
		$this->assertSame( '2026-04-01', $occurrences[0]->format( 'Y-m-d' ) );
		$this->assertSame( '2026-04-15', $occurrences[1]->format( 'Y-m-d' ) );
		$this->assertSame( '2026-04-29', $occurrences[2]->format( 'Y-m-d' ) );
		$this->assertSame( '2026-05-13', $occurrences[3]->format( 'Y-m-d' ) );
	}

	/**
	 * @covers ::calculate_occurrences
	 */
	public function test_monthly_day_recurrence_first_tuesday(): void {
		// First Tuesday of April 2026 is April 7.
		$rule = [
			'frequency'   => 'monthly-day',
			'day_of_week' => 2, // Tuesday.
		];

		$occurrences = Recurrence::calculate_occurrences( $rule, '2026-04-07', 4 );

		$this->assertCount( 4, $occurrences );
		$this->assertSame( '2026-04-07', $occurrences[0]->format( 'Y-m-d' ) );
		// First Tuesday of May 2026 is May 5.
		$this->assertSame( '2026-05-05', $occurrences[1]->format( 'Y-m-d' ) );
		// First Tuesday of June 2026 is June 2.
		$this->assertSame( '2026-06-02', $occurrences[2]->format( 'Y-m-d' ) );
		// First Tuesday of July 2026 is July 7.
		$this->assertSame( '2026-07-07', $occurrences[3]->format( 'Y-m-d' ) );
	}

	/**
	 * @covers ::calculate_occurrences
	 */
	public function test_monthly_day_recurrence_third_wednesday(): void {
		// Third Wednesday of April 2026 is April 15.
		$rule = [
			'frequency'   => 'monthly-day',
			'day_of_week' => 3, // Wednesday.
		];

		$occurrences = Recurrence::calculate_occurrences( $rule, '2026-04-15', 3 );

		$this->assertCount( 3, $occurrences );
		$this->assertSame( '2026-04-15', $occurrences[0]->format( 'Y-m-d' ) );
		// Third Wednesday of May 2026 is May 20.
		$this->assertSame( '2026-05-20', $occurrences[1]->format( 'Y-m-d' ) );
		// Third Wednesday of June 2026 is June 17.
		$this->assertSame( '2026-06-17', $occurrences[2]->format( 'Y-m-d' ) );
	}

	/**
	 * @covers ::calculate_occurrences
	 */
	public function test_monthly_date_recurrence_generates_correct_dates(): void {
		$rule = [
			'frequency'    => 'monthly-date',
			'day_of_month' => 15,
		];

		$occurrences = Recurrence::calculate_occurrences( $rule, '2026-04-15', 4 );

		$this->assertCount( 4, $occurrences );
		$this->assertSame( '2026-04-15', $occurrences[0]->format( 'Y-m-d' ) );
		$this->assertSame( '2026-05-15', $occurrences[1]->format( 'Y-m-d' ) );
		$this->assertSame( '2026-06-15', $occurrences[2]->format( 'Y-m-d' ) );
		$this->assertSame( '2026-07-15', $occurrences[3]->format( 'Y-m-d' ) );
	}

	/**
	 * @covers ::calculate_occurrences
	 */
	public function test_monthly_date_clamps_to_last_day_of_short_month(): void {
		$rule = [
			'frequency'    => 'monthly-date',
			'day_of_month' => 31,
		];

		$occurrences = Recurrence::calculate_occurrences( $rule, '2026-01-31', 3 );

		$this->assertCount( 3, $occurrences );
		$this->assertSame( '2026-01-31', $occurrences[0]->format( 'Y-m-d' ) );
		// February has 28 days in 2026.
		$this->assertSame( '2026-02-28', $occurrences[1]->format( 'Y-m-d' ) );
		$this->assertSame( '2026-03-31', $occurrences[2]->format( 'Y-m-d' ) );
	}

	/**
	 * @covers ::calculate_occurrences
	 */
	public function test_end_date_stops_occurrence_generation(): void {
		$rule = [
			'frequency'   => 'weekly',
			'day_of_week' => 1,
			'end_date'    => '2026-04-20',
		];

		$occurrences = Recurrence::calculate_occurrences( $rule, '2026-04-06', 10 );

		$this->assertCount( 3, $occurrences );
		$this->assertSame( '2026-04-06', $occurrences[0]->format( 'Y-m-d' ) );
		$this->assertSame( '2026-04-13', $occurrences[1]->format( 'Y-m-d' ) );
		$this->assertSame( '2026-04-20', $occurrences[2]->format( 'Y-m-d' ) );
	}

	/**
	 * @covers ::calculate_occurrences
	 */
	public function test_end_date_is_inclusive(): void {
		$rule = [
			'frequency'   => 'weekly',
			'day_of_week' => 1,
			'end_date'    => '2026-04-13',
		];

		$occurrences = Recurrence::calculate_occurrences( $rule, '2026-04-06', 10 );

		$this->assertCount( 2, $occurrences );
		$this->assertSame( '2026-04-13', $occurrences[1]->format( 'Y-m-d' ) );
	}

	/**
	 * @covers ::calculate_occurrences
	 */
	public function test_default_count_is_ten(): void {
		$rule = [
			'frequency'   => 'weekly',
			'day_of_week' => 5,
		];

		$occurrences = Recurrence::calculate_occurrences( $rule, '2026-01-02' );

		$this->assertCount( 10, $occurrences );
	}
}
