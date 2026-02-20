<?php
/**
 * Simple Church Theme Functions
 *
 * @package Simple_Church
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SIMPLE_CHURCH_VERSION', '1.0.0' );
define( 'SIMPLE_CHURCH_DIR', get_template_directory() );
define( 'SIMPLE_CHURCH_URI', get_template_directory_uri() );

/**
 * Check whether the MyPCO Online plugin is active.
 *
 * @return bool
 */
function simple_church_is_mypco_active() {
	return function_exists( 'run_mypco_online' );
}

/**
 * Theme setup.
 */
function simple_church_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array(
		'search-form',
		'comment-form',
		'comment-list',
		'gallery',
		'caption',
		'style',
		'script',
	) );
	add_theme_support( 'custom-logo', array(
		'height'      => 60,
		'width'       => 200,
		'flex-height' => true,
		'flex-width'  => true,
	) );

	register_nav_menus( array(
		'primary'   => __( 'Primary Menu', 'simple-church' ),
		'overlay'   => __( 'Overlay Menu', 'simple-church' ),
		'footer'    => __( 'Footer Menu', 'simple-church' ),
	) );
}
add_action( 'after_setup_theme', 'simple_church_setup' );

/**
 * Enqueue scripts and styles.
 */
function simple_church_scripts() {
	// Google Fonts — build URL from hero font choices + Inter for body
	$headline_font = get_theme_mod( 'simple_church_hero_headline_font', 'DM Sans' );
	$subtitle_font = get_theme_mod( 'simple_church_hero_subtitle_font', 'DM Sans' );
	$tagline_font  = get_theme_mod( 'simple_church_hero_tagline_font', 'DM Sans' );

	$font_families = array( 'Inter:wght@300;400;500;600;700;800;900' );
	$loaded_fonts  = array();

	// Map font name → Google Fonts query string
	$font_map = simple_church_hero_font_map();
	foreach ( array( $headline_font, $subtitle_font, $tagline_font ) as $font_name ) {
		if ( isset( $font_map[ $font_name ] ) && ! isset( $loaded_fonts[ $font_name ] ) ) {
			$font_families[] = $font_map[ $font_name ];
			$loaded_fonts[ $font_name ] = true;
		}
	}

	wp_enqueue_style(
		'simple-church-fonts',
		'https://fonts.googleapis.com/css2?family=' . implode( '&family=', array_map( 'rawurlencode', $font_families ) ) . '&display=swap',
		array(),
		null
	);

	// Main theme stylesheet
	wp_enqueue_style(
		'simple-church-style',
		SIMPLE_CHURCH_URI . '/assets/css/theme.css',
		array( 'simple-church-fonts' ),
		SIMPLE_CHURCH_VERSION
	);

	// Navigation script
	wp_enqueue_script(
		'simple-church-navigation',
		SIMPLE_CHURCH_URI . '/assets/js/navigation.js',
		array(),
		SIMPLE_CHURCH_VERSION,
		true
	);

	// Scroll reveal / parallax
	wp_enqueue_script(
		'simple-church-parallax',
		SIMPLE_CHURCH_URI . '/assets/js/parallax.js',
		array(),
		SIMPLE_CHURCH_VERSION,
		true
	);

	// Typing animation (front page only)
	if ( is_front_page() ) {
		wp_enqueue_script(
			'simple-church-typing',
			SIMPLE_CHURCH_URI . '/assets/js/typing.js',
			array(),
			SIMPLE_CHURCH_VERSION,
			true
		);
	}
}
add_action( 'wp_enqueue_scripts', 'simple_church_scripts' );

/**
 * Custom walker for the overlay menu — outputs clean markup.
 */
class Simple_Church_Overlay_Walker extends Walker_Nav_Menu {

	public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
		$classes = implode( ' ', array_filter( $item->classes ) );
		$output .= '<li class="overlay-menu__item ' . esc_attr( $classes ) . '">';
		$output .= '<a class="overlay-menu__link" href="' . esc_url( $item->url ) . '">';
		$output .= esc_html( $item->title );
		$output .= '</a>';
	}

	public function end_el( &$output, $item, $depth = 0, $args = null ) {
		$output .= '</li>';
	}
}

/**
 * Add theme customizer settings.
 */
function simple_church_customize_register( $wp_customize ) {
	$font_choices = simple_church_hero_font_choices();

	// ── Hero Panel ──────────────────────────────────────────────────
	$wp_customize->add_panel( 'simple_church_hero_panel', array(
		'title'    => __( 'Hero Section', 'simple-church' ),
		'priority' => 30,
	) );

	// ── 1. Display Settings ─────────────────────────────────────────
	$wp_customize->add_section( 'simple_church_hero_display', array(
		'title' => __( 'Display Settings', 'simple-church' ),
		'panel' => 'simple_church_hero_panel',
		'priority' => 10,
	) );

	$wp_customize->add_setting( 'simple_church_hero_display_mode', array(
		'default'           => 'random',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'simple_church_hero_display_mode', array(
		'label'   => __( 'Display Mode', 'simple-church' ),
		'section' => 'simple_church_hero_display',
		'type'    => 'select',
		'choices' => array(
			'random'   => __( 'Random on each page load', 'simple-church' ),
			'specific' => __( 'Always show a specific variation', 'simple-church' ),
		),
	) );

	$wp_customize->add_setting( 'simple_church_hero_default_variation', array(
		'default'           => 1,
		'sanitize_callback' => 'absint',
	) );
	$wp_customize->add_control( 'simple_church_hero_default_variation', array(
		'label'       => __( 'Default Variation', 'simple-church' ),
		'section'     => 'simple_church_hero_display',
		'type'        => 'select',
		'choices'     => array( 1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5' ),
		'description' => __( 'Used when Display Mode is "specific".', 'simple-church' ),
	) );

	$wp_customize->add_setting( 'simple_church_hero_variation_count', array(
		'default'           => 5,
		'sanitize_callback' => 'absint',
	) );
	$wp_customize->add_control( 'simple_church_hero_variation_count', array(
		'label'       => __( 'Number of Variations', 'simple-church' ),
		'section'     => 'simple_church_hero_display',
		'type'        => 'select',
		'choices'     => array( 1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5' ),
		'description' => __( 'How many variations are available.', 'simple-church' ),
	) );

	// ── 2. Headline Styling ─────────────────────────────────────────
	$wp_customize->add_section( 'simple_church_hero_headline', array(
		'title' => __( 'Headline Styling', 'simple-church' ),
		'panel' => 'simple_church_hero_panel',
		'priority' => 20,
	) );

	$wp_customize->add_setting( 'simple_church_hero_headline_font', array(
		'default'           => 'DM Sans',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'simple_church_hero_headline_font', array(
		'label'   => __( 'Font', 'simple-church' ),
		'section' => 'simple_church_hero_headline',
		'type'    => 'select',
		'choices' => $font_choices,
	) );

	$wp_customize->add_setting( 'simple_church_hero_headline_size', array(
		'default'           => '11',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'simple_church_hero_headline_size', array(
		'label'       => __( 'Size (vw)', 'simple-church' ),
		'section'     => 'simple_church_hero_headline',
		'type'        => 'number',
		'input_attrs' => array( 'min' => 3, 'max' => 20, 'step' => 0.5 ),
		'description' => __( 'Viewport-width units. 11 is the default.', 'simple-church' ),
	) );

	$wp_customize->add_setting( 'simple_church_hero_headline_color', array(
		'default'           => '#1a1a1a',
		'sanitize_callback' => 'sanitize_hex_color',
	) );
	$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'simple_church_hero_headline_color', array(
		'label'   => __( 'Text Color', 'simple-church' ),
		'section' => 'simple_church_hero_headline',
	) ) );

	// ── 3. Subtitle Styling ─────────────────────────────────────────
	$wp_customize->add_section( 'simple_church_hero_subtitle', array(
		'title' => __( 'Subtitle Styling', 'simple-church' ),
		'panel' => 'simple_church_hero_panel',
		'priority' => 30,
	) );

	$wp_customize->add_setting( 'simple_church_hero_subtitle_font', array(
		'default'           => 'DM Sans',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'simple_church_hero_subtitle_font', array(
		'label'   => __( 'Font', 'simple-church' ),
		'section' => 'simple_church_hero_subtitle',
		'type'    => 'select',
		'choices' => $font_choices,
	) );

	$wp_customize->add_setting( 'simple_church_hero_subtitle_size', array(
		'default'           => '2.5',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'simple_church_hero_subtitle_size', array(
		'label'       => __( 'Size (vw)', 'simple-church' ),
		'section'     => 'simple_church_hero_subtitle',
		'type'        => 'number',
		'input_attrs' => array( 'min' => 1, 'max' => 10, 'step' => 0.25 ),
		'description' => __( 'Viewport-width units. 2.5 is the default.', 'simple-church' ),
	) );

	$wp_customize->add_setting( 'simple_church_hero_subtitle_color', array(
		'default'           => '#1a1a1a',
		'sanitize_callback' => 'sanitize_hex_color',
	) );
	$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'simple_church_hero_subtitle_color', array(
		'label'   => __( 'Text Color', 'simple-church' ),
		'section' => 'simple_church_hero_subtitle',
	) ) );

	// ── 4. Bottom Tagline ───────────────────────────────────────────
	$wp_customize->add_section( 'simple_church_hero_tagline', array(
		'title' => __( 'Bottom Tagline', 'simple-church' ),
		'panel' => 'simple_church_hero_panel',
		'priority' => 40,
	) );

	$wp_customize->add_setting( 'simple_church_hero_bottom_tagline', array(
		'default'           => 'Hope Begins with Jesus.',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'simple_church_hero_bottom_tagline', array(
		'label'       => __( 'Text', 'simple-church' ),
		'section'     => 'simple_church_hero_tagline',
		'type'        => 'text',
		'description' => __( 'Centered text above the scroll indicator. Leave empty to hide.', 'simple-church' ),
	) );

	$wp_customize->add_setting( 'simple_church_hero_tagline_font', array(
		'default'           => 'DM Sans',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'simple_church_hero_tagline_font', array(
		'label'   => __( 'Font', 'simple-church' ),
		'section' => 'simple_church_hero_tagline',
		'type'    => 'select',
		'choices' => $font_choices,
	) );

	$wp_customize->add_setting( 'simple_church_hero_tagline_size', array(
		'default'           => '1',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'simple_church_hero_tagline_size', array(
		'label'       => __( 'Size (vw)', 'simple-church' ),
		'section'     => 'simple_church_hero_tagline',
		'type'        => 'number',
		'input_attrs' => array( 'min' => 0.5, 'max' => 5, 'step' => 0.25 ),
		'description' => __( 'Viewport-width units. 1 is the default.', 'simple-church' ),
	) );

	$wp_customize->add_setting( 'simple_church_hero_tagline_color', array(
		'default'           => '#1a1a1a',
		'sanitize_callback' => 'sanitize_hex_color',
	) );
	$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'simple_church_hero_tagline_color', array(
		'label'   => __( 'Text Color', 'simple-church' ),
		'section' => 'simple_church_hero_tagline',
	) ) );

	// ── 5. Layout ───────────────────────────────────────────────────
	$wp_customize->add_section( 'simple_church_hero_layout', array(
		'title' => __( 'Layout', 'simple-church' ),
		'panel' => 'simple_church_hero_panel',
		'priority' => 50,
	) );

	$wp_customize->add_setting( 'simple_church_hero_vertical_offset', array(
		'default'           => 100,
		'sanitize_callback' => 'absint',
	) );
	$wp_customize->add_control( 'simple_church_hero_vertical_offset', array(
		'label'       => __( 'Vertical Offset (px)', 'simple-church' ),
		'section'     => 'simple_church_hero_layout',
		'type'        => 'number',
		'input_attrs' => array( 'min' => 0, 'max' => 400, 'step' => 10 ),
		'description' => __( 'Move the headline block upward by this many pixels. Default 100.', 'simple-church' ),
	) );

	// ── 6. Typing Animation ─────────────────────────────────────────
	$wp_customize->add_section( 'simple_church_hero_typing', array(
		'title' => __( 'Typing Animation', 'simple-church' ),
		'panel' => 'simple_church_hero_panel',
		'priority' => 60,
	) );

	$wp_customize->add_setting( 'simple_church_hero_typing_speed', array(
		'default'           => 80,
		'sanitize_callback' => 'absint',
	) );
	$wp_customize->add_control( 'simple_church_hero_typing_speed', array(
		'label'       => __( 'Typing Speed (ms per character)', 'simple-church' ),
		'section'     => 'simple_church_hero_typing',
		'type'        => 'number',
		'input_attrs' => array( 'min' => 20, 'max' => 300, 'step' => 10 ),
		'description' => __( 'Lower = faster. Default 80.', 'simple-church' ),
	) );

	$wp_customize->add_setting( 'simple_church_hero_typing_pause', array(
		'default'           => 2000,
		'sanitize_callback' => 'absint',
	) );
	$wp_customize->add_control( 'simple_church_hero_typing_pause', array(
		'label'       => __( 'Pause Before Next Word (ms)', 'simple-church' ),
		'section'     => 'simple_church_hero_typing',
		'type'        => 'number',
		'input_attrs' => array( 'min' => 500, 'max' => 10000, 'step' => 250 ),
		'description' => __( 'How long the completed word stays visible. Default 2000.', 'simple-church' ),
	) );

	// ── 7. Variations ───────────────────────────────────────────────
	$wp_customize->add_section( 'simple_church_hero_variations', array(
		'title' => __( 'Variations', 'simple-church' ),
		'panel' => 'simple_church_hero_panel',
		'priority' => 70,
	) );

	$var_defaults = simple_church_hero_variation_defaults();

	for ( $i = 1; $i <= 5; $i++ ) {
		$d = $var_defaults[ $i ];

		$wp_customize->add_setting( "simple_church_hero_variation_{$i}_headline", array(
			'default'           => $d['headline'],
			'sanitize_callback' => 'sanitize_text_field',
		) );
		$wp_customize->add_control( "simple_church_hero_variation_{$i}_headline", array(
			'label'       => sprintf( __( 'Variation %d — Headlines', 'simple-church' ), $i ),
			'section'     => 'simple_church_hero_variations',
			'type'        => 'textarea',
			'description' => __( 'Comma-separated words/phrases that cycle in the typing animation.', 'simple-church' ),
		) );

		$wp_customize->add_setting( "simple_church_hero_variation_{$i}_subtitle", array(
			'default'           => $d['subtitle'],
			'sanitize_callback' => 'sanitize_text_field',
		) );
		$wp_customize->add_control( "simple_church_hero_variation_{$i}_subtitle", array(
			'label'   => sprintf( __( 'Variation %d — Subtitle', 'simple-church' ), $i ),
			'section' => 'simple_church_hero_variations',
			'type'    => 'text',
		) );
	}
}
add_action( 'customize_register', 'simple_church_customize_register' );

/**
 * Available Google Fonts for the hero section.
 */
function simple_church_hero_font_choices() {
	return array(
		'DM Sans'          => 'DM Sans',
		'Inter'            => 'Inter',
		'Outfit'           => 'Outfit',
		'Space Grotesk'    => 'Space Grotesk',
		'Plus Jakarta Sans' => 'Plus Jakarta Sans',
		'Syne'             => 'Syne',
		'Poppins'          => 'Poppins',
		'Montserrat'       => 'Montserrat',
		'Raleway'          => 'Raleway',
		'Playfair Display' => 'Playfair Display',
		'Lora'             => 'Lora',
		'Cormorant Garamond' => 'Cormorant Garamond',
	);
}

/**
 * Google Fonts query fragments keyed by font name.
 */
function simple_church_hero_font_map() {
	return array(
		'DM Sans'            => 'DM+Sans:wght@400;500;700',
		'Inter'              => 'Inter:wght@300;400;500;600;700;800;900',
		'Outfit'             => 'Outfit:wght@300;400;500;600;700',
		'Space Grotesk'      => 'Space+Grotesk:wght@300;400;500;600;700',
		'Plus Jakarta Sans'  => 'Plus+Jakarta+Sans:wght@300;400;500;600;700',
		'Syne'               => 'Syne:wght@400;500;600;700;800',
		'Poppins'            => 'Poppins:wght@300;400;500;600;700',
		'Montserrat'         => 'Montserrat:wght@300;400;500;600;700;800',
		'Raleway'            => 'Raleway:wght@300;400;500;600;700',
		'Playfair Display'   => 'Playfair+Display:wght@400;500;600;700',
		'Lora'               => 'Lora:wght@400;500;600;700',
		'Cormorant Garamond' => 'Cormorant+Garamond:wght@300;400;500;600;700',
	);
}

/**
 * Default values for hero variations.
 */
function simple_church_hero_variation_defaults() {
	return array(
		1 => array( 'headline' => 'unlock,discover,explore',  'subtitle' => 'the another angle.' ),
		2 => array( 'headline' => 'seek,find,believe',        'subtitle' => 'hope begins with Jesus.' ),
		3 => array( 'headline' => 'create,inspire,connect',   'subtitle' => 'something meaningful.' ),
		4 => array( 'headline' => 'build,grow,thrive',        'subtitle' => 'a stronger community.' ),
		5 => array( 'headline' => 'dream,pursue,achieve',     'subtitle' => 'what matters most.' ),
	);
}

/**
 * Register block pattern categories.
 */
function simple_church_register_pattern_categories() {
	register_block_pattern_category( 'simple-church', array(
		'label' => __( 'Simple Church', 'simple-church' ),
	) );
	register_block_pattern_category( 'simple-church-premium', array(
		'label' => __( 'Simple Church — Premium', 'simple-church' ),
	) );
}
add_action( 'init', 'simple_church_register_pattern_categories' );

/**
 * Enqueue the patterns interactive JS (tabs, accordion, stat counters).
 */
function simple_church_patterns_scripts() {
	wp_enqueue_script(
		'simple-church-patterns',
		SIMPLE_CHURCH_URI . '/assets/js/patterns.js',
		array(),
		SIMPLE_CHURCH_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'simple_church_patterns_scripts' );

/**
 * Disable the admin bar on the front-end for cleaner parallax experience.
 */
function simple_church_disable_admin_bar_styles() {
	if ( ! is_admin() ) {
		remove_action( 'wp_head', '_admin_bar_bump_cb' );
	}
}
add_action( 'init', 'simple_church_disable_admin_bar_styles' );
