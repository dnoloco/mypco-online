<?php
/**
 * Front page template — hero with typing animation + parallax scroll sections.
 *
 * @package MyPCO_Developer
 */

get_header();

// Build hero variations from Customizer settings.
$variation_count = absint( get_theme_mod( 'mypco_hero_variation_count', 5 ) );
$variation_count = max( 1, min( 5, $variation_count ) );
$display_mode    = get_theme_mod( 'mypco_hero_display_mode', 'random' );
$var_defaults    = mypco_hero_variation_defaults();

$variations = array();
for ( $i = 1; $i <= $variation_count; $i++ ) {
	$d         = $var_defaults[ $i ];
	$words_raw = get_theme_mod( "mypco_hero_variation_{$i}_headline", $d['headline'] );
	$variations[] = array(
		'words'    => array_map( 'trim', explode( ',', $words_raw ) ),
		'subtitle' => get_theme_mod( "mypco_hero_variation_{$i}_subtitle", $d['subtitle'] ),
	);
}

// Select which variation to display.
if ( 'specific' === $display_mode ) {
	$active_index = absint( get_theme_mod( 'mypco_hero_default_variation', 1 ) ) - 1;
	$active_index = max( 0, min( $variation_count - 1, $active_index ) );
} else {
	$active_index = wp_rand( 0, $variation_count - 1 );
}

$active = $variations[ $active_index ];

// Styling settings.
$headline_color = get_theme_mod( 'mypco_hero_headline_color', '#1a1a1a' );
$subtitle_color = get_theme_mod( 'mypco_hero_subtitle_color', '#1a1a1a' );
$headline_font  = get_theme_mod( 'mypco_hero_headline_font', 'DM Sans' );
$subtitle_font  = get_theme_mod( 'mypco_hero_subtitle_font', 'DM Sans' );
$headline_size  = floatval( get_theme_mod( 'mypco_hero_headline_size', '11' ) );
$subtitle_size  = floatval( get_theme_mod( 'mypco_hero_subtitle_size', '2.5' ) );
$typing_speed     = absint( get_theme_mod( 'mypco_hero_typing_speed', 80 ) );
$typing_pause     = absint( get_theme_mod( 'mypco_hero_typing_pause', 2000 ) );
$vertical_offset  = absint( get_theme_mod( 'mypco_hero_vertical_offset', 100 ) );
$bottom_tagline   = get_theme_mod( 'mypco_hero_bottom_tagline', 'Hope Begins with Jesus.' );
$tagline_font     = get_theme_mod( 'mypco_hero_tagline_font', 'DM Sans' );
$tagline_size     = floatval( get_theme_mod( 'mypco_hero_tagline_size', '1' ) );
$tagline_color    = get_theme_mod( 'mypco_hero_tagline_color', '#1a1a1a' );
?>

<!-- ============================================
     SECTION 1 — Hero with typing headline
     ============================================ -->
<section class="hero" id="hero"
	style="--hero-headline-color: <?php echo esc_attr( $headline_color ); ?>; --hero-subtitle-color: <?php echo esc_attr( $subtitle_color ); ?>; --hero-headline-font: '<?php echo esc_attr( $headline_font ); ?>', sans-serif; --hero-subtitle-font: '<?php echo esc_attr( $subtitle_font ); ?>', sans-serif; --hero-headline-size: <?php echo esc_attr( $headline_size ); ?>vw; --hero-subtitle-size: <?php echo esc_attr( $subtitle_size ); ?>vw; --hero-offset: <?php echo esc_attr( $vertical_offset ); ?>px; --hero-tagline-font: '<?php echo esc_attr( $tagline_font ); ?>', sans-serif; --hero-tagline-size: <?php echo esc_attr( $tagline_size ); ?>vw; --hero-tagline-color: <?php echo esc_attr( $tagline_color ); ?>;">
	<div class="hero__content">
		<h1 class="hero__headline" id="typed-output"
			data-words="<?php echo esc_attr( wp_json_encode( $active['words'] ) ); ?>"
			data-typing-speed="<?php echo esc_attr( $typing_speed ); ?>"
			data-typing-pause="<?php echo esc_attr( $typing_pause ); ?>">
			<span class="hero__typed-text"></span><span class="hero__cursor">|</span>
		</h1>
		<hr class="hero__divider">
		<p class="hero__subtitle"><?php echo esc_html( $active['subtitle'] ); ?></p>
	</div>

	<?php if ( $bottom_tagline ) : ?>
		<div class="hero__bottom-tagline">
			<p><?php echo esc_html( $bottom_tagline ); ?></p>
		</div>
	<?php endif; ?>

	<div class="hero__scroll-indicator">
		<div class="hero__scroll-line"></div>
	</div>
</section>

<!-- ============================================
     SECTION 2 — Statement (scroll reveal, dark)
     ============================================ -->
<section class="section section--dark section--full" id="statement">
	<div class="section__inner">
		<div class="reveal-group">
			<h2 class="section__heading reveal">
				An approach built on simplicity.
			</h2>
			<p class="section__text reveal" data-reveal-delay="200">
				We believe in stripping away the unnecessary to reveal what matters most.
				Every pixel serves a purpose. Every interaction is intentional.
			</p>
			<p class="section__text reveal" data-reveal-delay="400">
				Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod
				tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam,
				quis nostrud exercitation ullamco laboris.
			</p>
		</div>
	</div>
</section>

<!-- ============================================
     SECTION 3 — Services / capabilities grid
     ============================================ -->
<section class="section section--light" id="services">
	<div class="section__inner">
		<div class="reveal-group">
			<span class="section__label reveal">What we do</span>
			<h2 class="section__heading reveal" data-reveal-delay="100">
				Capabilities
			</h2>
		</div>

		<div class="card-grid">
			<div class="card reveal" data-reveal-delay="0">
				<span class="card__number">01</span>
				<h3 class="card__title">Strategy</h3>
				<p class="card__text">
					Lorem ipsum dolor sit amet, consectetur adipiscing elit.
					Pellentesque habitant morbi tristique senectus.
				</p>
			</div>
			<div class="card reveal" data-reveal-delay="100">
				<span class="card__number">02</span>
				<h3 class="card__title">Design</h3>
				<p class="card__text">
					Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.
					Ut enim ad minim veniam exercitation.
				</p>
			</div>
			<div class="card reveal" data-reveal-delay="200">
				<span class="card__number">03</span>
				<h3 class="card__title">Development</h3>
				<p class="card__text">
					Duis aute irure dolor in reprehenderit in voluptate velit esse
					cillum dolore eu fugiat nulla pariatur.
				</p>
			</div>
			<div class="card reveal" data-reveal-delay="300">
				<span class="card__number">04</span>
				<h3 class="card__title">Integration</h3>
				<p class="card__text">
					Excepteur sint occaecat cupidatat non proident, sunt in culpa
					qui officia deserunt mollit anim id est.
				</p>
			</div>
		</div>
	</div>
</section>

<!-- ============================================
     SECTION 4 — Parallax image break
     ============================================ -->
<section class="parallax-break" id="parallax-break">
	<div class="parallax-break__overlay"></div>
	<div class="parallax-break__content reveal">
		<blockquote class="parallax-break__quote">
			&ldquo;Simplicity is the ultimate sophistication.&rdquo;
		</blockquote>
	</div>
</section>

<!-- ============================================
     SECTION 5 — About / story (split layout)
     ============================================ -->
<section class="section section--light" id="about">
	<div class="section__inner">
		<div class="split">
			<div class="split__left">
				<span class="section__label reveal">About</span>
				<h2 class="section__heading reveal" data-reveal-delay="100">
					Built for communities that value clarity.
				</h2>
			</div>
			<div class="split__right">
				<p class="section__text reveal" data-reveal-delay="200">
					Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vivamus lacinia
					odio vitae vestibulum vestibulum. Cras vehicula, mi eget laoreet venenatis,
					justo arcu scelerisque mauris, a facilisis nisi tellus vel nulla.
				</p>
				<p class="section__text reveal" data-reveal-delay="300">
					Proin gravida nibh vel velit auctor aliquet. Aenean sollicitudin, lorem quis
					bibendum auctor, nisi elit consequat ipsum, nec sagittis sem nibh id elit.
					Duis sed odio sit amet nibh vulputate cursus a sit amet mauris.
				</p>
				<a href="#" class="section__link reveal" data-reveal-delay="400">Learn more &rarr;</a>
			</div>
		</div>
	</div>
</section>

<!-- ============================================
     SECTION 6 — Module placeholders
     ============================================ -->
<section class="section section--dark" id="modules">
	<div class="section__inner">
		<div class="reveal-group">
			<span class="section__label reveal">Modules</span>
			<h2 class="section__heading reveal" data-reveal-delay="100">
				Everything you need, nothing you don't.
			</h2>
		</div>

		<div class="module-grid">
			<div class="module-card reveal" data-reveal-delay="0">
				<div class="module-card__icon">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
						<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
						<line x1="16" y1="2" x2="16" y2="6"/>
						<line x1="8" y1="2" x2="8" y2="6"/>
						<line x1="3" y1="10" x2="21" y2="10"/>
					</svg>
				</div>
				<h3 class="module-card__title">Calendar</h3>
				<p class="module-card__text">Display and manage events with multiple view options.</p>
			</div>
			<div class="module-card reveal" data-reveal-delay="100">
				<div class="module-card__icon">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
						<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
						<circle cx="9" cy="7" r="4"/>
						<path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
						<path d="M16 3.13a4 4 0 0 1 0 7.75"/>
					</svg>
				</div>
				<h3 class="module-card__title">Groups</h3>
				<p class="module-card__text">Manage and showcase community groups and registrations.</p>
			</div>
			<div class="module-card reveal" data-reveal-delay="200">
				<div class="module-card__icon">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
						<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
					</svg>
				</div>
				<h3 class="module-card__title">Services</h3>
				<p class="module-card__text">Service planning, volunteer management, and scheduling.</p>
			</div>
			<div class="module-card reveal" data-reveal-delay="300">
				<div class="module-card__icon">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
						<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
						<path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
					</svg>
				</div>
				<h3 class="module-card__title">Series</h3>
				<p class="module-card__text">Message archives organised by series, speakers, and topics.</p>
			</div>
			<div class="module-card reveal" data-reveal-delay="400">
				<div class="module-card__icon">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
						<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
						<polyline points="14 2 14 8 20 8"/>
						<line x1="16" y1="13" x2="8" y2="13"/>
						<line x1="16" y1="17" x2="8" y2="17"/>
					</svg>
				</div>
				<h3 class="module-card__title">Signups</h3>
				<p class="module-card__text">Event registration with integrated payment processing.</p>
			</div>
			<div class="module-card reveal" data-reveal-delay="500">
				<div class="module-card__icon">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
						<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
					</svg>
				</div>
				<h3 class="module-card__title">Contacts</h3>
				<p class="module-card__text">Community communication and messaging tools.</p>
			</div>
		</div>
	</div>
</section>

<!-- ============================================
     SECTION 7 — CTA
     ============================================ -->
<section class="section section--cta" id="cta">
	<div class="section__inner">
		<div class="cta reveal">
			<h2 class="cta__heading">Ready to get started?</h2>
			<p class="cta__text">
				Lorem ipsum dolor sit amet, consectetur adipiscing elit.
				Let us help you build something meaningful.
			</p>
			<a href="#" class="cta__button">Get in touch</a>
		</div>
	</div>
</section>

<?php get_footer(); ?>
