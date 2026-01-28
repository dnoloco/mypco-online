<?php
// includes/pco-admin.php

class pco_admin {

    private $model;

    public function __construct(pco_api_model $model) {
        $this->model = $model;

        // Update database table if needed
        add_action('admin_init', [$this, 'maybe_update_clearstream_log_table']);

        // Hooks for menu pages
        add_action('admin_menu', [$this, 'add_admin_menu_page']);
        add_action('admin_init', [$this, 'settings_init']);

        // Handle Clearstream message sending
        add_action('admin_init', [$this, 'handle_clearstream_send']);

        // Handle message log bulk actions
        add_action('admin_init', [$this, 'handle_message_log_bulk_actions']);

        // Handle single message delete
        add_action('admin_init', [$this, 'handle_single_message_delete']);

        // AJAX handler for searching Clearstream subscribers
        add_action('wp_ajax_search_clearstream_subscribers', [$this, 'ajax_search_clearstream_subscribers']);

        // Keep Messages menu active when on compose page
        add_filter('parent_file', [$this, 'set_messages_menu_active']);
    }

    /**
     * Update database table to add status column
     */
    public static function update_clearstream_log_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'clearstream_log';

        // Check if status column exists
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM `{$table_name}` LIKE %s",
            'status'
        ));

        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `status` VARCHAR(20) DEFAULT 'sent' AFTER `message_body`");
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `scheduled_at` DATETIME NULL AFTER `status`");
        }
    }

    /**
     * Check and update clearstream_log table to add recipient_names column
     */
    public function maybe_update_clearstream_log_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'clearstream_log';

        // Check if recipient_names column exists
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM `{$table_name}` LIKE %s",
            'recipient_names'
        ));

        if (empty($column_exists)) {
            // Add recipient_names column after recipient_count
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `recipient_names` TEXT NULL AFTER `recipient_count`");
        }
    }

    /**
     * Check if current user can send Clearstream messages
     */
    private function user_can_send_clearstream() {
        // Editors and above can send
        return current_user_can('edit_pages');
    }

    /**
     * Keep Messages menu active when on compose page from message log
     */
    public function set_messages_menu_active($parent_file) {
        global $submenu_file;

        // Check if we're on the compose page coming from message log
        if (isset($_GET['page']) && $_GET['page'] === 'pco-services'
            && isset($_GET['view']) && $_GET['view'] === 'clearstream_compose'
            && isset($_GET['from']) && $_GET['from'] === 'messages') {
            $submenu_file = 'pco-message-log';
        }

        return $parent_file;
    }

    /**
     * Get PCO Person ID for current WordPress user
     */
    private function get_pco_person_id_for_current_user() {
        $current_user = wp_get_current_user();

        // Check if we have a cached person ID
        $cached_person_id = get_user_meta($current_user->ID, 'pco_person_id', true);
        if (!empty($cached_person_id)) {
            return $cached_person_id;
        }

        // Search for person by first and last name
        $first_name = $current_user->first_name;
        $last_name = $current_user->last_name;

        if (empty($first_name) && empty($last_name)) {
            // Try to split display name
            $name_parts = explode(' ', $current_user->display_name, 2);
            $first_name = $name_parts[0] ?? '';
            $last_name = $name_parts[1] ?? '';
        }

        if (empty($first_name) || empty($last_name)) {
            return null;
        }

        // Search PCO People for this person
        $search_query = trim($first_name . ' ' . $last_name);
        $search_params = [
            'where[search_name_or_email]' => $search_query,
            'per_page' => 5
        ];

        $search_key = 'pco_person_search_' . md5($search_query);
        $search_results = $this->model->get_data_with_caching('people', '/v2/people', $search_params, $search_key);

        if (isset($search_results['data']) && !empty($search_results['data'])) {
            // Try to find exact match
            foreach ($search_results['data'] as $person) {
                $p_first = strtolower($person['attributes']['first_name'] ?? '');
                $p_last = strtolower($person['attributes']['last_name'] ?? '');

                if ($p_first === strtolower($first_name) && $p_last === strtolower($last_name)) {
                    // Cache the person ID
                    update_user_meta($current_user->ID, 'pco_person_id', $person['id']);
                    return $person['id'];
                }
            }

            // If no exact match, use first result
            $person_id = $search_results['data'][0]['id'];
            update_user_meta($current_user->ID, 'pco_person_id', $person_id);
            return $person_id;
        }

        return null;
    }

    /**
     * Get allowed service types for current user based on PCO permissions
     */
    private function get_allowed_service_types_for_user() {
        // Admins see all service types
        if (current_user_can('manage_options')) {
            $response = $this->model->get_service_types();
            return $response['data'] ?? [];
        }

        $person_id = $this->get_pco_person_id_for_current_user();

        if (empty($person_id)) {
            // User not found in PCO - return empty array
            return [];
        }

        // Get all service types
        $all_types_response = $this->model->get_service_types();
        $all_types = $all_types_response['data'] ?? [];

        if (empty($all_types)) {
            return [];
        }

        $allowed_types = [];

        // For each service type, check if this person has any permissions
        foreach ($all_types as $service_type) {
            $type_id = $service_type['id'];

            // Check if person is a team member in any plans for this service type
            // Get recent plans for this service type
            $plans_response = $this->model->get_upcoming_plans($type_id, 1);
            $plans = $plans_response['data'] ?? [];

            if (!empty($plans)) {
                // Check if person is on any team for this service type
                // We'll check the first plan as a sample
                $sample_plan = $plans[0];
                $plan_id = $sample_plan['id'];

                $team_response = $this->model->get_plan_team_members($plan_id);
                $team_members = $team_response['data'] ?? [];

                foreach ($team_members as $tm) {
                    $tm_person_id = $tm['relationships']['person']['data']['id'] ?? null;

                    if ($tm_person_id == $person_id) {
                        // This user is on a team for this service type
                        $allowed_types[] = $service_type;
                        break;
                    }
                }
            }
        }

        // If no permissions found through team membership, return all types
        // (this is a fallback - PCO doesn't have a direct permissions API)
        if (empty($allowed_types)) {
            return $all_types;
        }

        return $allowed_types;
    }

    /**
     * Fetch subscribers from Clearstream API
     */
    private function get_clearstream_subscribers($search_query = '') {
        $api_token = get_option('clearstream_api_token');

        if (empty($api_token)) {
            return ['error' => 'API token not configured'];
        }

        $api_url = 'https://api.getclearstream.com/v1/subscribers';

        // Get more subscribers (up to 100 per page)
        $params = ['per_page' => 100];
        if (!empty($search_query)) {
            $params['query'] = $search_query;
        }

        $api_url .= '?' . http_build_query($params);

        $args = [
            'headers' => [
                'X-API-Key' => $api_token,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 15
        ];

        $response = wp_remote_get($api_url, $args);

        if (is_wp_error($response)) {
            return ['error' => $response->get_error_message()];
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        $data = json_decode($response_body, true);

        if ($response_code === 200) {
            if (isset($data['data'])) {
                return $data['data'];
            } elseif (isset($data['subscribers'])) {
                return $data['subscribers'];
            } elseif (is_array($data)) {
                return $data;
            }
        }

        return ['error' => 'Failed to fetch subscribers'];
    }

    /**
     * AJAX handler to search Clearstream subscribers
     */
    public function ajax_search_clearstream_subscribers() {
        check_ajax_referer('clearstream_search', 'nonce');

        if (!$this->user_can_send_clearstream()) {
            wp_send_json_error(['message' => 'Permission denied']);
            return;
        }

        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

        $subscribers = $this->get_clearstream_subscribers($search);

        if (isset($subscribers['error'])) {
            wp_send_json_error(['message' => $subscribers['error']]);
            return;
        }

        wp_send_json_success(['subscribers' => $subscribers]);
    }

    /**
     * Adds top-level and submenu pages.
     */
    public function add_admin_menu_page() {
        // Top-level menu
        add_menu_page(
            __('Service Plans', 'pco-aio'),
            __('PCO', 'pco-aio'),
            'edit_posts',
            'pco-services',
            [$this, 'render_services_page'],
            'dashicons-calendar-alt',
            30
        );

        // Submenu pages
        add_submenu_page(
            'pco-services',
            __('Service Plans', 'pco-aio'),
            __('Service Plans', 'pco-aio'),
            'edit_posts',
            'pco-services'
        );

        add_submenu_page(
            'pco-services',
            __('Team Reports', 'pco-aio'),
            __('Reports', 'pco-aio'),
            'edit_posts',
            'pco-reports',
            [$this, 'render_reports_page']
        );

        add_submenu_page(
            'pco-services',
            __('Messages', 'pco-aio'),
            __('Messages', 'pco-aio'),
            'edit_posts',
            'pco-message-log',
            [$this, 'render_clearstream_log']
        );

        add_submenu_page(
            'pco-services',
            __('Shortcode Usage', 'pco-aio'),
            __('Shortcodes', 'pco-aio'),
            'manage_options',
            'pco-shortcode-usage',
            [$this, 'render_shortcodes_page']
        );

        add_submenu_page(
            'pco-services',
            __('Permissions', 'pco-aio'),
            __('Permissions', 'pco-aio'),
            'delete_others_posts',
            'pco-permissions',
            [$this, 'render_permissions_page']
        );

        add_submenu_page(
            'pco-services',
            __('PCO Settings', 'pco-aio'),
            __('Settings', 'pco-aio'),
            'manage_options',
            'pco-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Initialize settings
     */
    public function settings_init() {
        // PCO API Settings
        register_setting('pco_settings_group', 'pco_client_id');
        register_setting('pco_settings_group', 'pco_secret_key');

        // Clearstream Settings
        register_setting('pco_settings_group', 'clearstream_api_token');
        register_setting('pco_settings_group', 'clearstream_message_header');

        // PCO API Section
        add_settings_section(
            'pco_api_section',
            'Planning Center Online API',
            function() {
                echo '<p>Enter your Planning Center Online API credentials. You can generate these from your PCO account settings.</p>';
            },
            'pco-settings'
        );

        add_settings_field(
            'pco_client_id',
            'Client ID',
            [$this, 'pco_client_id_callback'],
            'pco-settings',
            'pco_api_section'
        );

        add_settings_field(
            'pco_secret_key',
            'Secret Key',
            [$this, 'pco_secret_key_callback'],
            'pco-settings',
            'pco_api_section'
        );

        // Clearstream Section
        add_settings_section(
            'clearstream_section',
            'Clearstream API Configuration',
            function() {
                echo '<p>Enter your Clearstream API token and message header value.</p>';
            },
            'pco-settings'
        );

        add_settings_field(
            'clearstream_api_token',
            'API Token',
            [$this, 'clearstream_api_token_callback'],
            'pco-settings',
            'clearstream_section'
        );

        add_settings_field(
            'clearstream_message_header',
            'Message Header Value',
            [$this, 'clearstream_message_header_callback'],
            'pco-settings',
            'clearstream_section'
        );
    }

    // Settings field callbacks
    public function pco_client_id_callback() {
        $value = get_option('pco_client_id');
        echo '<input type="text" id="pco_client_id" name="pco_client_id" value="' . esc_attr($value) . '" class="regular-text" style="width: 400px;" />';
        echo '<p class="description">Your PCO Application ID (Client ID)</p>';
    }

    public function pco_secret_key_callback() {
        $value = get_option('pco_secret_key');
        echo '<input type="password" id="pco_secret_key" name="pco_secret_key" value="' . esc_attr($value) . '" class="regular-text" style="width: 400px;" />';
        echo '<p class="description">Your PCO Secret Key (keep this secure)</p>';
    }

    public function clearstream_api_token_callback() {
        $token = get_option('clearstream_api_token');
        echo '<input type="password" id="clearstream_api_token" name="clearstream_api_token" value="' . esc_attr($token) . '" class="regular-text" style="width: 400px;" />';
        echo '<p class="description">Your API Token (found in Clearstream settings)</p>';
    }

    public function clearstream_message_header_callback() {
        $header = get_option('clearstream_message_header');
        echo '<input type="text" id="clearstream_message_header" name="clearstream_message_header" value="' . esc_attr($header) . '" class="regular-text" style="width: 400px;" />';
        echo '<p class="description">The message header value that identifies your message source</p>';
    }

    /**
     * Unified Settings Page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) return;

        // Check PCO API connection
        $pco_connection = $this->check_pco_api_connection();

        // Check Clearstream (basic check - just verify token exists)
        $clearstream_token = get_option('clearstream_api_token');
        $clearstream_status = !empty($clearstream_token) ? 'configured' : 'not_configured';

        ?>
        <div class="wrap">
            <h1>PCO Integration Settings</h1>
            <?php
            if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {
                echo '<div class="notice notice-success is-dismissible"><p><strong>Settings saved.</strong></p></div>';
            }
            ?>

            <!-- Connection Status Cards -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                <!-- PCO Connection Status -->
                <div class="card">
                    <h2 style="margin-top: 0;">Planning Center API Status</h2>
                    <?php $this->render_connection_status_box($pco_connection); ?>
                </div>

                <!-- Clearstream Connection Status -->
                <div class="card">
                    <h2 style="margin-top: 0;">Clearstream API Status</h2>
                    <?php if ($clearstream_status === 'configured'): ?>
                        <div style='padding:10px; border-left: 4px solid green; background: #fff; box-shadow: 0 1px 1px rgba(0,0,0,0.1);'>
                            <strong>Status:</strong> API Token Configured
                        </div>
                    <?php else: ?>
                        <div style='padding:10px; border-left: 4px solid orange; background: #fff; box-shadow: 0 1px 1px rgba(0,0,0,0.1);'>
                            <strong>Status:</strong> Not Configured
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Settings Form -->
            <form method="post" action="options.php">
                <?php
                settings_fields('pco_settings_group');
                do_settings_sections('pco-settings');
                submit_button('Save Settings');
                ?>
            </form>

            <!-- CSS Customization Info -->
            <hr style="margin: 40px 0;">
            <div class="card">
                <h2>CSS Customization</h2>
                <p>To customize the appearance of calendar and other frontend elements, edit the CSS file located at:</p>
                <p><code><?php echo esc_html(PCO_AIO_PATH . 'assets/pco-styles.css'); ?></code></p>
                <p>Changes to this file will apply to all PCO shortcodes on your site.</p>
            </div>
        </div>
        <?php
    }

    /**
     * Check PCO API Connection
     */
    private function check_pco_api_connection() {
        $client_id = get_option('pco_client_id');
        $secret_key = get_option('pco_secret_key');

        if (empty($client_id) || empty($secret_key)) {
            return ['status' => 'error', 'message' => 'API credentials not configured'];
        }

        $key = 'pco_status_check';
        $response = $this->model->get_data_with_caching('services', '/v2/service_types', ['per_page' => 1], $key);
        delete_transient($key);

        if (isset($response['error'])) {
            return ['status' => 'error', 'message' => $response['error']];
        }
        if (!empty($response['data'])) {
            return ['status' => 'success', 'message' => 'Connection Successful'];
        }
        return ['status' => 'warning', 'message' => 'No data returned'];
    }

    private function render_connection_status_box($status) {
        $color = ($status['status'] == 'success') ? 'green' : 'red';
        echo "<div style='padding:10px; border-left: 4px solid $color; background: #fff; box-shadow: 0 1px 1px rgba(0,0,0,0.1);'>";
        echo "<strong>Status:</strong> " . esc_html($status['message']);
        echo "</div>";
    }

    /**
     * Main Controller for Services Page (Router).
     */
    public function render_services_page() {
        if (!current_user_can('edit_pages')) return;

        // Display success/error messages from Clearstream
        if (isset($_GET['message'])) {
            if ($_GET['message'] === 'sent' || $_GET['message'] === 'scheduled') {
                $success_data = get_transient('clearstream_success_message');
                if ($success_data) {
                    // Handle both old format (just count) and new format (array with status)
                    if (is_array($success_data)) {
                        $count = $success_data['count'];
                        $status = $success_data['status'];
                        $scheduled_at = $success_data['scheduled_at'] ?? null;

                        if ($status === 'scheduled' && $scheduled_at) {
                            $formatted_time = date('M j, Y \a\t g:i A', strtotime($scheduled_at));
                            echo '<div class="notice notice-success is-dismissible"><p><strong>Success!</strong> Message scheduled for ' . esc_html($formatted_time) . ' to ' . intval($count) . ' recipients via Clearstream.</p></div>';
                        } else {
                            echo '<div class="notice notice-success is-dismissible"><p><strong>Success!</strong> Message sent to ' . intval($count) . ' recipients via Clearstream.</p></div>';
                        }
                    } else {
                        // Old format - just a count
                        echo '<div class="notice notice-success is-dismissible"><p><strong>Success!</strong> Message sent to ' . intval($success_data) . ' recipients via Clearstream.</p></div>';
                    }
                    delete_transient('clearstream_success_message');
                }
            } elseif ($_GET['message'] === 'error') {
                $error = get_transient('clearstream_error_message');
                if ($error) {
                    echo '<div class="notice notice-error is-dismissible"><p><strong>Clearstream API Error (Code ' . intval($error['code']) . '):</strong> ' . esc_html($error['message']) . '</p></div>';
                    delete_transient('clearstream_error_message');
                }
            } elseif ($_GET['message'] === 'no_permission') {
                echo '<div class="notice notice-error is-dismissible"><p><strong>Permission Denied:</strong> You do not have permission to send Clearstream messages. Contact an administrator.</p></div>';
            }
        }

        $view = $_REQUEST['view'] ?? 'list';

        if ($view === 'plan_details' && isset($_REQUEST['plan_id'])) {
            $this->render_single_plan_view(sanitize_text_field($_REQUEST['plan_id']));
        } elseif ($view === 'clearstream_compose') {
            $this->render_clearstream_compose_page();
        } else {
            $this->render_services_list_view();
        }
    }

    /**
     * VIEW 1: The List of Plans (All Service Types) - WordPress Style
     */
    private function render_services_list_view() {
        // Get all service types
        $service_types_response = $this->model->get_service_types();
        $service_types = $service_types_response['data'] ?? [];

        if (empty($service_types)) {
            echo '<div class="wrap"><h1>Service Plans</h1><p>No service types found.</p></div>';
            return;
        }

        // Get filter parameters
        $filter_type = isset($_GET['filter_type']) ? sanitize_text_field($_GET['filter_type']) : 'all';
        $filter_month = isset($_GET['filter_month']) ? sanitize_text_field($_GET['filter_month']) : 'all';
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'date';
        $order = isset($_GET['order']) && $_GET['order'] === 'desc' ? 'desc' : 'asc';

        // Fetch plans from all service types
        $all_plans = [];
        $type_counts = ['all' => 0];
        $available_months = [];

        foreach ($service_types as $type) {
            $type_id = $type['id'];
            $type_name = $type['attributes']['name'] ?? 'Unknown';
            $default_service_time = $type['attributes']['default_service_time'] ?? '10:00:00';

            $type_counts[$type_id] = 0;

            // Fetch plans for this service type
            $plans_response = $this->model->get_upcoming_plans($type_id, 100);
            $plans = $plans_response['data'] ?? [];

            foreach ($plans as $plan) {
                $p_attr = $plan['attributes'];
                $plan_id = $plan['id'];

                // Get date/time
                $pco_datetime_string = $p_attr['sort_date'] ?? $p_attr['dates'] ?? null;
                if (empty($pco_datetime_string)) continue;

                // Determine service time
                $actual_service_time = $default_service_time;
                if (!empty($p_attr['times']) && is_array($p_attr['times'])) {
                    $time_data = $p_attr['times'][0] ?? null;
                    if (!empty($time_data['time'])) {
                        $actual_service_time = $time_data['time'];
                    }
                }

                // Extract date and combine with time
                $date_part = substr($pco_datetime_string, 0, 10);
                $combined_datetime_string = $date_part . ' ' . $actual_service_time;

                try {
                    $local_timezone = wp_timezone_string();
                    $tz_object = new DateTimeZone($local_timezone);
                    $plan_date = new DateTime($combined_datetime_string, $tz_object);

                    $date_str = $plan_date->format('D, M j, Y');
                    $time_str = $plan_date->format('g:i A');
                    $month_year = $plan_date->format('F Y'); // "January 2026"
                    $sort_date = $plan_date->format('Y-m-d H:i:s');

                    // Track available months
                    if (!in_array($month_year, $available_months)) {
                        $available_months[] = $month_year;
                    }

                } catch (Exception $e) {
                    continue;
                }

                $specific_plan_title = $p_attr['title'] ?? $p_attr['series_title'] ?? 'Untitled Plan';
                $series_title = $p_attr['series_title'] ?? '—';

                // Store plan data
                $all_plans[] = [
                    'plan_id' => $plan_id,
                    'title' => $specific_plan_title,
                    'series' => $series_title,
                    'date_str' => $date_str,
                    'time_str' => $time_str,
                    'month_year' => $month_year,
                    'sort_date' => $sort_date,
                    'type_id' => $type_id,
                    'type_name' => $type_name,
                    'pco_edit_link' => "https://services.planningcenteronline.com/plans/" . $plan_id
                ];

                $type_counts['all']++;
                $type_counts[$type_id]++;
            }
        }

        // Sort plans by date (default)
        usort($all_plans, function($a, $b) use ($orderby, $order) {
            if ($orderby === 'date') {
                $val_a = $a['sort_date'];
                $val_b = $b['sort_date'];
            } elseif ($orderby === 'type_name') {
                $val_a = $a['type_name'];
                $val_b = $b['type_name'];
            } elseif ($orderby === 'title') {
                $val_a = $a['title'];
                $val_b = $b['title'];
            } else {
                $val_a = $a['sort_date'];
                $val_b = $b['sort_date'];
            }

            $result = strcasecmp($val_a, $val_b);
            return $order === 'asc' ? $result : -$result;
        });

        // Helper function for sortable URLs
        function get_services_sort_url($column, $current_orderby, $current_order, $filter_type, $filter_month) {
            $new_order = ($current_orderby === $column && $current_order === 'asc') ? 'desc' : 'asc';
            $url = admin_url('admin.php?page=pco-services');
            $url = add_query_arg('orderby', $column, $url);
            $url = add_query_arg('order', $new_order, $url);
            if ($filter_type !== 'all') {
                $url = add_query_arg('filter_type', $filter_type, $url);
            }
            if ($filter_month !== 'all') {
                $url = add_query_arg('filter_month', $filter_month, $url);
            }
            return $url;
        }

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Service Plans</h1>
            <hr class="wp-header-end">

            <!-- SERVICE TYPE FILTER TABS -->
            <ul class="subsubsub">
                <li class="all">
                    <a href="<?php echo admin_url('admin.php?page=pco-services'); ?>" <?php echo $filter_type === 'all' ? 'class="current"' : ''; ?>>
                        All <span class="count">(<?php echo $type_counts['all']; ?>)</span>
                    </a>
                    <?php if (!empty($service_types)): ?> | <?php endif; ?>
                </li>
                <?php foreach ($service_types as $index => $type):
                    $type_id = $type['id'];
                    $type_name = $type['attributes']['name'] ?? 'Unknown';
                    $count = $type_counts[$type_id] ?? 0;
                    ?>
                    <li class="type-<?php echo esc_attr($type_id); ?>">
                        <a href="<?php echo add_query_arg('filter_type', $type_id, admin_url('admin.php?page=pco-services')); ?>"
                            <?php echo $filter_type === $type_id ? 'class="current"' : ''; ?>>
                            <?php echo esc_html($type_name); ?> <span class="count">(<?php echo $count; ?>)</span>
                        </a>
                        <?php if ($index < count($service_types) - 1): ?> | <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if (empty($all_plans)): ?>
                <p>No upcoming plans found.</p>
            <?php else: ?>

                <!-- FILTERS -->
                <div class="tablenav top">
                    <div class="alignleft actions">
                        <select name="filter_month" id="filter-month" onchange="window.location.href='<?php echo admin_url('admin.php?page=pco-services'); ?>' + (this.value !== 'all' ? '&filter_month=' + encodeURIComponent(this.value) : '') + '<?php echo $filter_type !== 'all' ? '&filter_type=' . $filter_type : ''; ?>' + '<?php echo '&orderby=' . $orderby . '&order=' . $order; ?>';">
                            <option value="all">All Dates</option>
                            <?php foreach ($available_months as $month): ?>
                                <option value="<?php echo esc_attr($month); ?>" <?php selected($filter_month, $month); ?>>
                                    <?php echo esc_html($month); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <table class="wp-list-table widefat fixed striped table-view-list posts">
                    <thead>
                    <tr>
                        <th class="manage-column sortable <?php echo $orderby === 'date' ? 'sorted' : ''; ?> <?php echo $orderby === 'date' ? $order : 'asc'; ?>" style="width: 180px;">
                            <a href="<?php echo esc_url(get_services_sort_url('date', $orderby, $order, $filter_type, $filter_month)); ?>">
                                <span>Date</span>
                                <span class="sorting-indicators">
                        <span class="sorting-indicator asc" aria-hidden="true"></span>
                        <span class="sorting-indicator desc" aria-hidden="true"></span>
                    </span>
                            </a>
                        </th>
                        <th class="manage-column column-title column-primary sortable <?php echo $orderby === 'title' ? 'sorted' : ''; ?> <?php echo $orderby === 'title' ? $order : 'asc'; ?>">
                            <a href="<?php echo esc_url(get_services_sort_url('title', $orderby, $order, $filter_type, $filter_month)); ?>">
                                <span>Title</span>
                                <span class="sorting-indicators">
                        <span class="sorting-indicator asc" aria-hidden="true"></span>
                        <span class="sorting-indicator desc" aria-hidden="true"></span>
                    </span>
                            </a>
                        </th>
                        <th class="manage-column sortable <?php echo $orderby === 'type_name' ? 'sorted' : ''; ?> <?php echo $orderby === 'type_name' ? $order : 'asc'; ?>" style="width: 200px;">
                            <a href="<?php echo esc_url(get_services_sort_url('type_name', $orderby, $order, $filter_type, $filter_month)); ?>">
                                <span>Service Type</span>
                                <span class="sorting-indicators">
                        <span class="sorting-indicator asc" aria-hidden="true"></span>
                        <span class="sorting-indicator desc" aria-hidden="true"></span>
                    </span>
                            </a>
                        </th>
                    </tr>
                    </thead>

                    <tbody>
                    <?php
                    $row_count = 0;
                    foreach ($all_plans as $plan):
                        // Apply filters
                        if ($filter_type !== 'all' && $filter_type !== $plan['type_id']) {
                            continue;
                        }
                        if ($filter_month !== 'all' && $filter_month !== $plan['month_year']) {
                            continue;
                        }

                        $row_count++;
                        $details_url = admin_url('admin.php?page=pco-services&view=plan_details&plan_id=' . $plan['plan_id']);
                        ?>
                        <tr>
                            <td data-colname="Date">
                                <?php echo esc_html($plan['date_str']); ?><br>
                                <small style="color: #666;"><?php echo esc_html($plan['time_str']); ?></small>
                            </td>
                            <td class="title column-title has-row-actions column-primary" data-colname="Title">
                                <strong>
                                    <a href="<?php echo esc_url($details_url); ?>" class="row-title">
                                        <?php echo esc_html($plan['title']); ?>
                                    </a>
                                </strong>
                                <div class="row-actions">
                        <span class="view">
                            <a href="<?php echo esc_url($details_url); ?>">View Details</a> |
                        </span>
                                    <span class="edit">
                            <a href="<?php echo esc_url($plan['pco_edit_link']); ?>" target="_blank">Edit in PCO</a>
                        </span>
                                </div>
                            </td>
                            <td data-colname="Service Type">
                                <?php echo esc_html($plan['type_name']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if ($row_count === 0): ?>
                        <tr>
                            <td colspan="3" style="text-align: center; padding: 20px;">
                                No plans match the selected filters.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>

            <?php endif; ?>
        </div>

        <style>
            .subsubsub { margin-bottom: 15px; }
            .tablenav { padding: 8px 0; margin-bottom: 10px; }
            .tablenav .actions { display: inline-block; }
            .tablenav select { max-width: 200px; }
        </style>
        <?php
    }

    /**
     * Custom comparison function to sort team members by response status.
     */
    private function sort_by_status($a, $b) {
        $order = ['C' => 1, 'U' => 2, 'D' => 3];
        $status_a = $a['status'] ?? 'N';
        $status_b = $b['status'] ?? 'N';

        $val_a = $order[$status_a] ?? 99;
        $val_b = $order[$status_b] ?? 99;

        return $val_a <=> $val_b;
    }

    /**
     * VIEW 2: Single Plan Details Page - WordPress Style with Sorting
     */
    private function render_single_plan_view($plan_id) {
        $response = $this->model->get_single_plan($plan_id);

        if (isset($response['error']) || empty($response['data'])) {
            echo '<div class="wrap"><h1>Error</h1><p>Plan not found or API error.</p></div>';
            return;
        }

        $plan = $response['data'];
        $p_attr = $plan['attributes'];

        // Define $default_time as a fallback
        $default_time = '10:00:00';
        $service_type_id = $plan['relationships']['service_type']['data']['id'] ?? null;

        if ($service_type_id) {
            $service_type_details = $this->model->get_single_service_type($service_type_id);
            $default_time = $service_type_details['attributes']['default_service_time'] ?? '10:00:00';
        }

        // Get the local timezone object
        $local_timezone_string = $this->model->get_timezone() ?? wp_timezone_string();
        try {
            $tz_object = new DateTimeZone($local_timezone_string);
        } catch (Exception $e) {
            $tz_object = new DateTimeZone('UTC');
        }

        // --- DATE LOGIC ---
        $pco_datetime_string = $p_attr['dates'] ?? null;
        $actual_service_time = $default_time;

        if (!empty($p_attr['times']) && is_array($p_attr['times'])) {
            $time_data = $p_attr['times'][0] ?? null;
            if (!empty($time_data['time'])) {
                $actual_service_time = $time_data['time'];
            }
        }

        $title = $p_attr['title'] ?? $p_attr['series_title'] ?? 'Untitled Plan';
        $series = $p_attr['series_title'] ?? '';

        $date_str = 'N/A';
        $time_str = 'N/A';
        $day_str = 'N/A';

        if (!empty($pco_datetime_string)) {
            try {
                $plan_date = new DateTime($pco_datetime_string);
                $plan_date->setTimezone($tz_object);

                list($hour, $minute, $second) = explode(':', $actual_service_time);
                $plan_date->setTime((int)$hour, (int)$minute, (int)$second);

                $day_str = $plan_date->format('D');
                $date_str = $plan_date->format('M j, Y');
                $time_str = $plan_date->format('g:ia');

            } catch (Exception $e) {
                $date_str = 'Date Error';
                $time_str = 'Time Error';
            }
        }

        // --- FETCH ALL TEAMS AND POSITIONS ---
        $teams_response = $this->model->get_all_teams();
        $team_name_map = [];

        if (!empty($teams_response['data'])) {
            foreach ($teams_response['data'] as $team_obj) {
                $id = $team_obj['id'];
                $name = $team_obj['attributes']['name'] ?? 'Unknown Team';
                $team_name_map[$id] = $name;
            }
        }

        $position_response = $this->model->get_team_positions($service_type_id);
        $position_name_map = [];

        if (!empty($position_response['data'])) {
            foreach ($position_response['data'] as $pos_obj) {
                $id = $pos_obj['id'];
                $name = $pos_obj['attributes']['name'] ?? 'Unknown Position';
                $position_name_map[$id] = $name;
            }
        }

        // --- FETCH AND PROCESS TEAM MEMBERS ---
        $all_members = [];
        $team_summary = [];
        $status_counts = ['all' => 0, 'C' => 0, 'U' => 0, 'D' => 0];

        $team_response = $this->model->get_plan_team_members($plan_id);
        $team_members_data = $team_response['data'] ?? [];

        // Process included TeamPosition data
        $included_positions = [];
        if (!empty($team_response['included'])) {
            foreach ($team_response['included'] as $included_item) {
                if ($included_item['type'] === 'TeamPosition') {
                    $included_positions[$included_item['id']] = $included_item['attributes']['name'] ?? '';
                }
            }
        }

        foreach ($team_members_data as $tm_obj) {
            $member = $tm_obj['attributes'];
            $team_id = $tm_obj['relationships']['team']['data']['id'] ?? null;
            $person_id = $tm_obj['relationships']['person']['data']['id'] ?? null;
            $team_name = $team_name_map[$team_id] ?? 'Unassigned Team';

            $name = $member['name'] ?? 'Unknown Person';
            $status = $member['status'] ?? 'N';

            $position_name = '';

            // Try multiple sources for position information (in priority order)

            // 1. Check team_position relationship from included data
            $team_position_id = $tm_obj['relationships']['team_position']['data']['id'] ?? null;
            if ($team_position_id && isset($included_positions[$team_position_id])) {
                $position_name = $included_positions[$team_position_id];
            }

            // 2. If not found, check if position is directly in team_member attributes
            if (empty($position_name)) {
                $position_name = $member['team_position_name'] ?? '';
            }

            // 3. If not found, check schedules
            if (empty($position_name) && $person_id) {
                $schedule_response = $this->model->get_person_schedules($person_id);
                $schedules = $schedule_response['data'] ?? [];

                foreach ($schedules as $schedule) {
                    $schedule_plan_id = $schedule['relationships']['plan']['data']['id'] ?? null;
                    if ($schedule_plan_id == $plan_id) {
                        $position_name = $schedule['attributes']['position_name'] ??
                            $schedule['attributes']['team_position_name'] ??
                            $schedule['attributes']['assignment_name'] ??
                            $schedule['attributes']['title'] ??
                            '';
                        break;
                    }
                }
            }

            // 4. If still not found, try to get from team_position map as fallback
            if (empty($position_name) && $team_position_id && isset($position_name_map[$team_position_id])) {
                $position_name = $position_name_map[$team_position_id];
            }

            if (!in_array($status, ['C', 'U', 'D'])) {
                continue;
            }

            // Extract last name for sorting
            $name_parts = explode(' ', $name);
            $last_name = end($name_parts);
            $first_name = count($name_parts) > 1 ? $name_parts[0] : '';

            $data = [
                'name' => $name,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'status' => $status,
                'person_id' => $person_id,
                'position' => $position_name,
                'team_name' => $team_name
            ];

            $all_members[] = $data;

            if (!isset($team_summary[$team_name])) {
                $team_summary[$team_name] = 0;
            }
            $team_summary[$team_name]++;

            // Count statuses
            $status_counts['all']++;
            if (isset($status_counts[$status])) {
                $status_counts[$status]++;
            }
        }

        ksort($team_summary);

        // Get filter and sort parameters
        $current_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'all';
        $current_team = isset($_GET['filter_team']) ? sanitize_text_field($_GET['filter_team']) : 'all';
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'last_name';
        $order = isset($_GET['order']) && $_GET['order'] === 'desc' ? 'desc' : 'asc';

        // Sort members - ALWAYS by status first (C > U > D), then by last name
        usort($all_members, function($a, $b) {
            // Define status priority: Accepted (C) = 1, Pending (U) = 2, Declined (D) = 3
            $status_priority = ['C' => 1, 'U' => 2, 'D' => 3];

            $status_a = $status_priority[$a['status']] ?? 99;
            $status_b = $status_priority[$b['status']] ?? 99;

            // First sort by status
            if ($status_a !== $status_b) {
                return $status_a <=> $status_b;
            }

            // Then sort by last name (case-insensitive)
            return strcasecmp($a['last_name'], $b['last_name']);
        });

        // Helper function for sortable column URLs
        function get_plan_sort_url($column, $current_orderby, $current_order, $plan_id, $current_status, $current_team) {
            $new_order = ($current_orderby === $column && $current_order === 'asc') ? 'desc' : 'asc';
            $url = admin_url('admin.php');
            $url = add_query_arg('page', 'pco-services', $url);
            $url = add_query_arg('view', 'plan_details', $url);
            $url = add_query_arg('plan_id', $plan_id, $url);
            $url = add_query_arg('orderby', $column, $url);
            $url = add_query_arg('order', $new_order, $url);
            if ($current_status !== 'all') {
                $url = add_query_arg('filter_status', $current_status, $url);
            }
            if ($current_team !== 'all') {
                $url = add_query_arg('filter_team', $current_team, $url);
            }
            return $url;
        }

        $back_url = admin_url('admin.php?page=pco-services');
        $compose_url = admin_url('admin.php?page=pco-services&view=clearstream_compose');
        ?>

        <div class="wrap">
            <!-- HEADER -->
            <h1 class="wp-heading-inline"><?php echo esc_html($title); ?></h1>
            <a href="<?php echo esc_url($back_url); ?>" class="page-title-action">Back to Services</a>
            <hr class="wp-header-end">

            <p style="font-size: 14px; color: #666; margin-top: 5px; margin-bottom: 25px; font-weight: 600;">
                <?php echo esc_html($day_str); ?>, <?php echo esc_html($date_str); ?> (<?php echo esc_html($time_str); ?>)
            </p>

            <!-- STATUS FILTER TABS -->
            <ul class="subsubsub">
                <li class="all">
                    <a href="<?php echo esc_url(add_query_arg(['page' => 'pco-services', 'view' => 'plan_details', 'plan_id' => $plan_id], admin_url('admin.php'))); ?>"
                        <?php echo $current_status === 'all' ? 'class="current"' : ''; ?>>
                        All <span class="count">(<?php echo $status_counts['all']; ?>)</span>
                    </a> |
                </li>
                <li class="accepted">
                    <a href="<?php echo esc_url(add_query_arg(['page' => 'pco-services', 'view' => 'plan_details', 'plan_id' => $plan_id, 'filter_status' => 'C'], admin_url('admin.php'))); ?>"
                        <?php echo $current_status === 'C' ? 'class="current"' : ''; ?>>
                        Accepted <span class="count">(<?php echo $status_counts['C']; ?>)</span>
                    </a> |
                </li>
                <li class="pending">
                    <a href="<?php echo esc_url(add_query_arg(['page' => 'pco-services', 'view' => 'plan_details', 'plan_id' => $plan_id, 'filter_status' => 'U'], admin_url('admin.php'))); ?>"
                        <?php echo $current_status === 'U' ? 'class="current"' : ''; ?>>
                        Pending <span class="count">(<?php echo $status_counts['U']; ?>)</span>
                    </a> |
                </li>
                <li class="declined">
                    <a href="<?php echo esc_url(add_query_arg(['page' => 'pco-services', 'view' => 'plan_details', 'plan_id' => $plan_id, 'filter_status' => 'D'], admin_url('admin.php'))); ?>"
                        <?php echo $current_status === 'D' ? 'class="current"' : ''; ?>>
                        Declined <span class="count">(<?php echo $status_counts['D']; ?>)</span>
                    </a>
                </li>
            </ul>

            <form method="POST" action="<?php echo esc_url($compose_url); ?>" id="team-members-form">
                <input type="hidden" name="plan_date" value="<?php echo esc_attr($day_str . ', ' . $date_str); ?>">
                <input type="hidden" name="plan_id" value="<?php echo esc_attr($plan_id); ?>">
                <input type="hidden" name="plan_title" value="<?php echo esc_attr($title); ?>">

                <!-- FILTERS -->
                <div class="tablenav top">
                    <div class="alignleft actions">
                        <select name="filter_team" id="filter-team" onchange="window.location.href='<?php echo admin_url('admin.php?page=pco-services&view=plan_details&plan_id=' . $plan_id); ?>' + (this.value !== 'all' ? '&filter_team=' + this.value : '') + '<?php echo $current_status !== 'all' ? '&filter_status=' . $current_status : ''; ?>' + '<?php echo '&orderby=' . $orderby . '&order=' . $order; ?>';">
                            <option value="all">All Teams</option>
                            <?php foreach ($team_summary as $team_name => $count): ?>
                                <option value="<?php echo esc_attr($team_name); ?>" <?php selected($current_team, $team_name); ?>>
                                    <?php echo esc_html($team_name); ?> (<?php echo $count; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="alignleft actions">
                        <button type="submit" class="button action">Send Text to Selected</button>
                    </div>
                </div>

                <?php if (empty($all_members)): ?>
                    <p>No team members assigned.</p>
                <?php else: ?>

                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                        <tr>
                            <td class="manage-column column-cb check-column"><input type="checkbox" id="cb-select-all"></td>
                            <th class="manage-column column-primary sortable <?php echo $orderby === 'name' ? 'sorted' : ''; ?> <?php echo $orderby === 'name' ? $order : 'asc'; ?>">
                                <a href="<?php echo esc_url(get_plan_sort_url('name', $orderby, $order, $plan_id, $current_status, $current_team)); ?>">
                                    <span>Name</span>
                                    <span class="sorting-indicators">
                                        <span class="sorting-indicator asc" aria-hidden="true"></span>
                                        <span class="sorting-indicator desc" aria-hidden="true"></span>
                                    </span>
                                </a>
                            </th>
                            <th class="manage-column sortable <?php echo $orderby === 'team_name' ? 'sorted' : ''; ?> <?php echo $orderby === 'team_name' ? $order : 'asc'; ?>">
                                <a href="<?php echo esc_url(get_plan_sort_url('team_name', $orderby, $order, $plan_id, $current_status, $current_team)); ?>">
                                    <span>Team</span>
                                    <span class="sorting-indicators">
                                        <span class="sorting-indicator asc" aria-hidden="true"></span>
                                        <span class="sorting-indicator desc" aria-hidden="true"></span>
                                    </span>
                                </a>
                            </th>
                            <th class="manage-column sortable <?php echo $orderby === 'position' ? 'sorted' : ''; ?> <?php echo $orderby === 'position' ? $order : 'asc'; ?>">
                                <a href="<?php echo esc_url(get_plan_sort_url('position', $orderby, $order, $plan_id, $current_status, $current_team)); ?>">
                                    <span>Position</span>
                                    <span class="sorting-indicators">
                                        <span class="sorting-indicator asc" aria-hidden="true"></span>
                                        <span class="sorting-indicator desc" aria-hidden="true"></span>
                                    </span>
                                </a>
                            </th>
                            <th class="manage-column sortable <?php echo $orderby === 'status' ? 'sorted' : ''; ?> <?php echo $orderby === 'status' ? $order : 'asc'; ?>">
                                <a href="<?php echo esc_url(get_plan_sort_url('status', $orderby, $order, $plan_id, $current_status, $current_team)); ?>">
                                    <span>Status</span>
                                    <span class="sorting-indicators">
                                        <span class="sorting-indicator asc" aria-hidden="true"></span>
                                        <span class="sorting-indicator desc" aria-hidden="true"></span>
                                    </span>
                                </a>
                            </th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php
                        $row_count = 0;
                        foreach ($all_members as $member):
                            // Apply filters
                            if ($current_status !== 'all' && $current_status !== $member['status']) {
                                continue;
                            }
                            if ($current_team !== 'all' && $current_team !== $member['team_name']) {
                                continue;
                            }

                            $status_code = $member['status'];
                            $status_label = 'Unknown';
                            $status_color = '#666';

                            if ($status_code === 'C') {
                                $status_label = 'Accepted';
                                $status_color = '#46b450';
                            } elseif ($status_code === 'D') {
                                $status_label = 'Declined';
                                $status_color = '#dc3232';
                            } elseif ($status_code === 'U') {
                                $status_label = 'Pending';
                                $status_color = '#e5a500';
                            }

                            $row_count++;
                            ?>
                            <tr>
                                <th scope="row" class="check-column">
                                    <?php if ($status_code === 'C' || $status_code === 'U'): ?>
                                        <input type="checkbox" name="recipients[]" value="<?php echo esc_attr($member['person_id'] . '|' . $member['name']); ?>" class="team-member-checkbox">
                                    <?php endif; ?>
                                </th>
                                <td class="column-primary" data-colname="Name">
                                    <strong><?php echo esc_html($member['name']); ?></strong>
                                </td>
                                <td data-colname="Team"><?php echo esc_html($member['team_name']); ?></td>
                                <td data-colname="Position"><?php echo esc_html($member['position']); ?></td>
                                <td data-colname="Status">
                                    <span style="color: <?php echo $status_color; ?>; font-weight: bold;">
                                        <?php echo esc_html($status_label); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if ($row_count === 0): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 20px;">
                                    No team members match the selected filters.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>

                    <div class="tablenav bottom">
                        <div class="alignleft actions">
                            <button type="submit" class="button action">Send Text to Selected</button>
                        </div>
                    </div>

                <?php endif; ?>
            </form>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Select all checkbox functionality
                const selectAllCheckbox = document.getElementById('cb-select-all');
                const memberCheckboxes = document.querySelectorAll('.team-member-checkbox');

                if (selectAllCheckbox) {
                    selectAllCheckbox.addEventListener('change', function() {
                        memberCheckboxes.forEach(checkbox => {
                            checkbox.checked = this.checked;
                        });
                    });
                }

                // Update select all based on individual checkboxes
                memberCheckboxes.forEach(checkbox => {
                    checkbox.addEventListener('change', function() {
                        const allChecked = Array.from(memberCheckboxes).every(cb => cb.checked);
                        const someChecked = Array.from(memberCheckboxes).some(cb => cb.checked);

                        if (selectAllCheckbox) {
                            selectAllCheckbox.checked = allChecked;
                            selectAllCheckbox.indeterminate = someChecked && !allChecked;
                        }
                    });
                });
            });
        </script>

        <style>
            .subsubsub { margin-bottom: 15px; }
            .tablenav { padding: 8px 0; }
            .tablenav.top { margin-bottom: 10px; }
            .tablenav.bottom { margin-top: 10px; }
            .tablenav .actions { display: inline-block; }
            .tablenav select { max-width: 200px; }
            .column-cb { width: 2.2em; }
        </style>
        <?php
    }

    /**
     * VIEW 3: Clearstream Compose Page.
     */
    public function render_clearstream_compose_page() {
        // Check permission
        if (!$this->user_can_send_clearstream()) {
            wp_redirect(admin_url('admin.php?page=pco-services&message=no_permission'));
            exit;
        }

        $clearstream_api_token = get_option('clearstream_api_token');
        $message_header_value = get_option('clearstream_message_header');

        $recipient_ids_names = isset($_POST['recipients']) ? (array)$_POST['recipients'] : [];
        $plan_date = isset($_POST['plan_date']) ? $_POST['plan_date'] : '';
        $plan_id = isset($_POST['plan_id']) ? $_POST['plan_id'] : '';
        $plan_title = isset($_POST['plan_title']) ? $_POST['plan_title'] : '';

        // Check if this is a new message (not from a plan)
        $is_new_message = isset($_GET['new']) && $_GET['new'] == '1';

        $recipients_data = [];

        if (!empty($recipient_ids_names)) {
            foreach (array_unique($recipient_ids_names) as $id_name_string) {
                list($person_id, $person_name) = explode('|', $id_name_string, 2);

                if (empty($person_id) || !is_numeric($person_id)) continue;

                $primary_phone = null;
                $phone_response = $this->model->get_person_phone_numbers($person_id);
                $phones = $phone_response['data'] ?? [];

                if (!empty($phones)) {
                    foreach ($phones as $phone_obj) {
                        $p_attr = $phone_obj['attributes'];

                        $is_primary = $p_attr['primary'] ?? false;
                        $location = $p_attr['location'] ?? '';

                        if ($is_primary && $location === 'Mobile') {
                            $primary_phone = $p_attr['number'];
                            break;
                        }
                    }
                }

                if ($primary_phone) {
                    $recipients_data[] = [
                        'id' => $person_id,
                        'name' => $person_name,
                        'phone' => $primary_phone,
                        'source' => 'pco'
                    ];
                }
            }
        }

        $phone_numbers_list = array_column($recipients_data, 'phone');
        $names_list = array_column($recipients_data, 'name');
        $recipient_count = count($phone_numbers_list);

        $short_date = date('D, M j, Y', strtotime($plan_date));
        $display_date = $short_date !== false ? $short_date : $plan_date;

        ?>
        <div class="wrap">
            <h1>Compose Text Message</h1>
            <hr>

            <?php if (empty($clearstream_api_token)): ?>
                <div class="notice notice-error"><p><strong>Error:</strong> Clearstream API Token is missing. Please configure it in <a href="<?php echo esc_url(admin_url('admin.php?page=pco-settings')); ?>">Settings</a>.</p></div>
            <?php endif; ?>

            <?php if (empty($message_header_value)): ?>
                <div class="notice notice-error"><p><strong>Error:</strong> Message Header Value is missing. Please configure it in <a href="<?php echo esc_url(admin_url('admin.php?page=pco-settings')); ?>">Settings</a>.</p></div>
            <?php endif; ?>

            <form method="POST" action="" id="clearstream-compose-form">
                <input type="hidden" name="action" value="send_clearstream">
                <input type="hidden" name="plan_id" value="<?php echo esc_attr($plan_id); ?>">
                <input type="hidden" name="plan_title" value="<?php echo esc_attr($plan_title); ?>">
                <input type="hidden" name="plan_date" value="<?php echo esc_attr($plan_date); ?>">
                <input type="hidden" name="from_page" value="<?php echo isset($_GET['from']) ? esc_attr($_GET['from']) : 'services'; ?>">

                <!-- Hidden field to store all phone numbers (will be updated by JavaScript) -->
                <input type="hidden" name="recipients_phones" id="recipients_phones_hidden" value="<?php echo esc_attr(implode(',', $phone_numbers_list)); ?>">
                <input type="hidden" name="recipient_names" id="recipient_names_hidden" value="<?php echo esc_attr(implode(', ', $names_list)); ?>">

                <div style="max-width: 800px;">

                    <!-- HEADER SECTION (Toggleable with checkbox) -->
                    <div style="margin-bottom: 20px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                            <label style="font-weight: 600;">Header</label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 400;">
                                <input type="checkbox" id="use_custom_header" name="use_custom_header" value="1">
                                <span>Use custom header</span>
                            </label>
                        </div>
                        <input
                            type="text"
                            name="message_header_value"
                            id="message_header_input"
                            value="<?php echo esc_attr($message_header_value); ?>"
                            readonly
                            style="width: 100%; padding: 10px 12px; background: #f0f0f0; border: 1px solid #ddd; border-radius: 4px; color: #666;"
                        >
                    </div>

                    <!-- MESSAGE SECTION -->
                    <div style="margin-bottom: 20px;">
                        <label for="message_body" style="display: block; font-weight: 600; margin-bottom: 5px;">Message</label>
                        <textarea
                            name="message_body"
                            id="message_body"
                            rows="8"
                            required
                            placeholder="Type your message..."
                            style="width: 100%; padding: 10px; font-size: 14px; border: 1px solid #ddd; border-radius: 4px; resize: vertical;"
                        ></textarea>
                        <div style="text-align: right; margin-top: 8px; font-size: 14px; color: #666;">
                            <span id="char-count">0</span> / <span id="char-limit">160</span>
                            <span style="margin-left: 15px; color: #999;">Credit: <strong id="credit-count">1</strong></span>
                        </div>
                    </div>

                    <!-- TO SECTION WITH ADD SUBSCRIBER -->
                    <div style="margin-bottom: 20px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                            <label style="font-weight: 600;">
                                To
                                <span style="font-weight: 400; color: #666; font-size: 13px;" id="recipient-count">(<?php echo intval($recipient_count); ?> Recipients)</span>
                            </label>
                            <button type="button" id="add-subscriber-btn" class="button button-secondary button-small">+ Add Subscriber</button>
                        </div>

                        <div id="recipients-container" style="padding: 10px 12px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; min-height: 60px; max-height: 150px; overflow-y: auto;">
                            <?php if (empty($recipients_data)): ?>
                                <span style="color: #999; font-style: italic;">No recipients selected</span>
                            <?php else: ?>
                                <?php foreach ($recipients_data as $recipient): ?>
                                    <div class="recipient-tag" data-phone="<?php echo esc_attr($recipient['phone']); ?>" data-source="pco">
                                        <span><?php echo esc_html($recipient['name']); ?></span>
                                        <button type="button" class="remove-recipient" title="Remove">×</button>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- FROM SECTION -->
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; font-weight: 600; margin-bottom: 5px;">From</label>
                        <div style="padding: 10px 12px; background: #f0f0f0; border: 1px solid #ddd; border-radius: 4px; color: #666;">
                            Clearstream
                        </div>
                    </div>

                    <!-- SCHEDULE SECTION -->
                    <div style="margin-bottom: 30px;">
                        <label style="display: block; font-weight: 600; margin-bottom: 8px;">Schedule</label>
                        <div style="margin-bottom: 15px;">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-bottom: 10px;">
                                <input type="radio" name="send_schedule" value="now" id="schedule_now" checked>
                                <span>Send now</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="radio" name="send_schedule" value="later" id="schedule_later">
                                <span>Send later</span>
                            </label>
                        </div>

                        <div id="schedule_datetime_container" style="display: none; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                <div>
                                    <label for="schedule_date" style="display: block; font-weight: 600; margin-bottom: 5px;">Date</label>
                                    <input type="date" name="schedule_date" id="schedule_date" class="regular-text" style="width: 100%;">
                                </div>
                                <div>
                                    <label for="schedule_time" style="display: block; font-weight: 600; margin-bottom: 5px;">Time</label>
                                    <input type="time" name="schedule_time" id="schedule_time" class="regular-text" style="width: 100%;">
                                </div>
                            </div>
                            <p style="margin: 10px 0 0 0; font-size: 13px; color: #666;">
                                <em>Timezone: <?php echo esc_html(wp_timezone_string()); ?></em>
                            </p>
                        </div>
                    </div>

                    <!-- ACTION BUTTONS -->
                    <div style="display: flex; gap: 15px; padding-top: 20px; border-top: 1px solid #ddd;">
                        <input
                            type="submit"
                            name="submit"
                            id="submit"
                            class="button button-primary button-large"
                            value="Send Message"
                            <?php echo (empty($clearstream_api_token) || empty($message_header_value)) ? 'disabled' : ''; ?>
                        >
                        <?php
                        // Determine cancel URL based on where user came from
                        $cancel_url = admin_url('admin.php?page=pco-services');
                        if ($is_new_message || isset($_GET['from']) && $_GET['from'] === 'messages') {
                            $cancel_url = admin_url('admin.php?page=pco-message-log');
                        }
                        ?>
                        <a href="<?php echo esc_url($cancel_url); ?>" class="button button-large">Cancel</a>
                    </div>

                </div>
            </form>

            <!-- SUBSCRIBER SEARCH MODAL -->
            <div id="subscriber-modal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 100000; align-items: center; justify-content: center;">
                <div style="background: white; padding: 30px; border-radius: 8px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto;">
                    <h2 style="margin-top: 0;">Add Clearstream Subscriber</h2>

                    <div style="margin-bottom: 20px;">
                        <input
                            type="text"
                            id="subscriber-search"
                            placeholder="Search subscribers by name or phone..."
                            style="width: 100%; padding: 10px; font-size: 14px; border: 1px solid #ddd; border-radius: 4px;"
                        >
                    </div>

                    <div id="subscriber-results" style="margin-bottom: 20px; max-height: 400px; overflow-y: auto;">
                        <p style="color: #666; font-style: italic;">Type to search for subscribers...</p>
                    </div>

                    <div style="text-align: right;">
                        <button type="button" id="close-modal-btn" class="button button-large">Close</button>
                    </div>
                </div>
            </div>

        </div>

        <style>
            .recipient-tag {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 5px 10px;
                margin: 3px;
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 3px;
                font-size: 13px;
            }
            .recipient-tag[data-source="clearstream"] {
                background: #e3f2fd;
                border-color: #90caf9;
            }
            .recipient-tag .remove-recipient {
                background: none;
                border: none;
                color: #999;
                font-size: 18px;
                line-height: 1;
                cursor: pointer;
                padding: 0;
                width: 18px;
                height: 18px;
            }
            .recipient-tag .remove-recipient:hover {
                color: #dc3232;
            }
            .subscriber-item {
                padding: 10px;
                border: 1px solid #ddd;
                border-radius: 4px;
                margin-bottom: 8px;
                cursor: pointer;
                transition: background 0.2s;
            }
            .subscriber-item:hover {
                background: #f0f0f0;
            }
            .subscriber-item.added {
                background: #e8f5e9;
                border-color: #4caf50;
                cursor: default;
            }
        </style>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const textarea = document.getElementById('message_body');
                const charCount = document.getElementById('char-count');
                const charLimit = document.getElementById('char-limit');
                const creditCount = document.getElementById('credit-count');
                const recipientsContainer = document.getElementById('recipients-container');
                const recipientCountSpan = document.getElementById('recipient-count');
                const hiddenPhonesInput = document.getElementById('recipients_phones_hidden');
                const hiddenNamesInput = document.getElementById('recipient_names_hidden');
                const addSubscriberBtn = document.getElementById('add-subscriber-btn');
                const modal = document.getElementById('subscriber-modal');
                const closeModalBtn = document.getElementById('close-modal-btn');
                const searchInput = document.getElementById('subscriber-search');
                const resultsDiv = document.getElementById('subscriber-results');

                // Header controls
                const customHeaderCheckbox = document.getElementById('use_custom_header');
                const headerInput = document.getElementById('message_header_input');

                // Schedule controls
                const scheduleNow = document.getElementById('schedule_now');
                const scheduleLater = document.getElementById('schedule_later');
                const scheduleDatetimeContainer = document.getElementById('schedule_datetime_container');
                const scheduleDate = document.getElementById('schedule_date');
                const scheduleTime = document.getElementById('schedule_time');

                let recipients = [];
                let allSubscribers = [];

                // Initialize recipients from existing data
                document.querySelectorAll('.recipient-tag').forEach(tag => {
                    recipients.push({
                        phone: tag.getAttribute('data-phone'),
                        name: tag.querySelector('span').textContent,
                        source: tag.getAttribute('data-source')
                    });
                });

                // Custom header toggle
                customHeaderCheckbox.addEventListener('change', function() {
                    if (this.checked) {
                        headerInput.removeAttribute('readonly');
                        headerInput.style.background = '#fff';
                        headerInput.style.color = '#000';
                        headerInput.focus();
                    } else {
                        headerInput.setAttribute('readonly', 'readonly');
                        headerInput.style.background = '#f0f0f0';
                        headerInput.style.color = '#666';
                    }
                });

                // Schedule toggle
                const submitBtn = document.getElementById('submit');

                scheduleNow.addEventListener('change', function() {
                    if (this.checked) {
                        scheduleDatetimeContainer.style.display = 'none';
                        scheduleDate.removeAttribute('required');
                        scheduleTime.removeAttribute('required');
                        submitBtn.value = 'Send Message';
                    }
                });

                scheduleLater.addEventListener('change', function() {
                    if (this.checked) {
                        scheduleDatetimeContainer.style.display = 'block';
                        scheduleDate.setAttribute('required', 'required');
                        scheduleTime.setAttribute('required', 'required');
                        submitBtn.value = 'Schedule Message';

                        // Set default to tomorrow at 10am if empty
                        if (!scheduleDate.value) {
                            const tomorrow = new Date();
                            tomorrow.setDate(tomorrow.getDate() + 1);
                            scheduleDate.value = tomorrow.toISOString().split('T')[0];
                        }
                        if (!scheduleTime.value) {
                            scheduleTime.value = '10:00';
                        }
                    }
                });

                // Character counter
                function updateCounter() {
                    const text = textarea.value;
                    const charLength = text.length;
                    charCount.textContent = charLength;

                    const charMax = 160;
                    charLimit.textContent = charMax;

                    let credits = Math.ceil(charLength / 160) || 1;
                    creditCount.textContent = credits;

                    if (charLength > charMax) {
                        charCount.style.color = '#dc3232';
                        charCount.style.fontWeight = 'bold';
                    } else if (charLength > charMax * 0.9) {
                        charCount.style.color = '#f0a500';
                        charCount.style.fontWeight = 'bold';
                    } else {
                        charCount.style.color = 'inherit';
                        charCount.style.fontWeight = 'normal';
                    }
                }

                textarea.addEventListener('input', updateCounter);
                updateCounter();

                // Update recipients display
                function updateRecipientsDisplay() {
                    if (recipients.length === 0) {
                        recipientsContainer.innerHTML = '<span style="color: #999; font-style: italic;">No recipients selected</span>';
                    } else {
                        recipientsContainer.innerHTML = '';
                        recipients.forEach((recipient, index) => {
                            const tag = document.createElement('div');
                            tag.className = 'recipient-tag';
                            tag.setAttribute('data-phone', recipient.phone);
                            tag.setAttribute('data-source', recipient.source);
                            tag.innerHTML = `
                            <span>${recipient.name}</span>
                            <button type="button" class="remove-recipient" data-index="${index}" title="Remove">×</button>
                        `;
                            recipientsContainer.appendChild(tag);
                        });

                        // Attach remove handlers
                        document.querySelectorAll('.remove-recipient').forEach(btn => {
                            btn.addEventListener('click', function() {
                                const index = parseInt(this.getAttribute('data-index'));
                                recipients.splice(index, 1);
                                updateRecipientsDisplay();
                            });
                        });
                    }

                    // Update count and hidden inputs
                    recipientCountSpan.textContent = `(${recipients.length} Recipients)`;
                    hiddenPhonesInput.value = recipients.map(r => r.phone).join(',');
                    hiddenNamesInput.value = recipients.map(r => r.name).join(', ');
                }

                // Helper function to extract subscriber data using correct Clearstream field names
                function extractSubscriberData(sub) {
                    const phone = sub.mobile_number || '';
                    const firstName = sub.first || '';
                    const lastName = sub.last || '';
                    const fullName = sub.full_name || `${firstName} ${lastName}`.trim() || phone;

                    return {
                        phone: phone,
                        name: fullName,
                        firstName: firstName,
                        lastName: lastName
                    };
                }

                // Load all subscribers initially (only when modal opens)
                function loadAllSubscribers() {
                    resultsDiv.innerHTML = '<p style="color: #666;">Loading subscribers...</p>';

                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            action: 'search_clearstream_subscribers',
                            nonce: '<?php echo wp_create_nonce('clearstream_search'); ?>',
                            search: ''
                        })
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.data.subscribers) {
                                const rawSubscribers = data.data.subscribers;
                                allSubscribers = rawSubscribers.map(sub => extractSubscriberData(sub));

                                // Don't display anything yet - wait for user to type
                                resultsDiv.innerHTML = '<p style="color: #666; font-style: italic;">Type to search for subscribers...</p>';
                            } else {
                                resultsDiv.innerHTML = '<p style="color: #dc3232;">Error loading subscribers: ' + (data.data?.message || 'Unknown error') + '</p>';
                            }
                        })
                        .catch(error => {
                            resultsDiv.innerHTML = '<p style="color: #dc3232;">Error: ' + error.message + '</p>';
                        });
                }

                // Display subscribers in the modal
                function displaySubscribers(subscribers) {
                    if (subscribers.length === 0) {
                        resultsDiv.innerHTML = '<p style="color: #666;">No subscribers found.</p>';
                        return;
                    }

                    resultsDiv.innerHTML = '';
                    subscribers.forEach(subscriber => {
                        const isAdded = recipients.some(r => r.phone === subscriber.phone);

                        const item = document.createElement('div');
                        item.className = 'subscriber-item' + (isAdded ? ' added' : '');
                        item.innerHTML = `
                        <div style="font-weight: 600;">${subscriber.name}</div>
                        <div style="font-size: 13px; color: #666;">${subscriber.phone}</div>
                        ${isAdded ? '<div style="font-size: 12px; color: #4caf50; margin-top: 4px;">✓ Already added</div>' : ''}
                    `;

                        if (!isAdded) {
                            item.addEventListener('click', function() {
                                recipients.push({
                                    phone: subscriber.phone,
                                    name: subscriber.name,
                                    source: 'clearstream'
                                });
                                updateRecipientsDisplay();
                                this.classList.add('added');
                                this.innerHTML += '<div style="font-size: 12px; color: #4caf50; margin-top: 4px;">✓ Added</div>';
                            });
                        }

                        resultsDiv.appendChild(item);
                    });
                }

                // Search/filter subscribers as user types
                searchInput.addEventListener('input', function() {
                    const query = this.value.toLowerCase().trim();

                    if (query.length === 0) {
                        resultsDiv.innerHTML = '<p style="color: #666; font-style: italic;">Type to search for subscribers...</p>';
                        return;
                    }

                    if (query.length < 2) {
                        resultsDiv.innerHTML = '<p style="color: #666; font-style: italic;">Type at least 2 characters...</p>';
                        return;
                    }

                    // Filter subscribers - be more strict with matching
                    const filtered = allSubscribers.filter(sub => {
                        // Get all searchable fields in lowercase
                        const firstName = (sub.firstName || '').toLowerCase();
                        const lastName = (sub.lastName || '').toLowerCase();
                        const fullName = sub.name.toLowerCase();
                        const phone = sub.phone.replace(/\D/g, '');

                        // If query looks like a phone number (contains digits), search phone
                        if (/\d/.test(query)) {
                            const searchDigits = query.replace(/\D/g, '');
                            return phone.includes(searchDigits);
                        }

                        // Otherwise search name fields
                        // Match if query is in first name, last name, or full name
                        return firstName.includes(query) ||
                            lastName.includes(query) ||
                            fullName.includes(query);
                    });

                    // Show results or "no matches" message
                    if (filtered.length === 0) {
                        resultsDiv.innerHTML = '<p style="color: #666;">No subscribers match "' + query + '"</p>';
                    } else {
                        displaySubscribers(filtered);
                    }
                });

                // Open modal
                addSubscriberBtn.addEventListener('click', function() {
                    modal.style.display = 'flex';
                    searchInput.value = '';
                    resultsDiv.innerHTML = '<p style="color: #666; font-style: italic;">Type to search for subscribers...</p>';

                    // Load subscribers if not already loaded
                    if (allSubscribers.length === 0) {
                        loadAllSubscribers();
                    }

                    searchInput.focus();
                });

                // Close modal
                closeModalBtn.addEventListener('click', function() {
                    modal.style.display = 'none';
                    searchInput.value = '';
                    resultsDiv.innerHTML = '<p style="color: #666; font-style: italic;">Type to search for subscribers...</p>';
                });

                // Close on outside click
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        closeModalBtn.click();
                    }
                });

                // Initialize the display to attach event handlers to existing recipients
                updateRecipientsDisplay();
            });
        </script>
        <?php
    }

    /**
     * Handle Clearstream message sending
     */
    public function handle_clearstream_send() {
        // Verify this is our form submission
        if (!isset($_POST['action']) || $_POST['action'] !== 'send_clearstream') {
            return;
        }

        // Check permission
        if (!$this->user_can_send_clearstream()) {
            wp_redirect(admin_url('admin.php?page=pco-services&message=no_permission'));
            exit;
        }

        $api_token = get_option('clearstream_api_token');

        // Get header value - either custom or default
        $use_custom_header = isset($_POST['use_custom_header']) && $_POST['use_custom_header'] == '1';
        if ($use_custom_header && !empty($_POST['message_header_value'])) {
            $message_header = sanitize_text_field($_POST['message_header_value']);
        } else {
            $message_header = get_option('clearstream_message_header');
        }

        // Clearstream doesn't allow colons in message headers - remove them
        $message_header = str_replace(':', '', $message_header);

        $message_body = sanitize_textarea_field($_POST['message_body']);
        $phone_numbers = array_map('trim', explode(',', $_POST['recipients_phones']));
        $phone_numbers = array_filter($phone_numbers); // Remove empty values

        // Collect recipient names if available
        $recipient_names_str = '';
        if (isset($_POST['recipient_names'])) {
            $recipient_names_str = sanitize_text_field($_POST['recipient_names']);
        }

        // Get plan data for logging
        $plan_id = sanitize_text_field($_POST['plan_id']);
        $plan_title = sanitize_text_field($_POST['plan_title']);
        $plan_date = sanitize_text_field($_POST['plan_date']);

        // Get the page to redirect back to
        $from_page = isset($_POST['from_page']) ? sanitize_text_field($_POST['from_page']) : 'services';

        // Get schedule preference
        $send_schedule = isset($_POST['send_schedule']) ? sanitize_text_field($_POST['send_schedule']) : 'now';
        $schedule_date = isset($_POST['schedule_date']) ? sanitize_text_field($_POST['schedule_date']) : '';
        $schedule_time = isset($_POST['schedule_time']) ? sanitize_text_field($_POST['schedule_time']) : '';

        // Validate inputs
        if (empty($api_token) || empty($message_header) || empty($message_body) || empty($phone_numbers)) {
            set_transient('clearstream_error_message', [
                'code' => 0,
                'message' => 'Missing required fields'
            ], 30);
            $redirect_page = $from_page === 'messages' ? 'pco-message-log' : 'pco-services';
            wp_redirect(admin_url('admin.php?page=' . $redirect_page . '&message=error'));
            exit;
        }

        // Calculate credits (including spaces)
        $char_length = strlen($message_body);
        $credits_used = max(1, ceil($char_length / 160));

        // Clearstream API endpoint
        $api_url = 'https://api.getclearstream.com/v1/messages';

        $payload = [
            'message_header' => $message_header,
            'message_body' => $message_body,
            'subscribers' => $phone_numbers
        ];

        // Determine status and handle scheduling
        $status = 'sent';
        $scheduled_at_value = null;

        if ($send_schedule === 'later' && !empty($schedule_date) && !empty($schedule_time)) {
            $status = 'scheduled';
            $scheduled_at_value = $schedule_date . ' ' . $schedule_time . ':00';

            // Get local timezone
            $local_timezone = wp_timezone_string();

            // Fallback to 'America/Chicago' if timezone string is empty
            if (empty($local_timezone)) {
                $local_timezone = 'America/Chicago';
            }

            $datetime_string = $schedule_date . ' ' . $schedule_time;

            try {
                $local_dt = new DateTime($datetime_string, new DateTimeZone($local_timezone));

                // Validate that scheduled time is in the future
                $now = new DateTime('now', new DateTimeZone($local_timezone));
                if ($local_dt <= $now) {
                    set_transient('clearstream_error_message', [
                        'code' => 0,
                        'message' => 'Scheduled time must be in the future'
                    ], 30);
                    $redirect_page = $from_page === 'messages' ? 'pco-message-log' : 'pco-services';
                    wp_redirect(admin_url('admin.php?page=' . $redirect_page . '&message=error'));
                    exit;
                }

                // Clearstream API requires for scheduled messages:
                // - schedule: 1 (boolean to enable scheduling)
                // - datetime: YYYY-MM-DD HH:MM:SS format in LOCAL time
                // - timezone: The timezone identifier (e.g., America/Chicago)
                // Clearstream will handle the UTC conversion using the timezone
                $payload['schedule'] = 1;
                $payload['datetime'] = $local_dt->format('Y-m-d H:i:s');
                $payload['timezone'] = $local_timezone;
            } catch (Exception $e) {
                set_transient('clearstream_error_message', [
                    'code' => 0,
                    'message' => 'Invalid schedule date/time: ' . $e->getMessage()
                ], 30);
                $redirect_page = $from_page === 'messages' ? 'pco-message-log' : 'pco-services';
                wp_redirect(admin_url('admin.php?page=' . $redirect_page . '&message=error'));
                exit;
            }
        }

        $args = [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-Key' => $api_token
            ],
            'body' => json_encode($payload),
            'method' => 'POST',
            'timeout' => 30
        ];

        // DEBUG: Log the payload being sent
        error_log('Clearstream Payload: ' . json_encode($payload));

        // Send request to Clearstream
        $response = wp_remote_post($api_url, $args);

        // Handle response
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();

            set_transient('clearstream_error_message', [
                'code' => 0,
                'message' => $error_message
            ], 30);
            $redirect_page = $from_page === 'messages' ? 'pco-message-log' : 'pco-services';
            wp_redirect(admin_url('admin.php?page=' . $redirect_page . '&message=error'));
            exit;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);

        // DEBUG: Log the response
        error_log('Clearstream Response Code: ' . $response_code);
        error_log('Clearstream Response Body: ' . $response_body);

        if ($response_code === 200 || $response_code === 201) {
            // Success - Log the message
            global $wpdb;
            $table_name = $wpdb->prefix . 'clearstream_log';

            $current_user = wp_get_current_user();

            // Get user's first and last name from WordPress
            $first_name = get_user_meta($current_user->ID, 'first_name', true);
            $last_name = get_user_meta($current_user->ID, 'last_name', true);
            $user_full_name = trim($first_name . ' ' . $last_name);

            // Fallback to display name if first/last name not set
            if (empty($user_full_name)) {
                $user_full_name = $current_user->display_name;
            }

            $wpdb->insert(
                $table_name,
                [
                    'plan_id' => $plan_id,
                    'plan_title' => $plan_title,
                    'plan_date' => $plan_date,
                    'credits_used' => $credits_used,
                    'recipient_count' => count($phone_numbers),
                    'recipient_names' => $recipient_names_str,
                    'message_body' => $message_body,
                    'status' => $status,
                    'scheduled_at' => $scheduled_at_value,
                    'user_id' => $current_user->ID,
                    'user_name' => $user_full_name,
                    'sent_at' => current_time('mysql')
                ],
                ['%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s']
            );

            $recipient_count = count($phone_numbers);

            // Store different success info based on status
            $success_data = [
                'count' => $recipient_count,
                'status' => $status,
                'scheduled_at' => $scheduled_at_value
            ];
            set_transient('clearstream_success_message', $success_data, 30);

            // Redirect to the page they came from
            $redirect_page = $from_page === 'messages' ? 'pco-message-log' : 'pco-services';
            wp_redirect(admin_url('admin.php?page=' . $redirect_page . '&message=' . ($status === 'scheduled' ? 'scheduled' : 'sent')));
            exit;
        } else {
            // API returned an error
            $error_detail = $response_data['message'] ?? $response_body;

            set_transient('clearstream_error_message', [
                'code' => $response_code,
                'message' => $error_detail
            ], 30);

            $redirect_page = $from_page === 'messages' ? 'pco-message-log' : 'pco-services';
            wp_redirect(admin_url('admin.php?page=' . $redirect_page . '&message=error'));
            exit;
        }
    }

    /**
     * Render Message Log Page with WordPress-style filtering and sorting
     */
    /**
     * Handle bulk delete actions for message log
     */
    public function handle_message_log_bulk_actions() {
        // Check if this is a bulk delete request
        if (!isset($_POST['action']) || $_POST['action'] !== 'bulk_delete_messages') {
            return;
        }

        // Check if bulk_action is set to delete
        if (!isset($_POST['bulk_action']) || $_POST['bulk_action'] !== 'delete') {
            return;
        }

        // Check permissions - only administrators can delete
        if (!current_user_can('delete_others_posts')) {
            wp_die('You do not have permission to delete messages.');
        }

        // Verify nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'bulk_delete_messages')) {
            wp_die('Security check failed.');
        }

        // Get message IDs to delete
        $message_ids = isset($_POST['message_ids']) ? array_map('intval', $_POST['message_ids']) : [];

        if (empty($message_ids)) {
            wp_redirect(admin_url('admin.php?page=pco-message-log&message=no_selection'));
            exit;
        }

        // Delete messages
        global $wpdb;
        $table_name = $wpdb->prefix . 'clearstream_log';
        $placeholders = implode(',', array_fill(0, count($message_ids), '%d'));
        $deleted = $wpdb->query($wpdb->prepare("DELETE FROM {$table_name} WHERE id IN ($placeholders)", $message_ids));

        wp_redirect(admin_url('admin.php?page=pco-message-log&message=deleted&count=' . $deleted));
        exit;
    }

    /**
     * Handle single message delete from detail view
     */
    public function handle_single_message_delete() {
        // Only process if we're on the message log page with a message_id and delete_message POST
        if (!isset($_GET['page']) || $_GET['page'] !== 'pco-message-log') {
            return;
        }

        if (!isset($_GET['message_id']) || !isset($_POST['delete_message'])) {
            return;
        }

        $message_id = intval($_GET['message_id']);

        // Check permissions - only administrators can delete
        if (!current_user_can('delete_others_posts')) {
            wp_die('You do not have permission to delete messages.');
        }

        // Verify nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'delete_message_' . $message_id)) {
            wp_die('Security check failed.');
        }

        // Delete the message
        global $wpdb;
        $table_name = $wpdb->prefix . 'clearstream_log';
        $wpdb->delete($table_name, ['id' => $message_id], ['%d']);

        // Redirect back to messages list
        wp_redirect(admin_url('admin.php?page=pco-message-log&message=deleted&count=1'));
        exit;
    }

    /**
     * Render Message Log page
     */
    /**
     * Render Message Log page
     */
    public function render_clearstream_log() {
        // Check if we're viewing a single message
        if (isset($_GET['message_id'])) {
            $this->render_message_detail();
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'clearstream_log';

        // Get current filter and sort parameters
        $current_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'sent_at';
        $order = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'asc' : 'desc';

        // Get current user info for permission filtering
        $current_user = wp_get_current_user();
        $can_view_all = current_user_can('delete_others_posts'); // Administrators
        $can_delete = current_user_can('delete_others_posts'); // Only administrators can delete

        // Build WHERE clause based on status and permissions
        $where_clauses = [];

        if ($current_status !== 'all') {
            $where_clauses[] = $wpdb->prepare("status = %s", $current_status);
        }

        // Editors can only see their own messages
        if (!$can_view_all) {
            $where_clauses[] = $wpdb->prepare("user_id = %d", $current_user->ID);
        }

        $where_sql = empty($where_clauses) ? '' : 'WHERE ' . implode(' AND ', $where_clauses);

        // Get counts for each status
        $counts = [
            'all' => 0,
            'sent' => 0,
            'scheduled' => 0
        ];

        $count_where = $can_view_all ? '' : $wpdb->prepare("WHERE user_id = %d", $current_user->ID);

        $counts['all'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} {$count_where}");
        $counts['sent'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} {$count_where}" . ($count_where ? " AND" : " WHERE") . " status = 'sent'");
        $counts['scheduled'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} {$count_where}" . ($count_where ? " AND" : " WHERE") . " status = 'scheduled'");

        // Get messages
        $messages = $wpdb->get_results(
            "SELECT * FROM {$table_name} {$where_sql} ORDER BY {$orderby} {$order}",
            ARRAY_A
        );

        // Helper function for sortable column headers
        function get_sort_url($column, $current_orderby, $current_order, $current_status) {
            $new_order = ($current_orderby === $column && $current_order === 'desc') ? 'asc' : 'desc';
            $url = admin_url('admin.php?page=pco-message-log');
            $url = add_query_arg('orderby', $column, $url);
            $url = add_query_arg('order', $new_order, $url);
            if ($current_status !== 'all') {
                $url = add_query_arg('status', $current_status, $url);
            }
            return $url;
        }

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Messages</h1>
            <a href="<?php echo admin_url('admin.php?page=pco-services&view=clearstream_compose&new=1&from=messages'); ?>" class="page-title-action">New Message</a>
            <hr class="wp-header-end">

            <?php
            // Show success/error messages
            if (isset($_GET['message'])) {
                if ($_GET['message'] === 'deleted') {
                    $count = isset($_GET['count']) ? intval($_GET['count']) : 0;
                    echo '<div class="notice notice-success is-dismissible"><p>Successfully deleted ' . $count . ' message(s).</p></div>';
                } elseif ($_GET['message'] === 'no_selection') {
                    echo '<div class="notice notice-error is-dismissible"><p>No messages selected for deletion.</p></div>';
                } elseif ($_GET['message'] === 'sent' || $_GET['message'] === 'scheduled') {
                    // Get success data from transient
                    $success_data = get_transient('clearstream_success_message');
                    if ($success_data) {
                        delete_transient('clearstream_success_message');

                        $count = $success_data['count'] ?? 0;
                        $status = $success_data['status'] ?? 'sent';
                        $scheduled_at = $success_data['scheduled_at'] ?? null;

                        if ($status === 'scheduled' && $scheduled_at) {
                            $formatted_time = date('M j, Y \a\t g:i A', strtotime($scheduled_at));
                            echo '<div class="notice notice-success is-dismissible"><p><strong>Success!</strong> Message scheduled for ' . esc_html($formatted_time) . ' to ' . intval($count) . ' recipients via Clearstream.</p></div>';
                        } else {
                            echo '<div class="notice notice-success is-dismissible"><p><strong>Success!</strong> Message sent to ' . intval($count) . ' recipients via Clearstream.</p></div>';
                        }
                    }
                } elseif ($_GET['message'] === 'error') {
                    $error = get_transient('clearstream_error_message');
                    if ($error) {
                        echo '<div class="notice notice-error is-dismissible"><p><strong>Clearstream API Error (Code ' . intval($error['code']) . '):</strong> ' . esc_html($error['message']) . '</p></div>';
                        delete_transient('clearstream_error_message');
                    }
                }
            }
            ?>

            <!-- Status Filter Tabs -->
            <ul class="subsubsub" style="margin-bottom: 20px;">
                <li class="all">
                    <a href="<?php echo admin_url('admin.php?page=pco-message-log'); ?>" <?php echo $current_status === 'all' ? 'class="current"' : ''; ?>>
                        All <span class="count">(<?php echo $counts['all']; ?>)</span>
                    </a> |
                </li>
                <li class="sent">
                    <a href="<?php echo add_query_arg('status', 'sent', admin_url('admin.php?page=pco-message-log')); ?>" <?php echo $current_status === 'sent' ? 'class="current"' : ''; ?>>
                        Sent <span class="count">(<?php echo $counts['sent']; ?>)</span>
                    </a> |
                </li>
                <li class="scheduled">
                    <a href="<?php echo add_query_arg('status', 'scheduled', admin_url('admin.php?page=pco-message-log')); ?>" <?php echo $current_status === 'scheduled' ? 'class="current"' : ''; ?>>
                        Scheduled <span class="count">(<?php echo $counts['scheduled']; ?>)</span>
                    </a>
                </li>
            </ul>

            <?php if (empty($messages)): ?>
                <p>No messages found.</p>
            <?php else: ?>
                <form method="post" action="">
                    <?php wp_nonce_field('bulk_delete_messages'); ?>
                    <input type="hidden" name="action" value="bulk_delete_messages">

                    <div class="tablenav top">
                        <?php if ($can_delete): ?>
                            <div class="alignleft actions bulkactions">
                                <label for="bulk-action-selector-top" class="screen-reader-text">Select bulk action</label>
                                <select name="bulk_action" id="bulk-action-selector-top">
                                    <option value="-1">Bulk actions</option>
                                    <option value="delete">Delete</option>
                                </select>
                                <input type="submit" id="doaction" class="button action" value="Apply" onclick="if(document.getElementById('bulk-action-selector-top').value === 'delete') { return confirm('Are you sure you want to delete the selected messages? This cannot be undone.'); } return true;">
                            </div>
                        <?php endif; ?>
                    </div>

                    <table class="wp-list-table widefat fixed striped table-view-list">
                        <thead>
                        <tr>
                            <?php if ($can_delete): ?>
                                <td class="manage-column column-cb check-column">
                                    <input type="checkbox" id="select-all-messages">
                                </td>
                            <?php endif; ?>
                            <th scope="col" class="manage-column column-primary">
                                <span>Message</span>
                            </th>
                            <th scope="col" class="manage-column sortable <?php echo $orderby === 'recipient_count' ? 'sorted' : ''; ?> <?php echo $orderby === 'recipient_count' ? $order : 'desc'; ?>">
                                <a href="<?php echo esc_url(get_sort_url('recipient_count', $orderby, $order, $current_status)); ?>">
                                    <span>Recipients</span>
                                    <span class="sorting-indicators">
                                        <span class="sorting-indicator asc" aria-hidden="true"></span>
                                        <span class="sorting-indicator desc" aria-hidden="true"></span>
                                    </span>
                                </a>
                            </th>
                            <th scope="col" class="manage-column sortable <?php echo $orderby === 'credits_used' ? 'sorted' : ''; ?> <?php echo $orderby === 'credits_used' ? $order : 'desc'; ?>">
                                <a href="<?php echo esc_url(get_sort_url('credits_used', $orderby, $order, $current_status)); ?>">
                                    <span>Credit</span>
                                    <span class="sorting-indicators">
                                        <span class="sorting-indicator asc" aria-hidden="true"></span>
                                        <span class="sorting-indicator desc" aria-hidden="true"></span>
                                    </span>
                                </a>
                            </th>
                            <th scope="col" class="manage-column sortable <?php echo $orderby === 'status' ? 'sorted' : ''; ?> <?php echo $orderby === 'status' ? $order : 'desc'; ?>">
                                <a href="<?php echo esc_url(get_sort_url('status', $orderby, $order, $current_status)); ?>">
                                    <span>Status</span>
                                    <span class="sorting-indicators">
                                        <span class="sorting-indicator asc" aria-hidden="true"></span>
                                        <span class="sorting-indicator desc" aria-hidden="true"></span>
                                    </span>
                                </a>
                            </th>
                            <th scope="col" class="manage-column sortable <?php echo $orderby === 'user_name' ? 'sorted' : ''; ?> <?php echo $orderby === 'user_name' ? $order : 'desc'; ?>">
                                <a href="<?php echo esc_url(get_sort_url('user_name', $orderby, $order, $current_status)); ?>">
                                    <span>User</span>
                                    <span class="sorting-indicators">
                                        <span class="sorting-indicator asc" aria-hidden="true"></span>
                                        <span class="sorting-indicator desc" aria-hidden="true"></span>
                                    </span>
                                </a>
                            </th>
                            <th scope="col" class="manage-column sortable <?php echo $orderby === 'sent_at' ? 'sorted' : ''; ?> <?php echo $orderby === 'sent_at' ? $order : 'desc'; ?>">
                                <a href="<?php echo esc_url(get_sort_url('sent_at', $orderby, $order, $current_status)); ?>">
                                    <span>Date / Time Sent</span>
                                    <span class="sorting-indicators">
                                        <span class="sorting-indicator asc" aria-hidden="true"></span>
                                        <span class="sorting-indicator desc" aria-hidden="true"></span>
                                    </span>
                                </a>
                            </th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($messages as $msg):
                            $status_label = ucfirst($msg['status']);
                            $status_color = $msg['status'] === 'sent' ? '#46b450' : ($msg['status'] === 'scheduled' ? '#e5a500' : '#999');

                            // Truncate message for display - show more characters now
                            $message_preview = strlen($msg['message_body']) > 200
                                ? substr($msg['message_body'], 0, 200) . '...'
                                : $msg['message_body'];

                            // Look up fresh user name from WordPress
                            $user_first = get_user_meta($msg['user_id'], 'first_name', true);
                            $user_last = get_user_meta($msg['user_id'], 'last_name', true);
                            $user_display = trim($user_first . ' ' . $user_last);
                            if (empty($user_display)) {
                                $user_display = $msg['user_name']; // Fallback to stored name
                            }

                            // Link to detail view
                            $detail_url = admin_url('admin.php?page=pco-message-log&message_id=' . $msg['id']);
                            ?>
                            <tr>
                                <?php if ($can_delete): ?>
                                    <th scope="row" class="check-column">
                                        <input type="checkbox" name="message_ids[]" value="<?php echo intval($msg['id']); ?>" class="message-checkbox">
                                    </th>
                                <?php endif; ?>
                                <td class="column-primary" data-colname="Message" style="max-width: 500px;">
                                    <a href="<?php echo esc_url($detail_url); ?>" class="row-title">
                                        <strong><?php echo esc_html($message_preview); ?></strong>
                                    </a>
                                </td>
                                <td data-colname="Recipients"><?php echo esc_html($msg['recipient_count']); ?></td>
                                <td data-colname="Credit"><?php echo esc_html($msg['credits_used']); ?></td>
                                <td data-colname="Status">
                                    <span style="color: <?php echo $status_color; ?>; font-weight: bold;">
                                        <?php echo esc_html($status_label); ?>
                                    </span>
                                </td>
                                <td data-colname="User"><?php echo esc_html($user_display); ?></td>
                                <td data-colname="Date / Time Sent">
                                    <?php
                                    $date = $msg['status'] === 'scheduled' && !empty($msg['scheduled_at'])
                                        ? $msg['scheduled_at']
                                        : $msg['sent_at'];
                                    echo esc_html(date('M j, Y', strtotime($date)));
                                    ?>
                                    <br>
                                    <small><?php echo esc_html(date('g:i a', strtotime($date))); ?></small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </form>

                <style>
                    /* Make message column wider */
                    .wp-list-table .column-primary {
                        width: 45%;
                    }

                    /* Style message links */
                    .wp-list-table .column-primary a.row-title {
                        text-decoration: none;
                        color: #2271b1;
                    }

                    .wp-list-table .column-primary a.row-title:hover {
                        color: #135e96;
                    }
                </style>

                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const selectAll = document.getElementById('select-all-messages');
                        const checkboxes = document.querySelectorAll('.message-checkbox');

                        if (selectAll) {
                            selectAll.addEventListener('change', function() {
                                checkboxes.forEach(checkbox => {
                                    checkbox.checked = selectAll.checked;
                                });
                            });
                        }
                    });
                </script>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render Message Detail View
     */
    private function render_message_detail() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'clearstream_log';

        $message_id = intval($_GET['message_id']);
        $message = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $message_id), ARRAY_A);

        if (!$message) {
            wp_die('Message not found.');
        }

        // Check permissions - users can only view their own messages unless they're admin
        $current_user = wp_get_current_user();
        $can_view_all = current_user_can('delete_others_posts');
        $can_delete = current_user_can('delete_others_posts');

        if (!$can_view_all && $message['user_id'] != $current_user->ID) {
            wp_die('You do not have permission to view this message.');
        }

        // Get recipient names and count
        $recipient_count = $message['recipient_count'];
        $recipient_names = !empty($message['recipient_names']) ? $message['recipient_names'] : '';

        // Look up user name
        $user_first = get_user_meta($message['user_id'], 'first_name', true);
        $user_last = get_user_meta($message['user_id'], 'last_name', true);
        $user_display = trim($user_first . ' ' . $user_last);
        if (empty($user_display)) {
            $user_display = $message['user_name'];
        }

        $status_label = ucfirst($message['status']);
        $status_color = $message['status'] === 'sent' ? '#46b450' : ($message['status'] === 'scheduled' ? '#e5a500' : '#999');

        // Format dates
        $date_to_display = $message['status'] === 'scheduled' && !empty($message['scheduled_at'])
            ? $message['scheduled_at']
            : $message['sent_at'];

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Message Details</h1>
            <a href="<?php echo admin_url('admin.php?page=pco-message-log'); ?>" class="page-title-action">← Back to Messages</a>
            <hr class="wp-header-end">

            <div style="max-width: 800px; margin-top: 20px;">

                <!-- Message Section -->
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 5px;">Message</label>
                    <textarea readonly style="width: 100%; min-height: 120px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, sans-serif; resize: vertical; line-height: 1.5;"><?php echo esc_textarea($message['message_body']); ?></textarea>
                    <div style="text-align: right; margin-top: 8px; font-size: 14px; color: #666;">
                        <?php echo strlen($message['message_body']); ?> characters • <?php echo $message['credits_used']; ?> credit(s)
                    </div>
                </div>

                <!-- Plan Info Section (if from a plan) -->
                <?php if (!empty($message['plan_title'])): ?>
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; font-weight: 600; margin-bottom: 5px;">Plan</label>
                        <div style="padding: 10px 12px; background: #f0f0f0; border: 1px solid #ddd; border-radius: 4px;">
                            <strong><?php echo esc_html($message['plan_title']); ?></strong>
                            <?php if (!empty($message['plan_date'])): ?>
                                <br><span style="color: #666; font-size: 14px;"><?php echo esc_html($message['plan_date']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Recipients Section -->
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 5px;">
                        Recipients <span style="font-weight: 400; color: #666;">(<?php echo $recipient_count; ?>)</span>
                    </label>
                    <div style="padding: 10px 12px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                        <?php if (!empty($recipient_names)): ?>
                            <?php echo esc_html($recipient_names); ?>
                        <?php else: ?>
                            <span style="color: #666;"><?php echo $recipient_count; ?> recipient(s) received this message</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Status Section -->
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 5px;">Status</label>
                    <div style="padding: 10px 12px; background: #f0f0f0; border: 1px solid #ddd; border-radius: 4px;">
                        <span style="color: <?php echo $status_color; ?>; font-weight: bold;">
                            <?php echo esc_html($status_label); ?>
                        </span>
                    </div>
                </div>

                <!-- Sent By Section -->
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 5px;">Sent By</label>
                    <div style="padding: 10px 12px; background: #f0f0f0; border: 1px solid #ddd; border-radius: 4px;">
                        <?php echo esc_html($user_display); ?>
                    </div>
                </div>

                <!-- Date/Time Section -->
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 5px;">
                        <?php echo $message['status'] === 'scheduled' ? 'Scheduled For' : 'Sent At'; ?>
                    </label>
                    <div style="padding: 10px 12px; background: #f0f0f0; border: 1px solid #ddd; border-radius: 4px;">
                        <?php echo date('l, F j, Y \a\t g:i A', strtotime($date_to_display)); ?>
                    </div>
                </div>

                <!-- Delete Button (Admin Only) -->
                <?php if ($can_delete): ?>
                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete this message? This action cannot be undone.');" style="margin-top: 30px;">
                        <?php wp_nonce_field('delete_message_' . $message_id); ?>
                        <button type="submit" name="delete_message" class="button button-large" style="color: #b32d2e;">Delete Message</button>
                    </form>
                <?php endif; ?>

            </div>
        </div>
        <?php
    }

    /**
     * Render Permissions Page - Generic for all permission types
     */
    public function render_permissions_page() {
        // Only administrators and authors can manage permissions
        if (!current_user_can('delete_others_posts')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        // Handle permission changes
        if (isset($_POST['update_permissions']) && check_admin_referer('pco_update_permissions')) {
            $user_id = intval($_POST['user_id']);
            $capability = sanitize_text_field($_POST['capability']);
            $action = sanitize_text_field($_POST['permission_action']);

            if ($user_id && in_array($action, ['grant', 'revoke'])) {
                $user = get_user_by('id', $user_id);

                if ($user) {
                    if ($action === 'grant') {
                        $user->add_cap($capability);
                        $message = 'Permission granted successfully.';
                        $message_type = 'success';
                    } else {
                        $user->remove_cap($capability);
                        $message = 'Permission revoked successfully.';
                        $message_type = 'success';
                    }
                }
            }
        }

        // Define all manageable permissions
        $permissions = [
            'send_clearstream_messages' => [
                'label' => 'Send Clearstream Messages',
                'description' => 'Allows user to send text messages via Clearstream',
                'default_roles' => ['administrator', 'editor']
            ]
            // Add more permissions here in the future:
            // 'manage_pco_calendars' => [
            //     'label' => 'Manage PCO Calendars',
            //     'description' => 'Allows user to manage Planning Center calendars',
            //     'default_roles' => ['administrator']
            // ]
        ];

        // Get all users with Editor role or higher
        $users = get_users([
            'role__in' => ['administrator', 'editor', 'author'],
            'orderby' => 'display_name',
            'order' => 'ASC'
        ]);

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Manage Permissions</h1>
            <hr class="wp-header-end">

            <?php if (isset($message)): ?>
                <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
                    <p><?php echo esc_html($message); ?></p>
                </div>
            <?php endif; ?>

            <p>Manage user permissions for PCO integration features. Only Editors, Authors, and Administrators are shown.</p>

            <h2>Default Role Permissions</h2>
            <table class="wp-list-table widefat" style="max-width: 800px;">
                <thead>
                <tr>
                    <th>Permission</th>
                    <th>Administrators</th>
                    <th>Editors</th>
                    <th>Authors</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($permissions as $cap => $perm_data): ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($perm_data['label']); ?></strong>
                            <br><small style="color: #666;"><?php echo esc_html($perm_data['description']); ?></small>
                        </td>
                        <td style="text-align: center;">
                            <?php echo in_array('administrator', $perm_data['default_roles']) ? '<span style="color: #46b450;">✓</span>' : '—'; ?>
                        </td>
                        <td style="text-align: center;">
                            <?php echo in_array('editor', $perm_data['default_roles']) ? '<span style="color: #46b450;">✓</span>' : '—'; ?>
                        </td>
                        <td style="text-align: center;">
                            <?php echo in_array('author', $perm_data['default_roles']) ? '<span style="color: #46b450;">✓</span>' : '—'; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <h2 style="margin-top: 40px;">User Permissions</h2>
            <p style="color: #666; margin-bottom: 20px;">Grant or revoke custom permissions for individual users. Role-based permissions cannot be changed here.</p>

            <?php if (empty($users)): ?>
                <p>No eligible users found.</p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                    <tr>
                        <th class="manage-column column-primary" style="width: 25%;">User</th>
                        <th class="manage-column" style="width: 15%;">Role</th>
                        <?php foreach ($permissions as $cap => $perm_data): ?>
                            <th class="manage-column" style="text-align: center;"><?php echo esc_html($perm_data['label']); ?></th>
                        <?php endforeach; ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $user):
                        $roles = implode(', ', array_map('ucfirst', $user->roles));
                        ?>
                        <tr>
                            <td class="column-primary" data-colname="User">
                                <strong><?php echo esc_html($user->display_name); ?></strong>
                                <br>
                                <small style="color: #666;"><?php echo esc_html($user->user_email); ?></small>
                            </td>
                            <td data-colname="Role">
                                <?php echo esc_html($roles); ?>
                            </td>
                            <?php foreach ($permissions as $cap => $perm_data):
                                $has_custom_permission = $user->has_cap($cap);
                                $has_via_role = false;
                                foreach ($user->roles as $role) {
                                    if (in_array($role, $perm_data['default_roles'])) {
                                        $has_via_role = true;
                                        break;
                                    }
                                }
                                $can_use = $has_custom_permission || $has_via_role;
                                ?>
                                <td data-colname="<?php echo esc_attr($perm_data['label']); ?>" style="text-align: center;">
                                    <?php if ($can_use): ?>
                                        <span style="color: #46b450; font-weight: bold;">✓</span>
                                        <?php if ($has_custom_permission): ?>
                                            <br><small style="color: #666;">(Custom)</small>
                                        <?php else: ?>
                                            <br><small style="color: #666;">(Role)</small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: #ddd;">—</span>
                                    <?php endif; ?>

                                    <?php if (!$has_via_role): ?>
                                        <br>
                                        <form method="POST" style="display: inline;">
                                            <?php wp_nonce_field('pco_update_permissions'); ?>
                                            <input type="hidden" name="user_id" value="<?php echo $user->ID; ?>">
                                            <input type="hidden" name="capability" value="<?php echo esc_attr($cap); ?>">
                                            <input type="hidden" name="update_permissions" value="1">

                                            <?php if ($has_custom_permission): ?>
                                                <input type="hidden" name="permission_action" value="revoke">
                                                <button type="submit" class="button button-small" style="margin-top: 5px;">Revoke</button>
                                            <?php else: ?>
                                                <input type="hidden" name="permission_action" value="grant">
                                                <button type="submit" class="button button-primary button-small" style="margin-top: 5px;">Grant</button>
                                            <?php endif; ?>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <div style="margin-top: 30px; padding: 15px; background: #f0f0f0; border-left: 4px solid #0073aa;">
                <h3 style="margin-top: 0;">How This Works</h3>
                <p><strong>Role-based permissions</strong> are automatic and cannot be changed here. To modify role permissions, change the user's role in WordPress Users.</p>
                <p><strong>Custom permissions</strong> can be granted to users who don't have role-based access. Use the Grant/Revoke buttons to manage these.</p>
            </div>
        </div>
        <?php
    }

    /**
     * Render Team Reports Page - Main Controller
     */
    public function render_reports_page() {
        if (!current_user_can('edit_posts')) return;

        $view = $_GET['view'] ?? 'overview';

        if ($view === 'members' && isset($_GET['service_type']) && isset($_GET['team_id']) && isset($_GET['position'])) {
            $this->render_members_detail_page(
                sanitize_text_field($_GET['service_type']),
                sanitize_text_field($_GET['team_id']),
                sanitize_text_field($_GET['position'])
            );
        } elseif ($view === 'teams' && isset($_GET['service_type'])) {
            $this->render_teams_detail_page(sanitize_text_field($_GET['service_type']));
        } else {
            $this->render_reports_overview_page();
        }
    }

    /**
     * Extract last name from full name for sorting
     */
    private function get_last_name($full_name) {
        $parts = explode(' ', trim($full_name));
        return end($parts);
    }

    /**
     * Render Reports Overview - Shows all Service Types with aggregate stats
     */
    private function render_reports_overview_page() {
        // Get date range from query params or use current month
        $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : date('Y-m-01');
        $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : date('Y-m-t');

        // Get all service types
        $service_types_response = $this->model->get_service_types();
        $service_types = $service_types_response['data'] ?? [];

        if (empty($service_types)) {
            echo '<div class="wrap"><div class="notice notice-error"><p>No service types found.</p></div></div>';
            return;
        }

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Service Type Reports</h1>
            <hr class="wp-header-end">

            <!-- Date Filter -->
            <div style="background: #fff; border: 1px solid #ccd0d4; padding: 12px 15px; margin-bottom: 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <form method="GET" action="" style="margin: 0;">
                    <input type="hidden" name="page" value="pco-reports">
                    <label for="start_date" style="margin-right: 8px; font-weight: 600;">Date:</label>
                    <input type="date" name="start_date" id="start_date" value="<?php echo esc_attr($start_date); ?>" style="width: 160px; height: 32px;">
                    <span style="padding: 0 8px;">to</span>
                    <input type="date" name="end_date" id="end_date" value="<?php echo esc_attr($end_date); ?>" style="width: 160px; height: 32px;">
                    <input type="submit" name="filter" class="button button-primary" value="Update Report" style="margin-left: 12px; height: 32px; line-height: 30px;">
                </form>
            </div>

            <!-- Service Types Table -->
            <h2>
                Service Type Overview
                <small style="color: #666; font-weight: normal;">
                    (<?php echo esc_html(date('M j, Y', strtotime($start_date))); ?> - <?php echo esc_html(date('M j, Y', strtotime($end_date))); ?>)
                </small>
            </h2>

            <?php
            // Aggregate stats for all service types
            $service_type_stats = [];

            foreach ($service_types as $service_type) {
                $type_id = $service_type['id'];
                $type_name = $service_type['attributes']['name'] ?? 'Unnamed';

                // Fetch plans for this service type
                $plans_response = $this->model->get_plans_by_date_range($type_id, $start_date, $end_date);
                $plans = $plans_response['data'] ?? [];

                $stats = [
                    'scheduled' => 0,
                    'accepted' => 0,
                    'declined' => 0,
                    'pending' => 0,
                ];

                // Aggregate stats from all plans
                foreach ($plans as $plan) {
                    $plan_id = $plan['id'];
                    $team_response = $this->model->get_plan_team_members($plan_id);
                    $team_members = $team_response['data'] ?? [];

                    foreach ($team_members as $member) {
                        $status = $member['attributes']['status'] ?? 'N';

                        $stats['scheduled']++;

                        if ($status === 'C') {
                            $stats['accepted']++;
                        } elseif ($status === 'D') {
                            $stats['declined']++;
                        } elseif ($status === 'U') {
                            $stats['pending']++;
                        }
                    }
                }

                $service_type_stats[$type_id] = [
                    'name' => $type_name,
                    'stats' => $stats,
                    'plan_count' => count($plans),
                ];
            }
            ?>

            <table class="wp-list-table widefat fixed striped table-view-list">
                <thead>
                <tr>
                    <th class="manage-column column-primary" style="width: 40%;">Service Type</th>
                    <th class="manage-column" style="text-align: center; width: 15%;">Scheduled</th>
                    <th class="manage-column" style="text-align: center; width: 15%;">Accepted</th>
                    <th class="manage-column" style="text-align: center; width: 15%;">Declined</th>
                    <th class="manage-column" style="text-align: center; width: 15%;">Pending</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($service_type_stats as $type_id => $data):
                    $stats = $data['stats'];
                    $acceptance_rate = $stats['scheduled'] > 0
                        ? round(($stats['accepted'] / $stats['scheduled']) * 100, 1)
                        : 0;

                    $teams_url = admin_url(sprintf(
                        'admin.php?page=pco-reports&view=teams&service_type=%s&start_date=%s&end_date=%s',
                        urlencode($type_id),
                        urlencode($start_date),
                        urlencode($end_date)
                    ));
                    ?>
                    <tr>
                        <td class="column-primary">
                            <a href="<?php echo esc_url($teams_url); ?>" style="text-decoration: none;">
                                <strong style="font-size: 14px;"><?php echo esc_html($data['name']); ?></strong>
                            </a>
                            <small style="color: #666; display: block; margin-top: 3px;">
                                <?php echo intval($data['plan_count']); ?> plans | <?php echo esc_html($acceptance_rate); ?>% acceptance rate
                            </small>
                        </td>
                        <td style="text-align: center;">
                            <strong><?php echo intval($stats['scheduled']); ?></strong>
                        </td>
                        <td style="text-align: center; color: #46b450;">
                            <strong><?php echo intval($stats['accepted']); ?></strong>
                        </td>
                        <td style="text-align: center; color: #dc3232;">
                            <strong><?php echo intval($stats['declined']); ?></strong>
                        </td>
                        <td style="text-align: center; color: #e5a500;">
                            <strong><?php echo intval($stats['pending']); ?></strong>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                <?php
                // Calculate totals
                $totals = [
                    'scheduled' => array_sum(array_column(array_column($service_type_stats, 'stats'), 'scheduled')),
                    'accepted' => array_sum(array_column(array_column($service_type_stats, 'stats'), 'accepted')),
                    'declined' => array_sum(array_column(array_column($service_type_stats, 'stats'), 'declined')),
                    'pending' => array_sum(array_column(array_column($service_type_stats, 'stats'), 'pending')),
                ];
                ?>
                <tr style="background: #f9f9f9; font-weight: bold;">
                    <td>TOTALS</td>
                    <td style="text-align: center;"><?php echo intval($totals['scheduled']); ?></td>
                    <td style="text-align: center; color: #46b450;"><?php echo intval($totals['accepted']); ?></td>
                    <td style="text-align: center; color: #dc3232;"><?php echo intval($totals['declined']); ?></td>
                    <td style="text-align: center; color: #e5a500;"><?php echo intval($totals['pending']); ?></td>
                </tr>
                </tfoot>
            </table>
        </div>
        <?php
    }

    /**
     * Render Teams Detail Page - Shows positions within teams for a service type
     */
    private function render_teams_detail_page($service_type_id) {
        $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : date('Y-m-01');
        $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : date('Y-m-t');

        // Get service type name
        $service_type_response = $this->model->get_single_service_type($service_type_id);
        $service_type_name = $service_type_response['attributes']['name'] ?? 'Service Type';

        // Fetch plans for this service type (selected date range for stats)
        $plans_response = $this->model->get_plans_by_date_range($service_type_id, $start_date, $end_date);
        $plans = $plans_response['data'] ?? [];

        if (empty($plans)) {
            ?>
            <div class="wrap">
                <h1><?php echo esc_html($service_type_name); ?> - Team Reports</h1>
                <a href="<?php echo admin_url('admin.php?page=pco-reports&start_date=' . urlencode($start_date) . '&end_date=' . urlencode($end_date)); ?>" class="page-title-action">← Back to Overview</a>
                <hr>
                <div class="notice notice-info"><p>No plans found for the selected date range.</p></div>
            </div>
            <?php
            return;
        }

        // Get all teams for name mapping
        $teams_response = $this->model->get_all_teams();
        $team_name_map = [];
        $team_ids = [];
        if (!empty($teams_response['data'])) {
            foreach ($teams_response['data'] as $team_obj) {
                $id = $team_obj['id'];
                $name = $team_obj['attributes']['name'] ?? 'Unknown Team';
                $team_name_map[$id] = $name;
                $team_ids[$name] = $id;
            }
        }

        // Fetch total team rosters for actual member counts
        $team_roster_counts = [];
        foreach ($team_ids as $team_name => $team_id) {
            $roster_response = $this->model->get_team_members($team_id);
            $team_roster_counts[$team_name] = count($roster_response['data'] ?? []);
        }

        // Fetch plans from a longer date range (past 1 year) to get better position member counts
        // This helps capture all members who have ever served in each position
        $extended_start_date = date('Y-m-d', strtotime('-1 year', strtotime($start_date)));
        $extended_plans_response = $this->model->get_plans_by_date_range($service_type_id, $extended_start_date, $end_date);
        $extended_plans = $extended_plans_response['data'] ?? [];

        // Aggregate statistics by team and position
        // Use selected date range for stats, but extended range for member counts
        $team_position_stats = [];
        $team_position_member_ids = []; // Track all members who have served in each position (extended range)

        // First pass: Count members per position from extended range
        foreach ($extended_plans as $plan) {
            $plan_id = $plan['id'];
            $team_response = $this->model->get_plan_team_members($plan_id);
            $team_members = $team_response['data'] ?? [];

            foreach ($team_members as $member) {
                $person_id = $member['relationships']['person']['data']['id'] ?? null;
                $team_id = $member['relationships']['team']['data']['id'] ?? null;

                if (!$person_id || !$team_id) continue;

                $team_name = $team_name_map[$team_id] ?? 'Unassigned';

                // Get position name for this person on this plan
                $position_name = 'Unassigned Position';
                if ($person_id) {
                    $schedule_response = $this->model->get_person_schedules($person_id);
                    $schedules = $schedule_response['data'] ?? [];

                    foreach ($schedules as $schedule) {
                        $schedule_plan_id = $schedule['relationships']['plan']['data']['id'] ?? null;
                        if ($schedule_plan_id == $plan_id) {
                            $position_name = $schedule['attributes']['position_name'] ??
                                $schedule['attributes']['team_position_name'] ??
                                $schedule['attributes']['assignment_name'] ??
                                'Unassigned Position';
                            break;
                        }
                    }
                }

                // Skip "Unassigned Position" entries
                if ($position_name === 'Unassigned Position') {
                    continue;
                }

                // Initialize tracking arrays
                if (!isset($team_position_member_ids[$team_name])) {
                    $team_position_member_ids[$team_name] = [];
                }
                if (!isset($team_position_member_ids[$team_name][$position_name])) {
                    $team_position_member_ids[$team_name][$position_name] = [];
                }

                // Track unique member for this position
                if (!in_array($person_id, $team_position_member_ids[$team_name][$position_name])) {
                    $team_position_member_ids[$team_name][$position_name][] = $person_id;
                }
            }
        }

        // Second pass: Calculate stats from selected date range only
        foreach ($plans as $plan) {
            $plan_id = $plan['id'];
            $team_response = $this->model->get_plan_team_members($plan_id);
            $team_members = $team_response['data'] ?? [];

            foreach ($team_members as $member) {
                $person_id = $member['relationships']['person']['data']['id'] ?? null;
                $team_id = $member['relationships']['team']['data']['id'] ?? null;

                if (!$person_id || !$team_id) continue;

                $status = $member['attributes']['status'] ?? 'N';
                $team_name = $team_name_map[$team_id] ?? 'Unassigned';

                // Get position name for this person on this plan
                $position_name = 'Unassigned Position';
                if ($person_id) {
                    $schedule_response = $this->model->get_person_schedules($person_id);
                    $schedules = $schedule_response['data'] ?? [];

                    foreach ($schedules as $schedule) {
                        $schedule_plan_id = $schedule['relationships']['plan']['data']['id'] ?? null;
                        if ($schedule_plan_id == $plan_id) {
                            $position_name = $schedule['attributes']['position_name'] ??
                                $schedule['attributes']['team_position_name'] ??
                                $schedule['attributes']['assignment_name'] ??
                                'Unassigned Position';
                            break;
                        }
                    }
                }

                // Skip "Unassigned Position" entries
                if ($position_name === 'Unassigned Position') {
                    continue;
                }

                // Initialize team if not exists
                if (!isset($team_position_stats[$team_name])) {
                    $team_position_stats[$team_name] = [];
                }

                // Initialize position if not exists
                if (!isset($team_position_stats[$team_name][$position_name])) {
                    $team_position_stats[$team_name][$position_name] = [
                        'scheduled' => 0,
                        'accepted' => 0,
                        'declined' => 0,
                        'pending' => 0,
                    ];
                }

                // Count statistics
                $team_position_stats[$team_name][$position_name]['scheduled']++;

                if ($status === 'C') {
                    $team_position_stats[$team_name][$position_name]['accepted']++;
                } elseif ($status === 'D') {
                    $team_position_stats[$team_name][$position_name]['declined']++;
                } elseif ($status === 'U') {
                    $team_position_stats[$team_name][$position_name]['pending']++;
                }
            }
        }

        // Sort teams alphabetically
        ksort($team_position_stats);

        // Sort positions within each team alphabetically
        foreach ($team_position_stats as $team_name => &$positions) {
            ksort($positions);
        }

        $back_url = admin_url('admin.php?page=pco-reports&start_date=' . urlencode($start_date) . '&end_date=' . urlencode($end_date));
        ?>

        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo esc_html($service_type_name); ?> - Team Reports</h1>
            <a href="<?php echo esc_url($back_url); ?>" class="page-title-action">← Back to Overview</a>
            <hr class="wp-header-end">

            <p style="font-size: 1.1em; color: #666; margin-bottom: 20px;">
                <strong><?php echo count($plans); ?></strong> plans from
                <strong><?php echo esc_html(date('M j, Y', strtotime($start_date))); ?></strong> to
                <strong><?php echo esc_html(date('M j, Y', strtotime($end_date))); ?></strong>
            </p>

            <?php if (empty($team_position_stats)): ?>
                <p>No team member data found for these plans.</p>
            <?php else: ?>
                <?php
                $team_index = 0;
                foreach ($team_position_stats as $team_name => $positions):
                    // Get total team roster count
                    $total_team_members = $team_roster_counts[$team_name] ?? 0;

                    // Find team_id for this team name
                    $team_id = $team_ids[$team_name] ?? null;

                    // Only first team is open by default
                    $is_open = ($team_index === 0);
                    $team_index++;
                    ?>
                    <details class="postbox" style="margin-bottom: 20px; border: 1px solid #ccd0d4; background: #fff;" <?php echo $is_open ? 'open' : ''; ?>>
                        <summary class="postbox-header" style="cursor: pointer; padding: 15px 20px; background: #f0f0f0; border-bottom: 1px solid #ddd;">
                            <h2 class="hndle" style="display: inline; cursor: pointer; margin: 0;">
                                <?php echo esc_html($team_name); ?>
                                <small style="color: #666; font-weight: normal; margin-left: 10px;">
                                    (<?php echo intval($total_team_members); ?> members)
                                </small>
                            </h2>
                        </summary>

                        <div class="inside" style="margin: 0; padding: 0;">
                            <table class="wp-list-table widefat fixed striped" style="border: none; margin: 0;">
                                <thead>
                                <tr>
                                    <th class="manage-column column-primary" style="width: 35%;">Members</th>
                                    <th class="manage-column" style="text-align: center; width: 16.25%;">Scheduled</th>
                                    <th class="manage-column" style="text-align: center; width: 16.25%;">Accepted</th>
                                    <th class="manage-column" style="text-align: center; width: 16.25%;">Declined</th>
                                    <th class="manage-column" style="text-align: center; width: 16.25%;">Pending</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($positions as $position_name => $stats):
                                    $acceptance_rate = $stats['scheduled'] > 0
                                        ? round(($stats['accepted'] / $stats['scheduled']) * 100, 1)
                                        : 0;

                                    // Get total member count for this position (from extended range)
                                    $position_total_members = count($team_position_member_ids[$team_name][$position_name] ?? []);

                                    $members_url = admin_url(sprintf(
                                        'admin.php?page=pco-reports&view=members&service_type=%s&team_id=%s&position=%s&start_date=%s&end_date=%s',
                                        urlencode($service_type_id),
                                        urlencode($team_id),
                                        urlencode($position_name),
                                        urlencode($start_date),
                                        urlencode($end_date)
                                    ));
                                    ?>
                                    <tr>
                                        <td class="column-primary">
                                            <a href="<?php echo esc_url($members_url); ?>" style="text-decoration: none;">
                                                <strong style="font-size: 14px;"><?php echo esc_html($position_name); ?></strong>
                                            </a>
                                            <small style="color: #666; display: block; margin-top: 3px;">
                                                <?php echo intval($position_total_members); ?> members | <?php echo esc_html($acceptance_rate); ?>% acceptance rate
                                            </small>
                                        </td>
                                        <td style="text-align: center;">
                                            <strong><?php echo intval($stats['scheduled']); ?></strong>
                                        </td>
                                        <td style="text-align: center; color: #46b450;">
                                            <strong><?php echo intval($stats['accepted']); ?></strong>
                                        </td>
                                        <td style="text-align: center; color: #dc3232;">
                                            <strong><?php echo intval($stats['declined']); ?></strong>
                                        </td>
                                        <td style="text-align: center; color: #e5a500;">
                                            <strong><?php echo intval($stats['pending']); ?></strong>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                <?php
                                // Calculate team totals
                                $team_totals = [
                                    'scheduled' => array_sum(array_column($positions, 'scheduled')),
                                    'accepted' => array_sum(array_column($positions, 'accepted')),
                                    'declined' => array_sum(array_column($positions, 'declined')),
                                    'pending' => array_sum(array_column($positions, 'pending')),
                                ];
                                ?>
                                <tr style="background: #f9f9f9; font-weight: bold;">
                                    <td>TEAM TOTAL (<?php echo intval($total_team_members); ?> total members)</td>
                                    <td style="text-align: center;"><?php echo intval($team_totals['scheduled']); ?></td>
                                    <td style="text-align: center; color: #46b450;"><?php echo intval($team_totals['accepted']); ?></td>
                                    <td style="text-align: center; color: #dc3232;"><?php echo intval($team_totals['declined']); ?></td>
                                    <td style="text-align: center; color: #e5a500;"><?php echo intval($team_totals['pending']); ?></td>
                                </tr>
                                </tfoot>
                            </table>
                        </div>
                    </details>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render Members Detail Page - Shows individual members for a team/position
     */
    private function render_members_detail_page($service_type_id, $team_id, $position_name) {
        $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : date('Y-m-01');
        $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : date('Y-m-t');

        // Get service type name
        $service_type_response = $this->model->get_single_service_type($service_type_id);
        $service_type_name = $service_type_response['attributes']['name'] ?? 'Service Type';

        // Get team name
        $teams_response = $this->model->get_all_teams();
        $team_name = 'Unknown Team';
        if (!empty($teams_response['data'])) {
            foreach ($teams_response['data'] as $team_obj) {
                if ($team_obj['id'] === $team_id) {
                    $team_name = $team_obj['attributes']['name'] ?? 'Unknown Team';
                    break;
                }
            }
        }

        // Fetch plans for this service type
        $plans_response = $this->model->get_plans_by_date_range($service_type_id, $start_date, $end_date);
        $plans = $plans_response['data'] ?? [];

        if (empty($plans)) {
            $back_url = admin_url(sprintf(
                'admin.php?page=pco-reports&view=teams&service_type=%s&start_date=%s&end_date=%s',
                urlencode($service_type_id),
                urlencode($start_date),
                urlencode($end_date)
            ));
            ?>
            <div class="wrap">
                <h1><?php echo esc_html($team_name); ?> - <?php echo esc_html($position_name); ?></h1>
                <a href="<?php echo esc_url($back_url); ?>" class="page-title-action">← Back to Teams</a>
                <hr>
                <div class="notice notice-info"><p>No plans found for the selected date range.</p></div>
            </div>
            <?php
            return;
        }

        // Aggregate statistics by person for this specific team/position
        $member_stats = [];

        foreach ($plans as $plan) {
            $plan_id = $plan['id'];
            $team_response = $this->model->get_plan_team_members($plan_id);
            $team_members = $team_response['data'] ?? [];

            foreach ($team_members as $member) {
                $person_id = $member['relationships']['person']['data']['id'] ?? null;
                $member_team_id = $member['relationships']['team']['data']['id'] ?? null;

                // Filter by team
                if (!$person_id || $member_team_id !== $team_id) continue;

                $person_name = $member['attributes']['name'] ?? 'Unknown';
                $status = $member['attributes']['status'] ?? 'N';

                // Get position for this person on this plan
                $member_position = 'Unassigned Position';
                if ($person_id) {
                    $schedule_response = $this->model->get_person_schedules($person_id);
                    $schedules = $schedule_response['data'] ?? [];

                    foreach ($schedules as $schedule) {
                        $schedule_plan_id = $schedule['relationships']['plan']['data']['id'] ?? null;
                        if ($schedule_plan_id == $plan_id) {
                            $member_position = $schedule['attributes']['position_name'] ??
                                $schedule['attributes']['team_position_name'] ??
                                $schedule['attributes']['assignment_name'] ??
                                'Unassigned Position';
                            break;
                        }
                    }
                }

                // Filter by position
                if ($member_position !== $position_name) continue;

                // Initialize person stats if not exists
                if (!isset($member_stats[$person_id])) {
                    $member_stats[$person_id] = [
                        'name' => $person_name,
                        'scheduled' => 0,
                        'accepted' => 0,
                        'declined' => 0,
                        'pending' => 0,
                    ];
                }

                // Count statistics
                $member_stats[$person_id]['scheduled']++;

                if ($status === 'C') {
                    $member_stats[$person_id]['accepted']++;
                } elseif ($status === 'D') {
                    $member_stats[$person_id]['declined']++;
                } elseif ($status === 'U') {
                    $member_stats[$person_id]['pending']++;
                }
            }
        }

        // Sort members by last name
        uasort($member_stats, function($a, $b) {
            $last_a = $this->get_last_name($a['name']);
            $last_b = $this->get_last_name($b['name']);
            return strcmp($last_a, $last_b);
        });

        $back_url = admin_url(sprintf(
            'admin.php?page=pco-reports&view=teams&service_type=%s&start_date=%s&end_date=%s',
            urlencode($service_type_id),
            urlencode($start_date),
            urlencode($end_date)
        ));
        ?>

        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo esc_html($team_name); ?> - <?php echo esc_html($position_name); ?></h1>
            <a href="<?php echo esc_url($back_url); ?>" class="page-title-action">← Back to Teams</a>
            <hr class="wp-header-end">

            <p style="font-size: 1.1em; color: #666; margin-bottom: 20px;">
                <?php echo esc_html($service_type_name); ?> |
                <strong><?php echo count($plans); ?></strong> plans from
                <strong><?php echo esc_html(date('M j, Y', strtotime($start_date))); ?></strong> to
                <strong><?php echo esc_html(date('M j, Y', strtotime($end_date))); ?></strong>
            </p>

            <?php if (empty($member_stats)): ?>
                <p>No members found for this position in the selected date range.</p>
            <?php else: ?>
                <div class="card" style="max-width: 100%; padding: 0;">
                    <table class="wp-list-table widefat fixed striped" style="border: none; margin: 0;">
                        <thead>
                        <tr>
                            <th class="manage-column column-primary" style="width: 35%;">Team Member</th>
                            <th class="manage-column" style="text-align: center; width: 16.25%;">Scheduled</th>
                            <th class="manage-column" style="text-align: center; width: 16.25%;">Accepted</th>
                            <th class="manage-column" style="text-align: center; width: 16.25%;">Declined</th>
                            <th class="manage-column" style="text-align: center; width: 16.25%;">Pending</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($member_stats as $person_id => $stats):
                            $acceptance_rate = $stats['scheduled'] > 0
                                ? round(($stats['accepted'] / $stats['scheduled']) * 100, 1)
                                : 0;
                            ?>
                            <tr>
                                <td class="column-primary">
                                    <strong><?php echo esc_html($stats['name']); ?></strong>
                                    <small style="color: #666; display: block; margin-top: 3px;">
                                        <?php echo esc_html($acceptance_rate); ?>% acceptance rate
                                    </small>
                                </td>
                                <td style="text-align: center;">
                                    <strong><?php echo intval($stats['scheduled']); ?></strong>
                                </td>
                                <td style="text-align: center; color: #46b450;">
                                    <strong><?php echo intval($stats['accepted']); ?></strong>
                                </td>
                                <td style="text-align: center; color: #dc3232;">
                                    <strong><?php echo intval($stats['declined']); ?></strong>
                                </td>
                                <td style="text-align: center; color: #e5a500;">
                                    <strong><?php echo intval($stats['pending']); ?></strong>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                        <?php
                        // Calculate totals
                        $totals = [
                            'scheduled' => array_sum(array_column($member_stats, 'scheduled')),
                            'accepted' => array_sum(array_column($member_stats, 'accepted')),
                            'declined' => array_sum(array_column($member_stats, 'declined')),
                            'pending' => array_sum(array_column($member_stats, 'pending')),
                        ];
                        ?>
                        <tr style="background: #f9f9f9; font-weight: bold;">
                            <td>TOTALS</td>
                            <td style="text-align: center;"><?php echo intval($totals['scheduled']); ?></td>
                            <td style="text-align: center; color: #46b450;"><?php echo intval($totals['accepted']); ?></td>
                            <td style="text-align: center; color: #dc3232;"><?php echo intval($totals['declined']); ?></td>
                            <td style="text-align: center; color: #e5a500;"><?php echo intval($totals['pending']); ?></td>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render Shortcodes Page
     */
    public function render_shortcodes_page() {
        if (!current_user_can('publish_posts')) return;

        $shortcode_usage = $this->scan_for_shortcodes();
        $available_shortcodes = $this->get_available_shortcodes_docs();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Shortcode Management', 'pco-aio'); ?></h1>
            <hr>
            <h2>Available Shortcodes</h2>
            <table class="wp-list-table widefat striped">
                <thead>
                <tr><th>Tag</th><th>App</th><th>Description</th></tr>
                </thead>
                <tbody>
                <?php foreach ($available_shortcodes as $tag => $doc) : ?>
                    <tr>
                        <td><code>[<?php echo esc_html($tag); ?>]</code></td>
                        <td><?php echo esc_html($doc['app']); ?></td>
                        <td><?php echo esc_html($doc['description']); ?> <br> <small><?php echo esc_html($doc['usage']); ?></small></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <br>
            <h2>Shortcode Usage Report</h2>
            <?php if (empty($shortcode_usage)) : ?>
                <div class="notice notice-warning"><p>No PCO shortcodes found in published posts/pages.</p></div>
            <?php else : ?>
                <table class="wp-list-table widefat striped">
                    <thead><tr><th>ID</th><th>Title</th><th>Found</th></tr></thead>
                    <tbody>
                    <?php foreach ($shortcode_usage as $post_id => $data) : ?>
                        <tr>
                            <td><?php echo absint($post_id); ?></td>
                            <td><strong><?php echo esc_html($data['title']); ?></strong> (<a href="<?php echo get_edit_post_link($post_id); ?>">Edit</a>)</td>
                            <td><?php foreach ($data['shortcodes'] as $s) echo "<code>[$s]</code> "; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    // --- Helper Methods ---

    private function get_available_shortcodes_docs() {
        return [
            'pco_calendar' => ['app' => 'Calendar', 'description' => 'Displays featured and upcoming events.', 'usage' => '[pco_calendar count=20]'],
            'pco_groups' => ['app' => 'Groups', 'description' => 'Displays active groups.', 'usage' => '[pco_groups count=5]'],
            'pco_registrations' => ['app' => 'Registrations', 'description' => 'Displays registration events.', 'usage' => '[pco_registrations]'],
            'pco_sermons' => ['app' => 'Publishing', 'description' => 'Displays recent sermons.', 'usage' => '[pco_sermons]'],
            'pco_services_plans' => ['app' => 'Services', 'description' => 'Displays future service plans.', 'usage' => '[pco_services_plans]'],
        ];
    }

    private function scan_for_shortcodes() {
        global $wpdb;
        $tags = array_keys($this->get_available_shortcodes_docs());
        $regex = '/\\[(' . implode('|', array_map('preg_quote', $tags)) . ')[\\s\\]]/';

        $query = new WP_Query(['post_type' => ['post', 'page'], 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids']);
        $results = [];

        if ($query->have_posts()) {
            foreach ($query->posts as $pid) {
                $content = $wpdb->get_var($wpdb->prepare("SELECT post_content FROM $wpdb->posts WHERE ID = %d", $pid));
                if (preg_match_all($regex, $content, $matches)) {
                    $results[$pid] = ['title' => get_the_title($pid), 'shortcodes' => array_unique($matches[1])];
                }
            }
        }
        return $results;
    }
}
