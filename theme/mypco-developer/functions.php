<?php
/**
 * MyPCO Developer Theme Functions
 *
 * @package MyPCO_Developer
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MYPCO_THEME_VERSION', '1.0.0' );
define( 'MYPCO_THEME_DIR', get_template_directory() );
define( 'MYPCO_THEME_URI', get_template_directory_uri() );

/**
 * Theme setup.
 */
function mypco_developer_setup() {
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
		'primary'   => __( 'Primary Menu', 'mypco-developer' ),
		'overlay'   => __( 'Overlay Menu', 'mypco-developer' ),
		'footer'    => __( 'Footer Menu', 'mypco-developer' ),
	) );
}
add_action( 'after_setup_theme', 'mypco_developer_setup' );

/**
 * Enqueue scripts and styles.
 */
function mypco_developer_scripts() {
	// Google Fonts — build URL from hero font choices + Inter for body
	$headline_font = get_theme_mod( 'mypco_hero_headline_font', 'DM Sans' );
	$subtitle_font = get_theme_mod( 'mypco_hero_subtitle_font', 'DM Sans' );

	$font_families = array( 'Inter:wght@300;400;500;600;700;800;900' );

	// Map font name → Google Fonts query string
	$font_map = mypco_hero_font_map();
	if ( isset( $font_map[ $headline_font ] ) ) {
		$font_families[] = $font_map[ $headline_font ];
	}
	if ( $subtitle_font !== $headline_font && isset( $font_map[ $subtitle_font ] ) ) {
		$font_families[] = $font_map[ $subtitle_font ];
	}

	wp_enqueue_style(
		'mypco-developer-fonts',
		'https://fonts.googleapis.com/css2?family=' . implode( '&family=', array_map( 'rawurlencode', $font_families ) ) . '&display=swap',
		array(),
		null
	);

	// Main theme stylesheet
	wp_enqueue_style(
		'mypco-developer-style',
		MYPCO_THEME_URI . '/assets/css/theme.css',
		array( 'mypco-developer-fonts' ),
		MYPCO_THEME_VERSION
	);

	// Navigation script
	wp_enqueue_script(
		'mypco-developer-navigation',
		MYPCO_THEME_URI . '/assets/js/navigation.js',
		array(),
		MYPCO_THEME_VERSION,
		true
	);

	// Scroll reveal / parallax
	wp_enqueue_script(
		'mypco-developer-parallax',
		MYPCO_THEME_URI . '/assets/js/parallax.js',
		array(),
		MYPCO_THEME_VERSION,
		true
	);

	// Typing animation (front page only)
	if ( is_front_page() ) {
		wp_enqueue_script(
			'mypco-developer-typing',
			MYPCO_THEME_URI . '/assets/js/typing.js',
			array(),
			MYPCO_THEME_VERSION,
			true
		);
	}
}
add_action( 'wp_enqueue_scripts', 'mypco_developer_scripts' );

/**
 * Custom walker for the overlay menu — outputs clean markup.
 */
class MyPCO_Overlay_Walker extends Walker_Nav_Menu {

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
function mypco_developer_customize_register( $wp_customize ) {
	$font_choices = mypco_hero_font_choices();

	// Hero section
	$wp_customize->add_section( 'mypco_hero', array(
		'title'    => __( 'Hero Section', 'mypco-developer' ),
		'priority' => 30,
	) );

	// ── Variation selection ──────────────────────────────────────────

	$wp_customize->add_setting( 'mypco_hero_display_mode', array(
		'default'           => 'random',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'mypco_hero_display_mode', array(
		'label'   => __( 'Display Mode', 'mypco-developer' ),
		'section' => 'mypco_hero',
		'type'    => 'select',
		'choices' => array(
			'random'   => __( 'Random on each page load', 'mypco-developer' ),
			'specific' => __( 'Always show a specific variation', 'mypco-developer' ),
		),
	) );

	$wp_customize->add_setting( 'mypco_hero_default_variation', array(
		'default'           => 1,
		'sanitize_callback' => 'absint',
	) );
	$wp_customize->add_control( 'mypco_hero_default_variation', array(
		'label'       => __( 'Default Variation', 'mypco-developer' ),
		'section'     => 'mypco_hero',
		'type'        => 'select',
		'choices'     => array( 1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5' ),
		'description' => __( 'Used when Display Mode is "specific".', 'mypco-developer' ),
	) );

	$wp_customize->add_setting( 'mypco_hero_variation_count', array(
		'default'           => 5,
		'sanitize_callback' => 'absint',
	) );
	$wp_customize->add_control( 'mypco_hero_variation_count', array(
		'label'       => __( 'Number of Variations', 'mypco-developer' ),
		'section'     => 'mypco_hero',
		'type'        => 'select',
		'choices'     => array( 1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5' ),
		'description' => __( 'How many variations are available.', 'mypco-developer' ),
	) );

	// ── Headline styling ─────────────────────────────────────────────

	$wp_customize->add_setting( 'mypco_hero_headline_font', array(
		'default'           => 'DM Sans',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'mypco_hero_headline_font', array(
		'label'   => __( 'Headline Font', 'mypco-developer' ),
		'section' => 'mypco_hero',
		'type'    => 'select',
		'choices' => $font_choices,
	) );

	$wp_customize->add_setting( 'mypco_hero_headline_size', array(
		'default'           => '11',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'mypco_hero_headline_size', array(
		'label'       => __( 'Headline Size (vw)', 'mypco-developer' ),
		'section'     => 'mypco_hero',
		'type'        => 'number',
		'input_attrs' => array( 'min' => 3, 'max' => 20, 'step' => 0.5 ),
		'description' => __( 'Viewport-width units. 11 is the default.', 'mypco-developer' ),
	) );

	$wp_customize->add_setting( 'mypco_hero_headline_color', array(
		'default'           => '#1a1a1a',
		'sanitize_callback' => 'sanitize_hex_color',
	) );
	$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'mypco_hero_headline_color', array(
		'label'   => __( 'Headline Text Color', 'mypco-developer' ),
		'section' => 'mypco_hero',
	) ) );

	// ── Subtitle styling ─────────────────────────────────────────────

	$wp_customize->add_setting( 'mypco_hero_subtitle_font', array(
		'default'           => 'DM Sans',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'mypco_hero_subtitle_font', array(
		'label'   => __( 'Subtitle Font', 'mypco-developer' ),
		'section' => 'mypco_hero',
		'type'    => 'select',
		'choices' => $font_choices,
	) );

	$wp_customize->add_setting( 'mypco_hero_subtitle_size', array(
		'default'           => '2.5',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'mypco_hero_subtitle_size', array(
		'label'       => __( 'Subtitle Size (vw)', 'mypco-developer' ),
		'section'     => 'mypco_hero',
		'type'        => 'number',
		'input_attrs' => array( 'min' => 1, 'max' => 10, 'step' => 0.25 ),
		'description' => __( 'Viewport-width units. 2.5 is the default.', 'mypco-developer' ),
	) );

	$wp_customize->add_setting( 'mypco_hero_subtitle_color', array(
		'default'           => '#1a1a1a',
		'sanitize_callback' => 'sanitize_hex_color',
	) );
	$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'mypco_hero_subtitle_color', array(
		'label'   => __( 'Subtitle Text Color', 'mypco-developer' ),
		'section' => 'mypco_hero',
	) ) );

	// ── Layout ──────────────────────────────────────────────────────

	$wp_customize->add_setting( 'mypco_hero_vertical_offset', array(
		'default'           => 100,
		'sanitize_callback' => 'absint',
	) );
	$wp_customize->add_control( 'mypco_hero_vertical_offset', array(
		'label'       => __( 'Vertical Offset (px)', 'mypco-developer' ),
		'section'     => 'mypco_hero',
		'type'        => 'number',
		'input_attrs' => array( 'min' => 0, 'max' => 400, 'step' => 10 ),
		'description' => __( 'Move the headline block upward by this many pixels. Default 100.', 'mypco-developer' ),
	) );

	$wp_customize->add_setting( 'mypco_hero_bottom_tagline', array(
		'default'           => 'Hope Begins with Jesus.',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'mypco_hero_bottom_tagline', array(
		'label'       => __( 'Bottom Tagline', 'mypco-developer' ),
		'section'     => 'mypco_hero',
		'type'        => 'text',
		'description' => __( 'Centered text at the bottom of the hero, above the scroll indicator. Leave empty to hide.', 'mypco-developer' ),
	) );

	// ── Typing animation ─────────────────────────────────────────────

	$wp_customize->add_setting( 'mypco_hero_typing_speed', array(
		'default'           => 80,
		'sanitize_callback' => 'absint',
	) );
	$wp_customize->add_control( 'mypco_hero_typing_speed', array(
		'label'       => __( 'Typing Speed (ms per character)', 'mypco-developer' ),
		'section'     => 'mypco_hero',
		'type'        => 'number',
		'input_attrs' => array( 'min' => 20, 'max' => 300, 'step' => 10 ),
		'description' => __( 'Lower = faster. Default 80.', 'mypco-developer' ),
	) );

	$wp_customize->add_setting( 'mypco_hero_typing_pause', array(
		'default'           => 2000,
		'sanitize_callback' => 'absint',
	) );
	$wp_customize->add_control( 'mypco_hero_typing_pause', array(
		'label'       => __( 'Pause Before Next Word (ms)', 'mypco-developer' ),
		'section'     => 'mypco_hero',
		'type'        => 'number',
		'input_attrs' => array( 'min' => 500, 'max' => 10000, 'step' => 250 ),
		'description' => __( 'How long the completed word stays visible. Default 2000.', 'mypco-developer' ),
	) );

	// ── Per-variation content ────────────────────────────────────────

	$var_defaults = mypco_hero_variation_defaults();

	for ( $i = 1; $i <= 5; $i++ ) {
		$d = $var_defaults[ $i ];

		$wp_customize->add_setting( "mypco_hero_variation_{$i}_headline", array(
			'default'           => $d['headline'],
			'sanitize_callback' => 'sanitize_text_field',
		) );
		$wp_customize->add_control( "mypco_hero_variation_{$i}_headline", array(
			'label'       => sprintf( __( 'Variation %d — Headlines', 'mypco-developer' ), $i ),
			'section'     => 'mypco_hero',
			'type'        => 'textarea',
			'description' => __( 'Comma-separated words/phrases that cycle in the typing animation.', 'mypco-developer' ),
		) );

		$wp_customize->add_setting( "mypco_hero_variation_{$i}_subtitle", array(
			'default'           => $d['subtitle'],
			'sanitize_callback' => 'sanitize_text_field',
		) );
		$wp_customize->add_control( "mypco_hero_variation_{$i}_subtitle", array(
			'label'   => sprintf( __( 'Variation %d — Subtitle', 'mypco-developer' ), $i ),
			'section' => 'mypco_hero',
			'type'    => 'text',
		) );
	}
}

/**
 * Available Google Fonts for the hero section.
 */
function mypco_hero_font_choices() {
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
function mypco_hero_font_map() {
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
function mypco_hero_variation_defaults() {
	return array(
		1 => array( 'headline' => 'unlock,discover,explore',  'subtitle' => 'the another angle.' ),
		2 => array( 'headline' => 'seek,find,believe',        'subtitle' => 'hope begins with Jesus.' ),
		3 => array( 'headline' => 'create,inspire,connect',   'subtitle' => 'something meaningful.' ),
		4 => array( 'headline' => 'build,grow,thrive',        'subtitle' => 'a stronger community.' ),
		5 => array( 'headline' => 'dream,pursue,achieve',     'subtitle' => 'what matters most.' ),
	);
}
add_action( 'customize_register', 'mypco_developer_customize_register' );

/**
 * Disable the admin bar on the front-end for cleaner parallax experience.
 */
function mypco_developer_disable_admin_bar_styles() {
	if ( ! is_admin() ) {
		remove_action( 'wp_head', '_admin_bar_bump_cb' );
	}
}
add_action( 'init', 'mypco_developer_disable_admin_bar_styles' );
