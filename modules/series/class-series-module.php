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
        // Load and initialize admin component
        if (is_admin()) {
            $this->load_admin_component();
        }

        // Load and initialize public component (always loaded for shortcodes)
        $this->load_public_component();
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
