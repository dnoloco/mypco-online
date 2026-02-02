<?php
/**
 * Locations Module - Main Orchestrator
 *
 * This class coordinates the admin and public components of the Locations module.
 * Provides shortcodes for displaying upcoming Sunday gathering locations.
 */

require_once MYPCO_PLUGIN_DIR . 'includes/class-mypco-module-base.php';

class MyPCO_Locations_Module extends MyPCO_Module_Base {

    protected $module_key = 'locations';
    protected $module_name = 'Locations';
    protected $module_description = 'Display upcoming Sunday gathering locations from Planning Center.';

    /**
     * Module tier: freemium (basic display free, custom styling premium)
     */
    protected $tier = 'freemium';
    protected $requires_license = false;
    protected $min_license_tier = 'starter';

    /**
     * Features available in this module
     *
     * Free: Basic shortcodes for next Sunday and Sunday list
     * Premium: Custom styling, multiple event types, widgets
     */
    protected $features = [
        'free' => [
            'next_sunday_shortcode',
            'sunday_list_shortcode',
            'google_maps_links'
        ],
        'premium' => [
            'custom_styling',
            'multiple_event_types',
            'location_widgets'
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
     * Initialize the Locations module.
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
        require_once $this->get_module_path('admin/class-locations-admin.php');
        $this->admin = new MyPCO_Locations_Admin($this->loader, $this->api_model);
        $this->admin->init();
    }

    /**
     * Load the public component.
     */
    private function load_public_component() {
        require_once $this->get_module_path('public/class-locations-public.php');
        $this->public = new MyPCO_Locations_Public($this->loader, $this->api_model);
        $this->public->init();
    }

    /**
     * Get path within this module.
     */
    private function get_module_path($relative_path) {
        return MYPCO_PLUGIN_DIR . 'modules/locations/' . $relative_path;
    }
}
