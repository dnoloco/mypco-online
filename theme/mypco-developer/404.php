<?php
/**
 * 404 template.
 *
 * @package MyPCO_Developer
 */

get_header();
?>

<section class="section section--dark section--full">
	<div class="section__inner" style="text-align: center;">
		<h1 class="hero__headline reveal">404</h1>
		<p class="section__text reveal" data-reveal-delay="200">
			<?php esc_html_e( 'The page you are looking for does not exist.', 'mypco-developer' ); ?>
		</p>
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="cta__button reveal" data-reveal-delay="400">
			<?php esc_html_e( 'Go home', 'mypco-developer' ); ?>
		</a>
	</div>
</section>

<?php get_footer(); ?>
