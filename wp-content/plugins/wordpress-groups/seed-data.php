<?php
/**
 * Seed data for a group sub-site in local development.
 *
 * Run via: npx wp-env run cli wp eval-file wp-content/plugins/wordpress-groups/seed-data.php --url=http://localhost:8888/melbourne/
 *
 * Idempotent — safe to run multiple times.
 *
 * @package Groups
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Skip if already seeded.
$existing_events = get_posts( [
	'post_type'   => 'event',
	'post_status' => 'any',
	'numberposts' => 1,
] );
if ( ! empty( $existing_events ) ) {
	echo "Already seeded — skipping.\n";
	return;
}

echo "Seeding group site data...\n";

// Ensure custom tables exist.
if ( class_exists( 'Groups\Database\Schema' ) ) {
	\Groups\Database\Schema::create_tables();
}

$blog_id = get_current_blog_id();

// --- Users ---
$users = [
	'organizer' => [ 'Jane', 'Organizer', 'organizer@example.com', 'organizer' ],
	'member1'   => [ 'Alex', 'Member', 'member1@example.com', 'member' ],
	'member2'   => [ 'Sam', 'Contributor', 'member2@example.com', 'member' ],
	'member3'   => [ 'Taylor', 'Developer', 'member3@example.com', 'member' ],
];

$user_ids = [];
foreach ( $users as $login => $info ) {
	$user_id = username_exists( $login );
	if ( ! $user_id ) {
		$user_id = wp_create_user( $login, 'password', $info[2] );
	}
	if ( ! is_wp_error( $user_id ) ) {
		wp_update_user( [
			'ID'           => $user_id,
			'display_name' => $info[0] . ' ' . $info[1],
			'first_name'   => $info[0],
			'last_name'    => $info[1],
		] );
		// Add to this site with the correct role.
		if ( ! is_user_member_of_blog( $user_id, $blog_id ) ) {
			add_user_to_blog( $blog_id, $user_id, $info[3] );
		}
		$user_ids[ $login ] = $user_id;
	}
}
echo "✓ " . count( $user_ids ) . " users created/updated.\n";

$organizer_id = $user_ids['organizer'] ?? 1;

// --- Venues ---
$venue1_id = wp_insert_post( [
	'post_type'   => 'venue',
	'post_title'  => 'Community Hub Coworking',
	'post_status' => 'publish',
	'post_author' => $organizer_id,
	'post_content' => 'A modern coworking space with great facilities for meetups.',
] );
if ( $venue1_id && ! is_wp_error( $venue1_id ) ) {
	update_post_meta( $venue1_id, '_venue_address', '123 Main Street' );
	update_post_meta( $venue1_id, '_venue_city', 'Melbourne' );
	update_post_meta( $venue1_id, '_venue_state', 'VIC' );
	update_post_meta( $venue1_id, '_venue_country', 'Australia' );
	update_post_meta( $venue1_id, '_venue_zip', '3000' );
	update_post_meta( $venue1_id, '_venue_latitude', -37.8136 );
	update_post_meta( $venue1_id, '_venue_longitude', 144.9631 );
	update_post_meta( $venue1_id, '_venue_capacity', 50 );
	update_post_meta( $venue1_id, '_venue_accessibility_notes', 'Wheelchair accessible. Elevator available.' );
}

$venue2_id = wp_insert_post( [
	'post_type'   => 'venue',
	'post_title'  => 'City Library Meeting Room',
	'post_status' => 'publish',
	'post_author' => $organizer_id,
	'post_content' => 'Free meeting room at the public library. Projector and whiteboard available.',
] );
if ( $venue2_id && ! is_wp_error( $venue2_id ) ) {
	update_post_meta( $venue2_id, '_venue_address', '456 Library Lane' );
	update_post_meta( $venue2_id, '_venue_city', 'Melbourne' );
	update_post_meta( $venue2_id, '_venue_state', 'VIC' );
	update_post_meta( $venue2_id, '_venue_country', 'Australia' );
	update_post_meta( $venue2_id, '_venue_zip', '3000' );
	update_post_meta( $venue2_id, '_venue_latitude', -37.8100 );
	update_post_meta( $venue2_id, '_venue_longitude', 144.9650 );
	update_post_meta( $venue2_id, '_venue_capacity', 30 );
}
echo "✓ 2 venues created.\n";

// --- Events ---
$now = new DateTime( 'now', new DateTimeZone( 'UTC' ) );

$events = [
	[
		'title'   => 'Introduction to Block Themes',
		'content' => "Join us for an evening exploring WordPress block themes!\n\n- What are block themes\n- Creating your first block theme\n- Using theme.json for design tokens\n- Building custom templates\n\nAll skill levels welcome. Bring your laptop!",
		'offset'  => '+7 days',
		'time'    => '18:00:00',
		'hours'   => 2,
		'venue'   => $venue1_id,
		'status'  => 'event-scheduled',
		'limit'   => 30,
	],
	[
		'title'   => 'WordPress Performance Optimization',
		'content' => "Let's dive into making WordPress sites faster!\n\n- Caching strategies\n- Database optimization\n- Image optimization\n- Core Web Vitals",
		'offset'  => '+14 days',
		'time'    => '19:00:00',
		'hours'   => 1.5,
		'venue'   => $venue2_id,
		'status'  => 'event-scheduled',
		'limit'   => 25,
	],
	[
		'title'   => 'Contributor Day: Documentation Sprint',
		'content' => "Join our online contributor day focused on WordPress documentation.\n\nNo prior experience needed — we'll pair newcomers with experienced contributors.",
		'offset'  => '+21 days',
		'time'    => '12:00:00',
		'hours'   => 1,
		'venue'   => 0,
		'status'  => 'event-scheduled',
		'limit'   => 0,
		'online'  => 'https://meet.example.com/wp-docs',
	],
	[
		'title'   => 'WordPress 6.7 Release Party',
		'content' => "We celebrated the release of WordPress 6.7 with demos, lightning talks, and cake!\n\nThanks to everyone who came.",
		'offset'  => '-7 days',
		'time'    => '18:30:00',
		'hours'   => 2,
		'venue'   => $venue1_id,
		'status'  => 'event-past',
		'limit'   => 0,
	],
];

$event_ids = [];
foreach ( $events as $event_data ) {
	$start = clone $now;
	$start->modify( $event_data['offset'] );
	$time_parts = explode( ':', $event_data['time'] );
	$start->setTime( (int) $time_parts[0], (int) $time_parts[1], 0 );

	$end = clone $start;
	$end->modify( '+' . ( $event_data['hours'] * 60 ) . ' minutes' );

	$event_id = wp_insert_post( [
		'post_type'    => 'event',
		'post_title'   => $event_data['title'],
		'post_status'  => $event_data['status'],
		'post_content' => $event_data['content'],
		'post_author'  => $organizer_id,
	] );

	if ( $event_id && ! is_wp_error( $event_id ) ) {
		update_post_meta( $event_id, '_event_start_utc', $start->format( 'Y-m-d H:i:s' ) );
		update_post_meta( $event_id, '_event_end_utc', $end->format( 'Y-m-d H:i:s' ) );
		update_post_meta( $event_id, '_event_timezone', 'Australia/Melbourne' );

		if ( ! empty( $event_data['venue'] ) ) {
			update_post_meta( $event_id, '_event_venue_id', $event_data['venue'] );
		}
		if ( ! empty( $event_data['online'] ) ) {
			update_post_meta( $event_id, '_event_online_link', $event_data['online'] );
		}
		if ( ! empty( $event_data['limit'] ) ) {
			update_post_meta( $event_id, '_event_attendee_limit', $event_data['limit'] );
			update_post_meta( $event_id, '_event_waitlist_enabled', 1 );
		}

		$event_ids[] = $event_id;
	}
}
echo "✓ " . count( $event_ids ) . " events created.\n";

// --- RSVPs (as comments) ---
$rsvp_count = 0;
foreach ( $event_ids as $event_id ) {
	foreach ( $user_ids as $login => $user_id ) {
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
			$rsvp_count++;
		}
	}
}
echo "✓ $rsvp_count RSVPs created.\n";

// --- Pages ---
$pages = [
	'events'  => [
		'title'   => 'Events',
		'content' => '<!-- wp:groups/upcoming-events /-->',
	],
	'members' => [
		'title'   => 'Members',
		'content' => '<!-- wp:groups/group-members /-->',
	],
	'about'   => [
		'title'   => 'About',
		'content' => "<!-- wp:heading -->\n<h2>About WordPress Melbourne</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>We're a friendly community of WordPress enthusiasts in Melbourne, Australia. We meet regularly to learn, share, and connect.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>Whether you're a developer, designer, content creator, or just getting started with WordPress — you're welcome here!</p>\n<!-- /wp:paragraph -->",
	],
];

foreach ( $pages as $slug => $page_data ) {
	$existing = get_page_by_path( $slug );
	if ( ! $existing ) {
		wp_insert_post( [
			'post_type'    => 'page',
			'post_title'   => $page_data['title'],
			'post_status'  => 'publish',
			'post_name'    => $slug,
			'post_content' => $page_data['content'],
			'post_author'  => $organizer_id,
		] );
	}
}
echo "✓ Pages created (events, members, about).\n";

// Set front page to show events.
$front = get_page_by_path( 'events' );
if ( $front ) {
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $front->ID );
}

echo "\n✅ Seed data complete!\n";
