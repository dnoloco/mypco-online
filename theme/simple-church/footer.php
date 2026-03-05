<?php
/**
 * Theme footer.
 *
 * @package Simple_Church
 */
?>
</main>

<?php
$footer_bg    = get_theme_mod( 'simple_church_footer_bg_color', '#0a0a0a' );
$footer_text  = get_theme_mod( 'simple_church_footer_text_color', '#ffffff' );
$footer_links = get_theme_mod( 'simple_church_footer_link_color', '#999999' );
?>
<footer class="site-footer" style="background-color: <?php echo esc_attr( $footer_bg ); ?>; --footer-text: <?php echo esc_attr( $footer_text ); ?>; --footer-link: <?php echo esc_attr( $footer_links ); ?>;">
	<div class="site-footer__inner">
		<div class="site-footer__top">
			<div class="site-footer__brand">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="site-footer__title">
					<?php bloginfo( 'name' ); ?>
				</a>
			</div>

			<?php if ( has_nav_menu( 'footer' ) ) : ?>
				<nav class="site-footer__nav" aria-label="<?php esc_attr_e( 'Footer', 'simple-church' ); ?>">
					<?php
					wp_nav_menu( array(
						'theme_location' => 'footer',
						'container'      => false,
						'menu_class'     => 'footer-nav',
						'depth'          => 1,
					) );
					?>
				</nav>
			<?php endif; ?>
		</div>

		<div class="site-footer__bottom">
			<p class="site-footer__copy">
				&copy; <?php echo esc_html( date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?>. <?php esc_html_e( 'All rights reserved.', 'simple-church' ); ?>
			</p>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
