<?php
/**
 * Module Manager
 *
 * Handles registration, initialization, and management of plugin modules.
 */

class MyPCO_Module_Manager {

    /**
     * Array of registered modules.
     */
    private $modules = [];

    /**
     * The loader instance.
     */
    private $loader;

    /**
     * The API model instance.
     */
    private $api_model;

    /**
     * Initialize the module manager.
     */
    public function __construct($loader, $api_model) {
        $this->loader = $loader;
        $this->api_model = $api_model;
    }

    /**
     * Register a module.
     *
     * @param string $key Unique module identifier
     * @param array $config Module configuration
     */
    public function register_module($key, $config) {
        $defaults = [
            'name' => '',
            'description' => '',
            'type' => 'free', // 'free' or 'premium'
            'file' => '',
            'class' => '',
            'enabled' => false,
            'instance' => null
        ];

        $this->modules[$key] = wp_parse_args($config, $defaults);
    }

    /**
     * Initialize all enabled modules.
     */
    public function init_modules() {
        // 1. Fetch what is actually enabled in the database
        $active_settings = get_option('mypco_active_modules', []);

        // 2. Define the available modules
        $available = $this->get_modules();

        foreach ($available as $key => $data) {
            // Check if this specific key is enabled in our saved settings
            $is_enabled = isset($active_settings[$key]['enabled']) && $active_settings[$key]['enabled'] === true;

            $this->modules[$key] = [
                'enabled' => $is_enabled,
                'name'    => $data['name'],
                'file'    => MYPCO_PLUGIN_DIR . "modules/{$key}/admin/class-{$key}-admin.php",
                'class'   => 'MyPCO_' . ucfirst($key) . '_Admin'
            ];
        }
    }



    /**
     * Get all registered modules.
     */
    public function get_modules() {
        return [
            'services' => [
                'name'        => 'Services',
                'description' => 'Manage service plans, teams, and volunteer scheduling.',
                'premium'     => false // Free module
            ],
            'messages' => [
                'name'        => 'Messages',
                'description' => 'Send mass SMS via Clearstream integration.',
                'premium'     => true // Requires license
            ],
            'calendars' => [
                'name'        => 'Calendars',
                'description' => 'Sync Planning Center events with your website.',
                'premium'     => true
            ]
        ];
    }

    /**
     * Get a specific module.
     */
    public function get_module($key) {
        return isset($this->modules[$key]) ? $this->modules[$key] : null;
    }

    /**
     * Check if a module is enabled.
     */
    public function is_module_enabled($key) {
        // Check the actual database option saved by the AJAX toggle
        $active_modules = get_option('mypco_active_modules', []);
        return isset($active_modules[$key]['enabled']) && $active_modules[$key]['enabled'] === true;
    }

    /**
     * Enable a module.
     */
    public function enable_module($key) {
        $active_modules = get_option('mypco_active_modules', []);
        $active_modules[$key] = [
            'enabled' => true,
            'installed_at' => time()
        ];
        update_option('mypco_active_modules', $active_modules);
    }

    /**
     * Disable a module.
     */
    public function disable_module($key) {
        if (isset($this->modules[$key])) {
            $this->modules[$key]['enabled'] = false;
            update_option("mypco_module_{$key}_enabled", false);
            return true;
        }
        return false;
    }
}
