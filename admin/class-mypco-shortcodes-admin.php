<?php
/**
 * Shortcodes Admin
 *
 * Centralized shortcode management for all modules.
 * Provides a separate admin page for creating, editing, and managing
 * shortcode instances across all plugin modules.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MyPCO_Shortcodes_Admin {

    private $loader;
    private $api_model;

    /**
     * Option key for storing shortcode configurations.
     */
    const OPTION_KEY = 'mypco_shortcodes';

    public function __construct($loader, $api_model) {
        $this->loader = $loader;
        $this->api_model = $api_model;
    }

    /**
     * Initialize admin functionality.
     */
    public function init() {
        $this->loader->add_action('admin_menu', $this, 'add_menu_page');
        $this->loader->add_action('admin_enqueue_scripts', $this, 'enqueue_admin_assets');
        $this->loader->add_action('admin_init', $this, 'handle_save_shortcode');
        $this->loader->add_action('admin_init', $this, 'handle_delete_shortcode');
        $this->loader->add_action('admin_init', $this, 'handle_bulk_action');
    }

    // =========================================================================
    // Shortcode Type Registry
    // =========================================================================

    /**
     * Get all available shortcode types organized by module.
     *
     * Each module defines its shortcode types, default settings,
     * and the form fields available for configuration.
     *
     * @return array Shortcode type definitions keyed by type slug.
     */
    public static function get_shortcode_types() {
        $types = [
            'mypco_calendar' => [
                'module'      => 'calendar',
                'module_name' => 'Calendar',
                'name'        => 'Calendar',
                'description' => 'Display Planning Center calendar events with list, month, or gallery views.',
                'tag'         => 'mypco_calendar',
                'defaults'    => [
                    'description'      => '',
                    'count'            => 100,
                    'view'             => 'list',
                    'custom_class'     => '',
                    'primary_color'    => '#333333',
                    'text_color'       => '#333333',
                    'background_color' => '#ffffff',
                    'border_radius'    => 8,
                ],
                'fields' => [
                    [
                        'key'         => 'count',
                        'label'       => 'Number of Events',
                        'type'        => 'number',
                        'min'         => 1,
                        'max'         => 100,
                        'description' => 'Maximum number of events to fetch from Planning Center.',
                    ],
                    [
                        'key'         => 'view',
                        'label'       => 'Default View',
                        'type'        => 'select',
                        'options'     => [
                            'list'    => 'List - Chronological event list',
                            'month'   => 'Month - Calendar grid view',
                            'gallery' => 'Gallery - Card-based image layout',
                        ],
                        'description' => 'The default view when the calendar loads.',
                    ],
                ],
            ],
            'mypco_groups' => [
                'module'      => 'groups',
                'module_name' => 'Groups',
                'name'        => 'Groups',
                'description' => 'Display Planning Center groups with filtering options.',
                'tag'         => 'mypco_groups',
                'defaults'    => [
                    'description'      => '',
                    'count'            => 10,
                    'campus'           => '',
                    'custom_class'     => '',
                    'primary_color'    => '#333333',
                    'text_color'       => '#333333',
                    'background_color' => '#ffffff',
                    'border_radius'    => 8,
                ],
                'fields' => [
                    [
                        'key'         => 'count',
                        'label'       => 'Number of Groups',
                        'type'        => 'number',
                        'min'         => 1,
                        'max'         => 100,
                        'description' => 'Maximum number of groups to display.',
                    ],
                    [
                        'key'         => 'campus',
                        'label'       => 'Campus Filter',
                        'type'        => 'text',
                        'description' => 'Filter groups by campus name. Leave blank to show all campuses.',
                    ],
                ],
            ],
            'mypco_payment_form' => [
                'module'      => 'signups',
                'module_name' => 'Signups',
                'name'        => 'Payment Form',
                'description' => 'Display a Stripe payment form for event registrations.',
                'tag'         => 'mypco_payment_form',
                'defaults'    => [
                    'description'      => '',
                    'custom_class'     => '',
                    'primary_color'    => '#333333',
                    'text_color'       => '#333333',
                    'background_color' => '#ffffff',
                    'border_radius'    => 8,
                ],
                'fields' => [],
            ],
            'mypco_next_sunday' => [
                'module'      => 'locations',
                'module_name' => 'Locations',
                'name'        => 'Next Sunday',
                'description' => 'Show the next upcoming Sunday gathering event.',
                'tag'         => 'mypco_next_sunday',
                'defaults'    => [
                    'description'      => '',
                    'event_name'       => 'Sunday Gathering',
                    'layout_style'     => 'card',
                    'show_title'       => true,
                    'show_map'         => true,
                    'map_height'       => 200,
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
                ],
                'fields' => [
                    [
                        'key'         => 'event_name',
                        'label'       => 'Event Name Filter',
                        'type'        => 'text',
                        'description' => 'Enter the event name as it appears in Planning Center. Events containing this text will be displayed.',
                    ],
                    [
                        'key'         => 'layout_style',
                        'label'       => 'Layout Style',
                        'type'        => 'select',
                        'options'     => [
                            'card'    => 'Card - Boxed layout with shadow',
                            'minimal' => 'Minimal - Clean, no border',
                            'banner'  => 'Banner - Full width with background',
                        ],
                    ],
                    [
                        'key'   => 'show_title',
                        'label' => 'Show Event Title',
                        'type'  => 'checkbox',
                        'description' => 'Display the event title (e.g., "Sunday Gathering").',
                    ],
                    [
                        'key'   => 'show_map',
                        'label' => 'Show Map',
                        'type'  => 'checkbox',
                        'description' => 'Display an embedded Google Map below the location.',
                    ],
                    [
                        'key'   => 'map_height',
                        'label' => 'Map Height',
                        'type'  => 'number',
                        'min'   => 100,
                        'max'   => 500,
                        'step'  => 10,
                        'after' => 'px',
                    ],
                    [
                        'key'   => 'show_time',
                        'label' => 'Show Event Time',
                        'type'  => 'checkbox',
                    ],
                    [
                        'key'   => 'show_address',
                        'label' => 'Show Location Address',
                        'type'  => 'checkbox',
                    ],
                    [
                        'key'         => 'empty_message',
                        'label'       => 'Empty State Message',
                        'type'        => 'text',
                        'placeholder' => 'No upcoming Sunday gatherings found.',
                        'description' => 'Message shown when no events are found. Leave blank for the default.',
                    ],
                    [
                        'key'     => 'date_format',
                        'label'   => 'Date Format',
                        'type'    => 'select',
                        'options' => [
                            'D, M j, Y'  => 'Sun, Feb 2, 2026',
                            'l, F j, Y'  => 'Sunday, February 2, 2026',
                            'l, M j, Y'  => 'Sunday, Feb 2, 2026',
                            'F j, Y'     => 'February 2, 2026',
                            'M j, Y'     => 'Feb 2, 2026',
                            'm/d/Y'      => '02/02/2026',
                        ],
                    ],
                    [
                        'key'     => 'time_format',
                        'label'   => 'Time Format',
                        'type'    => 'select',
                        'options' => [
                            'g:i a' => '9:30 am',
                            'g:i A' => '9:30 AM',
                            'H:i'   => '09:30',
                        ],
                    ],
                ],
            ],
            'mypco_sunday_list' => [
                'module'      => 'locations',
                'module_name' => 'Locations',
                'name'        => 'Sunday List',
                'description' => 'List multiple upcoming Sunday gathering events.',
                'tag'         => 'mypco_sunday_list',
                'defaults'    => [
                    'description'      => '',
                    'event_name'       => 'Sunday Gathering',
                    'count'            => 'auto',
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
                ],
                'fields' => [
                    [
                        'key'         => 'event_name',
                        'label'       => 'Event Name Filter',
                        'type'        => 'text',
                        'description' => 'Enter the event name as it appears in Planning Center. Events containing this text will be displayed.',
                    ],
                    [
                        'key'     => 'count',
                        'label'   => 'Number of Sundays',
                        'type'    => 'select',
                        'options' => array_merge(
                            ['auto' => 'Auto (4 weeks, or 5 if month has 5 Sundays)'],
                            array_combine(range(1, 12), array_map(function($n) {
                                return $n . ($n === 1 ? ' Sunday' : ' Sundays');
                            }, range(1, 12)))
                        ),
                    ],
                    [
                        'key'   => 'show_time',
                        'label' => 'Show Event Time',
                        'type'  => 'checkbox',
                    ],
                    [
                        'key'   => 'show_address',
                        'label' => 'Show Location Address',
                        'type'  => 'checkbox',
                    ],
                    [
                        'key'         => 'empty_message',
                        'label'       => 'Empty State Message',
                        'type'        => 'text',
                        'placeholder' => 'No upcoming Sunday gatherings found.',
                        'description' => 'Message shown when no events are found. Leave blank for the default.',
                    ],
                    [
                        'key'     => 'date_format',
                        'label'   => 'Date Format',
                        'type'    => 'select',
                        'options' => [
                            'D, M j, Y'  => 'Sun, Feb 2, 2026',
                            'l, F j, Y'  => 'Sunday, February 2, 2026',
                            'l, M j, Y'  => 'Sunday, Feb 2, 2026',
                            'F j, Y'     => 'February 2, 2026',
                            'M j, Y'     => 'Feb 2, 2026',
                            'm/d/Y'      => '02/02/2026',
                        ],
                    ],
                    [
                        'key'     => 'time_format',
                        'label'   => 'Time Format',
                        'type'    => 'select',
                        'options' => [
                            'g:i a' => '9:30 am',
                            'g:i A' => '9:30 AM',
                            'H:i'   => '09:30',
                        ],
                    ],
                ],
            ],
        ];

        /**
         * Allow modules and add-ons to register their own shortcode types.
         *
         * @param array $types Shortcode type definitions.
         */
        return apply_filters('mypco_shortcode_types', $types);
    }

    /**
     * Get a single shortcode type definition.
     *
     * @param string $type_slug Shortcode type slug.
     * @return array|null Type definition or null.
     */
    public static function get_shortcode_type($type_slug) {
        $types = self::get_shortcode_types();
        return isset($types[$type_slug]) ? $types[$type_slug] : null;
    }

    /**
     * Get available modules (unique list from shortcode types).
     *
     * @return array Module key => name pairs.
     */
    public static function get_available_modules() {
        $types = self::get_shortcode_types();
        $modules = [];

        foreach ($types as $type) {
            $modules[$type['module']] = $type['module_name'];
        }

        return $modules;
    }

    /**
     * Get shortcode types for a specific module.
     *
     * @param string $module_key Module key.
     * @return array Filtered shortcode types.
     */
    public static function get_types_for_module($module_key) {
        $types = self::get_shortcode_types();

        return array_filter($types, function($type) use ($module_key) {
            return $type['module'] === $module_key;
        });
    }

    // =========================================================================
    // Shortcode Configuration CRUD
    // =========================================================================

    /**
     * Get all shortcode configurations.
     *
     * @return array Associative array of shortcode configs keyed by ID.
     */
    public function get_shortcodes() {
        $shortcodes = get_option(self::OPTION_KEY, []);

        if (!is_array($shortcodes)) {
            $shortcodes = [];
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
     * @return bool True if deleted, false if not found.
     */
    public function delete_shortcode($id) {
        $shortcodes = $this->get_shortcodes();

        if (!isset($shortcodes[$id])) {
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
     * Get settings for a shortcode by ID (static, for use by module public classes).
     *
     * @param int    $id        Shortcode ID.
     * @param string $type_slug Fallback shortcode type.
     * @return array Shortcode settings.
     */
    public static function get_shortcode_settings($id, $type_slug) {
        $shortcodes = get_option(self::OPTION_KEY, []);

        if (isset($shortcodes[$id])) {
            return $shortcodes[$id];
        }

        // Fallback to defaults for the type
        $type = self::get_shortcode_type($type_slug);
        if ($type) {
            return $type['defaults'];
        }

        return [];
    }

    // =========================================================================
    // Admin Menu & Assets
    // =========================================================================

    /**
     * Add the Shortcodes menu item.
     */
    public function add_menu_page() {
        add_submenu_page(
            'mypco-dashboard',
            __('Shortcodes', 'mypco-online'),
            __('Shortcodes', 'mypco-online'),
            'manage_options',
            'mypco-shortcodes',
            [$this, 'render_page']
        );
    }

    /**
     * Enqueue admin assets.
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'mypco-shortcodes') === false) {
            return;
        }

        wp_enqueue_style(
            'mypco-shortcodes-admin',
            MYPCO_PLUGIN_URL . 'admin/assets/css/mypco-shortcodes-admin.css',
            [],
            MYPCO_VERSION
        );
    }

    // =========================================================================
    // Page Rendering
    // =========================================================================

    /**
     * Render the page (routes to list or edit view).
     */
    public function render_page() {
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
     * Render the list view.
     */
    private function render_list_page() {
        $all_shortcodes = $this->get_shortcodes();
        $types = self::get_shortcode_types();
        $modules = self::get_available_modules();

        // Count by module
        $count_all = count($all_shortcodes);
        $counts_by_module = [];
        foreach ($modules as $mod_key => $mod_name) {
            $counts_by_module[$mod_key] = 0;
        }
        foreach ($all_shortcodes as $sc) {
            $sc_type_slug = $sc['shortcode_type'] ?? '';
            $sc_type = isset($types[$sc_type_slug]) ? $types[$sc_type_slug] : null;
            if ($sc_type) {
                $mod_key = $sc_type['module'];
                if (isset($counts_by_module[$mod_key])) {
                    $counts_by_module[$mod_key]++;
                }
            }
        }

        // Apply module filter
        $filter = isset($_GET['module_filter']) ? sanitize_text_field($_GET['module_filter']) : '';
        $shortcodes = $all_shortcodes;

        if (!empty($filter) && isset($modules[$filter])) {
            $shortcodes = array_filter($all_shortcodes, function($sc) use ($types, $filter) {
                $sc_type_slug = $sc['shortcode_type'] ?? '';
                $sc_type = isset($types[$sc_type_slug]) ? $types[$sc_type_slug] : null;
                return $sc_type && $sc_type['module'] === $filter;
            });
        }

        $data = [
            'shortcodes'       => $shortcodes,
            'types'            => $types,
            'modules'          => $modules,
            'count_all'        => $count_all,
            'counts_by_module' => $counts_by_module,
            'current_filter'   => $filter,
            'settings_saved'   => isset($_GET['settings_saved']),
            'deleted'          => isset($_GET['deleted']),
            'bulk_deleted'     => isset($_GET['bulk_deleted']) ? absint($_GET['bulk_deleted']) : 0,
            'page_url'         => admin_url('admin.php?page=mypco-shortcodes'),
        ];

        $this->load_template('shortcodes-page', $data);
    }

    /**
     * Render the edit/new shortcode view.
     */
    private function render_edit_page() {
        $action = sanitize_text_field($_GET['action']);
        $types = self::get_shortcode_types();
        $page_url = admin_url('admin.php?page=mypco-shortcodes');

        if ($action === 'new') {
            // Two-panel builder — no specific type needed up front
            $data = [
                'action'   => 'new',
                'types'    => $types,
                'modules'  => self::get_available_modules(),
                'page_url' => $page_url,
            ];
            $this->load_template('shortcodes-page', $data);
            return;
        }

        // Edit existing shortcode
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        $shortcode = $this->get_shortcode($id);
        if (!$shortcode) {
            wp_redirect($page_url);
            exit;
        }
        $type_slug = $shortcode['shortcode_type'];
        $type_def = isset($types[$type_slug]) ? $types[$type_slug] : null;

        $data = [
            'action'    => 'edit',
            'id'        => $id,
            'shortcode' => $shortcode,
            'type_slug' => $type_slug,
            'type_def'  => $type_def,
            'types'     => $types,
            'page_url'  => $page_url,
        ];

        $this->load_template('shortcodes-page', $data);
    }

    // =========================================================================
    // Form Handlers
    // =========================================================================

    /**
     * Handle shortcode save (create or update).
     */
    public function handle_save_shortcode() {
        if (!isset($_POST['mypco_save_module_shortcode'])) {
            return;
        }

        check_admin_referer('mypco_save_module_shortcode');

        if (!current_user_can('manage_options')) {
            return;
        }

        $id = isset($_POST['shortcode_id']) ? absint($_POST['shortcode_id']) : 0;
        $type_slug = isset($_POST['shortcode_type']) ? sanitize_text_field($_POST['shortcode_type']) : '';

        $types = self::get_shortcode_types();
        if (!isset($types[$type_slug])) {
            wp_redirect(admin_url('admin.php?page=mypco-shortcodes'));
            exit;
        }

        $type_def = $types[$type_slug];

        // Build sanitized settings starting with the type
        $settings = [
            'shortcode_type' => $type_slug,
            'description'    => isset($_POST['shortcode_description']) ? sanitize_text_field($_POST['shortcode_description']) : '',
        ];

        // Process module-specific fields
        foreach ($type_def['fields'] as $field) {
            $key = $field['key'];
            switch ($field['type']) {
                case 'checkbox':
                    $settings[$key] = isset($_POST[$key]);
                    break;
                case 'number':
                    $settings[$key] = isset($_POST[$key]) ? absint($_POST[$key]) : ($type_def['defaults'][$key] ?? 0);
                    break;
                case 'select':
                    $valid_options = array_keys($field['options']);
                    $settings[$key] = (isset($_POST[$key]) && in_array($_POST[$key], $valid_options))
                        ? sanitize_text_field($_POST[$key])
                        : ($type_def['defaults'][$key] ?? '');
                    break;
                case 'text':
                default:
                    $settings[$key] = isset($_POST[$key]) ? sanitize_text_field($_POST[$key]) : ($type_def['defaults'][$key] ?? '');
                    break;
            }
        }

        // Process common styling fields
        $settings['custom_class']     = isset($_POST['custom_class']) ? sanitize_html_class($_POST['custom_class']) : '';
        $settings['primary_color']    = isset($_POST['primary_color']) ? sanitize_hex_color($_POST['primary_color']) : '#333333';
        $settings['text_color']       = isset($_POST['text_color']) ? sanitize_hex_color($_POST['text_color']) : '#333333';
        $settings['background_color'] = isset($_POST['background_color']) ? sanitize_hex_color($_POST['background_color']) : '#ffffff';
        $settings['border_radius']    = isset($_POST['border_radius']) ? absint($_POST['border_radius']) : 8;

        $this->save_shortcode($id, $settings);

        wp_redirect(admin_url('admin.php?page=mypco-shortcodes&settings_saved=1'));
        exit;
    }

    /**
     * Handle shortcode deletion.
     */
    public function handle_delete_shortcode() {
        if (!isset($_GET['action']) || $_GET['action'] !== 'delete') {
            return;
        }

        if (!isset($_GET['page']) || $_GET['page'] !== 'mypco-shortcodes') {
            return;
        }

        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        if ($id === 0) {
            return;
        }

        check_admin_referer('mypco_delete_module_shortcode_' . $id);

        if (!current_user_can('manage_options')) {
            return;
        }

        $this->delete_shortcode($id);

        wp_redirect(admin_url('admin.php?page=mypco-shortcodes&deleted=1'));
        exit;
    }

    /**
     * Handle bulk actions.
     */
    public function handle_bulk_action() {
        if (!isset($_POST['mypco_bulk_module_shortcodes'])) {
            return;
        }

        check_admin_referer('mypco_bulk_module_shortcodes');

        if (!current_user_can('manage_options')) {
            return;
        }

        $action = isset($_POST['bulk_action']) ? sanitize_text_field($_POST['bulk_action']) : '';
        $ids = isset($_POST['shortcode_ids']) ? array_map('absint', $_POST['shortcode_ids']) : [];

        if ($action !== 'trash' || empty($ids)) {
            wp_redirect(admin_url('admin.php?page=mypco-shortcodes'));
            exit;
        }

        $deleted = 0;
        foreach ($ids as $id) {
            if ($this->delete_shortcode($id)) {
                $deleted++;
            }
        }

        wp_redirect(admin_url('admin.php?page=mypco-shortcodes&bulk_deleted=' . $deleted));
        exit;
    }

    /**
     * Load a template file.
     */
    private function load_template($template_name, $data = []) {
        extract($data);
        $template_path = MYPCO_PLUGIN_DIR . 'admin/templates/' . $template_name . '.php';

        if (file_exists($template_path)) {
            include $template_path;
        }
    }
}
