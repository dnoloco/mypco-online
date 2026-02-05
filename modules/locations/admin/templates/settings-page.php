<?php
/**
 * Locations Admin Page Template
 *
 * Renders either the shortcode list view or the edit/new shortcode view
 * based on which variables are available.
 *
 * List view variables:
 * - $shortcodes (array) - All shortcode configurations
 * - $cache_cleared (bool)
 * - $settings_saved (bool)
 * - $deleted (bool)
 * - $page_url (string)
 *
 * Edit view variables:
 * - $action (string) - 'edit' or 'new'
 * - $id (int) - Shortcode ID (0 for new)
 * - $shortcode (array) - Shortcode settings
 * - $type (string) - 'next_sunday' or 'sunday_list'
 * - $page_url (string)
 * - $is_default (bool)
 */

defined('ABSPATH') || exit;

// Determine which view to render
$is_edit_view = isset($action) && in_array($action, ['edit', 'new']);
?>

<div class="wrap mypco-locations-admin">

<?php if ($is_edit_view): ?>
    <?php // ================================================================
          // EDIT / NEW SHORTCODE VIEW
          // ================================================================ ?>

    <h1>
        <?php if ($action === 'edit'): ?>
            <?php printf(__('Edit Shortcode #%d', 'mypco-online'), $id); ?>
        <?php else: ?>
            <?php _e('Add New Shortcode', 'mypco-online'); ?>
        <?php endif; ?>
        <a href="<?php echo esc_url($page_url); ?>" class="page-title-action"><?php _e('Back to Locations', 'mypco-online'); ?></a>
    </h1>

    <hr class="wp-header-end">

    <?php if ($action === 'edit'): ?>
        <div class="mypco-shortcode-preview-bar">
            <strong><?php _e('Shortcode:', 'mypco-online'); ?></strong>
            <?php
            $shortcode_tag = ($type === 'next_sunday') ? 'mypco_next_sunday' : 'mypco_sunday_list';
            $preview_code = '[' . $shortcode_tag . ' id="' . $id . '"]';
            ?>
            <code id="shortcode-preview"><?php echo esc_html($preview_code); ?></code>
            <button type="button" class="button button-small mypco-copy-btn" data-copy="<?php echo esc_attr($preview_code); ?>">
                <?php _e('Copy', 'mypco-online'); ?>
            </button>
        </div>
    <?php endif; ?>

    <form method="post" action="">
        <?php wp_nonce_field('mypco_save_shortcode'); ?>
        <input type="hidden" name="mypco_save_shortcode" value="1">
        <input type="hidden" name="shortcode_id" value="<?php echo esc_attr($id); ?>">

        <div class="card">
            <h2><?php _e('General Settings', 'mypco-online'); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="shortcode_name"><?php _e('Label', 'mypco-online'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="shortcode_name" name="shortcode_name"
                               value="<?php echo esc_attr($shortcode['name'] ?? ''); ?>"
                               class="regular-text" placeholder="<?php esc_attr_e('e.g., Homepage Banner, Sidebar Widget', 'mypco-online'); ?>">
                        <p class="description">
                            <?php _e('A friendly name to identify this shortcode in the list.', 'mypco-online'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="shortcode_type"><?php _e('Shortcode Type', 'mypco-online'); ?></label>
                    </th>
                    <td>
                        <?php if ($is_default || ($action === 'edit' && $id > 0)): ?>
                            <input type="hidden" name="shortcode_type" value="<?php echo esc_attr($type); ?>">
                            <code><?php echo esc_html($type === 'next_sunday' ? 'mypco_next_sunday' : 'mypco_sunday_list'); ?></code>
                            <p class="description">
                                <?php _e('Type cannot be changed after creation.', 'mypco-online'); ?>
                            </p>
                        <?php else: ?>
                            <select id="shortcode_type" name="shortcode_type" class="mypco-type-selector">
                                <option value="next_sunday" <?php selected($type, 'next_sunday'); ?>>
                                    <?php _e('Next Sunday (mypco_next_sunday) - Shows the upcoming Sunday event', 'mypco-online'); ?>
                                </option>
                                <option value="sunday_list" <?php selected($type, 'sunday_list'); ?>>
                                    <?php _e('Sunday List (mypco_sunday_list) - Lists multiple upcoming Sundays', 'mypco-online'); ?>
                                </option>
                            </select>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="event_name"><?php _e('Event Name Filter', 'mypco-online'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="event_name" name="event_name"
                               value="<?php echo esc_attr($shortcode['event_name'] ?? 'Sunday Gathering'); ?>"
                               class="regular-text">
                        <p class="description">
                            <?php _e('Enter the event name as it appears in Planning Center. Events containing this text will be displayed.', 'mypco-online'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <?php // ---- Next Sunday-specific settings ---- ?>
        <div class="card mypco-type-next_sunday" <?php echo ($type !== 'next_sunday') ? 'style="display:none;"' : ''; ?>>
            <h2><?php _e('Next Sunday Display Settings', 'mypco-online'); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="layout_style"><?php _e('Layout Style', 'mypco-online'); ?></label>
                    </th>
                    <td>
                        <select id="layout_style" name="layout_style">
                            <option value="card" <?php selected($shortcode['layout_style'] ?? 'card', 'card'); ?>>
                                <?php _e('Card - Boxed layout with shadow', 'mypco-online'); ?>
                            </option>
                            <option value="minimal" <?php selected($shortcode['layout_style'] ?? '', 'minimal'); ?>>
                                <?php _e('Minimal - Clean, no border', 'mypco-online'); ?>
                            </option>
                            <option value="banner" <?php selected($shortcode['layout_style'] ?? '', 'banner'); ?>>
                                <?php _e('Banner - Full width with background', 'mypco-online'); ?>
                            </option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="show_title"><?php _e('Show Event Title', 'mypco-online'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="show_title" name="show_title" value="1"
                                <?php checked($shortcode['show_title'] ?? true); ?>>
                            <?php _e('Display the event title (e.g., "Sunday Gathering")', 'mypco-online'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="show_map"><?php _e('Show Map', 'mypco-online'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="show_map" name="show_map" value="1"
                                <?php checked($shortcode['show_map'] ?? true); ?>>
                            <?php _e('Display an embedded Google Map below the location', 'mypco-online'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="map_height"><?php _e('Map Height', 'mypco-online'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="map_height" name="map_height"
                               value="<?php echo esc_attr($shortcode['map_height'] ?? 200); ?>"
                               min="100" max="500" step="10" class="small-text"> px
                    </td>
                </tr>
            </table>
        </div>

        <?php // ---- Sunday List-specific settings ---- ?>
        <div class="card mypco-type-sunday_list" <?php echo ($type !== 'sunday_list') ? 'style="display:none;"' : ''; ?>>
            <h2><?php _e('Sunday List Display Settings', 'mypco-online'); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="count"><?php _e('Number of Sundays', 'mypco-online'); ?></label>
                    </th>
                    <td>
                        <?php $count_val = $shortcode['count'] ?? 'auto'; ?>
                        <select id="count" name="count">
                            <option value="auto" <?php selected($count_val, 'auto'); ?>>
                                <?php _e('Auto (4 weeks, or 5 if month has 5 Sundays)', 'mypco-online'); ?>
                            </option>
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php selected($count_val, (string) $i); ?>>
                                    <?php printf(_n('%d Sunday', '%d Sundays', $i, 'mypco-online'), $i); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </td>
                </tr>
            </table>
        </div>

        <?php // ---- Common display options ---- ?>
        <div class="card">
            <h2><?php _e('Display Options', 'mypco-online'); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Show/Hide Elements', 'mypco-online'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="show_time" value="1"
                                    <?php checked($shortcode['show_time'] ?? true); ?>>
                                <?php _e('Show event time', 'mypco-online'); ?>
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="show_address" value="1"
                                    <?php checked($shortcode['show_address'] ?? true); ?>>
                                <?php _e('Show location address', 'mypco-online'); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="empty_message"><?php _e('Empty State Message', 'mypco-online'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="empty_message" name="empty_message"
                               value="<?php echo esc_attr($shortcode['empty_message'] ?? ''); ?>"
                               class="regular-text"
                               placeholder="<?php esc_attr_e('No upcoming Sunday gatherings found.', 'mypco-online'); ?>">
                        <p class="description">
                            <?php _e('Message shown when no events are found. Leave blank for the default.', 'mypco-online'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="custom_class"><?php _e('Custom CSS Class', 'mypco-online'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="custom_class" name="custom_class"
                               value="<?php echo esc_attr($shortcode['custom_class'] ?? ''); ?>"
                               class="regular-text" placeholder="<?php esc_attr_e('my-custom-class', 'mypco-online'); ?>">
                        <p class="description">
                            <?php _e('Add a CSS class to the shortcode wrapper for custom styling.', 'mypco-online'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <?php // ---- Styling options ---- ?>
        <div class="card">
            <h2><?php _e('Styling', 'mypco-online'); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="primary_color"><?php _e('Primary Color', 'mypco-online'); ?></label>
                    </th>
                    <td>
                        <input type="color" id="primary_color" name="primary_color"
                               value="<?php echo esc_attr($shortcode['primary_color'] ?? '#333333'); ?>">
                        <span class="mypco-color-preview"><?php echo esc_html($shortcode['primary_color'] ?? '#333333'); ?></span>
                        <p class="description">
                            <?php _e('Used for the "This Week" badge, active date badge, and links.', 'mypco-online'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="text_color"><?php _e('Text Color', 'mypco-online'); ?></label>
                    </th>
                    <td>
                        <input type="color" id="text_color" name="text_color"
                               value="<?php echo esc_attr($shortcode['text_color'] ?? '#333333'); ?>">
                        <span class="mypco-color-preview"><?php echo esc_html($shortcode['text_color'] ?? '#333333'); ?></span>
                        <p class="description">
                            <?php _e('Main text color for headings and body text.', 'mypco-online'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="background_color"><?php _e('Background Color', 'mypco-online'); ?></label>
                    </th>
                    <td>
                        <input type="color" id="background_color" name="background_color"
                               value="<?php echo esc_attr($shortcode['background_color'] ?? '#ffffff'); ?>">
                        <span class="mypco-color-preview"><?php echo esc_html($shortcode['background_color'] ?? '#ffffff'); ?></span>
                        <p class="description">
                            <?php _e('Background color for cards and list items.', 'mypco-online'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="border_radius"><?php _e('Border Radius', 'mypco-online'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="border_radius" name="border_radius"
                               value="<?php echo esc_attr($shortcode['border_radius'] ?? 8); ?>"
                               min="0" max="30" step="1" class="small-text"> px
                        <p class="description">
                            <?php _e('Rounded corners for cards, badges, and buttons.', 'mypco-online'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <?php // ---- Date & Time Format ---- ?>
        <div class="card">
            <h2><?php _e('Date & Time Format', 'mypco-online'); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="date_format"><?php _e('Date Format', 'mypco-online'); ?></label>
                    </th>
                    <td>
                        <?php $date_fmt = $shortcode['date_format'] ?? 'l, F j, Y'; ?>
                        <select id="date_format" name="date_format">
                            <option value="D, M j, Y" <?php selected($date_fmt, 'D, M j, Y'); ?>>
                                <?php echo esc_html(date('D, M j, Y')); ?> (Sun, Feb 2, 2026)
                            </option>
                            <option value="l, F j, Y" <?php selected($date_fmt, 'l, F j, Y'); ?>>
                                <?php echo esc_html(date('l, F j, Y')); ?> (Sunday, February 2, 2026)
                            </option>
                            <option value="l, M j, Y" <?php selected($date_fmt, 'l, M j, Y'); ?>>
                                <?php echo esc_html(date('l, M j, Y')); ?> (Sunday, Feb 2, 2026)
                            </option>
                            <option value="F j, Y" <?php selected($date_fmt, 'F j, Y'); ?>>
                                <?php echo esc_html(date('F j, Y')); ?> (February 2, 2026)
                            </option>
                            <option value="M j, Y" <?php selected($date_fmt, 'M j, Y'); ?>>
                                <?php echo esc_html(date('M j, Y')); ?> (Feb 2, 2026)
                            </option>
                            <option value="m/d/Y" <?php selected($date_fmt, 'm/d/Y'); ?>>
                                <?php echo esc_html(date('m/d/Y')); ?> (02/02/2026)
                            </option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="time_format"><?php _e('Time Format', 'mypco-online'); ?></label>
                    </th>
                    <td>
                        <?php $time_fmt = $shortcode['time_format'] ?? 'g:i a'; ?>
                        <select id="time_format" name="time_format">
                            <option value="g:i a" <?php selected($time_fmt, 'g:i a'); ?>>
                                <?php echo esc_html(date('g:i a')); ?> (9:30 am)
                            </option>
                            <option value="g:i A" <?php selected($time_fmt, 'g:i A'); ?>>
                                <?php echo esc_html(date('g:i A')); ?> (9:30 AM)
                            </option>
                            <option value="H:i" <?php selected($time_fmt, 'H:i'); ?>>
                                <?php echo esc_html(date('H:i')); ?> (09:30)
                            </option>
                        </select>
                    </td>
                </tr>
            </table>
        </div>

        <p class="submit">
            <button type="submit" class="button button-primary">
                <?php echo ($action === 'edit') ? esc_html__('Save Settings', 'mypco-online') : esc_html__('Create Shortcode', 'mypco-online'); ?>
            </button>
            <a href="<?php echo esc_url($page_url); ?>" class="button"><?php _e('Cancel', 'mypco-online'); ?></a>
        </p>
    </form>

    <script>
    (function($) {
        'use strict';

        // Toggle type-specific sections when type selector changes (new shortcodes only)
        $('.mypco-type-selector').on('change', function() {
            var type = $(this).val();
            $('.mypco-type-next_sunday, .mypco-type-sunday_list').hide();
            $('.mypco-type-' + type).show();
        });

        // Copy shortcode to clipboard
        $('.mypco-copy-btn').on('click', function() {
            var text = $(this).data('copy');
            var $btn = $(this);

            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function() {
                    $btn.text('<?php echo esc_js(__('Copied!', 'mypco-online')); ?>');
                    setTimeout(function() {
                        $btn.text('<?php echo esc_js(__('Copy', 'mypco-online')); ?>');
                    }, 2000);
                });
            }
        });

        // Update color preview text
        $('input[type="color"]').on('input change', function() {
            $(this).next('.mypco-color-preview').text($(this).val());
        });
    })(jQuery);
    </script>

<?php else: ?>
    <?php // ================================================================
          // LIST VIEW (MAIN PAGE)
          // ================================================================ ?>

    <h1 class="wp-heading-inline"><?php _e('Locations', 'mypco-online'); ?></h1>
    <a href="<?php echo esc_url($page_url . '&action=new'); ?>" class="page-title-action"><?php _e('Add New', 'mypco-online'); ?></a>

    <hr class="wp-header-end">

    <?php if ($cache_cleared): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Locations cache cleared successfully!', 'mypco-online'); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($settings_saved): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Shortcode settings saved successfully!', 'mypco-online'); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($deleted): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Shortcode deleted.', 'mypco-online'); ?></p>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2><?php _e('Shortcodes', 'mypco-online'); ?></h2>
        <p><?php _e('Each shortcode has its own settings for event filtering, layout, colors, and more. Use the shortcode with its ID on any page or post.', 'mypco-online'); ?></p>

        <table class="wp-list-table widefat fixed striped">
            <thead>
            <tr>
                <th class="column-id" style="width: 50px;"><?php _e('ID', 'mypco-online'); ?></th>
                <th class="column-shortcode"><?php _e('Shortcode', 'mypco-online'); ?></th>
                <th class="column-type"><?php _e('Type', 'mypco-online'); ?></th>
                <th class="column-name"><?php _e('Label', 'mypco-online'); ?></th>
                <th class="column-event"><?php _e('Event Filter', 'mypco-online'); ?></th>
                <th class="column-actions" style="width: 140px;"><?php _e('Actions', 'mypco-online'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($shortcodes)): ?>
                <tr>
                    <td colspan="6"><?php _e('No shortcodes configured.', 'mypco-online'); ?></td>
                </tr>
            <?php else: ?>
                <?php foreach ($shortcodes as $sc_id => $sc): ?>
                    <?php
                    $sc_type = $sc['type'] ?? 'next_sunday';
                    $sc_tag = ($sc_type === 'next_sunday') ? 'mypco_next_sunday' : 'mypco_sunday_list';
                    $sc_code = '[' . $sc_tag . ' id="' . $sc_id . '"]';
                    $sc_name = !empty($sc['name']) ? $sc['name'] : '—';
                    $type_label = ($sc_type === 'next_sunday')
                        ? __('Next Sunday', 'mypco-online')
                        : __('Sunday List', 'mypco-online');
                    $is_sc_default = !empty($sc['is_default']);
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($sc_id); ?></strong></td>
                        <td>
                            <code class="mypco-shortcode-code"><?php echo esc_html($sc_code); ?></code>
                            <button type="button" class="button button-small mypco-copy-btn" data-copy="<?php echo esc_attr($sc_code); ?>">
                                <?php _e('Copy', 'mypco-online'); ?>
                            </button>
                        </td>
                        <td>
                            <?php echo esc_html($type_label); ?>
                            <?php if ($is_sc_default): ?>
                                <span class="mypco-badge-default"><?php _e('Default', 'mypco-online'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($sc_name); ?></td>
                        <td><?php echo esc_html($sc['event_name'] ?? ''); ?></td>
                        <td>
                            <a href="<?php echo esc_url($page_url . '&action=edit&id=' . $sc_id); ?>" class="button button-small">
                                <?php _e('Edit', 'mypco-online'); ?>
                            </a>
                            <?php if (!$is_sc_default): ?>
                                <a href="<?php echo esc_url(wp_nonce_url($page_url . '&action=delete&id=' . $sc_id, 'mypco_delete_shortcode_' . $sc_id)); ?>"
                                   class="button button-small button-link-delete"
                                   onclick="return confirm('<?php echo esc_js(__('Are you sure you want to delete this shortcode?', 'mypco-online')); ?>');">
                                    <?php _e('Delete', 'mypco-online'); ?>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2><?php _e('Cache Management', 'mypco-online'); ?></h2>
        <p><?php _e('Location data is cached for 1 hour. Clear the cache to fetch fresh data from Planning Center.', 'mypco-online'); ?></p>

        <form method="post" action="">
            <?php wp_nonce_field('mypco_clear_locations_cache'); ?>
            <input type="hidden" name="mypco_clear_locations_cache" value="1">
            <button type="submit" class="button button-secondary">
                <?php _e('Clear Locations Cache', 'mypco-online'); ?>
            </button>
        </form>
    </div>

    <script>
    (function($) {
        'use strict';

        // Copy shortcode to clipboard
        $('.mypco-copy-btn').on('click', function() {
            var text = $(this).data('copy');
            var $btn = $(this);

            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function() {
                    $btn.text('<?php echo esc_js(__('Copied!', 'mypco-online')); ?>');
                    setTimeout(function() {
                        $btn.text('<?php echo esc_js(__('Copy', 'mypco-online')); ?>');
                    }, 2000);
                });
            }
        });
    })(jQuery);
    </script>

<?php endif; ?>

</div>
