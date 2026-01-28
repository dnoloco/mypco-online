<?php
/**
 * Plugin Name: Planning Center All-in-One Integration
 * Description: Integrates cached feeds from PCO APIs (Calendar, Groups, etc.) with custom styling options.
 * Version: 1.5
 * Author: David Dean
 * License: GPL2
 */

defined('ABSPATH') || exit;

// Define plugin paths
define('PCO_AIO_PATH', plugin_dir_path(__FILE__));
define('PCO_AIO_URL', plugin_dir_url(__FILE__));
define('PCO_AIO_INCLUDES', PCO_AIO_PATH . 'includes/');

// --- 0. SAFE FILE LOADING ---

// 1. Load Classes (These must exist)
require_once PCO_AIO_INCLUDES . 'pco-api-model.php';
require_once PCO_AIO_INCLUDES . 'pco-shortcodes.php';
require_once PCO_AIO_INCLUDES . 'pco-admin.php';

// 2. Load New Classes for Signups & Registrations
if (file_exists(PCO_AIO_INCLUDES . 'pco-signups.php')) {
    require_once PCO_AIO_INCLUDES . 'pco-signups.php';
}
if (file_exists(PCO_AIO_INCLUDES . 'google-forms-webhook.php')) {
    require_once PCO_AIO_INCLUDES . 'google-forms-webhook.php';
}
if (file_exists(PCO_AIO_INCLUDES . 'stripe-payment-handler.php')) {
    require_once PCO_AIO_INCLUDES . 'stripe-payment-handler.php';
}

// 2. Load Config SAFELY (Prevents Fatal Error if file is missing)
$pco_config_file = PCO_AIO_PATH . 'config.php';
if (file_exists($pco_config_file)) {
    require_once $pco_config_file;
} else {
    // Initialize variables to avoid "Undefined Variable" warnings later
    $pco_client_id = null;
    $pco_secret_key = null;
}

// --- 1. HOOKS: ACTIVATION, DEACTIVATION, UNINSTALL ---

function pco_aio_activate() {
    // Double check class existence before calling static method
    if (class_exists('pco_api_model')) {
        pco_api_model::clear_all_cache();
    }

    // Create signup tables
    if (class_exists('pco_signups')) {
        pco_signups::create_tables();
    }

    // Generate Google Forms webhook secret if not exists
    if (empty(get_option('pco_google_forms_secret'))) {
        $secret = bin2hex(random_bytes(32));
        update_option('pco_google_forms_secret', $secret);
    }
}
register_activation_hook(__FILE__, 'pco_aio_activate');

function pco_aio_deactivate() {
    // No specific actions needed.
}
register_deactivation_hook(__FILE__, 'pco_aio_deactivate');

function pco_aio_uninstall() {
    if (!current_user_can('activate_plugins')) return;
    // Load model manually just in case
    if (file_exists(PCO_AIO_INCLUDES . 'pco-api-model.php')) {
        require_once PCO_AIO_INCLUDES . 'pco-api-model.php';
        if (class_exists('pco_api_model')) {
            pco_api_model::clear_all_cache();
        }
    }
}
register_uninstall_hook(__FILE__, 'pco_aio_uninstall');

// --- 2. ASSETS (CSS & JS) ---

function pco_aio_enqueue_assets() {
    if (!is_admin()) {
        $css_file = PCO_AIO_URL . 'assets/pco-styles.css';
        $js_file  = PCO_AIO_URL . 'assets/pco-scripts.js';

        wp_enqueue_style('pco-styles', $css_file, [], '1.0');
        wp_enqueue_script('pco-scripts', $js_file, [], '1.0', true);
    }
}
add_action('wp_enqueue_scripts', 'pco_aio_enqueue_assets');

// --- 3. MAIN INITIALIZATION ---

function pco_aio_init() {
    global $pco_client_id, $pco_secret_key;

    // Safety: If Config is missing, show admin error and STOP.
    if (empty($pco_client_id) || empty($pco_secret_key)) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error is-dismissible"><p><strong>PCO Integrator Error:</strong> <code>config.php</code> is missing or empty. Please create this file in the plugin folder with your credentials.</p></div>';
        });
        // Do not instantiate the plugin functionality if keys are missing
        return;
    }

    // Safety: Ensure Classes Loaded
    if (!class_exists('pco_api_model') || !class_exists('pco_shortcodes') || !class_exists('pco_admin')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error is-dismissible"><p><strong>PCO Integrator Error:</strong> Core class files are missing from the /includes directory.</p></div>';
        });
        return;
    }

    // Instantiate main classes
    $pco_model = new pco_api_model($pco_client_id, $pco_secret_key);
    new pco_shortcodes($pco_model);

    // Initialize signups handler (if class exists)
    $signups_handler = null;
    if (class_exists('pco_signups')) {
        $signups_handler = new pco_signups($pco_model);
    }

    // Initialize Stripe handler (if class exists)
    $stripe_handler = null;
    if (class_exists('stripe_payment_handler')) {
        $stripe_handler = new stripe_payment_handler();
    }

    // Initialize Google Forms webhook handler (if class exists)
    $google_forms_webhook = null;
    if (class_exists('google_forms_webhook')) {
        $google_forms_webhook = new google_forms_webhook();

        // Connect handlers if they exist
        if ($signups_handler) {
            $google_forms_webhook->set_signups_handler($signups_handler);
        }
        if ($stripe_handler) {
            $google_forms_webhook->set_stripe_handler($stripe_handler);
        }
    }

    // Create admin and set handlers
    $pco_admin = new pco_admin($pco_model);

    if ($google_forms_webhook) {
        $pco_admin->set_webhook_handler($google_forms_webhook);
    }
    if ($signups_handler) {
        $pco_admin->set_signups_handler($signups_handler);
    }
    if ($stripe_handler) {
        $pco_admin->set_stripe_handler($stripe_handler);
    }
}
add_action('init', 'pco_aio_init');