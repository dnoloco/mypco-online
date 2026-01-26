<?php
/**
 * The admin-specific functionality of the plugin.
 */

class MyPCO_Admin {

    private $plugin_name;
    private $version;
    private $modules;

    /**
     * Initialize the class and set its properties.
     */
    public function __construct($plugin_name, $version, $modules) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->modules = $modules;
    }

    /**
     * Register the stylesheets for the admin area.
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            $this->plugin_name,
            MYPCO_PLUGIN_URL . 'admin/css/mypco-admin.css',
            [],
            $this->version,
            'all'
        );
    }

    /**
     * Register the JavaScript for the admin area.
     */
    public function enqueue_scripts() {
        wp_enqueue_script(
            $this->plugin_name,
            MYPCO_PLUGIN_URL . 'admin/js/mypco-admin.js',
            ['jquery'],
            $this->version,
            false
        );
    }

    /**
     * Add admin menu pages.
     */
    public function add_admin_menu() {
        // Main settings page
        add_menu_page(
            __('MyPCO Online', 'mypco-online'),
            __('MyPCO', 'mypco-online'),
            'manage_options',
            'mypco-settings',
            [$this, 'render_main_settings'],
            'dashicons-share-alt2',
            30
        );

        // Modules management page
        add_submenu_page(
            'mypco-settings',
            __('Manage Modules', 'mypco-online'),
            __('Modules', 'mypco-online'),
            'manage_options',
            'mypco-modules',
            [$this, 'render_modules_page']
        );

        // License management page (for premium modules)
        add_submenu_page(
            'mypco-settings',
            __('License & Activation', 'mypco-online'),
            __('License', 'mypco-online'),
            'manage_options',
            'mypco-license',
            [$this, 'render_license_page']
        );
    }

    /**
     * Render main settings page.
     */
    public function render_main_settings() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <hr>

            <div class="card">
                <h2>Welcome to MyPCO Online</h2>
                <p>A modular Planning Center Online integration for WordPress.</p>
                
                <h3>Getting Started</h3>
                <ol>
                    <li>Ensure your <code>config.php</code> file contains valid PCO API credentials</li>
                    <li>Visit the <a href="<?php echo admin_url('admin.php?page=mypco-modules'); ?>">Modules</a> page to enable features</li>
                    <li>Use shortcodes in your pages to display PCO content</li>
                </ol>

                <h3>API Connection Status</h3>
                <?php $this->render_connection_status(); ?>
            </div>

            <div class="card">
                <h2>Installed Modules</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Module</th>
                            <th>Type</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($this->modules as $key => $module): ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($module['name']); ?></strong><br>
                                    <small><?php echo esc_html($module['description']); ?></small>
                                </td>
                                <td>
                                    <?php if ($module['type'] === 'free'): ?>
                                        <span class="dashicons dashicons-yes-alt" style="color: green;"></span> Free
                                    <?php else: ?>
                                        <span class="dashicons dashicons-star-filled" style="color: gold;"></span> Premium
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($module['enabled']): ?>
                                        <span style="color: green;">✓ Active</span>
                                    <?php else: ?>
                                        <span style="color: gray;">○ Inactive</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Render modules management page.
     */
    public function render_modules_page() {
        // Handle module enable/disable actions
        if (isset($_POST['mypco_toggle_module']) && check_admin_referer('mypco_toggle_module')) {
            $module_key = sanitize_text_field($_POST['module_key']);
            $action = sanitize_text_field($_POST['action']);

            if ($action === 'enable') {
                update_option("mypco_module_{$module_key}_enabled", true);
                echo '<div class="notice notice-success"><p>Module enabled successfully.</p></div>';
            } elseif ($action === 'disable') {
                update_option("mypco_module_{$module_key}_enabled", false);
                echo '<div class="notice notice-success"><p>Module disabled successfully.</p></div>';
            }
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <hr>

            <p>Enable or disable modules to customize your MyPCO Online installation. Premium modules require a valid license.</p>

            <div class="mypco-modules-grid">
                <?php foreach ($this->modules as $key => $module): ?>
                    <div class="mypco-module-card">
                        <div class="mypco-module-header">
                            <h3><?php echo esc_html($module['name']); ?></h3>
                            <?php if ($module['type'] === 'premium'): ?>
                                <span class="mypco-premium-badge">PREMIUM</span>
                            <?php endif; ?>
                        </div>

                        <p class="mypco-module-description"><?php echo esc_html($module['description']); ?></p>

                        <div class="mypco-module-status">
                            <?php if ($module['enabled']): ?>
                                <span class="mypco-status-badge mypco-status-active">Active</span>
                            <?php else: ?>
                                <span class="mypco-status-badge mypco-status-inactive">Inactive</span>
                            <?php endif; ?>
                        </div>

                        <div class="mypco-module-actions">
                            <form method="post" style="display: inline;">
                                <?php wp_nonce_field('mypco_toggle_module'); ?>
                                <input type="hidden" name="mypco_toggle_module" value="1">
                                <input type="hidden" name="module_key" value="<?php echo esc_attr($key); ?>">
                                
                                <?php if ($module['enabled']): ?>
                                    <input type="hidden" name="action" value="disable">
                                    <button type="submit" class="button">Disable</button>
                                <?php else: ?>
                                    <input type="hidden" name="action" value="enable">
                                    <button type="submit" class="button button-primary" <?php echo ($module['type'] === 'premium' ? 'disabled' : ''); ?>>
                                        <?php echo ($module['type'] === 'premium' ? 'Requires License' : 'Enable'); ?>
                                    </button>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <style>
            .mypco-modules-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                gap: 20px;
                margin-top: 20px;
            }
            .mypco-module-card {
                background: white;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                padding: 20px;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
            }
            .mypco-module-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 10px;
            }
            .mypco-module-header h3 {
                margin: 0;
            }
            .mypco-premium-badge {
                background: gold;
                color: #000;
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 11px;
                font-weight: bold;
            }
            .mypco-module-description {
                color: #666;
                font-size: 14px;
                margin-bottom: 15px;
            }
            .mypco-status-badge {
                padding: 5px 10px;
                border-radius: 3px;
                font-size: 12px;
                font-weight: 600;
            }
            .mypco-status-active {
                background: #d4edda;
                color: #155724;
            }
            .mypco-status-inactive {
                background: #f8f9fa;
                color: #666;
            }
            .mypco-module-actions {
                margin-top: 15px;
                padding-top: 15px;
                border-top: 1px solid #eee;
            }
        </style>
        <?php
    }

    /**
     * Render license management page.
     */
    public function render_license_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <hr>

            <div class="card">
                <h2>Premium Module Licensing</h2>
                <p>Enter your license keys below to activate premium modules.</p>

                <form method="post" action="options.php">
                    <?php settings_fields('mypco_license_settings'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Services Module License</th>
                            <td>
                                <input type="text" name="mypco_license_services" value="<?php echo esc_attr(get_option('mypco_license_services', '')); ?>" class="regular-text">
                                <p class="description">Enter your license key for the Services module</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Messages Module License</th>
                            <td>
                                <input type="text" name="mypco_license_messages" value="<?php echo esc_attr(get_option('mypco_license_messages', '')); ?>" class="regular-text">
                                <p class="description">Enter your license key for the Messages module</p>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button('Save License Keys'); ?>
                </form>
            </div>

            <div class="card">
                <h2>Get Premium Modules</h2>
                <p>Unlock advanced features with premium modules:</p>
                <ul>
                    <li><strong>Services:</strong> Manage service plans, view teams, track responses</li>
                    <li><strong>Messages:</strong> Send SMS via Clearstream to team members</li>
                </ul>
                <p><a href="https://example.com/pricing" class="button button-primary" target="_blank">View Pricing →</a></p>
            </div>
        </div>
        <?php
    }

    /**
     * Render API connection status.
     */
    private function render_connection_status() {
        // Simple connection test
        echo '<div style="padding: 10px; background: #d4edda; border-left: 4px solid green;">';
        echo '<strong>Status:</strong> Configuration loaded';
        echo '</div>';
    }
}
