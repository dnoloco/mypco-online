<?php
/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks. Also maintains the unique identifier of this
 * plugin as well as the current version of the plugin.
 */

class MyPCO {

    /**
     * The loader that's responsible for maintaining and registering all hooks.
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     */
    protected $version;

    /**
     * The PCO API model instance.
     */
    protected $api_model;

    /**
     * Array of registered modules.
     */
    protected $modules = [];

    /**
     * Define the core functionality of the plugin.
     */
    public function __construct() {
        $this->version = MYPCO_VERSION;
        $this->plugin_name = 'mypco-online';

        $this->load_dependencies();
        $this->set_locale();
        $this->init_api_model();
        $this->load_modules();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     */
    private function load_dependencies() {
        // Loader class
        require_once MYPCO_PLUGIN_DIR . 'includes/class-mypco-loader.php';
        
        // Internationalization
        require_once MYPCO_PLUGIN_DIR . 'includes/class-mypco-i18n.php';
        
        // Module manager
        require_once MYPCO_PLUGIN_DIR . 'includes/class-mypco-module-manager.php';
        
        // API Model (your existing model with minor updates)
        require_once MYPCO_PLUGIN_DIR . 'includes/class-mypco-api-model.php';
        
        // Admin class
        require_once MYPCO_PLUGIN_DIR . 'admin/class-mypco-admin.php';
        
        // Public class
        require_once MYPCO_PLUGIN_DIR . 'public/class-mypco-public.php';

        $this->loader = new MyPCO_Loader();
    }

    /**
     * Define the locale for this plugin for internationalization.
     */
    private function set_locale() {
        $plugin_i18n = new MyPCO_i18n();
        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }

    /**
     * Initialize the PCO API Model.
     */
    private function init_api_model() {
        // Load config file safely
        $config_file = MYPCO_PLUGIN_DIR . 'config.php';
        $pco_client_id = null;
        $pco_secret_key = null;

        if (file_exists($config_file)) {
            require_once $config_file;
        }

        // Check if credentials are available
        if (empty($pco_client_id) || empty($pco_secret_key)) {
            // Show admin notice
            $this->loader->add_action('admin_notices', $this, 'credentials_missing_notice');
            return;
        }

        // Initialize API model
        $timezone = get_option('timezone_string') ?: 'America/Chicago';
        $this->api_model = new MyPCO_API_Model($pco_client_id, $pco_secret_key, $timezone);
    }

    /**
     * Load and initialize all modules.
     */
    private function load_modules() {
        if (!$this->api_model) {
            return; // Don't load modules if API isn't configured
        }

        $module_manager = new MyPCO_Module_Manager($this->loader, $this->api_model);

        // Register core (free) modules
        $module_manager->register_module('calendar', [
            'name' => 'Calendar',
            'description' => 'Display PCO Calendar events with multiple views',
            'type' => 'free',
            'file' => MYPCO_PLUGIN_DIR . 'modules/calendar/class-calendar-module.php',
            'class' => 'MyPCO_Calendar_Module',
            'enabled' => true
        ]);

        $module_manager->register_module('groups', [
            'name' => 'Groups',
            'description' => 'Display PCO Groups',
            'type' => 'free',
            'file' => MYPCO_PLUGIN_DIR . 'modules/groups/class-groups-module.php',
            'class' => 'MyPCO_Groups_Module',
            'enabled' => true
        ]);

        // Register premium modules
        $module_manager->register_module('services', [
            'name' => 'Services',
            'description' => 'Manage service plans and teams',
            'type' => 'premium',
            'file' => MYPCO_PLUGIN_DIR . 'modules/services/class-services-module.php',
            'class' => 'MyPCO_Services_Module',
            'enabled' => $this->is_module_licensed('services')
        ]);

        $module_manager->register_module('messages', [
            'name' => 'Messages',
            'description' => 'Send text messages via Clearstream',
            'type' => 'premium',
            'file' => MYPCO_PLUGIN_DIR . 'modules/messages/class-messages-module.php',
            'class' => 'MyPCO_Messages_Module',
            'enabled' => $this->is_module_licensed('messages')
        ]);

        // Initialize all enabled modules
        $module_manager->init_modules();

        $this->modules = $module_manager->get_modules();
    }

    /**
     * Check if a module is licensed (placeholder for future licensing system).
     */
    private function is_module_licensed($module_key) {
        // For now, return true to enable all modules during development
        // Later, this will check license keys/subscriptions
        return get_option("mypco_license_{$module_key}", true);
    }

    /**
     * Register all admin-specific hooks.
     */
    private function define_admin_hooks() {
        $plugin_admin = new MyPCO_Admin($this->get_plugin_name(), $this->get_version(), $this->modules);

        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_admin_menu');
    }

    /**
     * Register all public-facing hooks.
     */
    private function define_public_hooks() {
        $plugin_public = new MyPCO_Public($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
    }

    /**
     * Display notice when credentials are missing.
     */
    public function credentials_missing_notice() {
        ?>
        <div class="notice notice-error is-dismissible">
            <p><strong>MyPCO Online:</strong> PCO API credentials are missing. Please create a <code>config.php</code> file in the plugin directory with your credentials.</p>
        </div>
        <?php
    }

    /**
     * Run the loader to execute all of the hooks.
     */
    public function run() {
        $this->loader->run();
    }

    /**
     * The name of the plugin.
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * The reference to the class that orchestrates the hooks.
     */
    public function get_loader() {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin.
     */
    public function get_version() {
        return $this->version;
    }

    /**
     * Get the API model instance.
     */
    public function get_api_model() {
        return $this->api_model;
    }
}
