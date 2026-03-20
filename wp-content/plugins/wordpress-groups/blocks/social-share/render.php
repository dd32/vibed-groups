<?php
/**
 * Server-side render for the Social Share block.
 *
 * Outputs share links for Twitter/X, Facebook, LinkedIn, and a copy-link button.
 * Uses the current event's title and permalink. No external JS required.
 *
 * @package Groups\Blocks
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$share_url   = esc_url( get_the_permalink() );
$share_title = rawurlencode( get_the_title() );
$share_text  = rawurlencode( get_the_title() . ' — ' . get_the_permalink() );

$wrapper_attributes = get_block_wrapper_attributes( [
	'class' => 'wp-block-groups-social-share',
] );

// Inline SVG icons (16x16).
$icon_x = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>';

$icon_facebook = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>';

$icon_linkedin = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>';

$icon_link = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>';

$links = [
	[
		'label' => __( 'Share on X', 'wordpress-groups' ),
		'url'   => 'https://x.com/intent/tweet?text=' . $share_text,
		'icon'  => $icon_x,
		'class' => 'wp-block-groups-social-share__link--x',
	],
	[
		'label' => __( 'Share on Facebook', 'wordpress-groups' ),
		'url'   => 'https://www.facebook.com/sharer/sharer.php?u=' . $share_url,
		'icon'  => $icon_facebook,
		'class' => 'wp-block-groups-social-share__link--facebook',
	],
	[
		'label' => __( 'Share on LinkedIn', 'wordpress-groups' ),
		'url'   => 'https://www.linkedin.com/sharing/share-offsite/?url=' . $share_url,
		'icon'  => $icon_linkedin,
		'class' => 'wp-block-groups-social-share__link--linkedin',
	],
	[
		'label' => __( 'Copy link', 'wordpress-groups' ),
		'url'   => $share_url,
		'icon'  => $icon_link,
		'class' => 'wp-block-groups-social-share__link--copy',
		'extra' => ' data-share-url="' . esc_attr( get_the_permalink() ) . '" role="button" onclick="navigator.clipboard.writeText(this.dataset.shareUrl);var s=this.querySelector(\'span\');s.textContent=\'' . esc_js( __( 'Copied!', 'wordpress-groups' ) ) . '\';this.setAttribute(\'aria-live\',\'polite\');setTimeout(function(){s.textContent=\'' . esc_js( __( 'Copy link', 'wordpress-groups' ) ) . '\';},2000);return false;"',
	],
];
?>

<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php foreach ( $links as $link ) : ?>
		<a
			href="<?php echo esc_url( $link['url'] ); ?>"
			class="wp-block-groups-social-share__link <?php echo esc_attr( $link['class'] ); ?>"
			aria-label="<?php echo esc_attr( $link['label'] ); ?>"
			target="_blank"
			rel="noopener noreferrer"
			<?php echo isset( $link['extra'] ) ? $link['extra'] : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		>
			<?php echo $link['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<span><?php echo esc_html( $link['label'] ); ?></span>
		</a>
	<?php endforeach; ?>
</div>
