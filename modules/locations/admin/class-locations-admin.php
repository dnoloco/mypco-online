<?php
/**
 * Locations Admin Component
 *
 * Handles all backend/admin functionality for the Locations module.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MyPCO_Locations_Admin {

    private $loader;
    private $api_model;

    public function __construct($loader, $api_model) {
        $this->loader = $loader;
        $this->api_model = $api_model;
    }

    /**
     * Initialize admin functionality.
     */
    public function init() {
        // Add settings page
        $this->loader->add_action('admin_menu', $this, 'add_settings_page');

        // Enqueue admin assets
        $this->loader->add_action('admin_enqueue_scripts', $this, 'enqueue_admin_assets');

        // Handle cache clearing
        $this->loader->add_action('admin_init', $this, 'handle_cache_clear');

        // Handle settings save
        $this->loader->add_action('admin_init', $this, 'handle_settings_save');
    }

    /**
     * Add admin settings page.
     */
    public function add_settings_page() {
        add_submenu_page(
            'mypco-dashboard',
            __('Locations Settings', 'mypco-online'),
            __('Locations', 'mypco-online'),
            'manage_options',
            'mypco-locations',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Enqueue admin-specific assets.
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our settings page
        if (strpos($hook, 'mypco-locations') === false) {
            return;
        }

        wp_enqueue_style(
            'mypco-locations-admin',
            MYPCO_PLUGIN_URL . 'modules/locations/admin/assets/css/locations-admin.css',
            [],
            MYPCO_VERSION
        );
    }

    /**
     * Render the settings page.
     */
    public function render_settings_page() {
        // Get current settings
        $settings = $this->get_settings();

        // Prepare data for template
        $data = [
            'shortcode_next' => '[mypco_next_sunday]',
            'shortcode_list' => '[mypco_sunday_list]',
            'cache_cleared' => isset($_GET['cache_cleared']),
            'settings_saved' => isset($_GET['settings_saved']),
            'module_status' => 'active',
            'event_name' => $settings['event_name'],
            'layout_style' => $settings['layout_style'],
            'show_map' => $settings['show_map'],
            'map_height' => $settings['map_height'],
            'primary_color' => $settings['primary_color'],
            'text_color' => $settings['text_color'],
            'background_color' => $settings['background_color'],
            'border_radius' => $settings['border_radius'],
            'date_format' => $settings['date_format'],
            'time_format' => $settings['time_format'],
        ];

        // Load template
        $this->load_template('settings-page', $data);
    }

    /**
     * Get default settings.
     */
    public function get_default_settings() {
        return [
            'event_name' => 'Sunday Gathering',
            'layout_style' => 'card',
            'show_map' => true,
            'map_height' => 200,
            'primary_color' => '#333333',
            'text_color' => '#333333',
            'background_color' => '#ffffff',
            'border_radius' => 8,
            'date_format' => 'l, F j, Y',
            'time_format' => 'g:i a',
        ];
    }

    /**
     * Get current settings merged with defaults.
     */
    public function get_settings() {
        $defaults = $this->get_default_settings();
        $saved = get_option('mypco_locations_settings', []);
        return wp_parse_args($saved, $defaults);
    }

    /**
     * Handle cache clearing.
     */
    public function handle_cache_clear() {
        if (!isset($_POST['mypco_clear_locations_cache'])) {
            return;
        }

        check_admin_referer('mypco_clear_locations_cache');

        if (!current_user_can('manage_options')) {
            return;
        }

        // Clear locations transients
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_mypco_locations%'");

        // Redirect with success message
        wp_redirect(admin_url('admin.php?page=mypco-locations&cache_cleared=1'));
        exit;
    }

    /**
     * Handle settings save.
     */
    public function handle_settings_save() {
        if (!isset($_POST['mypco_save_locations_settings'])) {
            return;
        }

        check_admin_referer('mypco_locations_settings');

        if (!current_user_can('manage_options')) {
            return;
        }

        // Sanitize and save settings
        $settings = [
            'event_name' => isset($_POST['event_name'])
                ? sanitize_text_field($_POST['event_name'])
                : 'Sunday Gathering',
            'layout_style' => isset($_POST['layout_style']) && in_array($_POST['layout_style'], ['card', 'minimal', 'banner'])
                ? sanitize_text_field($_POST['layout_style'])
                : 'card',
            'show_map' => isset($_POST['show_map']) ? true : false,
            'map_height' => isset($_POST['map_height'])
                ? absint($_POST['map_height'])
                : 200,
            'primary_color' => isset($_POST['primary_color'])
                ? sanitize_hex_color($_POST['primary_color'])
                : '#333333',
            'text_color' => isset($_POST['text_color'])
                ? sanitize_hex_color($_POST['text_color'])
                : '#333333',
            'background_color' => isset($_POST['background_color'])
                ? sanitize_hex_color($_POST['background_color'])
                : '#ffffff',
            'border_radius' => isset($_POST['border_radius'])
                ? absint($_POST['border_radius'])
                : 8,
            'date_format' => isset($_POST['date_format'])
                ? sanitize_text_field($_POST['date_format'])
                : 'l, F j, Y',
            'time_format' => isset($_POST['time_format'])
                ? sanitize_text_field($_POST['time_format'])
                : 'g:i a',
        ];

        update_option('mypco_locations_settings', $settings);

        // Clear cache so new settings take effect
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_mypco_locations%'");

        // Redirect with success message
        wp_redirect(admin_url('admin.php?page=mypco-locations&settings_saved=1'));
        exit;
    }

    /**
     * Load a template file.
     */
    private function load_template($template_name, $data = []) {
        extract($data);
        $template_path = MYPCO_PLUGIN_DIR . 'modules/locations/admin/templates/' . $template_name . '.php';

        if (file_exists($template_path)) {
            include $template_path;
        }
    }
}
