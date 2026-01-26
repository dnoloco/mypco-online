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
    }

    /**
     * Add admin settings page.
     */
    public function add_settings_page() {
        add_submenu_page(
            'mypco-settings',
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
        if ($hook !== 'mypco_page_mypco-calendar') {
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
        // Prepare data for template
        $data = [
            'shortcode' => '[mypco_calendar]',
            'old_shortcode' => '[pco_calendar]',
            'cache_cleared' => isset($_GET['cache_cleared']),
            'module_status' => 'active',
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
