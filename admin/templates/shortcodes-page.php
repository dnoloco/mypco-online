<?php
/**
 * Shortcodes Configuration Page Template
 *
 * Displays all available shortcodes with documentation and usage scanning.
 *
 * Available variables:
 * - $shortcodes (array) - Available shortcodes organized by module
 * - $usage (array) - Pages/posts using shortcodes
 */

defined('ABSPATH') || exit;
?>

<div class="wrap mypco-shortcodes-page">
    <h1><?php _e('MyPCO Shortcodes', 'mypco-online'); ?></h1>
    <p class="description">
        <?php _e('Use these shortcodes to display Planning Center content on your site. Copy and paste into any page or post.', 'mypco-online'); ?>
    </p>
    <hr class="wp-header-end">

    <!-- Available Shortcodes -->
    <h2><?php _e('Available Shortcodes', 'mypco-online'); ?></h2>
    
    <?php foreach ($shortcodes as $module_key => $module_data): ?>
        <div class="mypco-shortcode-module">
            <h3 class="mypco-module-name">
                <span class="dashicons dashicons-admin-plugins"></span>
                <?php echo esc_html($module_data['module']); ?>
            </h3>

            <?php foreach ($module_data['shortcodes'] as $shortcode): ?>
                <div class="mypco-shortcode-card">
                    <!-- Shortcode Header -->
                    <div class="mypco-shortcode-header">
                        <div class="mypco-shortcode-tags">
                            <code class="mypco-shortcode-tag">[<?php echo esc_html($shortcode['tag']); ?>]</code>
                            <?php if ($shortcode['alt_tag']): ?>
                                <span class="mypco-or"><?php _e('or', 'mypco-online'); ?></span>
                                <code class="mypco-shortcode-tag">[<?php echo esc_html($shortcode['alt_tag']); ?>]</code>
                            <?php endif; ?>
                            <button class="button button-small mypco-copy-btn" data-shortcode="[<?php echo esc_attr($shortcode['tag']); ?>]">
                                <span class="dashicons dashicons-clipboard"></span>
                                <?php _e('Copy', 'mypco-online'); ?>
                            </button>
                        </div>
                        <p class="mypco-shortcode-description"><?php echo esc_html($shortcode['description']); ?></p>
                    </div>

                    <!-- Parameters -->
                    <?php if (!empty($shortcode['parameters'])): ?>
                        <div class="mypco-shortcode-section">
                            <h4><?php _e('Parameters', 'mypco-online'); ?></h4>
                            <table class="widefat">
                                <thead>
                                <tr>
                                    <th style="width: 120px;"><?php _e('Parameter', 'mypco-online'); ?></th>
                                    <th style="width: 80px;"><?php _e('Type', 'mypco-online'); ?></th>
                                    <th style="width: 100px;"><?php _e('Default', 'mypco-online'); ?></th>
                                    <th><?php _e('Description', 'mypco-online'); ?></th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($shortcode['parameters'] as $param): ?>
                                    <tr>
                                        <td><code><?php echo esc_html($param['name']); ?></code></td>
                                        <td><span class="mypco-param-type"><?php echo esc_html($param['type']); ?></span></td>
                                        <td><code><?php echo esc_html($param['default']); ?></code></td>
                                        <td><?php echo esc_html($param['description']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <!-- Examples -->
                    <?php if (!empty($shortcode['examples'])): ?>
                        <div class="mypco-shortcode-section">
                            <h4><?php _e('Examples', 'mypco-online'); ?></h4>
                            <div class="mypco-examples-list">
                                <?php foreach ($shortcode['examples'] as $example): ?>
                                    <div class="mypco-example">
                                        <code><?php echo esc_html($example); ?></code>
                                        <button class="button button-small mypco-copy-btn" data-shortcode="<?php echo esc_attr($example); ?>">
                                            <span class="dashicons dashicons-clipboard"></span>
                                            <?php _e('Copy', 'mypco-online'); ?>
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <!-- Shortcode Usage Report -->
    <div style="margin-top: 40px;">
        <h2><?php _e('Shortcode Usage Report', 'mypco-online'); ?></h2>
        <p class="description">
            <?php _e('Pages and posts where MyPCO shortcodes are currently used.', 'mypco-online'); ?>
        </p>

        <?php if (empty($usage)): ?>
            <div class="notice notice-info inline">
                <p><?php _e('No shortcodes found in published posts or pages.', 'mypco-online'); ?></p>
            </div>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                <tr>
                    <th style="width: 60px;"><?php _e('ID', 'mypco-online'); ?></th>
                    <th><?php _e('Title', 'mypco-online'); ?></th>
                    <th style="width: 100px;"><?php _e('Type', 'mypco-online'); ?></th>
                    <th style="width: 250px;"><?php _e('Shortcodes Found', 'mypco-online'); ?></th>
                    <th style="width: 150px;"><?php _e('Actions', 'mypco-online'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($usage as $item): ?>
                    <tr>
                        <td><?php echo intval($item['id']); ?></td>
                        <td>
                            <strong>
                                <a href="<?php echo esc_url($item['edit_link']); ?>">
                                    <?php echo esc_html($item['title']); ?>
                                </a>
                            </strong>
                        </td>
                        <td><?php echo esc_html(ucfirst($item['type'])); ?></td>
                        <td>
                            <?php foreach ($item['shortcodes'] as $tag): ?>
                                <code style="margin-right: 5px;">[<?php echo esc_html($tag); ?>]</code>
                            <?php endforeach; ?>
                        </td>
                        <td>
                            <a href="<?php echo esc_url($item['edit_link']); ?>" class="button button-small">
                                <?php _e('Edit', 'mypco-online'); ?>
                            </a>
                            <a href="<?php echo esc_url($item['view_link']); ?>" class="button button-small" target="_blank">
                                <?php _e('View', 'mypco-online'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Help Section -->
    <div class="mypco-help-section">
        <h2><?php _e('How to Use Shortcodes', 'mypco-online'); ?></h2>
        
        <div class="mypco-help-grid">
            <div class="mypco-help-card">
                <h3><span class="dashicons dashicons-admin-page"></span> <?php _e('In Pages/Posts', 'mypco-online'); ?></h3>
                <ol>
                    <li><?php _e('Edit any page or post', 'mypco-online'); ?></li>
                    <li><?php _e('Click the shortcode copy button above', 'mypco-online'); ?></li>
                    <li><?php _e('Paste into your content', 'mypco-online'); ?></li>
                    <li><?php _e('Preview or publish', 'mypco-online'); ?></li>
                </ol>
            </div>

            <div class="mypco-help-card">
                <h3><span class="dashicons dashicons-admin-appearance"></span> <?php _e('In Widgets', 'mypco-online'); ?></h3>
                <ol>
                    <li><?php _e('Go to Appearance → Widgets', 'mypco-online'); ?></li>
                    <li><?php _e('Add a "Shortcode" widget', 'mypco-online'); ?></li>
                    <li><?php _e('Paste the shortcode', 'mypco-online'); ?></li>
                    <li><?php _e('Save the widget', 'mypco-online'); ?></li>
                </ol>
            </div>

            <div class="mypco-help-card">
                <h3><span class="dashicons dashicons-editor-code"></span> <?php _e('In Templates', 'mypco-online'); ?></h3>
                <ol>
                    <li><?php _e('Open your theme template file', 'mypco-online'); ?></li>
                    <li><?php _e('Use do_shortcode() function', 'mypco-online'); ?></li>
                    <li><?php _e('Example:', 'mypco-online'); ?> <code>&lt;?php echo do_shortcode('[pco_calendar]'); ?&gt;</code></li>
                    <li><?php _e('Save the template', 'mypco-online'); ?></li>
                </ol>
            </div>
        </div>
    </div>

    <!-- Copy Success Message -->
    <div id="mypco-copy-message" class="notice notice-success" style="display: none; position: fixed; top: 32px; right: 20px; z-index: 9999;">
        <p><?php _e('Shortcode copied to clipboard!', 'mypco-online'); ?></p>
    </div>
</div>
