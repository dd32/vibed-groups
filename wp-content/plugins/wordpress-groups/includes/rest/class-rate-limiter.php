<?php
/**
 * Basic rate limiting for REST API write operations.
 *
 * Uses transients to track request counts per IP address.
 * Limits POST, PUT, and DELETE requests to 60 per minute.
 *
 * @package Groups\REST
 */

namespace Groups\REST;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Applies per-IP rate limiting to write operations on the groups/v1 REST namespace.
 */
class Rate_Limiter {

	/**
	 * Maximum number of write requests per window.
	 *
	 * @var int
	 */
	const LIMIT = 60;

	/**
	 * Time window in seconds.
	 *
	 * @var int
	 */
	const WINDOW = 60;

	/**
	 * REST namespace to protect.
	 *
	 * @var string
	 */
	const REST_NAMESPACE = 'groups/v1';

	/**
	 * HTTP methods considered write operations.
	 *
	 * @var string[]
	 */
	const WRITE_METHODS = [ 'POST', 'PUT', 'PATCH', 'DELETE' ];

	/**
	 * Register the rate-limiting filter.
	 */
	public static function register(): void {
		add_filter( 'rest_pre_dispatch', [ __CLASS__, 'check_rate_limit' ], 10, 3 );
	}

	/**
	 * Check rate limit before dispatching a REST request.
	 *
	 * Only applies to write operations (POST/PUT/PATCH/DELETE) within the
	 * groups/v1 namespace. Read operations are not limited.
	 *
	 * @param mixed           $result  Response to replace the requested version with.
	 * @param \WP_REST_Server $server  Server instance.
	 * @param WP_REST_Request $request Request used to generate the response.
	 * @return mixed|WP_Error Original result or WP_Error if rate limit exceeded.
	 */
	public static function check_rate_limit( $result, $server, WP_REST_Request $request ) {
		// Only limit write operations.
		if ( ! in_array( $request->get_method(), self::WRITE_METHODS, true ) ) {
			return $result;
		}

		// Only limit our namespace.
		$route = $request->get_route();
		if ( 0 !== strpos( $route, '/' . self::REST_NAMESPACE ) ) {
			return $result;
		}

		$ip  = self::get_client_ip();
		$key = 'groups_rl_' . md5( $ip );

		$current = get_transient( $key );

		if ( false === $current ) {
			// First request in this window.
			set_transient( $key, 1, self::WINDOW );
			return $result;
		}

		$current = (int) $current;

		if ( $current >= self::LIMIT ) {
			return new WP_Error(
				'rate_limit_exceeded',
				__( 'Rate limit exceeded. Please wait before making more requests.', 'wordpress-groups' ),
				[
					'status'              => 429,
					'X-RateLimit-Limit'   => self::LIMIT,
					'X-RateLimit-Window'  => self::WINDOW,
				]
			);
		}

		// Increment the counter. Use the transient API — the TTL is already set.
		set_transient( $key, $current + 1, self::WINDOW );

		return $result;
	}

	/**
	 * Get the client's IP address.
	 *
	 * Checks common proxy headers before falling back to REMOTE_ADDR.
	 *
	 * @return string Client IP address.
	 */
	private static function get_client_ip(): string {
		$headers = [
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
			'HTTP_CLIENT_IP',
		];

		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				// X-Forwarded-For may contain multiple IPs; use the first.
				$ips = explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) );
				$ip  = trim( $ips[0] );

				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' ) );
	}
}
