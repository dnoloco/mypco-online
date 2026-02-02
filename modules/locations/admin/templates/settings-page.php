<?php
/**
 * Locations Admin Settings Page Template
 *
 * Available variables:
 * - $shortcode_next (string)
 * - $shortcode_list (string)
 * - $cache_cleared (bool)
 * - $settings_saved (bool)
 * - $module_status (string)
 * - $event_name (string)
 * - $layout_style (string)
 * - $show_map (bool)
 * - $map_height (int)
 * - $primary_color (string)
 * - $text_color (string)
 * - $background_color (string)
 * - $border_radius (int)
 * - $date_format (string)
 * - $time_format (string)
 */

defined('ABSPATH') || exit;
?>

<div class="wrap mypco-locations-admin">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php if ($cache_cleared): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Locations cache cleared successfully!', 'mypco-online'); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($settings_saved): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Settings saved successfully!', 'mypco-online'); ?></p>
        </div>
    <?php endif; ?>

    <hr>

    <div class="card">
        <h2><?php _e('Locations Module', 'mypco-online'); ?></h2>
        <p><?php _e('Display upcoming Sunday gathering locations from your Planning Center Calendar on your website. Perfect for mobile churches that meet in different locations.', 'mypco-online'); ?></p>

        <h3><?php _e('Available Shortcodes', 'mypco-online'); ?></h3>

        <table class="widefat" style="margin-bottom: 20px;">
            <thead>
            <tr>
                <th><?php _e('Shortcode', 'mypco-online'); ?></th>
                <th><?php _e('Description', 'mypco-online'); ?></th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td><code><?php echo esc_html($shortcode_next); ?></code></td>
                <td><?php _e('Displays the upcoming Sunday with date, time, location, and an interactive map.', 'mypco-online'); ?></td>
            </tr>
            <tr>
                <td><code><?php echo esc_html($shortcode_list); ?></code></td>
                <td><?php _e('Lists the next 4 Sundays (or 5 if at the beginning of a month with 5 Sundays) with date, time, and clickable location names.', 'mypco-online'); ?></td>
            </tr>
            </tbody>
        </table>

        <h3><?php _e('Module Status', 'mypco-online'); ?></h3>
        <p><strong><?php _e('Status:', 'mypco-online'); ?></strong> <span style="color: green;"><?php _e('Active (Free Module)', 'mypco-online'); ?></span></p>
    </div>

    <div class="card">
        <h2><?php _e('Event Settings', 'mypco-online'); ?></h2>
        <p><?php _e('Configure which events to display and how they appear.', 'mypco-online'); ?></p>

        <form method="post" action="">
            <?php wp_nonce_field('mypco_locations_settings'); ?>
            <input type="hidden" name="mypco_save_locations_settings" value="1">

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="event_name"><?php _e('Event Name Filter', 'mypco-online'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="event_name" name="event_name"
                               value="<?php echo esc_attr($event_name); ?>"
                               class="regular-text">
                        <p class="description">
                            <?php _e('Enter the name of your Sunday service event as it appears in Planning Center (e.g., "Sunday Gathering", "Sunday Service", "Worship Service"). Events containing this text will be displayed.', 'mypco-online'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <h3><?php _e('Next Sunday Display Settings', 'mypco-online'); ?></h3>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="layout_style"><?php _e('Layout Style', 'mypco-online'); ?></label>
                    </th>
                    <td>
                        <select id="layout_style" name="layout_style">
                            <option value="card" <?php selected($layout_style, 'card'); ?>>
                                <?php _e('Card - Boxed layout with shadow', 'mypco-online'); ?>
                            </option>
                            <option value="minimal" <?php selected($layout_style, 'minimal'); ?>>
                                <?php _e('Minimal - Clean, no border', 'mypco-online'); ?>
                            </option>
                            <option value="banner" <?php selected($layout_style, 'banner'); ?>>
                                <?php _e('Banner - Full width with background', 'mypco-online'); ?>
                            </option>
                        </select>
                        <p class="description">
                            <?php _e('Choose the visual style for the next Sunday display.', 'mypco-online'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="show_map"><?php _e('Show Map', 'mypco-online'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="show_map" name="show_map" value="1"
                                <?php checked($show_map, true); ?>>
                            <?php _e('Display an embedded Google Map', 'mypco-online'); ?>
                        </label>
                        <p class="description">
                            <?php _e('When enabled, an interactive map will be shown below the location details.', 'mypco-online'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="map_height"><?php _e('Map Height', 'mypco-online'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="map_height" name="map_height"
                               value="<?php echo esc_attr($map_height); ?>"
                               min="100" max="500" step="10" class="small-text"> px
                        <p class="description">
                            <?php _e('Height of the embedded map in pixels.', 'mypco-online'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <h3><?php _e('Date & Time Format', 'mypco-online'); ?></h3>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="date_format"><?php _e('Date Format', 'mypco-online'); ?></label>
                    </th>
                    <td>
                        <select id="date_format" name="date_format">
                            <option value="l, F j, Y" <?php selected($date_format, 'l, F j, Y'); ?>>
                                <?php echo esc_html(date('l, F j, Y')); ?> (Sunday, February 2, 2026)
                            </option>
                            <option value="l, M j, Y" <?php selected($date_format, 'l, M j, Y'); ?>>
                                <?php echo esc_html(date('l, M j, Y')); ?> (Sunday, Feb 2, 2026)
                            </option>
                            <option value="F j, Y" <?php selected($date_format, 'F j, Y'); ?>>
                                <?php echo esc_html(date('F j, Y')); ?> (February 2, 2026)
                            </option>
                            <option value="M j, Y" <?php selected($date_format, 'M j, Y'); ?>>
                                <?php echo esc_html(date('M j, Y')); ?> (Feb 2, 2026)
                            </option>
                            <option value="m/d/Y" <?php selected($date_format, 'm/d/Y'); ?>>
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
                        <select id="time_format" name="time_format">
                            <option value="g:i a" <?php selected($time_format, 'g:i a'); ?>>
                                <?php echo esc_html(date('g:i a')); ?> (9:30 am)
                            </option>
                            <option value="g:i A" <?php selected($time_format, 'g:i A'); ?>>
                                <?php echo esc_html(date('g:i A')); ?> (9:30 AM)
                            </option>
                            <option value="H:i" <?php selected($time_format, 'H:i'); ?>>
                                <?php echo esc_html(date('H:i')); ?> (09:30)
                            </option>
                        </select>
                    </td>
                </tr>
            </table>

            <h3><?php _e('Styling Options', 'mypco-online'); ?></h3>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="primary_color"><?php _e('Primary Color', 'mypco-online'); ?></label>
                    </th>
                    <td>
                        <input type="color" id="primary_color" name="primary_color"
                               value="<?php echo esc_attr($primary_color); ?>">
                        <span class="mypco-color-preview"><?php echo esc_html($primary_color); ?></span>
                        <p class="description">
                            <?php _e('Used for buttons and links.', 'mypco-online'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="text_color"><?php _e('Text Color', 'mypco-online'); ?></label>
                    </th>
                    <td>
                        <input type="color" id="text_color" name="text_color"
                               value="<?php echo esc_attr($text_color); ?>">
                        <span class="mypco-color-preview"><?php echo esc_html($text_color); ?></span>
                        <p class="description">
                            <?php _e('Main text color.', 'mypco-online'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="background_color"><?php _e('Background Color', 'mypco-online'); ?></label>
                    </th>
                    <td>
                        <input type="color" id="background_color" name="background_color"
                               value="<?php echo esc_attr($background_color); ?>">
                        <span class="mypco-color-preview"><?php echo esc_html($background_color); ?></span>
                        <p class="description">
                            <?php _e('Background color for cards and containers.', 'mypco-online'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="border_radius"><?php _e('Border Radius', 'mypco-online'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="border_radius" name="border_radius"
                               value="<?php echo esc_attr($border_radius); ?>"
                               min="0" max="30" step="1" class="small-text"> px
                        <p class="description">
                            <?php _e('Rounded corners for cards and buttons.', 'mypco-online'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary">
                    <?php _e('Save Settings', 'mypco-online'); ?>
                </button>
            </p>
        </form>
    </div>

    <div class="card">
        <h2><?php _e('Shortcode Options', 'mypco-online'); ?></h2>
        <p><?php _e('You can override settings using shortcode attributes:', 'mypco-online'); ?></p>

        <h4><?php _e('Next Sunday Shortcode', 'mypco-online'); ?></h4>
        <table class="widefat">
            <thead>
            <tr>
                <th><?php _e('Attribute', 'mypco-online'); ?></th>
                <th><?php _e('Description', 'mypco-online'); ?></th>
                <th><?php _e('Default', 'mypco-online'); ?></th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td><code>event</code></td>
                <td><?php _e('Filter events by name', 'mypco-online'); ?></td>
                <td><?php echo esc_html($event_name); ?></td>
            </tr>
            <tr>
                <td><code>layout</code></td>
                <td><?php _e('Layout style: card, minimal, or banner', 'mypco-online'); ?></td>
                <td><?php echo esc_html($layout_style); ?></td>
            </tr>
            <tr>
                <td><code>show_map</code></td>
                <td><?php _e('Show map: yes or no', 'mypco-online'); ?></td>
                <td><?php echo $show_map ? 'yes' : 'no'; ?></td>
            </tr>
            </tbody>
        </table>
        <p><strong><?php _e('Example:', 'mypco-online'); ?></strong> <code>[mypco_next_sunday event="Sunday Service" layout="banner" show_map="yes"]</code></p>

        <h4 style="margin-top: 20px;"><?php _e('Sunday List Shortcode', 'mypco-online'); ?></h4>
        <table class="widefat">
            <thead>
            <tr>
                <th><?php _e('Attribute', 'mypco-online'); ?></th>
                <th><?php _e('Description', 'mypco-online'); ?></th>
                <th><?php _e('Default', 'mypco-online'); ?></th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td><code>event</code></td>
                <td><?php _e('Filter events by name', 'mypco-online'); ?></td>
                <td><?php echo esc_html($event_name); ?></td>
            </tr>
            <tr>
                <td><code>count</code></td>
                <td><?php _e('Number of Sundays to show, or "auto" for smart detection', 'mypco-online'); ?></td>
                <td>auto</td>
            </tr>
            </tbody>
        </table>
        <p><strong><?php _e('Example:', 'mypco-online'); ?></strong> <code>[mypco_sunday_list event="Worship Service" count="6"]</code></p>
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
</div>
