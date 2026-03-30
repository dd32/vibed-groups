<?php
/**
 * Server-side render for the Group Directory block.
 *
 * Renders the list of group sites in the multisite network.
 * Each non-main site is treated as a community group.
 *
 * @package Groups\Blocks
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content (empty for server-rendered).
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$per_page     = ! empty( $attributes['perPage'] ) ? absint( $attributes['perPage'] ) : 12;
$main_site_id = get_main_site_id();

// Get all non-main sites in the network.
$sites = get_sites( [
	'number'       => $per_page,
	'site__not_in' => [ $main_site_id ],
	'public'       => 1,
	'archived'     => 0,
	'deleted'      => 0,
	'orderby'      => 'registered',
	'order'        => 'ASC',
] );

$groups    = [];
$total     = (int) get_sites( [
	'count'        => true,
	'site__not_in' => [ $main_site_id ],
	'public'       => 1,
	'archived'     => 0,
	'deleted'      => 0,
] );
$total_pages = $per_page > 0 ? (int) ceil( $total / $per_page ) : 1;

foreach ( $sites as $site ) {
	switch_to_blog( $site->blog_id );

	$member_count_data = count_users();
	$member_count      = isset( $member_count_data['total_users'] ) ? (int) $member_count_data['total_users'] : 0;
	$site_name         = get_bloginfo( 'name' );
	$site_url          = home_url( '/' );
	$site_description  = get_bloginfo( 'description' );

	// Count upcoming events on this site.
	$upcoming_count = 0;
	if ( post_type_exists( 'event' ) ) {
		$upcoming_query = new WP_Query( [
			'post_type'              => 'event',
			'post_status'            => [ 'event-scheduled', 'publish' ],
			'posts_per_page'         => 1,
			'no_found_rows'          => false,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'fields'                 => 'ids',
		] );
		$upcoming_count = (int) $upcoming_query->found_posts;
	}

	restore_current_blog();

	$groups[] = [
		'id'             => (int) $site->blog_id,
		'name'           => esc_html( $site_name ),
		'description'    => esc_html( $site_description ),
		'member_count'   => $member_count,
		'upcoming_count' => $upcoming_count,
		'site_url'       => esc_url( $site_url ),
		'registered'     => $site->registered,
	];
}

$wrapper_attributes = get_block_wrapper_attributes( [
	'class'         => 'wp-block-groups-group-directory',
	'data-per-page' => $per_page,
	'data-total'    => $total,
	'data-pages'    => $total_pages,
	'data-groups'   => wp_json_encode( $groups ),
] );
?>

<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="wp-block-groups-group-directory__search">
		<label for="group-directory-search" class="screen-reader-text">
			<?php esc_html_e( 'Search groups', 'wordpress-groups' ); ?>
		</label>
		<input
			type="search"
			id="group-directory-search"
			class="wp-block-groups-group-directory__search-input"
			placeholder="<?php esc_attr_e( 'Search groups…', 'wordpress-groups' ); ?>"
		/>
	</div>

	<div class="wp-block-groups-group-directory__results" aria-live="polite">
		<?php if ( empty( $groups ) ) : ?>
			<p class="wp-block-groups-group-directory__empty">
				<?php esc_html_e( 'No groups found.', 'wordpress-groups' ); ?>
			</p>
		<?php else : ?>
			<div class="wp-block-groups-group-directory__grid">
				<?php foreach ( $groups as $group ) : ?>
					<a href="<?php echo esc_url( $group['site_url'] ); ?>" class="wp-block-groups-group-directory__card">
						<h3 class="wp-block-groups-group-directory__card-name">
							<?php echo esc_html( $group['name'] ); ?>
						</h3>

						<?php if ( $group['description'] ) : ?>
							<p class="wp-block-groups-group-directory__card-description">
								<?php echo esc_html( $group['description'] ); ?>
							</p>
						<?php endif; ?>

						<div class="wp-block-groups-group-directory__card-meta">
							<span class="wp-block-groups-group-directory__card-members">
								<?php
								printf(
									/* translators: %s: Number of members. */
									esc_html( _n( '%s member', '%s members', $group['member_count'], 'wordpress-groups' ) ),
									number_format_i18n( $group['member_count'] )
								);
								?>
							</span>
							<?php if ( $group['upcoming_count'] > 0 ) : ?>
								<span class="wp-block-groups-group-directory__card-events">
									<?php
									printf(
										/* translators: %s: Number of upcoming events. */
										esc_html( _n( '%s upcoming event', '%s upcoming events', $group['upcoming_count'], 'wordpress-groups' ) ),
										number_format_i18n( $group['upcoming_count'] )
									);
									?>
								</span>
							<?php endif; ?>
						</div>
					</a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>

	<?php if ( $total_pages > 1 ) : ?>
		<nav class="wp-block-groups-group-directory__pagination" aria-label="<?php esc_attr_e( 'Group directory pagination', 'wordpress-groups' ); ?>">
			<span class="wp-block-groups-group-directory__page-info">
				<?php
				printf(
					/* translators: 1: Current page, 2: Total pages. */
					esc_html__( 'Page %1$d of %2$d', 'wordpress-groups' ),
					1,
					$total_pages
				);
				?>
			</span>
		</nav>
	<?php endif; ?>
</div>
