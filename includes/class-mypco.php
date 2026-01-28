<?php
/**
 * The core plugin class.
 */

class MyPCO {

    protected $loader;
    protected $plugin_name;
    protected $version;
    protected $api_model;
    protected $modules = [];
    protected $modules_ui;

    public function __construct() {
        $this->version = MYPCO_VERSION;
        $this->plugin_name = 'mypco-online';

        $this->load_dependencies();
        $this->set_locale();
        $this->init_api_model();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    private function load_dependencies() {
        require_once MYPCO_PLUGIN_DIR . 'includes/class-mypco-loader.php';
        require_once MYPCO_PLUGIN_DIR . 'includes/class-mypco-i18n.php';
        require_once MYPCO_PLUGIN_DIR . 'includes/class-mypco-credentials-manager.php';
        require_once MYPCO_PLUGIN_DIR . 'includes/class-mypco-module-manager.php';
        // Load the Module Manager logic
        require_once MYPCO_PLUGIN_DIR . 'includes/class-mypco-module-manager.php';
        // Load the new Modules UI Controller
        require_once MYPCO_PLUGIN_DIR . 'modules/class-mypco-modules.php';
        require_once MYPCO_PLUGIN_DIR . 'includes/class-mypco-api-model.php';
        require_once MYPCO_PLUGIN_DIR . 'admin/class-mypco-admin.php';
        require_once MYPCO_PLUGIN_DIR . 'admin/class-mypco-settings-page.php';
        require_once MYPCO_PLUGIN_DIR . 'public/class-mypco-public.php';

        $this->loader = new MyPCO_Loader();
    }

    private function set_locale() {
        $plugin_i18n = new MyPCO_i18n();
        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }

    private function init_api_model() {
        $credentials = MyPCO_Credentials_Manager::get_pco_credentials();

        if (empty($credentials['client_id']) || empty($credentials['secret_key'])) {
            return;
        }

        $timezone = get_option('timezone_string') ?: 'America/Chicago';
        // Initialize the model and store it in the class property
        $this->api_model = new MyPCO_API_Model($credentials['client_id'], $credentials['secret_key'], $timezone);
    }

    private function load_modules() {
        $module_manager = new MyPCO_Module_Manager($this->loader, $this->api_model);
        $module_manager->init_modules();

        // Initialize UI Controller
        $modules_ui = new MyPCO_Modules($this->loader, $this->api_model);
        $modules_ui->init(); // <--- THIS must run to register wp_ajax_mypco_toggle_module

        // --- START DEBUG ---
        $status = $module_manager->is_module_enabled('services') ? 'ENABLED' : 'DISABLED';
        add_action('admin_notices', function() use ($status) {
            echo '<div class="notice notice-info"><p>DEBUG: Services Module is currently: <strong>' . $status . '</strong></p></div>';

            if (defined('MYPCO_PLUGIN_DIR')) {
                $path = MYPCO_PLUGIN_DIR . 'modules/services/admin/class-services-admin.php';
                $exists = file_exists($path) ? 'FOUND' : 'NOT FOUND';
                echo '<div class="notice notice-info"><p>DEBUG: File Path (' . $path . ') is: <strong>' . $exists . '</strong></p></div>';
            }
        });
        // --- END DEBUG ---

        $this->modules = $module_manager->get_modules();

        // ... your existing module loading logic ...
        if ( $module_manager->is_module_enabled('services') ) {
            require_once MYPCO_PLUGIN_DIR . 'modules/services/admin/class-services-admin.php';
            $services_admin = new MyPCO_Services_Admin($this->loader, $this->api_model);
            $services_admin->init();
        }
    }

    private function define_admin_hooks() {
        // 1. Initialize the main Admin class
        // Note: I added $this->loader and $this->api_model so it can pass them to the Modules UI
        $plugin_admin = new MyPCO_Admin($this->plugin_name, $this->version, $this->loader, $this->api_model);

        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_admin_menu');

        // 2. Initialize the API Settings Page
        $plugin_settings = new MyPCO_Settings_Page($this->plugin_name, $this->version, $this->api_model);
        $this->loader->add_action('admin_menu', $plugin_settings, 'add_settings_menu');
        $this->loader->add_action('admin_init', $plugin_settings, 'handle_settings_save');

        // 3. IMPORTANT: Run the Module Loader logic
        // This starts the Modules UI and any active feature modules (Services, etc.)
        $this->load_modules();
    }

    private function define_public_hooks() {
        $plugin_public = new MyPCO_Public($this->plugin_name, $this->version);
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
    }

    public function run() { $this->loader->run(); }
}
