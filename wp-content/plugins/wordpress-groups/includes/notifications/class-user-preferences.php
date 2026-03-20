<?php
/**
 * User notification preferences.
 *
 * Manages per-user opt-in/opt-out settings for each notification type.
 *
 * @package Groups\Notifications
 */

namespace Groups\Notifications;

defined( 'ABSPATH' ) || exit;

/**
 * Stores and retrieves user notification preferences from user meta.
 *
 * Preferences are stored as a serialized associative array under the
 * `_groups_notification_prefs` user meta key. All notification types
 * default to opted-in.
 */
class User_Preferences {

	/**
	 * User meta key for notification preferences.
	 *
	 * @var string
	 */
	public const META_KEY = '_groups_notification_prefs';

	/**
	 * Valid notification types.
	 *
	 * @var array<string>
	 */
	public const TYPES = [
		'event_reminders',
		'announcements',
		'rsvp_confirmations',
	];

	/**
	 * Get the preference value for a specific notification type.
	 *
	 * @param int    $user_id User ID.
	 * @param string $type    Notification type (one of self::TYPES).
	 * @return bool True if opted in, false if opted out.
	 *
	 * @throws \InvalidArgumentException If the notification type is not valid.
	 */
	public static function get_preference( int $user_id, string $type ): bool {
		self::validate_type( $type );

		$prefs = self::get_all_preferences( $user_id );

		return $prefs[ $type ] ?? true;
	}

	/**
	 * Set the preference for a specific notification type.
	 *
	 * @param int    $user_id User ID.
	 * @param string $type    Notification type (one of self::TYPES).
	 * @param bool   $enabled Whether the notification type is enabled.
	 * @return bool True on success, false on failure.
	 *
	 * @throws \InvalidArgumentException If the notification type is not valid.
	 */
	public static function set_preference( int $user_id, string $type, bool $enabled ): bool {
		self::validate_type( $type );

		$prefs          = self::get_all_preferences( $user_id );
		$prefs[ $type ] = $enabled;

		return (bool) update_user_meta( $user_id, self::META_KEY, $prefs );
	}

	/**
	 * Check whether a user is opted in to a specific notification type.
	 *
	 * Alias for get_preference() for readability at call sites.
	 *
	 * @param int    $user_id User ID.
	 * @param string $type    Notification type (one of self::TYPES).
	 * @return bool True if opted in, false if opted out.
	 */
	public static function is_opted_in( int $user_id, string $type ): bool {
		return self::get_preference( $user_id, $type );
	}

	/**
	 * Get all preferences for a user.
	 *
	 * @param int $user_id User ID.
	 * @return array<string, bool> Associative array of type => enabled pairs.
	 */
	private static function get_all_preferences( int $user_id ): array {
		$prefs = get_user_meta( $user_id, self::META_KEY, true );

		if ( ! is_array( $prefs ) ) {
			return [];
		}

		return $prefs;
	}

	/**
	 * Validate that a notification type is recognised.
	 *
	 * @param string $type Notification type to validate.
	 *
	 * @throws \InvalidArgumentException If the type is not in self::TYPES.
	 */
	private static function validate_type( string $type ): void {
		if ( ! in_array( $type, self::TYPES, true ) ) {
			throw new \InvalidArgumentException(
				sprintf(
					'Invalid notification type "%s". Valid types: %s',
					$type,
					implode( ', ', self::TYPES )
				)
			);
		}
	}
}
