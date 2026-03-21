<?php
/**
 * Guest RSVP support for logged-out users.
 *
 * Allows anonymous users to RSVP via email + token verification.
 *
 * @package Groups\Models
 */

namespace Groups\Models;

defined( 'ABSPATH' ) || exit;

/**
 * Handles email-based RSVP for non-authenticated users.
 */
class Guest_Rsvp {

	/**
	 * Create a guest RSVP for an event.
	 *
	 * @param int    $event_id Event post ID.
	 * @param string $email    Guest's email address.
	 * @param string $name     Guest's display name.
	 * @return int|WP_Error Comment ID or error.
	 */
	public static function create( int $event_id, string $email, string $name ) {
		$event = get_post( $event_id );
		if ( ! $event || 'event' !== $event->post_type ) {
			return new \WP_Error( 'invalid_event', __( 'Invalid event.', 'wordpress-groups' ) );
		}

		if ( ! is_email( $email ) ) {
			return new \WP_Error( 'invalid_email', __( 'Please enter a valid email address.', 'wordpress-groups' ) );
		}

		$name = sanitize_text_field( $name );
		if ( empty( $name ) ) {
			return new \WP_Error( 'empty_name', __( 'Please enter your name.', 'wordpress-groups' ) );
		}

		// Check for existing RSVP with this email.
		$existing = get_comments( [
			'post_id'      => $event_id,
			'type'         => 'groups_rsvp',
			'author_email' => $email,
			'number'       => 1,
		] );

		if ( ! empty( $existing ) ) {
			$status = get_comment_meta( $existing[0]->comment_ID, '_rsvp_status', true );
			if ( 'not_attending' !== $status ) {
				return new \WP_Error( 'already_rsvped', __( 'This email is already registered for this event.', 'wordpress-groups' ) );
			}
			// Re-activate cancelled RSVP.
			update_comment_meta( $existing[0]->comment_ID, '_rsvp_status', 'attending' );
			return $existing[0]->comment_ID;
		}

		// Check capacity.
		$limit = (int) get_post_meta( $event_id, '_event_attendee_limit', true );
		if ( $limit > 0 ) {
			$attending = Rsvp::get_count( $event_id, 'attending' );
			$status    = $attending >= $limit ? 'waitlisted' : 'attending';
		} else {
			$status = 'attending';
		}

		// Generate verification token.
		$token = wp_generate_password( 32, false );

		$comment_id = wp_insert_comment( [
			'comment_post_ID'      => $event_id,
			'user_id'              => 0,
			'comment_author'       => $name,
			'comment_author_email' => $email,
			'comment_type'         => 'groups_rsvp',
			'comment_approved'     => 1,
			'comment_content'      => '',
		] );

		if ( ! $comment_id ) {
			return new \WP_Error( 'rsvp_failed', __( 'Could not create RSVP.', 'wordpress-groups' ) );
		}

		update_comment_meta( $comment_id, '_rsvp_status', $status );
		update_comment_meta( $comment_id, '_rsvp_guest_count', 0 );
		update_comment_meta( $comment_id, '_rsvp_token', wp_hash( $token ) );
		update_comment_meta( $comment_id, '_rsvp_is_guest', 1 );

		/**
		 * Fires when a guest RSVP is created.
		 *
		 * @param int    $comment_id Comment ID.
		 * @param int    $event_id   Event post ID.
		 * @param string $email      Guest email.
		 * @param string $status     RSVP status.
		 * @param string $token      Raw verification token.
		 */
		do_action( 'groups_guest_rsvp_created', $comment_id, $event_id, $email, $status, $token );

		return $comment_id;
	}

	/**
	 * Cancel a guest RSVP using email + token.
	 *
	 * @param int    $event_id Event post ID.
	 * @param string $email    Guest email.
	 * @param string $token    Verification token.
	 * @return bool|WP_Error True on success.
	 */
	public static function cancel( int $event_id, string $email, string $token ) {
		$comment = self::verify( $event_id, $email, $token );
		if ( is_wp_error( $comment ) ) {
			return $comment;
		}

		$was_attending = 'attending' === get_comment_meta( $comment->comment_ID, '_rsvp_status', true );
		update_comment_meta( $comment->comment_ID, '_rsvp_status', 'not_attending' );

		if ( $was_attending ) {
			Rsvp::promote_next( $event_id );
		}

		return true;
	}

	/**
	 * Verify a guest RSVP token.
	 *
	 * @param int    $event_id Event post ID.
	 * @param string $email    Guest email.
	 * @param string $token    Raw token.
	 * @return WP_Comment|WP_Error The RSVP comment or error.
	 */
	public static function verify( int $event_id, string $email, string $token ) {
		$comments = get_comments( [
			'post_id'      => $event_id,
			'type'         => 'groups_rsvp',
			'author_email' => $email,
			'number'       => 1,
		] );

		if ( empty( $comments ) ) {
			return new \WP_Error( 'not_found', __( 'No RSVP found for this email.', 'wordpress-groups' ) );
		}

		$stored_hash = get_comment_meta( $comments[0]->comment_ID, '_rsvp_token', true );
		if ( ! $stored_hash || ! hash_equals( $stored_hash, wp_hash( $token ) ) ) {
			return new \WP_Error( 'invalid_token', __( 'Invalid verification token.', 'wordpress-groups' ) );
		}

		return $comments[0];
	}
}
