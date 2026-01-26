<?php
/**
 * Signups Module - Main Orchestrator
 *
 * This module handles event signups with Google Forms integration and Stripe payments.
 * 
 * Features:
 * - Create and manage event signups
 * - Google Forms webhook integration
 * - Stripe payment processing
 * - Registration management
 * - Waitlist functionality
 */

require_once MYPCO_PLUGIN_DIR . 'includes/class-mypco-module-base.php';

class MyPCO_Signups_Module extends MyPCO_Module_Base {

    protected $module_key = 'signups';
    protected $module_name = 'Signups';
    
    /**
     * Admin component instance
     */
    private $admin;
    
    /**
     * Public component instance
     */
    private $public;

    /**
     * Initialize the Signups module.
     */
    public function init() {
        // Load admin component
        if (is_admin()) {
            $this->load_admin_component();
        }
        
        // Load public component (for webhooks and payment processing)
        $this->load_public_component();
    }

    /**
     * Load the admin component.
     */
    private function load_admin_component() {
        require_once $this->get_module_path('admin/class-signups-admin.php');
        $this->admin = new MyPCO_Signups_Admin($this->loader, $this->api_model);
        $this->admin->init();
    }

    /**
     * Load the public component.
     */
    private function load_public_component() {
        require_once $this->get_module_path('public/class-signups-public.php');
        $this->public = new MyPCO_Signups_Public($this->loader, $this->api_model);
        $this->public->init();
    }

    /**
     * Get path within this module.
     */
    private function get_module_path($relative_path) {
        return MYPCO_PLUGIN_DIR . 'modules/signups/' . $relative_path;
    }
}
