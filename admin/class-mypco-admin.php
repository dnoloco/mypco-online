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
        // Get current screen
        $screen = get_current_screen();

        // Load dashboard styles for main settings page (for draggable metaboxes)
        if ($screen && $screen->id === 'toplevel_page_mypco-settings') {
            wp_enqueue_style('dashboard');
        }

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
        // Get current screen
        $screen = get_current_screen();

        // Load postbox scripts for main settings page (for draggable metaboxes)
        if ($screen && $screen->id === 'toplevel_page_mypco-settings') {
            wp_enqueue_script('postbox');
        }

        wp_enqueue_script(
                $this->plugin_name,
                MYPCO_PLUGIN_URL . 'admin/js/mypco-admin.js',
                ['jquery'],
                $this->version,
                false
        );
    }

    /**
     * Add admin submenu pages.
     * Parent menu 'mypco-settings' is already created by the main class early to avoid hook ordering issues.
     */
    public function add_admin_menu() {
        // Add main settings page as first submenu (replaces the parent menu link)
        add_submenu_page(
                'mypco-settings',
                __('MyPCO Settings', 'mypco-online'),
                __('Settings', 'mypco-online'),
                'manage_options',
                'mypco-settings',
                [$this, 'render_main_settings']
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
     * Render main settings page with draggable metaboxes.
     */
    public function render_main_settings() {
        // Get current screen
        $screen = get_current_screen();

        // Enable screen options for layout (required for metabox saving to work)
        add_screen_option('layout_columns', array(
                'max' => 3,
                'default' => 3
        ));

        // Set up default metabox order for first-time users
        $this->set_default_metabox_order($screen);

        // Add metaboxes for dashboard-style layout
        // Quick Links - Top Left (side, high priority)
        add_meta_box(
                'mypco_quick_links',
                __('Quick Links', 'mypco-online'),
                [$this, 'render_quick_links_metabox'],
                $screen->id,
                'side',
                'high'
        );

        // API Connection Status - Bottom Left (side, default priority)
        add_meta_box(
                'mypco_connection_status',
                __('API Connection Status', 'mypco-online'),
                [$this, 'render_connection_metabox'],
                $screen->id,
                'side',
                'default'
        );

        // Welcome - Top Center (normal, high priority)
        add_meta_box(
                'mypco_welcome',
                __('Welcome to MyPCO Online', 'mypco-online'),
                [$this, 'render_welcome_metabox'],
                $screen->id,
                'normal',
                'high'
        );

        // Support - Bottom Center (normal, default priority)
        add_meta_box(
                'mypco_support',
                __('Support & Documentation', 'mypco-online'),
                [$this, 'render_support_metabox'],
                $screen->id,
                'normal',
                'default'
        );

        // Manage Modules - Right (advanced, default priority)
        add_meta_box(
                'mypco_modules_overview',
                __('Installed Modules', 'mypco-online'),
                [$this, 'render_modules_metabox'],
                $screen->id,
                'advanced',
                'default'
        );

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <style>
                /* 3-column layout for metaboxes */
                #dashboard-widgets-wrap {
                    margin-top: 20px;
                }

                #dashboard-widgets.columns-3 .postbox-container {
                    width: 33.33%;
                    float: left;
                }

                /* Left column */
                #postbox-container-1 {
                    padding-right: 10px;
                }

                /* Center column */
                #postbox-container-2 {
                    padding: 0 5px;
                }

                /* Right column */
                #postbox-container-3 {
                    padding-left: 10px;
                }

                /* Responsive: Stack on smaller screens */
                @media screen and (max-width: 1280px) {
                    #dashboard-widgets.columns-3 #postbox-container-1,
                    #dashboard-widgets.columns-3 #postbox-container-2 {
                        width: 49%;
                    }
                    #dashboard-widgets.columns-3 #postbox-container-3 {
                        width: 100%;
                        clear: both;
                    }
                }

                @media screen and (max-width: 768px) {
                    #dashboard-widgets.columns-3 #postbox-container-1,
                    #dashboard-widgets.columns-3 #postbox-container-2,
                    #dashboard-widgets.columns-3 #postbox-container-3 {
                        width: 100%;
                        float: none;
                        padding: 0;
                    }
                }

                /* Clear floats */
                #dashboard-widgets:after {
                    content: "";
                    display: table;
                    clear: both;
                }
            </style>

            <div id="dashboard-widgets-wrap">
                <div id="dashboard-widgets" class="metabox-holder columns-3">
                    <!-- Left Column (Side) -->
                    <div id="postbox-container-1" class="postbox-container">
                        <?php do_meta_boxes($screen->id, 'side', null); ?>
                    </div>

                    <!-- Center Column (Normal) -->
                    <div id="postbox-container-2" class="postbox-container">
                        <?php do_meta_boxes($screen->id, 'normal', null); ?>
                    </div>

                    <!-- Right Column (Advanced) -->
                    <div id="postbox-container-3" class="postbox-container">
                        <?php do_meta_boxes($screen->id, 'advanced', null); ?>
                    </div>
                </div>
            </div>
        </div>

        <?php
        // Add the nonce and page hook for saving metabox positions
        wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false);
        wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false);
        ?>

        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Initialize postboxes (enables dragging and collapsing)
                postboxes.add_postbox_toggles('<?php echo $screen->id; ?>');

                // Save state after sorting/dragging
                $('.meta-box-sortables').on('sortupdate', function() {
                    postboxes.save_state('<?php echo $screen->id; ?>');
                });

                // Also save on postbox toggle (collapse/expand)
                $('.postbox .handlediv, .postbox .hndle').on('click', function() {
                    setTimeout(function() {
                        postboxes.save_state('<?php echo $screen->id; ?>');
                    }, 100);
                });
            });
        </script>
        <?php
    }

    /**
     * Set default metabox order for first-time users.
     */
    private function set_default_metabox_order($screen) {
        $user_id = get_current_user_id();
        $meta_key = 'meta-box-order_' . $screen->id;

        // Check if user already has a saved order
        $current_order = get_user_meta($user_id, $meta_key, true);

        // If no saved order exists, set the default
        if (empty($current_order)) {
            $default_order = array(
                    'side' => 'mypco_quick_links,mypco_connection_status',
                    'normal' => 'mypco_welcome,mypco_support',
                    'advanced' => 'mypco_modules_overview'
            );

            update_user_meta($user_id, $meta_key, $default_order);
        }
    }

    /**
     * Render Welcome metabox.
     */
    public function render_welcome_metabox() {
        ?>
        <p><?php _e('A modular Planning Center Online integration for WordPress.', 'mypco-online'); ?></p>

        <h4><?php _e('Getting Started', 'mypco-online'); ?></h4>
        <ol>
            <li><?php _e('Configure your API credentials in', 'mypco-online'); ?> <a href="<?php echo admin_url('admin.php?page=mypco-credentials'); ?>"><?php _e('API Credentials', 'mypco-online'); ?></a></li>
            <li><?php _e('Visit the', 'mypco-online'); ?> <a href="<?php echo admin_url('admin.php?page=mypco-modules'); ?>"><?php _e('Modules', 'mypco-online'); ?></a> <?php _e('page to enable features', 'mypco-online'); ?></li>
            <li><?php _e('Use shortcodes in your pages to display PCO content', 'mypco-online'); ?></li>
        </ol>
        <?php
    }

    /**
     * Render Quick Links metabox.
     */
    public function render_quick_links_metabox() {
        ?>
        <ul>
            <li><a href="<?php echo admin_url('admin.php?page=mypco-credentials'); ?>" class="dashicons-before dashicons-admin-network"><?php _e('API Credentials', 'mypco-online'); ?></a></li>
            <li><a href="<?php echo admin_url('admin.php?page=mypco-modules'); ?>" class="dashicons-before dashicons-admin-plugins"><?php _e('Manage Modules', 'mypco-online'); ?></a></li>
            <li><a href="<?php echo admin_url('admin.php?page=mypco-calendar'); ?>" class="dashicons-before dashicons-calendar-alt"><?php _e('Calendar Settings', 'mypco-online'); ?></a></li>
            <li><a href="<?php echo admin_url('admin.php?page=mypco-groups'); ?>" class="dashicons-before dashicons-groups"><?php _e('Groups Settings', 'mypco-online'); ?></a></li>
            <li><a href="<?php echo admin_url('admin.php?page=mypco-services'); ?>" class="dashicons-before dashicons-schedule"><?php _e('Service Plans', 'mypco-online'); ?></a></li>
        </ul>
        <style>
            #mypco_quick_links ul { margin: 0; }
            #mypco_quick_links li { margin: 0 0 10px 0; }
            #mypco_quick_links a { text-decoration: none; padding-left: 5px; }
            #mypco_quick_links a:hover { color: #2271b1; }
        </style>
        <?php
    }

    /**
     * Render Connection Status metabox.
     */
    public function render_connection_metabox() {
        $creds = MyPCO_Credentials_Manager::get_pco_credentials();
        $has_creds = !empty($creds['client_id']) && !empty($creds['secret_key']);

        if ($has_creds) {
            ?>
            <div style="padding: 10px; background: #d4edda; border-left: 4px solid #28a745; margin-bottom: 10px;">
                <strong style="color: #155724;"><?php _e('✓ PCO Connected', 'mypco-online'); ?></strong>
                <p style="margin: 5px 0 0 0; font-size: 13px; color: #155724;">
                    <?php _e('API credentials configured and ready.', 'mypco-online'); ?>
                </p>
            </div>
            <?php
        } else {
            ?>
            <div style="padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107; margin-bottom: 10px;">
                <strong style="color: #856404;"><?php _e('⚠ Not Configured', 'mypco-online'); ?></strong>
                <p style="margin: 5px 0 0 0; font-size: 13px; color: #856404;">
                    <?php _e('Please configure your Planning Center API credentials.', 'mypco-online'); ?>
                </p>
            </div>
            <?php
        }

        $clearstream_creds = MyPCO_Credentials_Manager::get_clearstream_credentials();
        $has_clearstream = !empty($clearstream_creds['api_token']);

        if ($has_clearstream) {
            ?>
            <div style="padding: 10px; background: #d4edda; border-left: 4px solid #28a745;">
                <strong style="color: #155724;"><?php _e('✓ Clearstream Connected', 'mypco-online'); ?></strong>
                <p style="margin: 5px 0 0 0; font-size: 13px; color: #155724;">
                    <?php _e('SMS messaging is available.', 'mypco-online'); ?>
                </p>
            </div>
            <?php
        } else {
            ?>
            <div style="padding: 10px; background: #e7f3ff; border-left: 4px solid #2196f3;">
                <strong style="color: #014361;"><?php _e('ℹ Clearstream Optional', 'mypco-online'); ?></strong>
                <p style="margin: 5px 0 0 0; font-size: 13px; color: #014361;">
                    <?php _e('Configure Clearstream for SMS messaging.', 'mypco-online'); ?>
                </p>
            </div>
            <?php
        }
    }

    /**
     * Render Modules Overview metabox.
     */
    public function render_modules_metabox() {
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
            <tr>
                <th style="width: 40%;"><?php _e('Module', 'mypco-online'); ?></th>
                <th style="width: 20%;"><?php _e('Type', 'mypco-online'); ?></th>
                <th style="width: 20%;"><?php _e('Status', 'mypco-online'); ?></th>
                <th style="width: 20%;"><?php _e('Actions', 'mypco-online'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($this->modules as $key => $module): ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html($module['name']); ?></strong><br>
                        <small style="color: #666;"><?php echo esc_html($module['description']); ?></small>
                    </td>
                    <td>
                        <?php if ($module['type'] === 'free'): ?>
                            <span class="dashicons dashicons-yes-alt" style="color: green;"></span> <?php _e('Free', 'mypco-online'); ?>
                        <?php else: ?>
                            <span class="dashicons dashicons-star-filled" style="color: gold;"></span> <?php _e('Premium', 'mypco-online'); ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($module['enabled']): ?>
                            <span style="color: green;">✓ <?php _e('Active', 'mypco-online'); ?></span>
                        <?php else: ?>
                            <span style="color: gray;">○ <?php _e('Inactive', 'mypco-online'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (isset($module['settings_page'])): ?>
                            <a href="<?php echo admin_url('admin.php?page=' . $module['settings_page']); ?>" class="button button-small">
                                <?php _e('Settings', 'mypco-online'); ?>
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p style="text-align: right; margin-top: 10px;">
            <a href="<?php echo admin_url('admin.php?page=mypco-modules'); ?>" class="button button-primary">
                <?php _e('Manage All Modules', 'mypco-online'); ?> →
            </a>
        </p>
        <?php
    }

    /**
     * Render Support metabox.
     */
    public function render_support_metabox() {
        ?>
        <h4><?php _e('Need Help?', 'mypco-online'); ?></h4>
        <ul>
            <li><a href="https://docs.planningcenter.com" target="_blank"><?php _e('Planning Center Documentation', 'mypco-online'); ?> ↗</a></li>
            <li><a href="https://api.planningcenteronline.com/oauth/applications" target="_blank"><?php _e('PCO Developer Console', 'mypco-online'); ?> ↗</a></li>
        </ul>

        <h4><?php _e('Plugin Info', 'mypco-online'); ?></h4>
        <p>
            <strong><?php _e('Version:', 'mypco-online'); ?></strong> <?php echo MYPCO_VERSION; ?><br>
            <strong><?php _e('Modules:', 'mypco-online'); ?></strong> <?php echo count($this->modules); ?> <?php _e('installed', 'mypco-online'); ?>
        </p>
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

            <p>Enable or disable modules to customize your MyPCO Online installation.</p>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                <tr>
                    <th>Module</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($this->modules as $key => $module): ?>
                    <tr>
                        <td><strong><?php echo esc_html($module['name']); ?></strong></td>
                        <td>
                            <?php if ($module['type'] === 'free'): ?>
                                <span class="dashicons dashicons-yes-alt" style="color: green;"></span> Free
                            <?php else: ?>
                                <span class="dashicons dashicons-star-filled" style="color: gold;"></span> Premium
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($module['description']); ?></td>
                        <td>
                            <?php if ($module['enabled']): ?>
                                <span style="color: green;">✓ Active</span>
                            <?php else: ?>
                                <span style="color: gray;">○ Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="post" style="display:inline;">
                                <?php wp_nonce_field('mypco_toggle_module'); ?>
                                <input type="hidden" name="mypco_toggle_module" value="1">
                                <input type="hidden" name="module_key" value="<?php echo esc_attr($key); ?>">
                                <?php if ($module['enabled']): ?>
                                    <input type="hidden" name="action" value="disable">
                                    <button type="submit" class="button">Disable</button>
                                <?php else: ?>
                                    <input type="hidden" name="action" value="enable">
                                    <button type="submit" class="button button-primary">Enable</button>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
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
            <p>License management coming soon.</p>
        </div>
        <?php
    }

    /**
     * Render API connection status.
     */
    private function render_connection_status() {
        echo '<p><span class="dashicons dashicons-yes-alt" style="color: green;"></span> Connected to Planning Center Online</p>';
    }
}
