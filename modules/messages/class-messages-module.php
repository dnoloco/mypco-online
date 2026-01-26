<?php
/**
 * Messages Module (Premium)
 *
 * Handles Clearstream SMS integration for sending messages to team members.
 */

require_once MYPCO_PLUGIN_DIR . 'includes/class-mypco-module-base.php';

class MyPCO_Messages_Module extends MyPCO_Module_Base {

    protected $module_key = 'messages';
    protected $module_name = 'Messages';

    /**
     * Initialize the Messages module.
     */
    public function init() {
        // Add settings page for Clearstream configuration
        $this->add_admin_page(
            'mypco-settings',
            __('Message Settings', 'mypco-online'),
            __('Messages', 'mypco-online'),
            'manage_options',
            'mypco-messages',
            'render_settings_page'
        );

        // Register settings
        $this->loader->add_action('admin_init', $this, 'register_settings');
    }

    /**
     * Register Clearstream settings.
     */
    public function register_settings() {
        register_setting('mypco_messages_settings', 'mypco_clearstream_api_token');
        register_setting('mypco_messages_settings', 'mypco_clearstream_message_header');

        add_settings_section(
            'mypco_clearstream_section',
            'Clearstream API Configuration',
            [$this, 'render_settings_section'],
            'mypco-messages'
        );

        add_settings_field(
            'mypco_clearstream_api_token',
            'API Token',
            [$this, 'render_api_token_field'],
            'mypco-messages',
            'mypco_clearstream_section'
        );

        add_settings_field(
            'mypco_clearstream_message_header',
            'Message Header Value',
            [$this, 'render_message_header_field'],
            'mypco-messages',
            'mypco_clearstream_section'
        );
    }

    /**
     * Render settings section description.
     */
    public function render_settings_section() {
        echo '<p>Configure your Clearstream API credentials to enable SMS messaging.</p>';
    }

    /**
     * Render API token field.
     */
    public function render_api_token_field() {
        $token = get_option('mypco_clearstream_api_token');
        ?>
        <input type="password" 
               id="mypco_clearstream_api_token" 
               name="mypco_clearstream_api_token" 
               value="<?php echo esc_attr($token); ?>" 
               class="regular-text">
        <p class="description">Your Clearstream API Token (found in Clearstream settings).</p>
        <?php
    }

    /**
     * Render message header field.
     */
    public function render_message_header_field() {
        $header = get_option('mypco_clearstream_message_header');
        ?>
        <input type="text" 
               id="mypco_clearstream_message_header" 
               name="mypco_clearstream_message_header" 
               value="<?php echo esc_attr($header); ?>" 
               class="regular-text" 
               required>
        <p class="description">The message header value that identifies your message source.</p>
        <?php
    }

    /**
     * Render settings page.
     */
    public function render_settings_page() {
        // Check if settings have been saved
        if (isset($_GET['settings-updated'])) {
            add_settings_error(
                'mypco_messages',
                'mypco_message',
                'Settings saved successfully.',
                'updated'
            );
        }

        settings_errors('mypco_messages');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <hr>

            <form method="post" action="options.php">
                <?php
                settings_fields('mypco_messages_settings');
                do_settings_sections('mypco-messages');
                submit_button('Save Settings');
                ?>
            </form>

            <hr>

            <div class="card">
                <h2>About the Messages Module</h2>
                <p>The Messages module integrates with Clearstream to send SMS messages to team members scheduled in Planning Center Services.</p>
                
                <h3>Features</h3>
                <ul>
                    <li>Send bulk SMS to selected team members</li>
                    <li>Automatic phone number lookup from PCO People</li>
                    <li>Character counter with credit calculation</li>
                    <li>Message logging and history</li>
                    <li>Permission management</li>
                </ul>

                <h3>Setup Instructions</h3>
                <ol>
                    <li>Get your Clearstream API token from your Clearstream account</li>
                    <li>Enter your credentials above and save</li>
                    <li>Navigate to a service plan in the Services module</li>
                    <li>Select team members and click "Send Message"</li>
                </ol>

                <h3>Requirements</h3>
                <ul>
                    <li><strong>Active Clearstream Account</strong> - <a href="https://www.getclearstream.com/" target="_blank">Sign up here</a></li>
                    <li><strong>Services Module</strong> - Must be enabled</li>
                    <li><strong>Valid License</strong> - Premium feature</li>
                </ul>
            </div>
        </div>
        <?php
    }

    /**
     * Send SMS via Clearstream API.
     * 
     * This method would be called from the Services module when composing messages.
     * 
     * @param array $recipients Array of phone numbers
     * @param string $message Message content
     * @return array Result with success/error status
     */
    public function send_sms($recipients, $message) {
        $api_token = get_option('mypco_clearstream_api_token');
        $message_header = get_option('mypco_clearstream_message_header');

        if (empty($api_token) || empty($message_header)) {
            return [
                'success' => false,
                'error' => 'Clearstream credentials not configured'
            ];
        }

        // Prepare API request
        $data = [
            'message_header' => $message_header,
            'message' => $message,
            'recipients' => $recipients
        ];

        $response = wp_remote_post('https://api.getclearstream.com/v1/messages', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_token,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($data),
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message()
            ];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code === 200 || $status_code === 201) {
            // Log the message
            $this->log_message([
                'recipients' => count($recipients),
                'message' => $message,
                'timestamp' => current_time('mysql'),
                'status' => 'sent'
            ]);

            return [
                'success' => true,
                'message' => 'Message sent successfully'
            ];
        } else {
            return [
                'success' => false,
                'error' => $body['error'] ?? 'Unknown error occurred'
            ];
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
     * Log sent messages.
     */
    private function log_message($data) {
        $logs = get_option('mypco_message_logs', []);
        $logs[] = $data;
        
        // Keep only last 100 logs
        if (count($logs) > 100) {
            $logs = array_slice($logs, -100);
        }
        
        update_option('mypco_message_logs', $logs);
    }

    /**
     * Keep Messages menu active when on compose page from message log
     */
    public function set_messages_menu_active($parent_file) {
        global $submenu_file;

        // Check if we're on the compose page coming from message log
        if (isset($_GET['page']) && $_GET['page'] === 'mypco-services'
                && isset($_GET['view']) && $_GET['view'] === 'clearstream_compose'
                && isset($_GET['from']) && $_GET['from'] === 'messages') {
            $submenu_file = 'mypco-message-log';
        }

        return $parent_file;
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
     * Get message history.
     */
    public function get_message_history($limit = 20) {
        $logs = get_option('mypco_message_logs', []);
        return array_slice($logs, -$limit);
    }
}
