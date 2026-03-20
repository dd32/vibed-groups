<?php
/**
 * Event Duplicator — adds a "Duplicate Event" action to the admin list table.
 *
 * @package Groups\Admin
 */

namespace Groups\Admin;

use Groups\Models\Event as Event_Model;

defined( 'ABSPATH' ) || exit;

/**
 * Adds a row action to duplicate an event and handles the duplication request.
 */
class Event_Duplicator {

	/**
	 * Constructor — register hooks.
	 */
	public function __construct() {
		add_filter( 'post_row_actions', [ $this, 'add_duplicate_link' ], 10, 2 );
		add_action( 'admin_action_duplicate_event', [ $this, 'handle_duplication' ] );
	}

	/**
	 * Add a "Duplicate" link to event row actions.
	 *
	 * @param array    $actions Existing row actions.
	 * @param \WP_Post $post    Current post.
	 * @return array Modified actions.
	 */
	public function add_duplicate_link( array $actions, \WP_Post $post ): array {
		if ( 'event' !== $post->post_type ) {
			return $actions;
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			return $actions;
		}

		$url = wp_nonce_url(
			admin_url( 'admin.php?action=duplicate_event&post=' . $post->ID ),
			'duplicate_event_' . $post->ID
		);

		$actions['duplicate'] = sprintf(
			'<a href="%s" aria-label="%s">%s</a>',
			esc_url( $url ),
			/* translators: %s: Event title. */
			esc_attr( sprintf( __( 'Duplicate &#8220;%s&#8221;', 'wordpress-groups' ), $post->post_title ) ),
			esc_html__( 'Duplicate', 'wordpress-groups' )
		);

		return $actions;
	}

	/**
	 * Handle the duplication admin action.
	 */
	public function handle_duplication(): void {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;

		if ( ! $post_id ) {
			wp_die( esc_html__( 'No event specified.', 'wordpress-groups' ) );
		}

		check_admin_referer( 'duplicate_event_' . $post_id );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to duplicate events.', 'wordpress-groups' ) );
		}

		$source = get_post( $post_id );

		if ( ! $source || 'event' !== $source->post_type ) {
			wp_die( esc_html__( 'Invalid event.', 'wordpress-groups' ) );
		}

		// Clone the event as a draft.
		$new_post_id = wp_insert_post( [
			'post_type'    => 'event',
			'post_title'   => sprintf(
				/* translators: %s: Original event title. */
				__( '%s (Copy)', 'wordpress-groups' ),
				$source->post_title
			),
			'post_content' => $source->post_content,
			'post_excerpt' => $source->post_excerpt,
			'post_status'  => 'event-draft',
			'post_author'  => get_current_user_id(),
		], true );

		if ( is_wp_error( $new_post_id ) ) {
			wp_die( esc_html( $new_post_id->get_error_message() ) );
		}

		// Copy all event meta.
		foreach ( Event_Model::META_KEYS as $key => $meta_key ) {
			$value = get_post_meta( $post_id, $meta_key, true );
			if ( '' !== $value ) {
				update_post_meta( $new_post_id, $meta_key, $value );
			}
		}

		// Copy the featured image.
		$thumbnail_id = get_post_thumbnail_id( $post_id );
		if ( $thumbnail_id ) {
			set_post_thumbnail( $new_post_id, $thumbnail_id );
		}

		// Redirect to the edit screen for the new event.
		wp_safe_redirect( admin_url( 'post.php?action=edit&post=' . $new_post_id ) );
		exit;
	}
}
