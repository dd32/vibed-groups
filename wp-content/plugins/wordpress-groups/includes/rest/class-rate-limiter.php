<?php
/**
 * Basic rate limiting for REST API write operations.
 *
 * Uses transients to track request counts per IP address.
 * Limits POST, PUT, and DELETE requests to 60 per minute.
 *
 * ## Load Balancer / Multi-Server Note
 *
 * This implementation stores counters in the WordPress transient API, which
 * defaults to the `wp_options` table on single-server installs. This works
 * reliably for single-server deployments but has limitations in load-balanced
 * or multi-server environments:
 *
 * - If an external object cache (Redis, Memcached) is configured, transients
 *   are stored there and shared across application servers automatically.
 * - Without an external object cache, each server maintains its own counters
 *   in the database, which may allow a client to exceed the intended limit
 *   by a factor of N (where N is the number of application servers).
 *
 * For production load-balanced environments without a shared object cache,
 * consider using the `groups_rate_limiter_client_ip` filter to integrate
 * with an upstream rate limiter (e.g. at the CDN or reverse proxy layer),
 * or use the `groups_rate_limiter_disabled` filter to disable this
 * application-level limiter entirely in favour of infrastructure-level
 * rate limiting.
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
 *
 * Filters:
 * - `groups_rate_limiter_disabled` (bool)   — Return true to bypass rate limiting entirely.
 *                                             Useful when rate limiting is handled at the
 *                                             infrastructure layer (CDN, reverse proxy).
 * - `groups_rate_limiter_limit`   (int)     — Override the per-window request limit (default 60).
 * - `groups_rate_limiter_window`  (int)     — Override the time window in seconds (default 60).
 * - `groups_rate_limiter_client_ip` (string, WP_REST_Request) — Override the resolved client IP.
 *                                             Use this when a load balancer or proxy sets a
 *                                             custom header for the real client IP.
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
		/**
		 * Filters whether the rate limiter is disabled.
		 *
		 * Return true to bypass application-level rate limiting, for example
		 * when rate limiting is handled at the infrastructure layer (CDN,
		 * reverse proxy, load balancer).
		 *
		 * @param bool $disabled Whether rate limiting is disabled. Default false.
		 */
		if ( apply_filters( 'groups_rate_limiter_disabled', false ) ) {
			return $result;
		}

		// Only limit write operations.
		if ( ! in_array( $request->get_method(), self::WRITE_METHODS, true ) ) {
			return $result;
		}

		// Only limit our namespace.
		$route = $request->get_route();
		if ( 0 !== strpos( $route, '/' . self::REST_NAMESPACE ) ) {
			return $result;
		}

		$ip = self::get_client_ip();

		/**
		 * Filters the resolved client IP address for rate limiting.
		 *
		 * Use this filter in load-balanced environments where the real client
		 * IP is provided in a custom header not covered by the default logic.
		 *
		 * @param string          $ip      The resolved client IP.
		 * @param WP_REST_Request $request The current REST request.
		 */
		$ip = apply_filters( 'groups_rate_limiter_client_ip', $ip, $request );

		$key = 'groups_rl_' . md5( $ip );

		/**
		 * Filters the per-window request limit.
		 *
		 * @param int $limit The maximum number of write requests per window. Default 60.
		 */
		$limit = (int) apply_filters( 'groups_rate_limiter_limit', self::LIMIT );

		/**
		 * Filters the rate limit time window in seconds.
		 *
		 * @param int $window The time window in seconds. Default 60.
		 */
		$window = (int) apply_filters( 'groups_rate_limiter_window', self::WINDOW );

		$current = get_transient( $key );

		if ( false === $current ) {
			// First request in this window.
			set_transient( $key, 1, $window );
			return $result;
		}

		$current = (int) $current;

		if ( $current >= $limit ) {
			return new WP_Error(
				'rate_limit_exceeded',
				__( 'Rate limit exceeded. Please wait before making more requests.', 'wordpress-groups' ),
				[
					'status'              => 429,
					'X-RateLimit-Limit'   => $limit,
					'X-RateLimit-Window'  => $window,
				]
			);
		}

		// Increment the counter. Use the transient API — the TTL is already set.
		set_transient( $key, $current + 1, $window );

		return $result;
	}

	/**
	 * Get the client's IP address.
	 *
	 * Checks common proxy headers before falling back to REMOTE_ADDR.
	 *
	 * Note: In load-balanced environments, ensure that the trusted proxy
	 * headers are set correctly. The X-Forwarded-For header can be spoofed
	 * by clients if the load balancer does not strip/overwrite it. For
	 * production deployments behind a load balancer, use the
	 * `groups_rate_limiter_client_ip` filter to read the correct header
	 * set by your infrastructure.
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
