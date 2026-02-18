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
	// Google Fonts — Inter + DM Sans (hero)
	wp_enqueue_style(
		'mypco-developer-fonts',
		'https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500&family=Inter:wght@300;400;500;600;700;800;900&display=swap',
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
	// Hero section
	$wp_customize->add_section( 'mypco_hero', array(
		'title'    => __( 'Hero Section', 'mypco-developer' ),
		'priority' => 30,
	) );

	// Display mode — specific variation or random on each page load
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

	// Which variation to show when mode is "specific"
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

	// Number of active variations
	$wp_customize->add_setting( 'mypco_hero_variation_count', array(
		'default'           => 5,
		'sanitize_callback' => 'absint',
	) );
	$wp_customize->add_control( 'mypco_hero_variation_count', array(
		'label'       => __( 'Number of Variations', 'mypco-developer' ),
		'section'     => 'mypco_hero',
		'type'        => 'select',
		'choices'     => array( 1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5' ),
		'description' => __( 'How many variations are available. Settings below for unused variations are ignored.', 'mypco-developer' ),
	) );

	// Hero headline text colour
	$wp_customize->add_setting( 'mypco_hero_headline_color', array(
		'default'           => '#1a1a1a',
		'sanitize_callback' => 'sanitize_hex_color',
	) );
	$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'mypco_hero_headline_color', array(
		'label'   => __( 'Headline Text Color', 'mypco-developer' ),
		'section' => 'mypco_hero',
	) ) );

	// Hero subtitle text colour
	$wp_customize->add_setting( 'mypco_hero_subtitle_color', array(
		'default'           => '#1a1a1a',
		'sanitize_callback' => 'sanitize_hex_color',
	) );
	$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'mypco_hero_subtitle_color', array(
		'label'   => __( 'Subtitle Text Color', 'mypco-developer' ),
		'section' => 'mypco_hero',
	) ) );

	// Per-variation settings
	$var_defaults = mypco_hero_variation_defaults();

	for ( $i = 1; $i <= 5; $i++ ) {
		$d = $var_defaults[ $i ];

		// Headline (typed)
		$wp_customize->add_setting( "mypco_hero_variation_{$i}_headline", array(
			'default'           => $d['headline'],
			'sanitize_callback' => 'sanitize_text_field',
		) );
		$wp_customize->add_control( "mypco_hero_variation_{$i}_headline", array(
			'label'   => sprintf( __( 'Variation %d — Headline', 'mypco-developer' ), $i ),
			'section' => 'mypco_hero',
			'type'    => 'text',
		) );

		// Subtitle
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
 * Default values for hero variations.
 */
function mypco_hero_variation_defaults() {
	return array(
		1 => array( 'headline' => 'unlock',    'subtitle' => 'the another angle.' ),
		2 => array( 'headline' => 'discover',  'subtitle' => 'what matters most.' ),
		3 => array( 'headline' => 'explore',   'subtitle' => 'new possibilities.' ),
		4 => array( 'headline' => 'create',    'subtitle' => 'something meaningful.' ),
		5 => array( 'headline' => 'build',     'subtitle' => 'lasting connections.' ),
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
