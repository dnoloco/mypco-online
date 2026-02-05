<?php
/**
 * Locations Public Component
 *
 * Handles all frontend/public functionality for the Locations module.
 * Provides shortcodes for displaying upcoming Sunday gathering locations.
 * Supports per-shortcode settings via the `id` attribute.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MyPCO_Locations_Public {

    private $loader;
    private $api_model;
    private $timezone;

    public function __construct($loader, $api_model) {
        $this->loader = $loader;
        $this->api_model = $api_model;

        // Load date helper
        require_once MYPCO_PLUGIN_DIR . 'includes/class-mypco-date-helper.php';
        $this->timezone = MyPCO_Date_Helper::get_timezone();
    }

    /**
     * Initialize public functionality.
     */
    public function init() {
        // Register shortcodes
        add_shortcode('mypco_next_sunday', [$this, 'render_next_sunday_shortcode']);
        add_shortcode('mypco_sunday_list', [$this, 'render_sunday_list_shortcode']);

        // Enqueue public assets
        $this->loader->add_action('wp_enqueue_scripts', $this, 'enqueue_public_assets');
    }

    /**
     * Enqueue public-facing assets.
     */
    public function enqueue_public_assets() {
        global $post;

        // Only enqueue on pages with the location shortcodes
        if (!is_a($post, 'WP_Post') || !(
                has_shortcode($post->post_content, 'mypco_next_sunday') ||
                has_shortcode($post->post_content, 'mypco_sunday_list')
            )) {
            return;
        }

        wp_enqueue_style(
            'mypco-locations-public',
            MYPCO_PLUGIN_URL . 'modules/locations/public/assets/css/locations.css',
            [],
            MYPCO_VERSION
        );
    }

    /**
     * Get settings for a specific shortcode ID, with fallback.
     *
     * @param int    $id   Shortcode configuration ID.
     * @param string $type Shortcode type for fallback.
     * @return array Settings array.
     */
    private function get_shortcode_settings($id, $type = 'next_sunday') {
        // Load admin class for the static helper
        require_once MYPCO_PLUGIN_DIR . 'modules/locations/admin/class-locations-admin.php';
        return MyPCO_Locations_Admin::get_shortcode_settings($id, $type);
    }

    /**
     * Build scoped inline CSS for a shortcode instance.
     *
     * @param string $scope_class Unique CSS class for this instance.
     * @param array  $settings    Shortcode settings.
     * @return string CSS block.
     */
    private function build_scoped_styles($scope_class, $settings) {
        $primary = $settings['primary_color'] ?? '#333333';
        $text = $settings['text_color'] ?? '#333333';
        $bg = $settings['background_color'] ?? '#ffffff';
        $radius = $settings['border_radius'] ?? 8;

        return "<style>.{$scope_class} {
    --mypco-loc-primary: {$primary};
    --mypco-loc-text: {$text};
    --mypco-loc-bg: {$bg};
    --mypco-loc-radius: {$radius}px;
}</style>";
    }

    /**
     * Render the next Sunday shortcode.
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_next_sunday_shortcode($atts) {
        $atts = shortcode_atts([
            'id'         => 0,
            'event'      => '',
            'layout'     => '',
            'show_title' => '',
            'show_map'   => '',
        ], $atts, 'mypco_next_sunday');

        $id = absint($atts['id']);
        $settings = $this->get_shortcode_settings($id, 'next_sunday');

        // Allow shortcode attributes to override stored settings
        $event_name = !empty($atts['event']) ? $atts['event'] : ($settings['event_name'] ?? 'Sunday Gathering');
        $layout = !empty($atts['layout']) ? $atts['layout'] : ($settings['layout_style'] ?? 'card');

        if ($atts['show_title'] !== '') {
            $show_title = ($atts['show_title'] === 'yes' || $atts['show_title'] === '1' || $atts['show_title'] === true);
        } else {
            $show_title = $settings['show_title'] ?? true;
        }

        if ($atts['show_map'] !== '') {
            $show_map = ($atts['show_map'] === 'yes' || $atts['show_map'] === '1' || $atts['show_map'] === true);
        } else {
            $show_map = $settings['show_map'] ?? true;
        }

        $show_time = $settings['show_time'] ?? true;
        $show_address = $settings['show_address'] ?? true;

        // Fetch upcoming Sunday events
        $sunday_events = $this->fetch_sunday_events($event_name);

        if (empty($sunday_events)) {
            $empty_msg = !empty($settings['empty_message'])
                ? $settings['empty_message']
                : __('No upcoming Sunday gatherings found.', 'mypco-online');
            return '<div class="mypco-location-empty">' . esc_html($empty_msg) . '</div>';
        }

        // Get the next Sunday
        $next_sunday = $sunday_events[0];

        // Build scoped styles
        $scope_class = 'mypco-sc-' . ($id > 0 ? $id : 'default-ns');
        $custom_class = !empty($settings['custom_class']) ? ' ' . esc_attr($settings['custom_class']) : '';
        $scoped_css = $this->build_scoped_styles($scope_class, $settings);

        // Prepare data for template
        $data = [
            'event'        => $next_sunday,
            'layout'       => $layout,
            'show_title'   => $show_title,
            'show_map'     => $show_map,
            'show_time'    => $show_time,
            'show_address' => $show_address,
            'map_height'   => $settings['map_height'] ?? 200,
            'date_format'  => $settings['date_format'] ?? 'l, F j, Y',
            'time_format'  => $settings['time_format'] ?? 'g:i a',
            'settings'     => $settings,
            'scope_class'  => $scope_class,
            'custom_class' => $custom_class,
            'scoped_css'   => $scoped_css,
        ];

        return $this->load_template('next-sunday', $data);
    }

    /**
     * Render the Sunday list shortcode.
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_sunday_list_shortcode($atts) {
        $atts = shortcode_atts([
            'id'    => 0,
            'event' => '',
            'count' => '',
        ], $atts, 'mypco_sunday_list');

        $id = absint($atts['id']);
        $settings = $this->get_shortcode_settings($id, 'sunday_list');

        // Allow shortcode attributes to override stored settings
        $event_name = !empty($atts['event']) ? $atts['event'] : ($settings['event_name'] ?? 'Sunday Gathering');
        $count = !empty($atts['count']) ? $atts['count'] : ($settings['count'] ?? 'auto');

        $show_time = $settings['show_time'] ?? true;
        $show_address = $settings['show_address'] ?? true;

        // Fetch upcoming Sunday events
        $sunday_events = $this->fetch_sunday_events($event_name);

        if (empty($sunday_events)) {
            $empty_msg = !empty($settings['empty_message'])
                ? $settings['empty_message']
                : __('No upcoming Sunday gatherings found.', 'mypco-online');
            return '<div class="mypco-location-empty">' . esc_html($empty_msg) . '</div>';
        }

        // Determine how many Sundays to show
        $display_count = $this->calculate_sunday_count($count);

        // Get the events to display
        $events_to_display = array_slice($sunday_events, 0, $display_count);

        // Build scoped styles
        $scope_class = 'mypco-sc-' . ($id > 0 ? $id : 'default-sl');
        $custom_class = !empty($settings['custom_class']) ? ' ' . esc_attr($settings['custom_class']) : '';
        $scoped_css = $this->build_scoped_styles($scope_class, $settings);

        // Prepare data for template
        $data = [
            'events'       => $events_to_display,
            'date_format'  => $settings['date_format'] ?? 'l, F j, Y',
            'time_format'  => $settings['time_format'] ?? 'g:i a',
            'show_time'    => $show_time,
            'show_address' => $show_address,
            'settings'     => $settings,
            'scope_class'  => $scope_class,
            'custom_class' => $custom_class,
            'scoped_css'   => $scoped_css,
        ];

        return $this->load_template('sunday-list', $data);
    }

    /**
     * Calculate how many Sundays to show based on the month.
     *
     * @param string|int $count User-specified count or 'auto'
     * @return int Number of Sundays to show
     */
    private function calculate_sunday_count($count) {
        if ($count !== 'auto' && is_numeric($count)) {
            return absint($count);
        }

        // Auto-calculate: 4 weeks normally, 5 if beginning of month with 5 Sundays
        $now = new DateTime('now', $this->timezone);
        $day_of_month = (int) $now->format('j');
        $current_month = (int) $now->format('n');
        $current_year = (int) $now->format('Y');

        // Check if we're in the first week of the month (days 1-7)
        if ($day_of_month <= 7) {
            // Count Sundays in current month
            $sundays_in_month = $this->count_sundays_in_month($current_month, $current_year);

            if ($sundays_in_month >= 5) {
                return 5;
            }
        }

        return 4;
    }

    /**
     * Count the number of Sundays in a given month.
     *
     * @param int $month Month number (1-12)
     * @param int $year Year
     * @return int Number of Sundays
     */
    private function count_sundays_in_month($month, $year) {
        $first_day = new DateTime("$year-$month-01", $this->timezone);
        $last_day = new DateTime($first_day->format('Y-m-t'), $this->timezone);

        $sundays = 0;
        $current = clone $first_day;

        while ($current <= $last_day) {
            if ($current->format('w') == 0) { // 0 = Sunday
                $sundays++;
            }
            $current->modify('+1 day');
        }

        return $sundays;
    }

    /**
     * Fetch Sunday events from Planning Center Calendar.
     *
     * @param string $event_name Name to filter events by
     * @return array Array of formatted Sunday events
     */
    private function fetch_sunday_events($event_name) {
        if (!$this->api_model) {
            return [];
        }

        // Calculate date range - from today to 6 weeks out
        $now = new DateTime('now', $this->timezone);
        $start_date = $now->format('Y-m-d\T00:00:00\Z');

        $end_date_obj = clone $now;
        $end_date_obj->modify('+6 weeks');
        $end_date = $end_date_obj->format('Y-m-d\T23:59:59\Z');

        $params = [
            'where[starts_at][gte]' => $start_date,
            'where[starts_at][lte]' => $end_date,
            'order' => 'starts_at',
            'per_page' => 50,
            'include' => 'event'
        ];

        $transient_key = 'mypco_locations_events_' . md5(serialize($params) . $event_name);

        $response = $this->api_model->get_data_with_caching(
            'calendar',
            '/v2/event_instances',
            $params,
            $transient_key,
            HOUR_IN_SECONDS // Cache for 1 hour
        );

        if (isset($response['error']) || empty($response['data'])) {
            return [];
        }

        // Build event map from included items
        $event_map = [];
        if (!empty($response['included'])) {
            foreach ($response['included'] as $item) {
                if ($item['type'] === 'Event') {
                    $event_map[$item['id']] = $item['attributes'];
                }
            }
        }

        // Filter and format events
        $sunday_events = [];
        $seen_dates = []; // Track dates to avoid duplicates

        foreach ($response['data'] as $instance) {
            $parent_id = $instance['relationships']['event']['data']['id'] ?? null;
            $parent = $event_map[$parent_id] ?? null;

            if (!$parent) {
                continue;
            }

            $parent_name = $parent['name'] ?? '';

            // Filter by event name (case-insensitive partial match)
            if (!empty($event_name) && stripos($parent_name, $event_name) === false) {
                continue;
            }

            // Check if this is on a Sunday
            $starts_at = $instance['attributes']['starts_at'];
            try {
                $event_date = new DateTime($starts_at, new DateTimeZone('UTC'));
                $event_date->setTimezone($this->timezone);

                // Skip if not Sunday (0 = Sunday in PHP)
                if ($event_date->format('w') != 0) {
                    continue;
                }

                // Skip if we already have an event for this date
                $date_key = $event_date->format('Y-m-d');
                if (isset($seen_dates[$date_key])) {
                    continue;
                }
                $seen_dates[$date_key] = true;

                // Format the event
                $formatted_event = $this->format_event($instance, $parent, $event_date);
                $sunday_events[] = $formatted_event;

            } catch (Exception $e) {
                continue;
            }
        }

        // Sort by date
        usort($sunday_events, function($a, $b) {
            return strcmp($a['date_key'], $b['date_key']);
        });

        return $sunday_events;
    }

    /**
     * Format an event instance for display.
     *
     * Uses a neutral date format for storage; templates handle final display formatting.
     *
     * @param array $instance Event instance from API
     * @param array $parent Parent event attributes
     * @param DateTime $event_date Parsed event date
     * @return array Formatted event data
     */
    private function format_event($instance, $parent, $event_date) {
        $attr = $instance['attributes'];

        // Parse location
        $location_full = $attr['location'] ?? '';
        $location_parts = $this->parse_location($location_full);

        // Create Google Maps URL
        $maps_url = $this->create_maps_url($location_full);

        return [
            'id' => $instance['id'],
            'name' => $parent['name'] ?? 'Sunday Gathering',
            'date_obj' => $event_date,
            'date_key' => $event_date->format('Y-m-d'),
            'day_of_week' => $event_date->format('l'),
            'day_short' => $event_date->format('D'),
            'day_number' => $event_date->format('j'),
            'month_short' => $event_date->format('M'),
            'location_full' => $location_full,
            'location_name' => $location_parts['name'],
            'location_address' => $location_parts['address'],
            'maps_url' => $maps_url,
        ];
    }

    /**
     * Parse location string into name and address.
     *
     * @param string $location_full Full location string
     * @return array Array with 'name' and 'address' keys
     */
    private function parse_location($location_full) {
        if (empty($location_full)) {
            return [
                'name' => '',
                'address' => '',
            ];
        }

        // PCO format is typically "Location Name - Address"
        if (strpos($location_full, ' - ') !== false) {
            $parts = explode(' - ', $location_full, 2);
            return [
                'name' => trim($parts[0]),
                'address' => isset($parts[1]) ? trim($parts[1]) : '',
            ];
        }

        return [
            'name' => $location_full,
            'address' => '',
        ];
    }

    /**
     * Create Google Maps URL for a location.
     *
     * @param string $location Location string
     * @return string Google Maps URL
     */
    private function create_maps_url($location) {
        if (empty($location)) {
            return '';
        }

        $query = urlencode($location);
        return 'https://www.google.com/maps/dir/?api=1&destination=' . $query;
    }

    /**
     * Create Google Maps embed URL for iframe.
     *
     * @param string $location Location string
     * @return string Google Maps embed URL
     */
    private function create_maps_embed_url($location) {
        if (empty($location)) {
            return '';
        }

        $query = urlencode($location);
        return 'https://www.google.com/maps?q=' . $query . '&output=embed';
    }

    /**
     * Load a template file and return output.
     *
     * @param string $template_name Template name without extension
     * @param array $data Data to pass to template
     * @return string HTML output
     */
    private function load_template($template_name, $data = []) {
        // Make helper function available in template
        $data['create_maps_embed_url'] = [$this, 'create_maps_embed_url'];

        extract($data);

        ob_start();

        $template_path = MYPCO_PLUGIN_DIR . 'modules/locations/public/templates/' . $template_name . '.php';

        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<!-- Template not found: ' . esc_html($template_name) . ' -->';
        }

        return ob_get_clean();
    }

    /**
     * Public method for creating maps embed URL (used in templates).
     */
    public function create_maps_embed_url_public($location) {
        return $this->create_maps_embed_url($location);
    }
}
