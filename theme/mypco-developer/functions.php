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
	// Google Fonts — Inter
	wp_enqueue_style(
		'mypco-developer-fonts',
		'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap',
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

	// Number of hero slides
	$wp_customize->add_setting( 'mypco_hero_slide_count', array(
		'default'           => 3,
		'sanitize_callback' => 'absint',
	) );
	$wp_customize->add_control( 'mypco_hero_slide_count', array(
		'label'       => __( 'Number of Hero Slides', 'mypco-developer' ),
		'section'     => 'mypco_hero',
		'type'        => 'select',
		'choices'     => array(
			1 => '1',
			2 => '2',
			3 => '3',
			4 => '4',
			5 => '5',
		),
		'description' => __( 'How many headline slides to rotate through. Settings below for unused slides are ignored.', 'mypco-developer' ),
	) );

	$slide_defaults = mypco_hero_slide_defaults();

	for ( $i = 1; $i <= 5; $i++ ) {
		$d = $slide_defaults[ $i ];

		// Headline
		$wp_customize->add_setting( "mypco_hero_slide_{$i}_headline", array(
			'default'           => $d['headline'],
			'sanitize_callback' => 'sanitize_text_field',
		) );
		$wp_customize->add_control( "mypco_hero_slide_{$i}_headline", array(
			'label'   => sprintf( __( 'Slide %d — Headline', 'mypco-developer' ), $i ),
			'section' => 'mypco_hero',
			'type'    => 'text',
		) );

		// Typing words
		$wp_customize->add_setting( "mypco_hero_slide_{$i}_words", array(
			'default'           => $d['words'],
			'sanitize_callback' => 'sanitize_text_field',
		) );
		$wp_customize->add_control( "mypco_hero_slide_{$i}_words", array(
			'label'       => sprintf( __( 'Slide %d — Typing Words', 'mypco-developer' ), $i ),
			'section'     => 'mypco_hero',
			'type'        => 'textarea',
			'description' => __( 'Comma-separated words for the typing animation.', 'mypco-developer' ),
		) );

		// Gradient start color
		$wp_customize->add_setting( "mypco_hero_slide_{$i}_color_start", array(
			'default'           => $d['color_start'],
			'sanitize_callback' => 'sanitize_hex_color',
		) );
		$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, "mypco_hero_slide_{$i}_color_start", array(
			'label'   => sprintf( __( 'Slide %d — Gradient Start Color', 'mypco-developer' ), $i ),
			'section' => 'mypco_hero',
		) ) );

		// Gradient end color
		$wp_customize->add_setting( "mypco_hero_slide_{$i}_color_end", array(
			'default'           => $d['color_end'],
			'sanitize_callback' => 'sanitize_hex_color',
		) );
		$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, "mypco_hero_slide_{$i}_color_end", array(
			'label'   => sprintf( __( 'Slide %d — Gradient End Color', 'mypco-developer' ), $i ),
			'section' => 'mypco_hero',
		) ) );
	}

	// Hero subtitle
	$wp_customize->add_setting( 'mypco_hero_subtitle', array(
		'default'           => 'Minimal design. Purposeful technology. Scroll to explore.',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'mypco_hero_subtitle', array(
		'label'   => __( 'Hero Subtitle', 'mypco-developer' ),
		'section' => 'mypco_hero',
		'type'    => 'text',
	) );
}
/**
 * Default values for hero slides.
 */
function mypco_hero_slide_defaults() {
	return array(
		1 => array( 'headline' => 'We craft',   'words' => 'digital experiences,meaningful connections',   'color_start' => '#6366f1', 'color_end' => '#ec4899' ),
		2 => array( 'headline' => 'We build',   'words' => 'modern platforms,powerful tools',              'color_start' => '#f59e0b', 'color_end' => '#ef4444' ),
		3 => array( 'headline' => 'We create',  'words' => 'creative solutions,community platforms',       'color_start' => '#10b981', 'color_end' => '#3b82f6' ),
		4 => array( 'headline' => 'We design',  'words' => 'intuitive interfaces,beautiful systems',       'color_start' => '#8b5cf6', 'color_end' => '#06b6d4' ),
		5 => array( 'headline' => 'We deliver', 'words' => 'real results,lasting impact',                  'color_start' => '#f43f5e', 'color_end' => '#f97316' ),
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
