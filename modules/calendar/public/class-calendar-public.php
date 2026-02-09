<?php
/**
 * Calendar Public Component
 *
 * Handles all frontend/public functionality for the Calendar module.
 * Provides shortcode for displaying PCO Calendar events with multiple views.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MyPCO_Calendar_Public {

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
        add_shortcode('mypco_calendar', [$this, 'render_calendar_shortcode']);
        add_shortcode('pco_calendar', [$this, 'render_calendar_shortcode']); // Backward compat
        add_shortcode('mypco_next_sunday', [$this, 'render_custom_event_shortcode']);
        add_shortcode('mypco_sunday_list', [$this, 'render_custom_list_shortcode']);

        // Enqueue public assets
        $this->loader->add_action('wp_enqueue_scripts', $this, 'enqueue_public_assets');
    }

    /**
     * Enqueue public-facing assets.
     */
    public function enqueue_public_assets() {
        global $post;

        if (!is_a($post, 'WP_Post')) {
            return;
        }

        $has_calendar = has_shortcode($post->post_content, 'mypco_calendar') ||
                        has_shortcode($post->post_content, 'pco_calendar');

        $has_custom_event = has_shortcode($post->post_content, 'mypco_next_sunday') ||
                            has_shortcode($post->post_content, 'mypco_sunday_list');

        if (!$has_calendar && !$has_custom_event) {
            return;
        }

        // Calendar shortcode assets
        if ($has_calendar) {
            wp_enqueue_style(
                'mypco-calendar-public',
                MYPCO_PLUGIN_URL . 'modules/calendar/public/assets/css/calendar.css',
                [],
                MYPCO_VERSION
            );

            // Add critical inline CSS to prevent flicker on page load
            $critical_css = '
                .pco-view-section { display: none; }
                .pco-view-section.active { display: block; }
                .pco-sidebar-hidden { display: none; }
                .pco-grid-full-width { grid-template-columns: 1fr; }
                #pco-view-month .pco-month-header { display: flex; justify-content: space-between; align-items: center; }
                .pco-month-days-header { display: grid; grid-template-columns: repeat(7, 1fr); }
                .pco-month-days-header .pco-day-header { text-align: center; }
                .pco-month-loading { display: flex; justify-content: center; align-items: center; min-height: 200px; color: #999; }
            ';
            wp_add_inline_style('mypco-calendar-public', $critical_css);

            wp_enqueue_script(
                'mypco-calendar-public',
                MYPCO_PLUGIN_URL . 'modules/calendar/public/assets/js/calendar.js',
                ['jquery'],
                MYPCO_VERSION,
                true
            );
        }

        // Custom event shortcode assets
        if ($has_custom_event) {
            wp_enqueue_style(
                'mypco-custom-events-public',
                MYPCO_PLUGIN_URL . 'modules/calendar/public/assets/css/custom-events.css',
                [],
                MYPCO_VERSION
            );
        }
    }

    /**
     * Render the calendar shortcode.
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_calendar_shortcode($atts) {
        $atts = shortcode_atts([
            'id'    => 0,
            'count' => '',
            'view'  => '',
        ], $atts, 'mypco_calendar');

        // Load centralized shortcode settings when id is provided
        $id = absint($atts['id']);
        if ($id > 0) {
            require_once MYPCO_PLUGIN_DIR . 'admin/class-mypco-shortcodes-admin.php';
            $settings = MyPCO_Shortcodes_Admin::get_shortcode_settings($id, 'mypco_calendar_list');
        } else {
            $settings = [];
        }

        // Allow shortcode attributes to override stored settings, then fall back to defaults
        $atts['count'] = !empty($atts['count']) ? (int) $atts['count'] : ($settings['count'] ?? 100);
        $atts['view']  = !empty($atts['view']) ? $atts['view'] : ($settings['view'] ?? 'list');

        // Fetch data from API
        $events_data = $this->fetch_calendar_data($atts);

        if (isset($events_data['error'])) {
            return $this->render_error($events_data['error']);
        }

        // Fetch tags/categories
        $tags = $this->fetch_tags();

        // Process the data
        $processed_data = $this->process_calendar_data($events_data);

        // Pass expanded events to JavaScript
        wp_localize_script('mypco-calendar-public', 'mypcoCalendarData', [
            'expandedEvents' => $processed_data['expanded_events'],
            'currentMonth' => date('n'),
            'currentYear' => date('Y'),
            'tags' => $tags,
        ]);

        // Pass to template and return output
        return $this->load_template('calendar-main', array_merge($processed_data, [
            'default_view' => $atts['view'],
            'tags' => $tags,
        ]));
    }

    /**
     * Fetch calendar data from PCO API.
     */
    private function fetch_calendar_data($atts) {
        if (!$this->api_model) {
            return ['error' => 'API not configured. Please set up your Planning Center credentials.'];
        }

        // Fetch events starting from the first day of the current month
        // so the month view shows the entire current month, not just future events
        $month_start = date('Y-m-01\T00:00:00\Z');

        $params = [
            'where[starts_at][gte]' => $month_start,
            'order' => 'starts_at',
            'per_page' => min((int) $atts['count'], 100),
            'include' => 'event,event.tags'
        ];

        $transient_key = 'mypco_calendar_v2_' . md5(serialize($params));

        return $this->api_model->get_data_with_caching(
            'calendar',
            '/v2/event_instances',
            $params,
            $transient_key
        );
    }

    /**
     * Fetch tags (categories) from PCO API.
     */
    private function fetch_tags() {
        if (!$this->api_model) {
            return [];
        }

        $params = [
            'per_page' => 100,
            'include' => 'tag_group'
        ];

        $transient_key = 'mypco_calendar_tags_' . md5(serialize($params));

        $response = $this->api_model->get_data_with_caching(
            'calendar',
            '/v2/tags',
            $params,
            $transient_key
        );

        if (isset($response['error']) || empty($response['data'])) {
            return [];
        }

        // Build tag group map
        $tag_groups = [];
        if (!empty($response['included'])) {
            foreach ($response['included'] as $item) {
                if ($item['type'] === 'TagGroup') {
                    $tag_groups[$item['id']] = $item['attributes']['name'] ?? '';
                }
            }
        }

        // Format tags for dropdown (only public/church center categories)
        $tags = [];
        foreach ($response['data'] as $tag) {
            // Only include tags that are public (church_center_category = true)
            $is_public = $tag['attributes']['church_center_category'] ?? false;
            if (!$is_public) {
                continue;
            }

            $tag_id = $tag['id'];
            $tag_name = $tag['attributes']['name'] ?? '';
            $tag_group_id = $tag['relationships']['tag_group']['data']['id'] ?? null;
            $tag_group_name = $tag_group_id ? ($tag_groups[$tag_group_id] ?? '') : '';

            $tags[] = [
                'id' => $tag_id,
                'name' => $tag_name,
                'group_id' => $tag_group_id,
                'group_name' => $tag_group_name,
            ];
        }

        // Sort tags by group name, then by tag name
        usort($tags, function($a, $b) {
            $group_cmp = strcmp($a['group_name'], $b['group_name']);
            if ($group_cmp !== 0) return $group_cmp;
            return strcmp($a['name'], $b['name']);
        });

        return $tags;
    }

    /**
     * Process raw calendar data into display-ready format.
     */
    private function process_calendar_data($response_data) {
        $event_instances = $response_data['data'] ?? [];
        $included_items = $response_data['included'] ?? [];

        // Build event map and event-to-tags map
        $event_map = [];
        $event_tags_map = []; // Maps event ID to array of tag IDs

        foreach ($included_items as $item) {
            if ($item['type'] === 'Event') {
                $event_map[$item['id']] = $item['attributes'];
                // Get tag IDs from relationships
                $tag_ids = [];
                if (!empty($item['relationships']['tags']['data'])) {
                    foreach ($item['relationships']['tags']['data'] as $tag_ref) {
                        $tag_ids[] = $tag_ref['id'];
                    }
                }
                $event_tags_map[$item['id']] = $tag_ids;
            }
        }

        // Sort events by start date
        usort($event_instances, function($a, $b) {
            return strcmp($a['attributes']['starts_at'], $b['attributes']['starts_at']);
        });

        // Process all events and separate featured ones
        $featured_events_raw = [];
        $all_events_list = [];

        foreach ($event_instances as $instance) {
            $parent_id = $instance['relationships']['event']['data']['id'] ?? null;
            $parent = $event_map[$parent_id] ?? null;
            $tag_ids = $event_tags_map[$parent_id] ?? [];

            $formatted = $this->format_event_instance($instance, $parent, $tag_ids);

            // Add to all events list (for Upcoming section)
            $all_events_list[] = $formatted;

            // Also track featured events separately (for Featured section)
            if ($parent && !empty($parent['featured'])) {
                $featured_events_raw[] = $formatted;
            }
        }

        // Deduplicate featured events (show only one per parent event for recurring)
        $featured_events = $this->deduplicate_featured_events($featured_events_raw);

        // Build expanded events for month view JavaScript
        $expanded_events = $this->build_expanded_events($all_events_list, []);

        // Group events by parent for gallery view
        $grouped_events = $this->group_events_for_gallery($event_instances, $event_map);

        return [
            'featured_events' => $featured_events,
            'regular_events' => $all_events_list,  // All events for Upcoming section
            'all_events' => $all_events_list,
            'grouped_events' => $grouped_events,
            'event_map' => $event_map,
            'expanded_events' => $expanded_events,
            'current_month' => date('F Y'),
            'timezone' => $this->timezone,
        ];
    }

    /**
     * Deduplicate featured events for recurring events.
     * Shows only one entry per parent event with date range info.
     * Returns ALL featured events but marks which should be initially visible.
     */
    private function deduplicate_featured_events($featured_events) {
        // Get settings
        $settings = get_option('mypco_calendar_settings', []);
        $max_featured = isset($settings['featured_count']) ? (int) $settings['featured_count'] : 2;
        $display_mode = isset($settings['featured_mode']) ? $settings['featured_mode'] : 'upcoming';

        // Group by parent event, selecting the next upcoming instance
        $now = new DateTime('now', $this->timezone);
        $grouped = [];
        foreach ($featured_events as $event) {
            $parent_id = $event['parent_id'];
            if (!isset($grouped[$parent_id])) {
                $grouped[$parent_id] = [
                    'event' => $event,
                    'instances' => [],
                ];
            }
            $grouped[$parent_id]['instances'][] = $event;

            // Select the closest upcoming instance as the representative event
            try {
                $event_start = new DateTime($event['starts_at'], new DateTimeZone('UTC'));
                $event_start->setTimezone($this->timezone);
                $current_start = new DateTime($grouped[$parent_id]['event']['starts_at'], new DateTimeZone('UTC'));
                $current_start->setTimezone($this->timezone);

                if ($event_start >= $now && ($current_start < $now || $event_start < $current_start)) {
                    $grouped[$parent_id]['event'] = $event;
                }
            } catch (Exception $e) {
                // Keep existing selection on parse error
            }
        }

        // Process each group - use closest upcoming instance, mark as recurring if multiple
        $deduplicated = [];
        foreach ($grouped as $parent_id => $data) {
            $event = $data['event'];
            $instances = $data['instances'];
            $is_recurring = count($instances) > 1;

            // Calculate date display for featured event
            if ($is_recurring) {
                // Show next upcoming instance date with recurring indicator
                $event['is_recurring'] = true;
                $event['instance_count'] = count($instances);
            } else {
                $event['is_recurring'] = false;
                // Check if it's a multi-day event
                if ($event['ends_at']) {
                    try {
                        $start = new DateTime($event['starts_at'], new DateTimeZone('UTC'));
                        $end = new DateTime($event['ends_at'], new DateTimeZone('UTC'));
                        $start->setTimezone($this->timezone);
                        $end->setTimezone($this->timezone);

                        if ($start->format('Y-m-d') !== $end->format('Y-m-d')) {
                            $event['is_multi_day'] = true;
                            // Format as "Apr 23, 2026 - Apr 26, 2026"
                            $event['featured_date_display'] = $start->format('M j, Y') . ' - ' . $end->format('M j, Y');
                        }
                    } catch (Exception $e) {
                        // Use default date display
                    }
                }
            }

            $deduplicated[] = $event;
        }

        // Apply display mode
        if ($display_mode === 'random') {
            shuffle($deduplicated);
        }
        // 'upcoming' is already sorted by date

        // Mark which events should be initially visible vs hidden (for category filtering)
        $index = 0;
        foreach ($deduplicated as &$event) {
            $event['initially_hidden'] = ($index >= $max_featured);
            $index++;
        }
        unset($event); // break reference

        // Return ALL featured events (not limited) - template/JS will handle visibility
        return $deduplicated;
    }

    /**
     * Format a single event instance for display.
     */
    private function format_event_instance($instance, $parent, $tag_ids = []) {
        $attr = $instance['attributes'];
        $starts_at = $attr['starts_at'];
        $ends_at = $attr['ends_at'] ?? null;
        $is_all_day = $attr['all_day_event'] ?? false;

        // Parse location
        $location_full = $attr['location'] ?? '';
        $location_name = $this->parse_location_name($location_full);

        // Get registration URL
        $registration_url = $attr['registration_url']
            ?? $attr['signup_url']
            ?? ($parent['registration_url'] ?? null)
            ?? ($parent['signup_url'] ?? null)
            ?? '';

        // Parse dates using helper
        try {
            $start_dt = MyPCO_Date_Helper::parse_event_date($starts_at, $is_all_day, $this->timezone, false);
            $date_display = MyPCO_Date_Helper::get_date_display($starts_at, $ends_at, $is_all_day, $this->timezone);
            $time_display = MyPCO_Date_Helper::get_time_display($starts_at, $is_all_day, $this->timezone, $ends_at);
            $time_short = MyPCO_Date_Helper::get_time_display($starts_at, $is_all_day, $this->timezone); // Start time only
            $date_key = $start_dt->format('Y-m-d');
            $month_header = $start_dt->format('F Y');
            $day_header = $start_dt->format('l, M j');
        } catch (Exception $e) {
            $date_display = 'Date Error';
            $time_display = '';
            $time_short = '';
            $date_key = '';
            $month_header = 'Date Error';
            $day_header = 'Date Error';
        }

        return [
            'id' => $instance['id'],
            'parent_id' => $instance['relationships']['event']['data']['id'] ?? null,
            'name' => $parent['name'] ?? 'Untitled Event',
            'description' => $parent['description'] ?? '',
            'summary' => $parent['summary'] ?? '',
            'image_url' => $parent['image_url'] ?? '',
            'starts_at' => $starts_at,
            'ends_at' => $ends_at,
            'is_all_day' => $is_all_day,
            'is_featured' => !empty($parent['featured']),
            'date_display' => $date_display,
            'time_display' => $time_display,
            'time_short' => $time_short,
            'date_key' => $date_key,
            'month_header' => $month_header,
            'day_header' => $day_header,
            'location' => $location_full,
            'location_name' => $location_name,
            'registration_url' => $registration_url,
            'tag_ids' => $tag_ids,
            // For JavaScript event data
            'event_data' => json_encode([
                'name' => $parent['name'] ?? '',
                'description' => $parent['description'] ?? '',
                'summary' => $parent['summary'] ?? '',
                'image_url' => $parent['image_url'] ?? '',
                'time' => $time_display,
                'date' => $date_display,
                'dateKey' => $date_key,
                'location' => $location_full,
                'location_name' => $location_name,
                'registration_url' => $registration_url,
                'tag_ids' => $tag_ids,
            ]),
        ];
    }

    /**
     * Build expanded events array for month view JavaScript.
     */
    private function build_expanded_events($regular_events, $featured_events) {
        $expanded = [];
        $all_events = array_merge($regular_events, $featured_events);

        foreach ($all_events as $event) {
            $starts_at = $event['starts_at'];
            $ends_at = $event['ends_at'];
            $is_all_day = $event['is_all_day'];

            // Get all dates this event spans
            if ($ends_at) {
                $event_dates = MyPCO_Date_Helper::expand_multi_day_event($starts_at, $ends_at, $is_all_day, $this->timezone);
            } else {
                $event_dates = [$event['date_key']];
            }

            $event_data = [
                'name' => $event['name'],
                'description' => $event['description'],
                'summary' => $event['summary'],
                'image_url' => $event['image_url'],
                'time' => $event['time_short'] ?? $event['time_display'], // Short time for month grid display
                'time_full' => $event['time_display'], // Full time range for detail view
                'date' => $event['date_display'],
                'location' => $event['location'],
                'location_name' => $event['location_name'],
                'registration_url' => $event['registration_url'],
                'is_featured' => $event['is_featured'] ?? false,
                'tag_ids' => $event['tag_ids'] ?? [],
            ];

            foreach ($event_dates as $date_key) {
                if (!isset($expanded[$date_key])) {
                    $expanded[$date_key] = [];
                }
                $event_data['dateKey'] = $date_key;
                $expanded[$date_key][] = $event_data;
            }
        }

        return $expanded;
    }

    /**
     * Group events by parent ID for gallery view.
     */
    private function group_events_for_gallery($instances, $event_map) {
        $grouped = [];

        foreach ($instances as $instance) {
            $parent_id = $instance['relationships']['event']['data']['id'] ?? null;
            if (!$parent_id) continue;

            if (!isset($grouped[$parent_id])) {
                $parent = $event_map[$parent_id] ?? [];
                $grouped[$parent_id] = [
                    'parent' => $parent,
                    'instances' => [],
                ];
            }

            $grouped[$parent_id]['instances'][] = $this->format_event_instance($instance, $event_map[$parent_id] ?? []);
        }

        return $grouped;
    }

    /**
     * Parse location name from full location string.
     */
    private function parse_location_name($location_full) {
        if (empty($location_full)) {
            return '';
        }

        if (strpos($location_full, ' - ') !== false) {
            return trim(substr($location_full, 0, strpos($location_full, ' - ')));
        }

        return $location_full;
    }

    /**
     * Render error message.
     */
    private function render_error($error_message) {
        return '<div class="mypco-calendar-error"><p>' . esc_html($error_message) . '</p></div>';
    }

    /**
     * Load a template file and return output.
     */
    private function load_template($template_name, $data = []) {
        extract($data);

        ob_start();

        $template_path = MYPCO_PLUGIN_DIR . 'modules/calendar/public/templates/' . $template_name . '.php';

        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<!-- Template not found: ' . esc_html($template_name) . ' -->';
        }

        return ob_get_clean();
    }

    // =========================================================================
    // Custom Event Shortcodes (migrated from Locations module)
    // =========================================================================

    /**
     * Build scoped inline CSS for a custom event shortcode instance.
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
     * Render the custom single event shortcode.
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_custom_event_shortcode($atts) {
        $atts = shortcode_atts([
            'id'         => 0,
            'event'      => '',
            'layout'     => '',
            'show_title' => '',
            'show_map'   => '',
        ], $atts, 'mypco_next_sunday');

        $id = absint($atts['id']);
        if ($id > 0) {
            require_once MYPCO_PLUGIN_DIR . 'admin/class-mypco-shortcodes-admin.php';
            $settings = MyPCO_Shortcodes_Admin::get_shortcode_settings($id, 'mypco_next_sunday');
        } else {
            $settings = [];
        }

        // Allow shortcode attributes to override stored settings
        $event_name = !empty($atts['event']) ? $atts['event'] : ($settings['event_name'] ?? '');
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

        // Fetch upcoming events
        $events = $this->fetch_custom_events($event_name);

        if (empty($events)) {
            $empty_msg = !empty($settings['empty_message'])
                ? $settings['empty_message']
                : __('No upcoming events found.', 'mypco-online');
            return '<div class="mypco-location-empty">' . esc_html($empty_msg) . '</div>';
        }

        // Get the next event
        $next_event = $events[0];

        // Resolve date/time formats (handle custom option)
        $date_format = $this->resolve_format($settings['date_format'] ?? 'l, F j, Y', $settings['date_format_custom'] ?? '');
        $time_format = $this->resolve_format($settings['time_format'] ?? 'g:i a', $settings['time_format_custom'] ?? '');

        // Build scoped styles
        $scope_class = 'mypco-sc-' . ($id > 0 ? $id : 'default-ns');
        $custom_class = !empty($settings['custom_class']) ? ' ' . esc_attr($settings['custom_class']) : '';
        $scoped_css = $this->build_scoped_styles($scope_class, $settings);

        // Prepare data for template
        $data = [
            'event'                 => $next_event,
            'layout'                => $layout,
            'show_title'            => $show_title,
            'show_map'              => $show_map,
            'show_time'             => $show_time,
            'show_address'          => $show_address,
            'map_height'            => $settings['map_height'] ?? 200,
            'date_format'           => $date_format,
            'time_format'           => $time_format,
            'settings'              => $settings,
            'scope_class'           => $scope_class,
            'custom_class'          => $custom_class,
            'scoped_css'            => $scoped_css,
            'create_maps_embed_url' => [$this, 'create_maps_embed_url_public'],
        ];

        return $this->load_template('custom-event', $data);
    }

    /**
     * Render the custom list shortcode.
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_custom_list_shortcode($atts) {
        $atts = shortcode_atts([
            'id'    => 0,
            'event' => '',
            'count' => '',
        ], $atts, 'mypco_sunday_list');

        $id = absint($atts['id']);
        if ($id > 0) {
            require_once MYPCO_PLUGIN_DIR . 'admin/class-mypco-shortcodes-admin.php';
            $settings = MyPCO_Shortcodes_Admin::get_shortcode_settings($id, 'mypco_sunday_list');
        } else {
            $settings = [];
        }

        // Allow shortcode attributes to override stored settings
        $event_name = !empty($atts['event']) ? $atts['event'] : ($settings['event_name'] ?? '');
        $count = !empty($atts['count']) ? $atts['count'] : ($settings['count'] ?? 'auto');

        $show_time = $settings['show_time'] ?? true;
        $show_address = $settings['show_address'] ?? true;

        // Fetch upcoming events
        $events = $this->fetch_custom_events($event_name);

        if (empty($events)) {
            $empty_msg = !empty($settings['empty_message'])
                ? $settings['empty_message']
                : __('No upcoming events found.', 'mypco-online');
            return '<div class="mypco-location-empty">' . esc_html($empty_msg) . '</div>';
        }

        // Determine how many events to show
        $display_count = $this->calculate_event_count($count);
        $events_to_display = array_slice($events, 0, $display_count);

        // Resolve date/time formats (handle custom option)
        $date_format = $this->resolve_format($settings['date_format'] ?? 'l, F j, Y', $settings['date_format_custom'] ?? '');
        $time_format = $this->resolve_format($settings['time_format'] ?? 'g:i a', $settings['time_format_custom'] ?? '');

        // Build scoped styles
        $scope_class = 'mypco-sc-' . ($id > 0 ? $id : 'default-sl');
        $custom_class = !empty($settings['custom_class']) ? ' ' . esc_attr($settings['custom_class']) : '';
        $scoped_css = $this->build_scoped_styles($scope_class, $settings);

        // Prepare data for template
        $data = [
            'events'       => $events_to_display,
            'date_format'  => $date_format,
            'time_format'  => $time_format,
            'show_time'    => $show_time,
            'show_address' => $show_address,
            'settings'     => $settings,
            'scope_class'  => $scope_class,
            'custom_class' => $custom_class,
            'scoped_css'   => $scoped_css,
        ];

        return $this->load_template('custom-list', $data);
    }

    /**
     * Resolve a format string, handling the 'custom' option.
     *
     * @param string $format        Selected format value.
     * @param string $custom_format Custom format string.
     * @return string Resolved format string.
     */
    private function resolve_format($format, $custom_format) {
        if ($format === 'custom' && !empty($custom_format)) {
            return $custom_format;
        }
        if ($format === 'custom') {
            return 'l, F j, Y'; // Fallback when custom is selected but empty
        }
        return $format;
    }

    /**
     * Calculate how many events to show based on the month.
     *
     * @param string|int $count User-specified count or 'auto'
     * @return int Number of events to show
     */
    private function calculate_event_count($count) {
        if ($count !== 'auto' && is_numeric($count)) {
            return absint($count);
        }

        // Auto-calculate: 4 weeks normally, 5 if beginning of month with 5 matching weekdays
        $now = new DateTime('now', $this->timezone);
        $day_of_month = (int) $now->format('j');
        $current_month = (int) $now->format('n');
        $current_year = (int) $now->format('Y');

        if ($day_of_month <= 7) {
            $event_days = $this->count_event_days_in_month($current_month, $current_year);
            if ($event_days >= 5) {
                return 5;
            }
        }

        return 4;
    }

    /**
     * Count the number of Sundays in a given month (event day count).
     *
     * @param int $month Month number (1-12)
     * @param int $year Year
     * @return int Number of Sundays
     */
    private function count_event_days_in_month($month, $year) {
        $first_day = new DateTime("$year-$month-01", $this->timezone);
        $last_day = new DateTime($first_day->format('Y-m-t'), $this->timezone);

        $count = 0;
        $current = clone $first_day;

        while ($current <= $last_day) {
            if ($current->format('w') == 0) {
                $count++;
            }
            $current->modify('+1 day');
        }

        return $count;
    }

    /**
     * Fetch custom events from Planning Center Calendar.
     *
     * Filters events by name and returns only Sunday occurrences,
     * deduplicated by date.
     *
     * @param string $event_name Name to filter events by
     * @return array Array of formatted event data
     */
    private function fetch_custom_events($event_name) {
        if (!$this->api_model) {
            return [];
        }

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

        $transient_key = 'mypco_custom_events_' . md5(serialize($params) . $event_name);

        $response = $this->api_model->get_data_with_caching(
            'calendar',
            '/v2/event_instances',
            $params,
            $transient_key,
            HOUR_IN_SECONDS
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
        $matched_events = [];
        $seen_dates = [];

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

            // Only include events on Sundays
            $starts_at = $instance['attributes']['starts_at'];
            try {
                $event_date = new DateTime($starts_at, new DateTimeZone('UTC'));
                $event_date->setTimezone($this->timezone);

                if ($event_date->format('w') != 0) {
                    continue;
                }

                // Deduplicate by date
                $date_key = $event_date->format('Y-m-d');
                if (isset($seen_dates[$date_key])) {
                    continue;
                }
                $seen_dates[$date_key] = true;

                $matched_events[] = $this->format_custom_event($instance, $parent, $event_date);
            } catch (Exception $e) {
                continue;
            }
        }

        usort($matched_events, function($a, $b) {
            return strcmp($a['date_key'], $b['date_key']);
        });

        return $matched_events;
    }

    /**
     * Format a custom event instance for display.
     *
     * @param array    $instance   Event instance from API
     * @param array    $parent     Parent event attributes
     * @param DateTime $event_date Parsed event date
     * @return array Formatted event data
     */
    private function format_custom_event($instance, $parent, $event_date) {
        $attr = $instance['attributes'];
        $location_full = $attr['location'] ?? '';
        $location_parts = $this->parse_custom_event_location($location_full);
        $maps_url = $this->create_maps_url($location_full);

        return [
            'id' => $instance['id'],
            'name' => $parent['name'] ?? 'Event',
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
    private function parse_custom_event_location($location_full) {
        if (empty($location_full)) {
            return ['name' => '', 'address' => ''];
        }

        if (strpos($location_full, ' - ') !== false) {
            $parts = explode(' - ', $location_full, 2);
            return [
                'name' => trim($parts[0]),
                'address' => isset($parts[1]) ? trim($parts[1]) : '',
            ];
        }

        return ['name' => $location_full, 'address' => ''];
    }

    /**
     * Create Google Maps direction URL for a location.
     *
     * @param string $location Location string
     * @return string Google Maps URL
     */
    private function create_maps_url($location) {
        if (empty($location)) {
            return '';
        }
        return 'https://www.google.com/maps/dir/?api=1&destination=' . urlencode($location);
    }

    /**
     * Create Google Maps embed URL for iframe.
     *
     * @param string $location Location string
     * @return string Google Maps embed URL
     */
    public function create_maps_embed_url_public($location) {
        if (empty($location)) {
            return '';
        }
        return 'https://www.google.com/maps?q=' . urlencode($location) . '&output=embed';
    }
}
