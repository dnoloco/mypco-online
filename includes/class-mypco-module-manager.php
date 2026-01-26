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
        foreach ($this->modules as $key => &$module) {
            if ($module['enabled'] && file_exists($module['file'])) {
                require_once $module['file'];

                if (class_exists($module['class'])) {
                    // Instantiate the module with dependencies
                    $module['instance'] = new $module['class']($this->loader, $this->api_model);
                    
                    // Call module's init method if it exists
                    if (method_exists($module['instance'], 'init')) {
                        $module['instance']->init();
                    }
                }
            }
        }
    }

    /**
     * Get all registered modules.
     */
    public function get_modules() {
        return $this->modules;
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
        return isset($this->modules[$key]) && $this->modules[$key]['enabled'];
    }

    /**
     * Enable a module.
     */
    public function enable_module($key) {
        if (isset($this->modules[$key])) {
            $this->modules[$key]['enabled'] = true;
            update_option("mypco_module_{$key}_enabled", true);
            return true;
        }
        return false;
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
