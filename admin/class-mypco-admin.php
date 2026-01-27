<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * Implements WordPress dashboard-style draggable widgets with:
 * - jQuery UI Sortable for drag-and-drop
 * - AJAX persistence of widget order
 * - Separated CSS/JS files
 * - WordPress postbox system
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

        // Register AJAX handlers
        add_action('wp_ajax_mypco_save_dashboard_order', [$this, 'ajax_save_dashboard_order']);
    }

    /**
     * Register the stylesheets for the admin area.
     */
    public function enqueue_styles() {
        $screen = get_current_screen();

        // Load dashboard styles for main settings page
        if ($screen && $screen->id === 'toplevel_page_mypco-settings') {
            // WordPress core dashboard styles
            wp_enqueue_style('dashboard');

            // Our custom dashboard widget styles
            wp_enqueue_style(
                    $this->plugin_name . '-dashboard',
                    MYPCO_PLUGIN_URL . 'admin/assets/css/mypco-dashboard.css',
                    array('dashboard'),
                    $this->version,
                    'all'
            );
        }

        // General admin styles
        wp_enqueue_style(
                $this->plugin_name,
                MYPCO_PLUGIN_URL . 'admin/assets/css/mypco-admin.css',
                [],
                $this->version,
                'all'
        );
    }

    /**
     * Register the JavaScript for the admin area.
     */
    public function enqueue_scripts() {
        $screen = get_current_screen();

        // Load postbox scripts for main settings page
        if ($screen && $screen->id === 'toplevel_page_mypco-settings') {
            // WordPress core scripts for metabox functionality
            wp_enqueue_script('postbox');
            wp_enqueue_script('jquery-ui-sortable');

            // Our custom dashboard JavaScript
            wp_enqueue_script(
                    $this->plugin_name . '-dashboard',
                    MYPCO_PLUGIN_URL . 'admin/assets/js/mypco-dashboard.js',
                    array('jquery', 'postbox', 'jquery-ui-sortable'),
                    $this->version,
                    true
            );
        }

        // General admin scripts
        wp_enqueue_script(
                $this->plugin_name,
                MYPCO_PLUGIN_URL . 'admin/assets/js/mypco-admin.js',
                ['jquery'],
                $this->version,
                false
        );
    }

    /**
     * Add admin submenu pages.
     */
    public function add_admin_menu() {
        // Add main settings page as first submenu
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

        // License management page
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
     * Handle AJAX request to save dashboard widget order.
     */
    public function ajax_save_dashboard_order() {
        // Verify nonce
        check_ajax_referer('mypco-dashboard-nonce', '_ajax_nonce');

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        // Get data
        $page = isset($_POST['page']) ? sanitize_text_field($_POST['page']) : '';
        $order = isset($_POST['order']) ? $_POST['order'] : array();
        $hidden = isset($_POST['hidden']) ? sanitize_text_field($_POST['hidden']) : '';

        if (empty($page)) {
            wp_send_json_error('Missing page parameter');
        }

        $user_id = get_current_user_id();

        // Save metabox order
        if (!empty($order) && is_array($order)) {
            $meta_key = 'meta-box-order_' . $page;
            update_user_meta($user_id, $meta_key, $order);
        }

        // Save closed/hidden metaboxes
        if ($hidden !== '') {
            $meta_key = 'closedpostboxes_' . $page;
            $hidden_array = explode(',', $hidden);
            $hidden_array = array_filter(array_map('trim', $hidden_array));
            update_user_meta($user_id, $meta_key, $hidden_array);
        }

        wp_send_json_success('Dashboard order saved');
    }

    /**
     * Render main settings page with draggable metaboxes.
     */
    public function render_main_settings() {
        // Get current screen
        $screen = get_current_screen();
        $pagenow = $screen->id;

        $user_id = get_current_user_id();

        // === DIAGNOSTIC INFO ===
        $diagnostics = array();
        $diagnostics['screen_id'] = $pagenow;
        $diagnostics['user_id'] = $user_id;

        // Check all possible hidden meta keys
        $possible_hidden_keys = array(
                'metaboxhidden_' . $pagenow,
                'metaboxhidden_toplevel_page_mypco-settings',
                'metaboxhidden_mypco_page_mypco-settings',
        );

        foreach ($possible_hidden_keys as $key) {
            $value = get_user_meta($user_id, $key, true);
            if (!empty($value)) {
                $diagnostics['hidden_meta'][$key] = $value;
            }
        }

        // FORCE DELETE all possible variations
        delete_user_meta($user_id, 'metaboxhidden_' . $pagenow);
        delete_user_meta($user_id, 'metaboxhidden_toplevel_page_mypco-settings');
        delete_user_meta($user_id, 'metaboxhidden_mypco_page_mypco-settings');

        // Enable screen options for columns
        add_screen_option('layout_columns', array(
                'max' => 3,
                'default' => 3
        ));

        // Set default metabox order for first-time users
        $this->set_default_metabox_order($pagenow);

        // Register all metaboxes
        $this->register_dashboard_widgets($pagenow);

        // Check what was actually registered
        global $wp_meta_boxes;
        $diagnostics['registered_boxes'] = array();
        if (isset($wp_meta_boxes[$pagenow])) {
            foreach ($wp_meta_boxes[$pagenow] as $context => $priorities) {
                if (is_array($priorities)) {
                    foreach ($priorities as $priority => $boxes) {
                        if (is_array($boxes)) {
                            foreach ($boxes as $box_id => $box) {
                                $diagnostics['registered_boxes'][] = array(
                                        'id' => $box_id,
                                        'context' => $context,
                                        'priority' => $priority,
                                        'title' => isset($box['title']) ? $box['title'] : 'N/A'
                                );
                            }
                        }
                    }
                }
            }
        }

        // Render the page
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <!-- DIAGNOSTIC OUTPUT -->
            <div class="notice notice-info">
                <h3>🔍 Diagnostic Information</h3>
                <pre style="background: #f0f0f0; padding: 15px; overflow: auto; max-height: 300px;"><?php
                    echo "Screen ID: " . esc_html($diagnostics['screen_id']) . "\n";
                    echo "User ID: " . esc_html($diagnostics['user_id']) . "\n\n";

                    echo "Registered Metaboxes (" . count($diagnostics['registered_boxes']) . "):\n";
                    foreach ($diagnostics['registered_boxes'] as $box) {
                        echo "  - {$box['id']} ({$box['context']}) - {$box['title']}\n";
                    }

                    if (!empty($diagnostics['hidden_meta'])) {
                        echo "\nHidden Meta Found (THIS IS THE PROBLEM!):\n";
                        print_r($diagnostics['hidden_meta']);
                    } else {
                        echo "\nNo hidden meta found.\n";
                    }
                    ?></pre>
                <p><strong>✅ Metaboxes are registered!</strong></p>
                <p><strong>Next step:</strong> View Page Source (Ctrl+U) and search for "DEBUG: render_" to see if callbacks are being executed.</p>
                <p>If you see "<!-- DEBUG: render_welcome_metabox called -->" in the source, callbacks ARE running but content isn't visible.</p>
                <p>If you DON'T see those comments, callbacks AREN'T running - copy the diagnostic info above and the error log.</p>
            </div>

            <!-- Hidden nonce for AJAX -->
            <input type="hidden" id="mypco-dashboard-nonce" value="<?php echo wp_create_nonce('mypco-dashboard-nonce'); ?>">

            <!-- Dashboard widgets container -->
            <div id="dashboard-widgets-wrap">
                <div id="dashboard-widgets" class="metabox-holder columns-3" data-pagenow="<?php echo esc_attr($pagenow); ?>">

                    <!-- Left Column (Side) -->
                    <div id="postbox-container-1" class="postbox-container">
                        <div id="side-sortables" class="meta-box-sortables ui-sortable">

                            <!-- Quick Links Widget -->
                            <div id="mypco_quick_links" class="postbox">
                                <div class="postbox-header">
                                    <h2 class="hndle ui-sortable-handle"><?php _e('Quick Links', 'mypco-online'); ?></h2>
                                    <div class="handle-actions">
                                        <button type="button" class="handlediv" aria-expanded="true">
                                            <span class="screen-reader-text"><?php _e('Toggle panel: Quick Links', 'mypco-online'); ?></span>
                                            <span class="toggle-indicator" aria-hidden="true"></span>
                                        </button>
                                    </div>
                                </div>
                                <div class="inside">
                                    <?php $this->render_quick_links_metabox(); ?>
                                </div>
                            </div>

                            <!-- API Connection Status Widget -->
                            <div id="mypco_connection_status" class="postbox">
                                <div class="postbox-header">
                                    <h2 class="hndle ui-sortable-handle"><?php _e('API Connection Status', 'mypco-online'); ?></h2>
                                    <div class="handle-actions">
                                        <button type="button" class="handlediv" aria-expanded="true">
                                            <span class="screen-reader-text"><?php _e('Toggle panel: API Connection Status', 'mypco-online'); ?></span>
                                            <span class="toggle-indicator" aria-hidden="true"></span>
                                        </button>
                                    </div>
                                </div>
                                <div class="inside">
                                    <?php $this->render_connection_metabox(); ?>
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- Center Column (Normal) -->
                    <div id="postbox-container-2" class="postbox-container">
                        <div id="normal-sortables" class="meta-box-sortables ui-sortable">

                            <!-- Welcome Widget -->
                            <div id="mypco_welcome" class="postbox">
                                <div class="postbox-header">
                                    <h2 class="hndle ui-sortable-handle"><?php _e('Welcome to MyPCO Online', 'mypco-online'); ?></h2>
                                    <div class="handle-actions">
                                        <button type="button" class="handlediv" aria-expanded="true">
                                            <span class="screen-reader-text"><?php _e('Toggle panel: Welcome', 'mypco-online'); ?></span>
                                            <span class="toggle-indicator" aria-hidden="true"></span>
                                        </button>
                                    </div>
                                </div>
                                <div class="inside">
                                    <?php $this->render_welcome_metabox(); ?>
                                </div>
                            </div>

                            <!-- Support Widget -->
                            <div id="mypco_support" class="postbox">
                                <div class="postbox-header">
                                    <h2 class="hndle ui-sortable-handle"><?php _e('Support & Documentation', 'mypco-online'); ?></h2>
                                    <div class="handle-actions">
                                        <button type="button" class="handlediv" aria-expanded="true">
                                            <span class="screen-reader-text"><?php _e('Toggle panel: Support', 'mypco-online'); ?></span>
                                            <span class="toggle-indicator" aria-hidden="true"></span>
                                        </button>
                                    </div>
                                </div>
                                <div class="inside">
                                    <?php $this->render_support_metabox(); ?>
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- Right Column (Advanced) -->
                    <div id="postbox-container-3" class="postbox-container">
                        <div id="advanced-sortables" class="meta-box-sortables ui-sortable">

                            <!-- Modules Overview Widget -->
                            <div id="mypco_modules_overview" class="postbox">
                                <div class="postbox-header">
                                    <h2 class="hndle ui-sortable-handle"><?php _e('Installed Modules', 'mypco-online'); ?></h2>
                                    <div class="handle-actions">
                                        <button type="button" class="handlediv" aria-expanded="true">
                                            <span class="screen-reader-text"><?php _e('Toggle panel: Modules', 'mypco-online'); ?></span>
                                            <span class="toggle-indicator" aria-hidden="true"></span>
                                        </button>
                                    </div>
                                </div>
                                <div class="inside">
                                    <?php $this->render_modules_metabox(); ?>
                                </div>
                            </div>

                        </div>
                    </div>

                </div><!-- #dashboard-widgets -->
            </div><!-- #dashboard-widgets-wrap -->

            <!-- FORCE VISIBILITY CSS -->
            <style>
                /* Force all metaboxes to be visible */
                #dashboard-widgets .postbox {
                    display: block !important;
                    visibility: visible !important;
                    opacity: 1 !important;
                    height: auto !important;
                    overflow: visible !important;
                }

                #dashboard-widgets .inside {
                    display: block !important;
                    visibility: visible !important;
                    opacity: 1 !important;
                }

                #dashboard-widgets .postbox-container {
                    display: block !important;
                    visibility: visible !important;
                }

                #dashboard-widgets .meta-box-sortables {
                    display: block !important;
                    visibility: visible !important;
                }

                /* Remove any "hide-if-js" or "hide-if-no-js" */
                .hide-if-js,
                .hide-if-no-js {
                    display: block !important;
                }
            </style>

        </div><!-- .wrap -->

        <?php
        // Output the postbox JavaScript initializer
        // This must be after the metaboxes are rendered
        ?>
        <script type="text/javascript">
            //<![CDATA[
            jQuery(document).ready( function($) {
                console.log('MyPCO Debug: DOM ready');

                // Check how many postboxes exist
                var postboxCount = $('.postbox').length;
                console.log('MyPCO Debug: Found ' + postboxCount + ' postboxes');

                // Check if any are hidden
                $('.postbox').each(function(index) {
                    var $box = $(this);
                    var id = $box.attr('id');
                    var isVisible = $box.is(':visible');
                    var display = $box.css('display');
                    var visibility = $box.css('visibility');
                    var opacity = $box.css('opacity');
                    var height = $box.css('height');

                    console.log('Postbox #' + index + ' (' + id + '): visible=' + isVisible + ', display=' + display + ', visibility=' + visibility + ', opacity=' + opacity + ', height=' + height);

                    // Force it to be visible
                    $box.removeClass('closed hide-if-js hide-if-no-js hidden');
                    $box.show();
                    $box.css({
                        'display': 'block',
                        'visibility': 'visible',
                        'opacity': '1'
                    });
                });

                // Close postboxes that should be closed
                $('.if-js-closed').removeClass('if-js-closed').addClass('closed');

                // Set up postboxes
                postboxes.add_postbox_toggles('<?php echo $pagenow; ?>');

                console.log('MyPCO Debug: postboxes initialized');
            });
            //]]>
        </script>
        <?php
    }

    /**
     * Register all dashboard widgets/metaboxes.
     */
    private function register_dashboard_widgets($pagenow) {
        // Validate that callbacks are callable
        $callbacks_check = array();
        $callbacks_check['render_quick_links_metabox'] = is_callable([$this, 'render_quick_links_metabox']);
        $callbacks_check['render_connection_metabox'] = is_callable([$this, 'render_connection_metabox']);
        $callbacks_check['render_welcome_metabox'] = is_callable([$this, 'render_welcome_metabox']);
        $callbacks_check['render_support_metabox'] = is_callable([$this, 'render_support_metabox']);
        $callbacks_check['render_modules_metabox'] = is_callable([$this, 'render_modules_metabox']);

        // Log any callbacks that aren't callable
        foreach ($callbacks_check as $callback => $is_callable) {
            if (!$is_callable) {
                error_log("MyPCO: Callback {$callback} is NOT callable!");
            }
        }

        // LEFT COLUMN (side context)
        add_meta_box(
                'mypco_quick_links',
                __('Quick Links', 'mypco-online'),
                [$this, 'render_quick_links_metabox'],
                $pagenow,
                'side',
                'high'
        );

        add_meta_box(
                'mypco_connection_status',
                __('API Connection Status', 'mypco-online'),
                [$this, 'render_connection_metabox'],
                $pagenow,
                'side',
                'default'
        );

        // CENTER COLUMN (normal context)
        add_meta_box(
                'mypco_welcome',
                __('Welcome to MyPCO Online', 'mypco-online'),
                [$this, 'render_welcome_metabox'],
                $pagenow,
                'normal',
                'high'
        );

        add_meta_box(
                'mypco_support',
                __('Support & Documentation', 'mypco-online'),
                [$this, 'render_support_metabox'],
                $pagenow,
                'normal',
                'default'
        );

        // RIGHT COLUMN (advanced context)
        add_meta_box(
                'mypco_modules_overview',
                __('Installed Modules', 'mypco-online'),
                [$this, 'render_modules_metabox'],
                $pagenow,
                'advanced',
                'high'
        );
    }

    /**
     * Set default metabox order for first-time users.
     */
    private function set_default_metabox_order($pagenow) {
        $user_id = get_current_user_id();
        $meta_key = 'meta-box-order_' . $pagenow;

        // Check if user already has a saved order
        $current_order = get_user_meta($user_id, $meta_key, true);

        // If no saved order exists, set the default
        if (empty($current_order)) {
            $default_order = array(
                    'side-sortables' => 'mypco_quick_links,mypco_connection_status',
                    'normal-sortables' => 'mypco_welcome,mypco_support',
                    'advanced-sortables' => 'mypco_modules_overview'
            );

            update_user_meta($user_id, $meta_key, $default_order);
        }
    }

    /**
     * Render Welcome metabox.
     */
    public function render_welcome_metabox() {
        echo "<!-- DEBUG: render_welcome_metabox called -->\n";
        ?>
        <p><?php _e('A modular Planning Center Online integration for WordPress.', 'mypco-online'); ?></p>

        <h4><?php _e('Getting Started', 'mypco-online'); ?></h4>
        <ol>
            <li><?php _e('Configure your API credentials in', 'mypco-online'); ?> <a href="<?php echo admin_url('admin.php?page=mypco-credentials'); ?>"><?php _e('API Credentials', 'mypco-online'); ?></a></li>
            <li><?php _e('Visit the', 'mypco-online'); ?> <a href="<?php echo admin_url('admin.php?page=mypco-modules'); ?>"><?php _e('Modules', 'mypco-online'); ?></a> <?php _e('page to enable features', 'mypco-online'); ?></li>
            <li><?php _e('Use shortcodes in your pages to display PCO content', 'mypco-online'); ?></li>
        </ol>
        <?php
        echo "<!-- DEBUG: render_welcome_metabox completed -->\n";
    }

    /**
     * Render Quick Links metabox.
     */
    public function render_quick_links_metabox() {
        echo "<!-- DEBUG: render_quick_links_metabox called -->\n";
        ?>
        <ul>
            <li><a href="<?php echo admin_url('admin.php?page=mypco-credentials'); ?>" class="dashicons-before dashicons-admin-network"><?php _e('API Credentials', 'mypco-online'); ?></a></li>
            <li><a href="<?php echo admin_url('admin.php?page=mypco-modules'); ?>" class="dashicons-before dashicons-admin-plugins"><?php _e('Manage Modules', 'mypco-online'); ?></a></li>
            <li><a href="<?php echo admin_url('admin.php?page=mypco-calendar'); ?>" class="dashicons-before dashicons-calendar-alt"><?php _e('Calendar Settings', 'mypco-online'); ?></a></li>
            <li><a href="<?php echo admin_url('admin.php?page=mypco-groups'); ?>" class="dashicons-before dashicons-groups"><?php _e('Groups Settings', 'mypco-online'); ?></a></li>
            <li><a href="<?php echo admin_url('admin.php?page=mypco-services'); ?>" class="dashicons-before dashicons-schedule"><?php _e('Service Plans', 'mypco-online'); ?></a></li>
        </ul>
        <?php
        echo "<!-- DEBUG: render_quick_links_metabox completed -->\n";
    }

    /**
     * Render Connection Status metabox.
     */
    public function render_connection_metabox() {
        echo "<!-- DEBUG: render_connection_metabox called -->\n";

        // Check if credentials manager class exists
        if (!class_exists('MyPCO_Credentials_Manager')) {
            ?>
            <div class="mypco-status-box mypco-status-warning">
                <strong><?php _e('⚠ Configuration Required', 'mypco-online'); ?></strong>
                <p><?php _e('Credentials manager not loaded. Please check plugin installation.', 'mypco-online'); ?></p>
            </div>
            <?php
            echo "<!-- DEBUG: render_connection_metabox completed (no credentials manager) -->\n";
            return;
        }

        $creds = MyPCO_Credentials_Manager::get_pco_credentials();
        $has_creds = !empty($creds['client_id']) && !empty($creds['secret_key']);

        if ($has_creds) {
            ?>
            <div class="mypco-status-box mypco-status-connected">
                <strong><?php _e('✓ PCO Connected', 'mypco-online'); ?></strong>
                <p><?php _e('API credentials configured and ready.', 'mypco-online'); ?></p>
            </div>
            <?php
        } else {
            ?>
            <div class="mypco-status-box mypco-status-warning">
                <strong><?php _e('⚠ Not Configured', 'mypco-online'); ?></strong>
                <p><?php _e('Please configure your Planning Center API credentials.', 'mypco-online'); ?></p>
            </div>
            <?php
        }

        $clearstream_creds = MyPCO_Credentials_Manager::get_clearstream_credentials();
        $has_clearstream = !empty($clearstream_creds['api_token']);

        if ($has_clearstream) {
            ?>
            <div class="mypco-status-box mypco-status-connected">
                <strong><?php _e('✓ Clearstream Connected', 'mypco-online'); ?></strong>
                <p><?php _e('SMS messaging is available.', 'mypco-online'); ?></p>
            </div>
            <?php
        } else {
            ?>
            <div class="mypco-status-box mypco-status-info">
                <strong><?php _e('ℹ Clearstream Optional', 'mypco-online'); ?></strong>
                <p><?php _e('Configure Clearstream for SMS messaging.', 'mypco-online'); ?></p>
            </div>
            <?php
        }
        echo "<!-- DEBUG: render_connection_metabox completed -->\n";
    }

    /**
     * Render Modules Overview metabox.
     */
    public function render_modules_metabox() {
        echo "<!-- DEBUG: render_modules_metabox called -->\n";
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
        <div class="mypco-modules-actions">
            <a href="<?php echo admin_url('admin.php?page=mypco-modules'); ?>" class="button button-primary">
                <?php _e('Manage All Modules', 'mypco-online'); ?> →
            </a>
        </div>
        <?php
        echo "<!-- DEBUG: render_modules_metabox completed -->\n";
    }

    /**
     * Render Support metabox.
     */
    public function render_support_metabox() {
        echo "<!-- DEBUG: render_support_metabox called -->\n";
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
        echo "<!-- DEBUG: render_support_metabox completed -->\n";
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

            <p>Enable or disable modules to customize your MyPCO Online installation.</p>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                <tr>
                    <th>Module</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Actions</th>
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
                        <td>
                            <form method="post" style="display: inline;">
                                <?php wp_nonce_field('mypco_toggle_module'); ?>
                                <input type="hidden" name="module_key" value="<?php echo esc_attr($key); ?>">
                                <?php if ($module['enabled']): ?>
                                    <input type="hidden" name="action" value="disable">
                                    <button type="submit" name="mypco_toggle_module" class="button">Disable</button>
                                <?php else: ?>
                                    <input type="hidden" name="action" value="enable">
                                    <button type="submit" name="mypco_toggle_module" class="button button-primary">Enable</button>
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
     * Render license page.
     */
    public function render_license_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

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
}
