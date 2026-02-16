<?php
/**
 * Theme header.
 *
 * @package MyPCO_Developer
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="site-header" id="site-header">
	<div class="site-header__inner">
		<div class="site-header__logo">
			<?php if ( has_custom_logo() ) : ?>
				<?php the_custom_logo(); ?>
			<?php else : ?>
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="site-header__title">
					<?php bloginfo( 'name' ); ?>
				</a>
			<?php endif; ?>
		</div>

		<div class="site-header__actions">
			<?php if ( has_nav_menu( 'primary' ) ) : ?>
				<nav class="site-header__nav" aria-label="<?php esc_attr_e( 'Primary', 'mypco-developer' ); ?>">
					<?php
					wp_nav_menu( array(
						'theme_location' => 'primary',
						'container'      => false,
						'menu_class'     => 'header-nav',
						'depth'          => 1,
					) );
					?>
				</nav>
			<?php endif; ?>

			<button class="menu-toggle" id="menu-toggle" aria-label="<?php esc_attr_e( 'Open menu', 'mypco-developer' ); ?>" aria-expanded="false">
				<span class="menu-toggle__line"></span>
				<span class="menu-toggle__line"></span>
			</button>
		</div>
	</div>
</header>

<!-- Full-screen overlay menu -->
<div class="overlay-menu" id="overlay-menu" aria-hidden="true">
	<div class="overlay-menu__inner">
		<nav class="overlay-menu__nav" aria-label="<?php esc_attr_e( 'Overlay', 'mypco-developer' ); ?>">
			<?php
			if ( has_nav_menu( 'overlay' ) ) {
				wp_nav_menu( array(
					'theme_location' => 'overlay',
					'container'      => false,
					'menu_class'     => 'overlay-menu__list',
					'depth'          => 1,
					'walker'         => new MyPCO_Overlay_Walker(),
				) );
			} elseif ( has_nav_menu( 'primary' ) ) {
				wp_nav_menu( array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'overlay-menu__list',
					'depth'          => 1,
					'walker'         => new MyPCO_Overlay_Walker(),
				) );
			}
			?>
		</nav>
	</div>
</div>

<main class="site-main" id="site-main">
