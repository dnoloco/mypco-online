<?php
/**
 * MyPCO Modules UI Controller
 * * Handles the rendering and AJAX toggling of plugin modules.
 */

if (!defined('ABSPATH')) exit;

class MyPCO_Modules {

    private $loader;
    private $api_model;
    private $module_manager;

    public function __construct($loader, $api_model) {
        $this->loader = $loader;
        $this->api_model = $api_model;

        // Ensure the manager is available
        if (class_exists('MyPCO_Module_Manager')) {
            $this->module_manager = new MyPCO_Module_Manager($this->loader, $this->api_model);
        }
    }

    /**
     * Initialize the Module UI hooks
     */
    public function init() {
        // Correct way to register AJAX in this boilerplate:
        $this->loader->add_action('wp_ajax_mypco_toggle_module', $this, 'ajax_toggle_module');
    }

    /**
     * Render the Modules Management Page
     */
    public function render_modules_page() {
        if (!$this->module_manager) {
            echo '<div class="notice notice-error"><p>Module Manager not found.</p></div>';
            return;
        }

        $modules = $this->module_manager->get_modules();
        // Assume license status is stored in options
        $license_status = get_option('mypco_license_status', 'inactive');
        $is_pro = ($license_status === 'active');

        ?>
        <div class="wrap">
            <h1>MyPCO Modules</h1>
            <p class="description">Enable or disable features to customize your integration experience.</p>

            <div class="mypco-modules-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; margin-top: 20px;">
                <?php foreach ($modules as $key => $module) :
                    $enabled = $this->module_manager->is_module_enabled($key);
                    $premium = isset($module['premium']) && $module['premium'];
                    $locked = ($premium && !$is_pro);
                    ?>
                    <div class="postbox mypco-module-card <?php echo $enabled ? 'is-active' : 'is-inactive'; ?>" style="margin-bottom: 0; display: flex; flex-direction: column;">
                        <div class="postbox-header" style="padding: 15px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                            <h2 style="margin:0; font-size: 1.1em;"><?php echo esc_html($module['name']); ?></h2>
                            <?php if ($premium) : ?>
                                <span class="mypco-badge-pro" style="background: #ffb900; color: #fff; font-size: 9px; padding: 2px 6px; border-radius: 3px; font-weight: bold;">PRO</span>
                            <?php endif; ?>
                        </div>

                        <div class="inside" style="padding: 15px; flex-grow: 1;">
                            <p style="color: #666; min-height: 40px;"><?php echo esc_html($module['description']); ?></p>

                            <div class="module-footer" style="margin-top: 20px; display: flex; justify-content: space-between; align-items: center;">
                                <div class="module-status">
                                    <?php if ($enabled) : ?>
                                        <span style="color: #46b450; font-weight: bold;"><span class="dashicons dashicons-yes"></span> Enabled</span>
                                    <?php else : ?>
                                        <span style="color: #999;"><span class="dashicons dashicons-no"></span> Disabled</span>
                                    <?php endif; ?>
                                </div>

                                <div class="module-action">
                                    <?php if ($locked) : ?>
                                        <a href="<?php echo admin_url('admin.php?page=mypco-license'); ?>" class="button button-secondary">Upgrade to Enable</a>
                                    <?php else : ?>
                                        <button type="button"
                                                class="button toggle-module-btn <?php echo $enabled ? 'button-secondary' : 'button-primary'; ?>"
                                                data-module="<?php echo esc_attr($key); ?>"
                                                data-action="<?php echo $enabled ? 'disable' : 'enable'; ?>">
                                            <?php echo $enabled ? 'Disable' : 'Enable'; ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <script>
            jQuery(document).ready(function($) {
                // Use 'on' with 'body' to ensure clicks are caught even if elements shift
                $('body').on('click', '.toggle-module-btn', function(e) {
                    e.preventDefault();

                    var $btn = $(this);
                    var moduleKey = $btn.attr('data-module');
                    var moduleAction = $btn.attr('data-action');

                    console.log('Attempting to ' + moduleAction + ' module: ' + moduleKey);

                    $btn.prop('disabled', true).text('Updating...');

                    $.post(ajaxurl, {
                        action: 'mypco_toggle_module',
                        module: moduleKey,
                        todo: moduleAction,
                        nonce: '<?php echo wp_create_nonce("mypco_module_toggle"); ?>'
                    }, function(res) {
                        console.log('Server Response:', res);
                        if (res.success) {
                            window.location.reload();
                        } else {
                            alert(res.data || 'Failed to update module.');
                            $btn.prop('disabled', false).text(moduleAction === 'enable' ? 'Enable' : 'Disable');
                        }
                    }).fail(function(xhr) {
                        console.error('AJAX Error:', xhr.responseText);
                        $btn.prop('disabled', false).text('Error - Try Again');
                    });
                });
            });
        </script>
        <?php
    }

    /**
     * AJAX Handler to toggle module state
     */
    public function ajax_toggle_module() {
        check_ajax_referer('mypco_module_toggle', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions.');
        }

        $module_key = sanitize_text_field($_POST['module']);

        // MATCH THE JS: Change 'module_action' to 'todo'
        $action = sanitize_text_field($_POST['todo']);

        if ($action === 'enable') {
            $this->module_manager->enable_module($module_key);
        } else {
            $this->module_manager->disable_module($module_key);
        }

        wp_send_json_success();
    }
}