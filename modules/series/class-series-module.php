<?php
/**
 * Series Module - Main Orchestrator
 *
 * Coordinates the admin and public components of the Series module.
 * Provides message management with series, speakers, topics, and media links.
 */

require_once MYPCO_PLUGIN_DIR . 'includes/class-mypco-module-base.php';

class MyPCO_Series_Module extends MyPCO_Module_Base {

    protected $module_key = 'series';
    protected $module_name = 'Series';
    protected $module_description = 'Manage and display message archives with series, speakers, topics, and media.';

    /**
     * Module tier: freemium (basic display free, customization premium)
     */
    protected $tier = 'freemium';
    protected $requires_license = false;
    protected $min_license_tier = 'starter';

    /**
     * Features available in this module
     *
     * Free: Basic message management and default shortcode display
     * Premium: Featured message widget, custom series display, advanced filtering
     */
    protected $features = [
        'free' => [
            'manage_messages',
            'manage_speakers',
            'manage_series',
            'manage_topics',
            'basic_shortcode',
            'message_list_view'
        ],
        'premium' => [
            'featured_message',
            'series_display',
            'custom_templates',
            'advanced_filtering',
            'custom_css'
        ]
    ];

    /**
     * Admin component instance
     */
    private $admin;

    /**
     * Public component instance
     */
    private $public;

    /**
     * Initialize the Series module.
     */
    public function init() {
        // Register custom post types and taxonomies (must run on every request)
        $this->loader->add_action('init', $this, 'register_post_types');
        $this->loader->add_action('init', $this, 'register_taxonomies');

        // Load and initialize admin component
        if (is_admin()) {
            $this->load_admin_component();
        }

        // Load and initialize public component (always loaded for shortcodes)
        $this->load_public_component();
    }

    /**
     * Register the Message and Speaker custom post types.
     */
    public function register_post_types() {
        // Message CPT
        $message_labels = [
            'name'                  => __('Messages', 'mypco-online'),
            'singular_name'         => __('Message', 'mypco-online'),
            'menu_name'             => __('Messages', 'mypco-online'),
            'name_admin_bar'        => __('Message', 'mypco-online'),
            'add_new'               => __('Add New', 'mypco-online'),
            'add_new_item'          => __('Add New Message', 'mypco-online'),
            'new_item'              => __('New Message', 'mypco-online'),
            'edit_item'             => __('Edit Message', 'mypco-online'),
            'view_item'             => __('View Message', 'mypco-online'),
            'all_items'             => __('All Messages', 'mypco-online'),
            'search_items'          => __('Search Messages', 'mypco-online'),
            'not_found'             => __('No messages found.', 'mypco-online'),
            'not_found_in_trash'    => __('No messages found in Trash.', 'mypco-online'),
        ];

        register_post_type('mypco_message', [
            'labels'             => $message_labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => ['slug' => 'messages'],
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 26,
            'menu_icon'          => 'dashicons-microphone',
            'supports'           => ['title', 'editor', 'thumbnail'],
            'show_in_rest'       => true,
        ]);

        // Speaker CPT
        $speaker_labels = [
            'name'                  => __('Speakers', 'mypco-online'),
            'singular_name'         => __('Speaker', 'mypco-online'),
            'menu_name'             => __('Speakers', 'mypco-online'),
            'name_admin_bar'        => __('Speaker', 'mypco-online'),
            'add_new'               => __('Add New', 'mypco-online'),
            'add_new_item'          => __('Add New Speaker', 'mypco-online'),
            'new_item'              => __('New Speaker', 'mypco-online'),
            'edit_item'             => __('Edit Speaker', 'mypco-online'),
            'view_item'             => __('View Speaker', 'mypco-online'),
            'all_items'             => __('All Speakers', 'mypco-online'),
            'search_items'          => __('Search Speakers', 'mypco-online'),
            'not_found'             => __('No speakers found.', 'mypco-online'),
            'not_found_in_trash'    => __('No speakers found in Trash.', 'mypco-online'),
        ];

        register_post_type('mypco_speaker', [
            'labels'             => $speaker_labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => ['slug' => 'speakers'],
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 27,
            'menu_icon'          => 'dashicons-groups',
            'supports'           => ['title', 'editor', 'thumbnail'],
            'show_in_rest'       => true,
        ]);
    }

    /**
     * Register the Series and Service Type taxonomies.
     */
    public function register_taxonomies() {
        // Series taxonomy
        $series_labels = [
            'name'                       => __('Series', 'mypco-online'),
            'singular_name'              => __('Series', 'mypco-online'),
            'search_items'               => __('Search Series', 'mypco-online'),
            'all_items'                  => __('All Series', 'mypco-online'),
            'edit_item'                  => __('Edit Series', 'mypco-online'),
            'update_item'                => __('Update Series', 'mypco-online'),
            'add_new_item'               => __('Add New Series', 'mypco-online'),
            'new_item_name'              => __('New Series Name', 'mypco-online'),
            'menu_name'                  => __('Series', 'mypco-online'),
            'not_found'                  => __('No series found.', 'mypco-online'),
        ];

        register_taxonomy('mypco_series', ['mypco_message'], [
            'labels'            => $series_labels,
            'hierarchical'      => true,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'rewrite'           => ['slug' => 'series'],
        ]);

        // Service Type taxonomy
        $service_type_labels = [
            'name'                       => __('Service Types', 'mypco-online'),
            'singular_name'              => __('Service Type', 'mypco-online'),
            'search_items'               => __('Search Service Types', 'mypco-online'),
            'all_items'                  => __('All Service Types', 'mypco-online'),
            'edit_item'                  => __('Edit Service Type', 'mypco-online'),
            'update_item'                => __('Update Service Type', 'mypco-online'),
            'add_new_item'               => __('Add New Service Type', 'mypco-online'),
            'new_item_name'              => __('New Service Type Name', 'mypco-online'),
            'menu_name'                  => __('Service Types', 'mypco-online'),
            'not_found'                  => __('No service types found.', 'mypco-online'),
        ];

        register_taxonomy('mypco_service_type', ['mypco_message'], [
            'labels'            => $service_type_labels,
            'hierarchical'      => true,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'rewrite'           => ['slug' => 'service-types'],
        ]);

        // Flush rewrite rules once after activation so new CPT/taxonomy
        // URLs are recognised (the activator sets this transient).
        if (get_transient('mypco_flush_rewrite_rules')) {
            delete_transient('mypco_flush_rewrite_rules');
            flush_rewrite_rules();
        }

        // Also flush when CPT/taxonomy registrations change. Bump the
        // version string whenever slugs or post types are modified.
        $rewrite_version = 'mypco_rewrite_v1';
        if (get_option('mypco_rewrite_version') !== $rewrite_version) {
            flush_rewrite_rules();
            update_option('mypco_rewrite_version', $rewrite_version);
        }
    }

    /**
     * Load the admin component.
     */
    private function load_admin_component() {
        require_once $this->get_module_path('admin/class-series-admin.php');
        $this->admin = new MyPCO_Series_Admin($this->loader, $this->api_model);
        $this->admin->init();
    }

    /**
     * Load the public component.
     */
    private function load_public_component() {
        require_once $this->get_module_path('public/class-series-public.php');
        $this->public = new MyPCO_Series_Public($this->loader, $this->api_model);
        $this->public->init();
    }

    /**
     * Get path within this module.
     */
    private function get_module_path($relative_path) {
        return MYPCO_PLUGIN_DIR . 'modules/series/' . $relative_path;
    }
}
