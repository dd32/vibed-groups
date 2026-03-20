<?php
/**
 * Seed data for local development.
 *
 * Run via: npm run env:seed
 * (or)   npx wp-env run cli wp eval-file wp-content/plugins/wordpress-groups/seed-data.php --url='http://localhost:8888/melbourne/'
 *
 * Designed to run on a sub-site (e.g. /melbourne/) in a multisite network.
 * Creates sample events, venues, RSVPs, and users so the local environment
 * has data to display.
 *
 * Idempotent: safe to run multiple times.
 *
 * @package Groups
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_blog_id = get_current_blog_id();
echo "Seeding WordPress Groups development data on site {$current_blog_id}...\n";

// Ensure plugin is loaded.
if ( ! class_exists( 'Groups\Plugin' ) ) {
	echo "Error: WordPress Groups plugin not active.\n";
	exit( 1 );
}

// Idempotency check: skip if seed data already exists on this site.
$existing_events = get_posts( [
	'post_type'   => 'event',
	'post_status' => 'any',
	'numberposts' => 1,
] );
if ( ! empty( $existing_events ) ) {
	echo "Seed data already exists on this site. Skipping.\n";
	exit( 0 );
}

// Create custom tables for this site.
if ( class_exists( 'Groups\Database\Schema' ) ) {
	\Groups\Database\Schema::create_tables();
	echo "Custom tables created.\n";
}

// Register CPTs and roles.
do_action( 'init' );

// Create test users (network-level, then add to this site).
$users = [
	'organizer' => [
		'email'        => 'organizer@example.com',
		'display_name' => 'Jane Organizer',
		'first_name'   => 'Jane',
		'last_name'    => 'Organizer',
		'role'         => 'editor',
	],
	'member1' => [
		'email'        => 'member1@example.com',
		'display_name' => 'Alex Member',
		'first_name'   => 'Alex',
		'last_name'    => 'Member',
		'role'         => 'subscriber',
	],
	'member2' => [
		'email'        => 'member2@example.com',
		'display_name' => 'Sam Contributor',
		'first_name'   => 'Sam',
		'last_name'    => 'Contributor',
		'role'         => 'subscriber',
	],
	'member3' => [
		'email'        => 'member3@example.com',
		'display_name' => 'Taylor Developer',
		'first_name'   => 'Taylor',
		'last_name'    => 'Developer',
		'role'         => 'subscriber',
	],
];

$user_ids = [];
foreach ( $users as $login => $data ) {
	$user_id = wp_create_user( $login, 'password', $data['email'] );
	if ( is_wp_error( $user_id ) ) {
		$user    = get_user_by( 'login', $login );
		$user_id = $user->ID;
	}

	wp_update_user( [
		'ID'           => $user_id,
		'display_name' => $data['display_name'],
		'first_name'   => $data['first_name'],
		'last_name'    => $data['last_name'],
	] );

	// In multisite, ensure the user is a member of this sub-site.
	if ( is_multisite() && ! is_user_member_of_blog( $user_id, $current_blog_id ) ) {
		add_user_to_blog( $current_blog_id, $user_id, $data['role'] );
	}

	$user_ids[ $login ] = $user_id;
}

$organizer_id = $user_ids['organizer'];
$member1_id   = $user_ids['member1'];
$member2_id   = $user_ids['member2'];
$member3_id   = $user_ids['member3'];

echo "Test users created and added to site.\n";

// Create venues.
$venue1_id = wp_insert_post( [
	'post_type'    => 'venue',
	'post_title'   => 'Community Hub Coworking',
	'post_status'  => 'publish',
	'post_content' => 'A modern coworking space in the heart of the city with great facilities for meetups.',
] );
if ( ! is_wp_error( $venue1_id ) ) {
	update_post_meta( $venue1_id, '_venue_address', '123 Main Street' );
	update_post_meta( $venue1_id, '_venue_city', 'Melbourne' );
	update_post_meta( $venue1_id, '_venue_state', 'VIC' );
	update_post_meta( $venue1_id, '_venue_country', 'Australia' );
	update_post_meta( $venue1_id, '_venue_zip', '3000' );
	update_post_meta( $venue1_id, '_venue_latitude', -37.8136 );
	update_post_meta( $venue1_id, '_venue_longitude', 144.9631 );
	update_post_meta( $venue1_id, '_venue_capacity', 50 );
	update_post_meta( $venue1_id, '_venue_accessibility_notes', 'Wheelchair accessible. Elevator available.' );
	update_post_meta( $venue1_id, '_venue_website', 'https://example.com/community-hub' );
}

$venue2_id = wp_insert_post( [
	'post_type'    => 'venue',
	'post_title'   => 'City Library Meeting Room',
	'post_status'  => 'publish',
	'post_content' => 'Free meeting room at the public library. Projector and whiteboard available.',
] );
if ( ! is_wp_error( $venue2_id ) ) {
	update_post_meta( $venue2_id, '_venue_address', '456 Library Lane' );
	update_post_meta( $venue2_id, '_venue_city', 'Melbourne' );
	update_post_meta( $venue2_id, '_venue_state', 'VIC' );
	update_post_meta( $venue2_id, '_venue_country', 'Australia' );
	update_post_meta( $venue2_id, '_venue_zip', '3000' );
	update_post_meta( $venue2_id, '_venue_latitude', -37.8100 );
	update_post_meta( $venue2_id, '_venue_longitude', 144.9650 );
	update_post_meta( $venue2_id, '_venue_capacity', 30 );
}

echo "Venues created.\n";

// Create events.
$now = new DateTime( 'now', new DateTimeZone( 'UTC' ) );

// Upcoming event 1: next week.
$next_week = clone $now;
$next_week->modify( '+7 days' )->setTime( 18, 0, 0 );
$next_week_end = clone $next_week;
$next_week_end->modify( '+2 hours' );

$event1_id = wp_insert_post( [
	'post_type'    => 'event',
	'post_title'   => 'Introduction to Block Themes',
	'post_status'  => 'event-scheduled',
	'post_content' => "Join us for an evening exploring WordPress block themes! We'll cover:\n\n- What are block themes and how they differ from classic themes\n- Creating your first block theme\n- Using theme.json for design tokens\n- Building custom templates and template parts\n\nAll skill levels welcome. Bring your laptop!",
	'post_author'  => $organizer_id,
] );
if ( ! is_wp_error( $event1_id ) ) {
	update_post_meta( $event1_id, '_event_start_utc', $next_week->format( 'Y-m-d H:i:s' ) );
	update_post_meta( $event1_id, '_event_end_utc', $next_week_end->format( 'Y-m-d H:i:s' ) );
	update_post_meta( $event1_id, '_event_timezone', 'Australia/Melbourne' );
	update_post_meta( $event1_id, '_event_venue_id', $venue1_id );
	update_post_meta( $event1_id, '_event_attendee_limit', 30 );
	update_post_meta( $event1_id, '_event_waitlist_enabled', 1 );
	wp_set_post_terms( $event1_id, [ 'Workshop', 'In-person' ], 'category' );
}

// Upcoming event 2: two weeks.
$two_weeks = clone $now;
$two_weeks->modify( '+14 days' )->setTime( 19, 0, 0 );
$two_weeks_end = clone $two_weeks;
$two_weeks_end->modify( '+1 hour 30 minutes' );

$event2_id = wp_insert_post( [
	'post_type'    => 'event',
	'post_title'   => 'WordPress Performance Optimization',
	'post_status'  => 'event-scheduled',
	'post_content' => "Let's dive into making WordPress sites faster!\n\n- Caching strategies (object cache, page cache, transients)\n- Database optimization\n- Image optimization and lazy loading\n- Core Web Vitals and how to measure them\n\nPresenter: Jane Organizer",
	'post_author'  => $organizer_id,
] );
if ( ! is_wp_error( $event2_id ) ) {
	update_post_meta( $event2_id, '_event_start_utc', $two_weeks->format( 'Y-m-d H:i:s' ) );
	update_post_meta( $event2_id, '_event_end_utc', $two_weeks_end->format( 'Y-m-d H:i:s' ) );
	update_post_meta( $event2_id, '_event_timezone', 'Australia/Melbourne' );
	update_post_meta( $event2_id, '_event_venue_id', $venue2_id );
	update_post_meta( $event2_id, '_event_attendee_limit', 25 );
	wp_set_post_terms( $event2_id, [ 'Presentation', 'In-person' ], 'category' );
}

// Online event: three weeks.
$three_weeks = clone $now;
$three_weeks->modify( '+21 days' )->setTime( 12, 0, 0 );
$three_weeks_end = clone $three_weeks;
$three_weeks_end->modify( '+1 hour' );

$event3_id = wp_insert_post( [
	'post_type'    => 'event',
	'post_title'   => 'Contributor Day: Documentation Sprint',
	'post_status'  => 'event-scheduled',
	'post_content' => "Join our online contributor day focused on improving WordPress documentation.\n\nNo prior experience needed - we'll pair newcomers with experienced contributors.\n\nMeet link will be shared before the event.",
	'post_author'  => $organizer_id,
] );
if ( ! is_wp_error( $event3_id ) ) {
	update_post_meta( $event3_id, '_event_start_utc', $three_weeks->format( 'Y-m-d H:i:s' ) );
	update_post_meta( $event3_id, '_event_end_utc', $three_weeks_end->format( 'Y-m-d H:i:s' ) );
	update_post_meta( $event3_id, '_event_timezone', 'Australia/Melbourne' );
	update_post_meta( $event3_id, '_event_online_link', 'https://meet.example.com/wp-docs' );
	wp_set_post_terms( $event3_id, [ 'Social', 'Online' ], 'category' );
}

// Past event.
$last_week = clone $now;
$last_week->modify( '-7 days' )->setTime( 18, 30, 0 );
$last_week_end = clone $last_week;
$last_week_end->modify( '+2 hours' );

$event4_id = wp_insert_post( [
	'post_type'    => 'event',
	'post_title'   => 'WordPress 6.7 Release Party',
	'post_status'  => 'event-past',
	'post_content' => "We celebrated the release of WordPress 6.7 with demos, lightning talks, and cake!\n\nThanks to everyone who came - great turnout!",
	'post_author'  => $organizer_id,
] );
if ( ! is_wp_error( $event4_id ) ) {
	update_post_meta( $event4_id, '_event_start_utc', $last_week->format( 'Y-m-d H:i:s' ) );
	update_post_meta( $event4_id, '_event_end_utc', $last_week_end->format( 'Y-m-d H:i:s' ) );
	update_post_meta( $event4_id, '_event_timezone', 'Australia/Melbourne' );
	update_post_meta( $event4_id, '_event_venue_id', $venue1_id );
	wp_set_post_terms( $event4_id, [ 'Social', 'In-person' ], 'category' );
}

echo "Events created.\n";

// Create RSVPs (as comments).
$rsvp_events = [ $event1_id, $event2_id, $event3_id ];
$rsvp_users  = [ $organizer_id, $member1_id, $member2_id, $member3_id ];

foreach ( $rsvp_events as $event_id ) {
	foreach ( $rsvp_users as $user_id ) {
		$user = get_user_by( 'ID', $user_id );
		if ( ! $user ) {
			continue;
		}

		$comment_id = wp_insert_comment( [
			'comment_post_ID'      => $event_id,
			'user_id'              => $user_id,
			'comment_author'       => $user->display_name,
			'comment_author_email' => $user->user_email,
			'comment_type'         => 'groups_rsvp',
			'comment_approved'     => 1,
			'comment_content'      => '',
		] );

		if ( $comment_id ) {
			update_comment_meta( $comment_id, '_rsvp_status', 'attending' );
			update_comment_meta( $comment_id, '_rsvp_guest_count', 0 );

			// Mark first member as newcomer on first event.
			if ( $user_id === $member3_id && $event_id === $event1_id ) {
				update_comment_meta( $comment_id, '_rsvp_is_first_event', 1 );
			}
		}
	}
}

// Past event: mark attendance.
foreach ( $rsvp_users as $user_id ) {
	$user = get_user_by( 'ID', $user_id );
	if ( ! $user ) {
		continue;
	}

	$comment_id = wp_insert_comment( [
		'comment_post_ID'      => $event4_id,
		'user_id'              => $user_id,
		'comment_author'       => $user->display_name,
		'comment_author_email' => $user->user_email,
		'comment_type'         => 'groups_rsvp',
		'comment_approved'     => 1,
	] );

	if ( $comment_id ) {
		update_comment_meta( $comment_id, '_rsvp_status', 'attending' );
		update_comment_meta( $comment_id, '_rsvp_attendance_confirmed', 1 );
	}
}

echo "RSVPs created.\n";

// Set up the front page with the event directory block.
$front_page = wp_insert_post( [
	'post_type'    => 'page',
	'post_title'   => 'Upcoming Events',
	'post_status'  => 'publish',
	'post_content' => '<!-- wp:groups/event-directory {"align":"wide"} /-->',
] );
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $front_page );

echo "Front page configured.\n";

// Add members page.
wp_insert_post( [
	'post_type'    => 'page',
	'post_title'   => 'Members',
	'post_status'  => 'publish',
	'post_name'    => 'members',
	'post_content' => '<!-- wp:groups/group-members /-->',
] );

echo "Members page created.\n";

// Set sub-site title.
update_option( 'blogname', 'WordPress Melbourne' );
update_option( 'blogdescription', 'Melbourne WordPress Community Group' );

echo "\nSeed data complete!\n";
echo "  - 4 users (organizer + 3 members)\n";
echo "  - 2 venues\n";
echo "  - 4 events (3 upcoming, 1 past)\n";
echo "  - RSVPs for all events\n";
echo "  - Front page and Members page configured\n";
echo "\nLogin: admin / password (or organizer / password)\n";
