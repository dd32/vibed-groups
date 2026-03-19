<?php
/**
 * Tests for the iCal export.
 *
 * @package Groups\Tests
 */

use Groups\Calendar\ICal_Export;
use Groups\Models\Event;
use Groups\Post_Types\Event as Event_Post_Type;
use Groups\Post_Types\Venue;

/**
 * @coversDefaultClass \Groups\Calendar\ICal_Export
 */
class Test_ICal_Export extends WP_UnitTestCase {

	/**
	 * The iCal export instance.
	 *
	 * @var ICal_Export
	 */
	private ICal_Export $ical;

	/**
	 * Set up before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		$event_cpt = new Event_Post_Type();
		$event_cpt->register_post_type();
		$event_cpt->register_post_statuses();

		$venue_cpt = new Venue();
		$venue_cpt->register_post_type();

		$this->ical = new ICal_Export();
	}

	/**
	 * Helper to create an event with default args.
	 *
	 * @param array $overrides Optional overrides.
	 * @return int Event post ID.
	 */
	private function create_event( array $overrides = [] ): int {
		$args = array_merge(
			[
				'title'     => 'Monthly WordPress Meetup',
				'content'   => 'Join us for our monthly meetup!',
				'start_utc' => '2026-04-15 18:00:00',
				'end_utc'   => '2026-04-15 20:00:00',
				'timezone'  => 'America/New_York',
				'status'    => 'event-scheduled',
			],
			$overrides
		);

		return Event::create( $args );
	}

	/**
	 * @covers ::build_vcalendar
	 * @covers ::escape_ical
	 */
	public function test_single_event_produces_valid_ical(): void {
		$post_id = $this->create_event();
		$post    = get_post( $post_id );

		// Build VEVENT data manually via reflection-accessible method.
		$vevents   = [];
		$timezones = [];

		$method = new ReflectionMethod( ICal_Export::class, 'build_vevents' );
		$method->setAccessible( true );
		$result = $method->invoke( $this->ical, [ $post ] );

		$vcalendar = $this->ical->build_vcalendar( $result['vevents'], $result['timezones'] );

		$this->assertStringStartsWith( "BEGIN:VCALENDAR\r\n", $vcalendar );
		$this->assertStringEndsWith( "END:VCALENDAR\r\n", $vcalendar );
		$this->assertStringContainsString( 'VERSION:2.0', $vcalendar );
		$this->assertStringContainsString( 'PRODID:-//WordPress Groups//Events//EN', $vcalendar );
		$this->assertStringContainsString( 'BEGIN:VEVENT', $vcalendar );
		$this->assertStringContainsString( 'END:VEVENT', $vcalendar );
		$this->assertStringContainsString( 'SUMMARY:Monthly WordPress Meetup', $vcalendar );
		$this->assertStringContainsString( 'DTSTART;TZID=America/New_York:', $vcalendar );
		$this->assertStringContainsString( 'DTEND;TZID=America/New_York:', $vcalendar );
		$this->assertStringContainsString( 'DESCRIPTION:Join us for our monthly meetup!', $vcalendar );
		$this->assertStringContainsString( 'UID:', $vcalendar );
		$this->assertStringContainsString( 'URL:', $vcalendar );
		$this->assertStringContainsString( 'BEGIN:VTIMEZONE', $vcalendar );
		$this->assertStringContainsString( 'TZID:America/New_York', $vcalendar );
	}

	/**
	 * @covers ::build_vcalendar
	 */
	public function test_feed_includes_multiple_events(): void {
		$id1 = $this->create_event( [
			'title'     => 'First Event',
			'start_utc' => '2026-05-01 18:00:00',
			'end_utc'   => '2026-05-01 20:00:00',
		] );
		$id2 = $this->create_event( [
			'title'     => 'Second Event',
			'start_utc' => '2026-05-08 18:00:00',
			'end_utc'   => '2026-05-08 20:00:00',
		] );
		$id3 = $this->create_event( [
			'title'     => 'Third Event',
			'start_utc' => '2026-05-15 18:00:00',
			'end_utc'   => '2026-05-15 20:00:00',
		] );

		$posts = array_map( 'get_post', [ $id1, $id2, $id3 ] );

		$method = new ReflectionMethod( ICal_Export::class, 'build_vevents' );
		$method->setAccessible( true );
		$result = $method->invoke( $this->ical, $posts );

		$vcalendar = $this->ical->build_vcalendar( $result['vevents'], $result['timezones'] );

		$this->assertSame( 3, substr_count( $vcalendar, 'BEGIN:VEVENT' ) );
		$this->assertSame( 3, substr_count( $vcalendar, 'END:VEVENT' ) );
		$this->assertStringContainsString( 'SUMMARY:First Event', $vcalendar );
		$this->assertStringContainsString( 'SUMMARY:Second Event', $vcalendar );
		$this->assertStringContainsString( 'SUMMARY:Third Event', $vcalendar );

		// Should only have one VTIMEZONE since all events share the same timezone.
		$this->assertSame( 1, substr_count( $vcalendar, 'BEGIN:VTIMEZONE' ) );
	}

	/**
	 * @covers ::escape_ical
	 */
	public function test_special_characters_are_escaped(): void {
		// Commas.
		$this->assertSame( 'Hello\, World', ICal_Export::escape_ical( 'Hello, World' ) );

		// Semicolons.
		$this->assertSame( 'foo\;bar', ICal_Export::escape_ical( 'foo;bar' ) );

		// Backslashes.
		$this->assertSame( 'path\\\\file', ICal_Export::escape_ical( 'path\\file' ) );

		// Newlines.
		$this->assertSame( 'line1\nline2', ICal_Export::escape_ical( "line1\nline2" ) );

		// Windows-style newlines.
		$this->assertSame( 'line1\nline2', ICal_Export::escape_ical( "line1\r\nline2" ) );

		// Combined.
		$this->assertSame(
			'Meet\, Greet\; Chat\nNew line',
			ICal_Export::escape_ical( "Meet, Greet; Chat\nNew line" )
		);
	}

	/**
	 * @covers ::escape_ical
	 */
	public function test_special_characters_escaped_in_event_output(): void {
		$post_id = $this->create_event( [
			'title'   => 'WordPress: Code, Coffee; Fun',
			'content' => "Line one.\nLine two, continued; here.",
		] );

		$post   = get_post( $post_id );
		$method = new ReflectionMethod( ICal_Export::class, 'build_vevents' );
		$method->setAccessible( true );
		$result = $method->invoke( $this->ical, [ $post ] );

		$vcalendar = $this->ical->build_vcalendar( $result['vevents'], $result['timezones'] );

		$this->assertStringContainsString( 'SUMMARY:WordPress: Code\, Coffee\; Fun', $vcalendar );
		$this->assertStringContainsString( 'DESCRIPTION:Line one.\nLine two\, continued\; here.', $vcalendar );
	}

	/**
	 * @covers ::format_utc_offset
	 */
	public function test_format_utc_offset(): void {
		$this->assertSame( '+0000', ICal_Export::format_utc_offset( 0 ) );
		$this->assertSame( '-0500', ICal_Export::format_utc_offset( -18000 ) );
		$this->assertSame( '+0530', ICal_Export::format_utc_offset( 19800 ) );
		$this->assertSame( '+1000', ICal_Export::format_utc_offset( 36000 ) );
		$this->assertSame( '-0930', ICal_Export::format_utc_offset( -34200 ) );
	}

	/**
	 * @covers ::build_vtimezone
	 */
	public function test_build_vtimezone_contains_required_components(): void {
		$tz        = new \DateTimeZone( 'America/New_York' );
		$reference = new \DateTime( '2026-06-15 12:00:00', $tz );

		$vtimezone = ICal_Export::build_vtimezone( $tz, $reference );

		$this->assertStringContainsString( 'BEGIN:VTIMEZONE', $vtimezone );
		$this->assertStringContainsString( 'END:VTIMEZONE', $vtimezone );
		$this->assertStringContainsString( 'TZID:America/New_York', $vtimezone );
		$this->assertStringContainsString( 'TZOFFSETFROM:', $vtimezone );
		$this->assertStringContainsString( 'TZOFFSETTO:', $vtimezone );
	}

	/**
	 * @covers ::build_vcalendar
	 */
	public function test_event_with_venue_includes_location(): void {
		// Create a venue post.
		$venue_id = wp_insert_post( [
			'post_type'   => 'venue',
			'post_title'  => 'Community Center',
			'post_status' => 'publish',
		] );
		update_post_meta( $venue_id, '_venue_address', '123 Main St' );
		update_post_meta( $venue_id, '_venue_city', 'New York' );

		$post_id = $this->create_event( [
			'venue_id' => $venue_id,
		] );

		$post   = get_post( $post_id );
		$method = new ReflectionMethod( ICal_Export::class, 'build_vevents' );
		$method->setAccessible( true );
		$result = $method->invoke( $this->ical, [ $post ] );

		$vcalendar = $this->ical->build_vcalendar( $result['vevents'], $result['timezones'] );

		$this->assertStringContainsString( 'LOCATION:Community Center\, 123 Main St\, New York', $vcalendar );
	}

	/**
	 * @covers ::build_vevents
	 */
	public function test_event_missing_timezone_is_skipped(): void {
		$post_id = wp_insert_post( [
			'post_type'   => Event_Post_Type::POST_TYPE,
			'post_title'  => 'Broken Event',
			'post_status' => 'event-scheduled',
		] );
		update_post_meta( $post_id, '_event_start_utc', '2026-04-15 18:00:00' );
		update_post_meta( $post_id, '_event_end_utc', '2026-04-15 20:00:00' );
		// No timezone set.

		$post   = get_post( $post_id );
		$method = new ReflectionMethod( ICal_Export::class, 'build_vevents' );
		$method->setAccessible( true );
		$result = $method->invoke( $this->ical, [ $post ] );

		$this->assertEmpty( $result['vevents'] );
	}

	/**
	 * @covers ::build_vcalendar
	 */
	public function test_empty_feed_produces_valid_vcalendar(): void {
		$vcalendar = $this->ical->build_vcalendar( [] );

		$this->assertStringStartsWith( "BEGIN:VCALENDAR\r\n", $vcalendar );
		$this->assertStringEndsWith( "END:VCALENDAR\r\n", $vcalendar );
		$this->assertStringNotContainsString( 'BEGIN:VEVENT', $vcalendar );
	}
}
