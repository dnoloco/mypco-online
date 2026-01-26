<?php
/**
 * Fired during plugin activation.
 */

class MyPCO_Activator {

    /**
     * Plugin activation tasks.
     */
    public static function activate() {
        // Set default options
        self::set_default_options();

        // Create database tables
        self::create_tables();

        // Generate webhook secret
        self::generate_webhook_secret();

        // Clear all PCO caches
        self::clear_caches();

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Set default plugin options.
     */
    private static function set_default_options() {
        // Enable free modules by default
        add_option('mypco_module_calendar_enabled', true);
        add_option('mypco_module_groups_enabled', true);

        // Premium modules enabled by default (can be changed to require license)
        add_option('mypco_module_services_enabled', true);
        add_option('mypco_module_messages_enabled', true);
        add_option('mypco_module_signups_enabled', true);

        // Set plugin version
        add_option('mypco_version', MYPCO_VERSION);
    }

    /**
     * Create database tables for signups and messages.
     */
    private static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Table for signup events
        $table_signups = $wpdb->prefix . 'mypco_signups';
        $sql_signups = "CREATE TABLE IF NOT EXISTS $table_signups (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            event_id varchar(255) NOT NULL,
            event_name varchar(255) NOT NULL,
            event_date date NOT NULL,
            google_form_id varchar(255) DEFAULT NULL,
            google_form_url text DEFAULT NULL,
            max_attendees int DEFAULT 0,
            payment_required tinyint(1) DEFAULT 0,
            payment_amount decimal(10,2) DEFAULT 0.00,
            payment_description text DEFAULT NULL,
            allow_partial_payment tinyint(1) DEFAULT 0,
            minimum_payment decimal(10,2) DEFAULT 0.00,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // Table for registrations
        $table_registrations = $wpdb->prefix . 'mypco_registrations';
        $sql_registrations = "CREATE TABLE IF NOT EXISTS $table_registrations (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            signup_id mediumint(9) NOT NULL,
            first_name varchar(100) NOT NULL,
            last_name varchar(100) NOT NULL,
            email varchar(100) NOT NULL,
            phone varchar(20) DEFAULT NULL,
            registration_data text DEFAULT NULL,
            payment_status varchar(20) DEFAULT 'pending',
            payment_amount decimal(10,2) DEFAULT 0.00,
            payment_date datetime DEFAULT NULL,
            stripe_payment_intent_id varchar(255) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY signup_id (signup_id)
        ) $charset_collate;";

        // Table for Clearstream message log
        $table_clearstream = $wpdb->prefix . 'mypco_clearstream_log';
        $sql_clearstream = "CREATE TABLE IF NOT EXISTS $table_clearstream (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            sender_name varchar(100) DEFAULT NULL,
            sender_id mediumint(9) DEFAULT NULL,
            recipient_count int DEFAULT 0,
            recipient_names text DEFAULT NULL,
            message_body text NOT NULL,
            status varchar(20) DEFAULT 'sent',
            scheduled_at datetime DEFAULT NULL,
            sent_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_signups);
        dbDelta($sql_registrations);
        dbDelta($sql_clearstream);
    }

    /**
     * Generate Google Forms webhook secret.
     */
    private static function generate_webhook_secret() {
        if (empty(get_option('mypco_google_forms_secret'))) {
            $secret = bin2hex(random_bytes(32));
            update_option('mypco_google_forms_secret', $secret);
        }
    }

    /**
     * Clear all PCO-related transients.
     */
    private static function clear_caches() {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_mypco_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_timeout_mypco_%'");
        // Also clear old pco_ transients if they exist
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_pco_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_timeout_pco_%'");
    }
}
