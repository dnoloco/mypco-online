<?php
/**
 * Locations Admin Component
 *
 * Handles all backend/admin functionality for the Locations module.
 * Manages shortcode configurations with per-shortcode settings.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MyPCO_Locations_Admin {

    private $loader;
    private $api_model;

    /**
     * Option key for storing shortcode configurations.
     */
    const OPTION_KEY = 'mypco_locations_shortcodes';

    /**
     * Legacy option key (pre-shortcode management).
     */
    const LEGACY_OPTION_KEY = 'mypco_locations_settings';

    public function __construct($loader, $api_model) {
        $this->loader = $loader;
        $this->api_model = $api_model;
    }

    /**
     * Initialize admin functionality.
     */
    public function init() {
        $this->loader->add_action('admin_menu', $this, 'add_settings_page');
        $this->loader->add_action('admin_enqueue_scripts', $this, 'enqueue_admin_assets');
        $this->loader->add_action('admin_init', $this, 'handle_cache_clear');
        $this->loader->add_action('admin_init', $this, 'handle_save_shortcode');
        $this->loader->add_action('admin_init', $this, 'handle_delete_shortcode');
    }

    /**
     * Add admin settings page.
     */
    public function add_settings_page() {
        add_submenu_page(
            'mypco-dashboard',
            __('Locations', 'mypco-online'),
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

    // =========================================================================
    // Shortcode Configuration Methods
    // =========================================================================

    /**
     * Get all shortcode configurations.
     *
     * Handles migration from legacy settings on first access.
     *
     * @return array Associative array of shortcode configs keyed by ID.
     */
    public function get_shortcodes() {
        $shortcodes = get_option(self::OPTION_KEY, null);

        // First run or migration needed
        if ($shortcodes === null) {
            $shortcodes = $this->create_defaults_from_legacy();
            update_option(self::OPTION_KEY, $shortcodes);
        }

        return $shortcodes;
    }

    /**
     * Get a single shortcode configuration by ID.
     *
     * @param int $id Shortcode ID.
     * @return array|null Shortcode config or null if not found.
     */
    public function get_shortcode($id) {
        $shortcodes = $this->get_shortcodes();
        return isset($shortcodes[$id]) ? $shortcodes[$id] : null;
    }

    /**
     * Save a shortcode configuration.
     *
     * @param int   $id   Shortcode ID (0 for new).
     * @param array $data Shortcode settings.
     * @return int The saved shortcode ID.
     */
    public function save_shortcode($id, $data) {
        $shortcodes = $this->get_shortcodes();

        if ($id === 0) {
            $id = $this->get_next_id($shortcodes);
        }

        $shortcodes[$id] = $data;
        update_option(self::OPTION_KEY, $shortcodes);

        return $id;
    }

    /**
     * Delete a shortcode configuration.
     *
     * @param int $id Shortcode ID.
     * @return bool True if deleted, false if default or not found.
     */
    public function delete_shortcode($id) {
        $shortcodes = $this->get_shortcodes();

        if (!isset($shortcodes[$id])) {
            return false;
        }

        // Prevent deleting defaults
        if (!empty($shortcodes[$id]['is_default'])) {
            return false;
        }

        unset($shortcodes[$id]);
        update_option(self::OPTION_KEY, $shortcodes);

        return true;
    }

    /**
     * Get the next available shortcode ID.
     *
     * @param array $shortcodes Current shortcodes.
     * @return int Next available ID.
     */
    private function get_next_id($shortcodes) {
        if (empty($shortcodes)) {
            return 1;
        }
        return max(array_keys($shortcodes)) + 1;
    }

    /**
     * Create default shortcode configs, migrating from legacy settings if they exist.
     *
     * @return array Default shortcode configurations.
     */
    private function create_defaults_from_legacy() {
        $legacy = get_option(self::LEGACY_OPTION_KEY, []);
        $base = self::get_default_shortcode_settings('next_sunday');

        // Merge legacy settings into base defaults
        if (!empty($legacy)) {
            foreach ($base as $key => $value) {
                if (isset($legacy[$key])) {
                    $base[$key] = $legacy[$key];
                }
            }
        }

        $next_sunday = array_merge($base, [
            'type' => 'next_sunday',
            'name' => 'Next Sunday',
            'is_default' => true,
        ]);

        $sunday_list_base = self::get_default_shortcode_settings('sunday_list');
        if (!empty($legacy)) {
            foreach ($sunday_list_base as $key => $value) {
                if (isset($legacy[$key])) {
                    $sunday_list_base[$key] = $legacy[$key];
                }
            }
        }

        $sunday_list = array_merge($sunday_list_base, [
            'type' => 'sunday_list',
            'name' => 'Sunday List',
            'is_default' => true,
        ]);

        return [
            1 => $next_sunday,
            2 => $sunday_list,
        ];
    }

    /**
     * Get default settings for a shortcode type.
     *
     * @param string $type Shortcode type: 'next_sunday' or 'sunday_list'.
     * @return array Default settings.
     */
    public static function get_default_shortcode_settings($type = 'next_sunday') {
        $common = [
            'event_name'       => 'Sunday Gathering',
            'show_time'        => true,
            'show_address'     => true,
            'empty_message'    => '',
            'custom_class'     => '',
            'primary_color'    => '#333333',
            'text_color'       => '#333333',
            'background_color' => '#ffffff',
            'border_radius'    => 8,
            'date_format'      => 'l, F j, Y',
            'time_format'      => 'g:i a',
        ];

        if ($type === 'next_sunday') {
            return array_merge($common, [
                'layout_style' => 'card',
                'show_title'   => true,
                'show_map'     => true,
                'map_height'   => 200,
            ]);
        }

        // sunday_list
        return array_merge($common, [
            'count' => 'auto',
        ]);
    }

    /**
     * Get settings for a shortcode by ID (static, for use by public class).
     *
     * @param int    $id   Shortcode ID.
     * @param string $type Fallback type if ID not found.
     * @return array Shortcode settings.
     */
    public static function get_shortcode_settings($id, $type = 'next_sunday') {
        $shortcodes = get_option(self::OPTION_KEY, []);

        if (isset($shortcodes[$id])) {
            return $shortcodes[$id];
        }

        // If no ID match, find first shortcode of this type (backward compatibility)
        foreach ($shortcodes as $config) {
            if (isset($config['type']) && $config['type'] === $type) {
                return $config;
            }
        }

        // Ultimate fallback
        return self::get_default_shortcode_settings($type);
    }

    // =========================================================================
    // Page Rendering
    // =========================================================================

    /**
     * Render the settings page (routes to list or edit view).
     */
    public function render_settings_page() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';

        switch ($action) {
            case 'edit':
            case 'new':
                $this->render_edit_page();
                break;
            default:
                $this->render_list_page();
                break;
        }
    }

    /**
     * Render the list view (main page).
     */
    private function render_list_page() {
        $shortcodes = $this->get_shortcodes();

        $data = [
            'shortcodes'     => $shortcodes,
            'cache_cleared'  => isset($_GET['cache_cleared']),
            'settings_saved' => isset($_GET['settings_saved']),
            'deleted'        => isset($_GET['deleted']),
            'page_url'       => admin_url('admin.php?page=mypco-locations'),
        ];

        $this->load_template('settings-page', $data);
    }

    /**
     * Render the edit/new shortcode view.
     */
    private function render_edit_page() {
        $action = sanitize_text_field($_GET['action']);
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;

        if ($action === 'edit' && $id > 0) {
            $shortcode = $this->get_shortcode($id);
            if (!$shortcode) {
                wp_redirect(admin_url('admin.php?page=mypco-locations'));
                exit;
            }
            $type = $shortcode['type'];
        } else {
            // New shortcode — default to next_sunday type
            $type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : 'next_sunday';
            if (!in_array($type, ['next_sunday', 'sunday_list'])) {
                $type = 'next_sunday';
            }
            $shortcode = self::get_default_shortcode_settings($type);
            $shortcode['type'] = $type;
            $shortcode['name'] = '';
            $shortcode['is_default'] = false;
            $id = 0;
        }

        $data = [
            'action'     => $action,
            'id'         => $id,
            'shortcode'  => $shortcode,
            'type'       => $type,
            'page_url'   => admin_url('admin.php?page=mypco-locations'),
            'is_default' => !empty($shortcode['is_default']),
        ];

        $this->load_template('settings-page', $data);
    }

    // =========================================================================
    // Form Handlers
    // =========================================================================

    /**
     * Handle shortcode save (create or update).
     */
    public function handle_save_shortcode() {
        if (!isset($_POST['mypco_save_shortcode'])) {
            return;
        }

        check_admin_referer('mypco_save_shortcode');

        if (!current_user_can('manage_options')) {
            return;
        }

        $id = isset($_POST['shortcode_id']) ? absint($_POST['shortcode_id']) : 0;
        $type = isset($_POST['shortcode_type']) ? sanitize_text_field($_POST['shortcode_type']) : 'next_sunday';

        if (!in_array($type, ['next_sunday', 'sunday_list'])) {
            $type = 'next_sunday';
        }

        // Build sanitized settings
        $settings = [
            'type'             => $type,
            'name'             => isset($_POST['shortcode_name']) ? sanitize_text_field($_POST['shortcode_name']) : '',
            'event_name'       => isset($_POST['event_name']) ? sanitize_text_field($_POST['event_name']) : 'Sunday Gathering',
            'show_time'        => isset($_POST['show_time']),
            'show_address'     => isset($_POST['show_address']),
            'empty_message'    => isset($_POST['empty_message']) ? sanitize_text_field($_POST['empty_message']) : '',
            'custom_class'     => isset($_POST['custom_class']) ? sanitize_html_class($_POST['custom_class']) : '',
            'primary_color'    => isset($_POST['primary_color']) ? sanitize_hex_color($_POST['primary_color']) : '#333333',
            'text_color'       => isset($_POST['text_color']) ? sanitize_hex_color($_POST['text_color']) : '#333333',
            'background_color' => isset($_POST['background_color']) ? sanitize_hex_color($_POST['background_color']) : '#ffffff',
            'border_radius'    => isset($_POST['border_radius']) ? absint($_POST['border_radius']) : 8,
            'date_format'      => isset($_POST['date_format']) ? sanitize_text_field($_POST['date_format']) : 'l, F j, Y',
            'time_format'      => isset($_POST['time_format']) ? sanitize_text_field($_POST['time_format']) : 'g:i a',
        ];

        // Type-specific settings
        if ($type === 'next_sunday') {
            $settings['layout_style'] = isset($_POST['layout_style']) && in_array($_POST['layout_style'], ['card', 'minimal', 'banner'])
                ? sanitize_text_field($_POST['layout_style']) : 'card';
            $settings['show_title'] = isset($_POST['show_title']);
            $settings['show_map'] = isset($_POST['show_map']);
            $settings['map_height'] = isset($_POST['map_height']) ? absint($_POST['map_height']) : 200;
        } else {
            $settings['count'] = isset($_POST['count']) ? sanitize_text_field($_POST['count']) : 'auto';
        }

        // Preserve is_default flag
        if ($id > 0) {
            $existing = $this->get_shortcode($id);
            if ($existing && !empty($existing['is_default'])) {
                $settings['is_default'] = true;
            }
        }

        $saved_id = $this->save_shortcode($id, $settings);

        // Clear cache so new settings take effect
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_mypco_locations%'");

        wp_redirect(admin_url('admin.php?page=mypco-locations&settings_saved=1'));
        exit;
    }

    /**
     * Handle shortcode deletion.
     */
    public function handle_delete_shortcode() {
        if (!isset($_GET['action']) || $_GET['action'] !== 'delete') {
            return;
        }

        if (!isset($_GET['page']) || $_GET['page'] !== 'mypco-locations') {
            return;
        }

        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        if ($id === 0) {
            return;
        }

        check_admin_referer('mypco_delete_shortcode_' . $id);

        if (!current_user_can('manage_options')) {
            return;
        }

        $this->delete_shortcode($id);

        wp_redirect(admin_url('admin.php?page=mypco-locations&deleted=1'));
        exit;
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

        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_mypco_locations%'");

        wp_redirect(admin_url('admin.php?page=mypco-locations&cache_cleared=1'));
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
