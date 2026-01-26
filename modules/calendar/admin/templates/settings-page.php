<?php
/**
 * Calendar Admin Settings Page Template
 *
 * Available variables:
 * - $shortcode (string)
 * - $old_shortcode (string)
 * - $cache_cleared (bool)
 * - $module_status (string)
 * - $features (array)
 */

defined('ABSPATH') || exit;
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php if ($cache_cleared): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Calendar cache cleared successfully!', 'mypco-online'); ?></p>
        </div>
    <?php endif; ?>

    <hr>

    <div class="card">
        <h2><?php _e('Calendar Module Settings', 'mypco-online'); ?></h2>
        <p><?php _e('The Calendar module displays events from Planning Center Online Calendar with multiple views.', 'mypco-online'); ?></p>

        <h3><?php _e('Shortcode Usage', 'mypco-online'); ?></h3>
        <p><?php _e('Use the following shortcode to display the calendar:', 'mypco-online'); ?></p>
        <p><code><?php echo esc_html($shortcode); ?></code></p>
        <p><em><?php printf(__('Note: The old %s shortcode still works for backward compatibility.', 'mypco-online'), '<code>' . esc_html($old_shortcode) . '</code>'); ?></em></p>

        <h3><?php _e('Features', 'mypco-online'); ?></h3>
        <ul>
            <?php foreach ($features as $feature): ?>
                <li><?php echo esc_html($feature); ?></li>
            <?php endforeach; ?>
        </ul>

        <h3><?php _e('Module Status', 'mypco-online'); ?></h3>
        <p><strong><?php _e('Status:', 'mypco-online'); ?></strong> <span style="color: green;">✓ <?php _e('Active (Free Module)', 'mypco-online'); ?></span></p>
    </div>

    <div class="card">
        <h2><?php _e('Cache Management', 'mypco-online'); ?></h2>
        <p><?php _e('Clear the calendar cache to fetch fresh data from Planning Center Online.', 'mypco-online'); ?></p>

        <form method="post" action="">
            <?php wp_nonce_field('mypco_clear_calendar_cache'); ?>
            <input type="hidden" name="mypco_clear_calendar_cache" value="1">
            <button type="submit" class="button button-secondary">
                <?php _e('Clear Calendar Cache', 'mypco-online'); ?>
            </button>
        </form>
    </div>

    <div class="card">
        <h2><?php _e('Shortcode Options', 'mypco-online'); ?></h2>
        <table class="widefat">
            <thead>
            <tr>
                <th><?php _e('Parameter', 'mypco-online'); ?></th>
                <th><?php _e('Description', 'mypco-online'); ?></th>
                <th><?php _e('Default', 'mypco-online'); ?></th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td><code>count</code></td>
                <td><?php _e('Maximum number of events to display', 'mypco-online'); ?></td>
                <td>100</td>
            </tr>
            </tbody>
        </table>

        <h4><?php _e('Example:', 'mypco-online'); ?></h4>
        <p><code>[mypco_calendar count="50"]</code></p>
    </div>
</div>
