<?php
/**
 * Seed data for the Tokyo group sub-site.
 *
 * @package Groups
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$existing = get_posts( [ 'post_type' => 'event', 'post_status' => 'any', 'numberposts' => 1 ] );
if ( ! empty( $existing ) ) {
	echo "Already seeded — skipping.\n";
	return;
}

echo "Seeding Tokyo group site...\n";

if ( class_exists( 'Groups\Database\Schema' ) ) {
	\Groups\Database\Schema::create_tables();
}

$blog_id = get_current_blog_id();

// Users.
$users = [
	'yuki'    => [ 'Yuki', 'Tanaka', 'yuki@wordpress.test', 'organizer' ],
	'kenji'   => [ 'Kenji', 'Suzuki', 'kenji@wordpress.test', 'co_organizer' ],
	'sakura'  => [ 'Sakura', 'Yamamoto', 'sakura@wordpress.test', 'member' ],
	'hiroshi' => [ 'Hiroshi', 'Watanabe', 'hiroshi@wordpress.test', 'member' ],
	'aiko'    => [ 'Aiko', 'Sato', 'aiko@wordpress.test', 'member' ],
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
			'user_email'   => $info[2],
			'display_name' => $info[0] . ' ' . $info[1],
			'first_name'   => $info[0],
			'last_name'    => $info[1],
		] );
		if ( ! is_user_member_of_blog( $user_id, $blog_id ) ) {
			add_user_to_blog( $blog_id, $user_id, $info[3] );
		}
		$user_ids[ $login ] = $user_id;
	}
}
echo "✓ " . count( $user_ids ) . " users.\n";

$organizer_id = $user_ids['yuki'] ?? 1;

// Venue.
$venue_id = wp_insert_post( [
	'post_type'   => 'venue',
	'post_title'  => 'Shibuya Tech Hub',
	'post_status' => 'publish',
	'post_author' => $organizer_id,
	'post_content' => 'A tech coworking space near Shibuya station with projector and fast wifi.',
] );
if ( $venue_id && ! is_wp_error( $venue_id ) ) {
	update_post_meta( $venue_id, '_venue_address', '1-2-3 Shibuya' );
	update_post_meta( $venue_id, '_venue_city', 'Tokyo' );
	update_post_meta( $venue_id, '_venue_state', 'Tokyo' );
	update_post_meta( $venue_id, '_venue_country', 'Japan' );
	update_post_meta( $venue_id, '_venue_zip', '150-0002' );
	update_post_meta( $venue_id, '_venue_latitude', 35.6595 );
	update_post_meta( $venue_id, '_venue_longitude', 139.7004 );
	update_post_meta( $venue_id, '_venue_capacity', 40 );
}
echo "✓ 1 venue.\n";

// Events.
$now = new DateTime( 'now', new DateTimeZone( 'UTC' ) );

$events_data = [
	[
		'title'  => 'WordPress Gutenberg Deep Dive',
		'content' => "Let's explore the latest Gutenberg features!\n\n- Block patterns and synced patterns\n- The Interactivity API\n- Custom block development tips\n\nPresentations in Japanese and English.",
		'offset' => '+5 days',
		'time'   => '19:00:00',
		'hours'  => 2,
		'status' => 'event-scheduled',
	],
	[
		'title'  => 'WordPress × WooCommerce Meetup',
		'content' => "A joint meetup with the WooCommerce community.\n\n- Running an online store with WordPress\n- Payment gateways in Japan\n- Performance tips for WooCommerce",
		'offset' => '+12 days',
		'time'   => '18:30:00',
		'hours'  => 2,
		'status' => 'event-scheduled',
	],
	[
		'title'  => 'Accessibility Workshop',
		'content' => "Hands-on workshop on making WordPress sites accessible.\n\nBring your laptop — we'll audit real sites together.",
		'offset' => '+19 days',
		'time'   => '13:00:00',
		'hours'  => 3,
		'status' => 'event-scheduled',
	],
	[
		'title'  => 'WordPress Translation Day',
		'content' => "We contributed to translating WordPress core and plugins into Japanese.\n\n12 new strings translated!\n\n<!-- wp:heading {\"level\":3} -->\n<h3 class=\"wp-block-heading\">Event Photos</h3>\n<!-- /wp:heading -->\n\n<!-- wp:gallery {\"linkTo\":\"none\",\"columns\":3,\"className\":\"groups-site-event-photo-gallery\"} -->\n<figure class=\"wp-block-gallery has-nested-images columns-3 is-cropped groups-site-event-photo-gallery\">\n<!-- wp:image {\"sizeSlug\":\"large\"} -->\n<figure class=\"wp-block-image size-large\"><img src=\"https://picsum.photos/seed/tokyo-translate1/800/600\" alt=\"Translation sprint in progress\"/></figure>\n<!-- /wp:image -->\n\n<!-- wp:image {\"sizeSlug\":\"large\"} -->\n<figure class=\"wp-block-image size-large\"><img src=\"https://picsum.photos/seed/tokyo-translate2/800/600\" alt=\"Team celebrating completed translations\"/></figure>\n<!-- /wp:image -->\n</figure>\n<!-- /wp:gallery -->",
		'offset' => '-14 days',
		'time'   => '10:00:00',
		'hours'  => 4,
		'status' => 'event-past',
	],
];

$event_ids = [];
foreach ( $events_data as $ed ) {
	$start = clone $now;
	$start->modify( $ed['offset'] );
	$tp = explode( ':', $ed['time'] );
	$start->setTime( (int) $tp[0], (int) $tp[1], 0 );
	$end = clone $start;
	$end->modify( '+' . ( $ed['hours'] * 60 ) . ' minutes' );

	$eid = wp_insert_post( [
		'post_type'    => 'event',
		'post_title'   => $ed['title'],
		'post_status'  => $ed['status'],
		'post_content' => $ed['content'],
		'post_author'  => $organizer_id,
	] );
	if ( $eid && ! is_wp_error( $eid ) ) {
		update_post_meta( $eid, '_event_start_utc', $start->format( 'Y-m-d H:i:s' ) );
		update_post_meta( $eid, '_event_end_utc', $end->format( 'Y-m-d H:i:s' ) );
		update_post_meta( $eid, '_event_timezone', 'Asia/Tokyo' );
		update_post_meta( $eid, '_event_venue_id', $venue_id );
		update_post_meta( $eid, '_event_attendee_limit', 40 );
		$event_ids[] = $eid;
	}
}
echo "✓ " . count( $event_ids ) . " events.\n";

// RSVPs.
$rsvp_count = 0;
foreach ( $event_ids as $eid ) {
	foreach ( $user_ids as $uid ) {
		$user = get_user_by( 'ID', $uid );
		if ( ! $user ) continue;
		$cid = wp_insert_comment( [
			'comment_post_ID'      => $eid,
			'user_id'              => $uid,
			'comment_author'       => $user->display_name,
			'comment_author_email' => $user->user_email,
			'comment_type'         => 'groups_rsvp',
			'comment_approved'     => 1,
		] );
		if ( $cid ) {
			update_comment_meta( $cid, '_rsvp_status', 'attending' );
			update_comment_meta( $cid, '_rsvp_guest_count', 0 );
			$rsvp_count++;
		}
	}
}
echo "✓ $rsvp_count RSVPs.\n";

// Discussion comments on the past event.
$past_event_id = $event_ids[3] ?? 0;
if ( $past_event_id ) {
	$discussion_comments = [
		[
			'user'    => 'sakura',
			'content' => 'This was such a productive day! I learned a lot about the translation workflow.',
		],
		[
			'user'    => 'kenji',
			'content' => 'Great work everyone. 12 strings is a solid contribution for one session!',
		],
		[
			'user'    => 'yuki',
			'content' => 'Thanks to all who participated. We will schedule another translation sprint next month.',
		],
	];

	$dc_count = 0;
	foreach ( $discussion_comments as $dc ) {
		$uid  = $user_ids[ $dc['user'] ] ?? 0;
		$user = $uid ? get_user_by( 'ID', $uid ) : null;
		if ( ! $user ) {
			continue;
		}
		$cid = wp_insert_comment( [
			'comment_post_ID'      => $past_event_id,
			'user_id'              => $uid,
			'comment_author'       => $user->display_name,
			'comment_author_email' => $user->user_email,
			'comment_type'         => 'comment',
			'comment_approved'     => 1,
			'comment_content'      => $dc['content'],
		] );
		if ( $cid ) {
			$dc_count++;
		}
	}
	echo "✓ $dc_count discussion comments.\n";
}

// Pages.
foreach ( [ 'events' => 'Events', 'members' => 'Members', 'about' => 'About' ] as $slug => $title ) {
	if ( ! get_page_by_path( $slug ) ) {
		$content = $slug === 'about'
			? "<!-- wp:heading -->\n<h2>About WordPress Tokyo</h2>\n<!-- /wp:heading -->\n<!-- wp:paragraph -->\n<p>We're a vibrant community of WordPress users and developers in Tokyo. Everyone is welcome!</p>\n<!-- /wp:paragraph -->"
			: ( $slug === 'members' ? '<!-- wp:groups/group-members /-->' : '<!-- wp:groups/upcoming-events /-->' );
		wp_insert_post( [
			'post_type'    => 'page',
			'post_title'   => $title,
			'post_status'  => 'publish',
			'post_name'    => $slug,
			'post_content' => $content,
			'post_author'  => $organizer_id,
		] );
	}
}

$front = get_page_by_path( 'events' );
if ( $front ) {
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $front->ID );
}

echo "\n✅ Tokyo seed complete!\n";
