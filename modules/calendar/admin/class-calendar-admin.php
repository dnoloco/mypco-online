<?php
/**
 * Calendar Admin Component
 *
 * Handles all backend/admin functionality for the Calendar module.
 */

class MyPCO_Calendar_Admin {

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
            __('Calendar Settings', 'mypco-online'),
            __('Calendar', 'mypco-online'),
            'manage_options',
            'mypco-calendar',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Enqueue admin-specific assets.
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our settings page
        if (strpos($hook, 'mypco-calendar') === false) {
            return;
        }

        wp_enqueue_style(
            'mypco-calendar-admin',
            MYPCO_PLUGIN_URL . 'modules/calendar/admin/assets/css/calendar-admin.css',
            [],
            MYPCO_VERSION
        );

        wp_enqueue_script(
            'mypco-calendar-admin',
            MYPCO_PLUGIN_URL . 'modules/calendar/admin/assets/js/calendar-admin.js',
            ['jquery'],
            MYPCO_VERSION,
            true
        );
    }

    /**
     * Render the settings page.
     * NO HTML HERE - just prepare data and load template.
     */
    public function render_settings_page() {
        // Get current settings
        $settings = get_option('mypco_calendar_settings', []);

        // Prepare data for template
        $data = [
            'shortcode' => '[mypco_calendar]',
            'old_shortcode' => '[pco_calendar]',
            'cache_cleared' => isset($_GET['cache_cleared']),
            'settings_saved' => isset($_GET['settings_saved']),
            'module_status' => 'active',
            'featured_count' => isset($settings['featured_count']) ? (int) $settings['featured_count'] : 2,
            'featured_mode' => isset($settings['featured_mode']) ? $settings['featured_mode'] : 'upcoming',
            'features' => [
                'List view with featured events',
                'Month view with mini calendar',
                'Gallery view with images',
                'Event detail view with registration links',
                'Responsive design',
                'Multi-day event support'
            ]
        ];

        // Load template
        $this->load_template('settings-page', $data);
    }

    /**
     * Handle cache clearing.
     */
    public function handle_cache_clear() {
        if (!isset($_POST['mypco_clear_calendar_cache'])) {
            return;
        }

        check_admin_referer('mypco_clear_calendar_cache');

        if (!current_user_can('manage_options')) {
            return;
        }

        // Clear calendar transients
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_mypco_calendar%'");

        // Redirect with success message
        wp_redirect(admin_url('admin.php?page=mypco-calendar&cache_cleared=1'));
        exit;
    }

    /**
     * Handle settings save.
     */
    public function handle_settings_save() {
        if (!isset($_POST['mypco_save_calendar_settings'])) {
            return;
        }

        check_admin_referer('mypco_calendar_settings');

        if (!current_user_can('manage_options')) {
            return;
        }

        // Sanitize and save settings
        $settings = [
            'featured_count' => isset($_POST['featured_count']) ? absint($_POST['featured_count']) : 2,
            'featured_mode' => isset($_POST['featured_mode']) && in_array($_POST['featured_mode'], ['upcoming', 'random'])
                ? sanitize_text_field($_POST['featured_mode'])
                : 'upcoming',
        ];

        update_option('mypco_calendar_settings', $settings);

        // Clear cache so new settings take effect
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_mypco_calendar%'");

        // Redirect with success message
        wp_redirect(admin_url('admin.php?page=mypco-calendar&settings_saved=1'));
        exit;
    }

    /**
     * Load a template file.
     */
    private function load_template($template_name, $data = []) {
        extract($data);
        $template_path = MYPCO_PLUGIN_DIR . 'modules/calendar/admin/templates/' . $template_name . '.php';

        if (file_exists($template_path)) {
            include $template_path;
        }
    }
}
