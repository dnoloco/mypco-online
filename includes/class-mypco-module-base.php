<?php
/**
 * Base Module Class
 *
 * All modules should extend this class to maintain consistency.
 */

abstract class MyPCO_Module_Base {

    /**
     * The loader instance.
     */
    protected $loader;

    /**
     * The API model instance.
     */
    protected $api_model;

    /**
     * Module key/identifier.
     */
    protected $module_key;

    /**
     * Module display name.
     */
    protected $module_name;

    /**
     * Initialize the module.
     */
    public function __construct($loader, $api_model) {
        $this->loader = $loader;
        $this->api_model = $api_model;
    }

    /**
     * Initialize module functionality.
     * Override this in child classes to set up hooks and shortcodes.
     */
    abstract public function init();

    /**
     * Register a shortcode for this module.
     */
    protected function register_shortcode($tag, $callback) {
        add_shortcode($tag, [$this, $callback]);
    }

    /**
     * Add an admin page for this module.
     */
    protected function add_admin_page($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback) {
        $this->loader->add_action('admin_menu', $this, function() use ($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback) {
            add_submenu_page(
                $parent_slug,
                $page_title,
                $menu_title,
                $capability,
                $menu_slug,
                [$this, $callback]
            );
        });
    }

    /**
     * Enqueue module-specific styles.
     */
    protected function enqueue_style($handle, $file, $dependencies = [], $version = null) {
        $version = $version ?: MYPCO_VERSION;
        wp_enqueue_style($handle, $file, $dependencies, $version);
    }

    /**
     * Enqueue module-specific scripts.
     */
    protected function enqueue_script($handle, $file, $dependencies = [], $version = null, $in_footer = true) {
        $version = $version ?: MYPCO_VERSION;
        wp_enqueue_script($handle, $file, $dependencies, $version, $in_footer);
    }

    /**
     * Get data with caching using the API model.
     */
    protected function get_cached_data($app_domain, $endpoint_path, $params, $transient_key, $expiration = null) {
        if (!$this->api_model) {
            return ['error' => 'API model not initialized'];
        }

        return $this->api_model->get_data_with_caching($app_domain, $endpoint_path, $params, $transient_key, $expiration);
    }

    /**
     * Render a template file.
     */
    protected function render_template($template_name, $variables = []) {
        extract($variables);
        
        $template_path = MYPCO_PLUGIN_DIR . "modules/{$this->module_key}/templates/{$template_name}.php";
        
        if (file_exists($template_path)) {
            include $template_path;
        }
    }

    /**
     * Get module key.
     */
    public function get_module_key() {
        return $this->module_key;
    }

    /**
     * Get module name.
     */
    public function get_module_name() {
        return $this->module_name;
    }
}
