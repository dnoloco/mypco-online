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

        // Enqueue public assets
        $this->loader->add_action('wp_enqueue_scripts', $this, 'enqueue_public_assets');
    }

    /**
     * Enqueue public-facing assets.
     */
    public function enqueue_public_assets() {
        global $post;

        // Only enqueue on pages with the calendar shortcode
        if (!is_a($post, 'WP_Post') || !(
                has_shortcode($post->post_content, 'mypco_calendar') ||
                has_shortcode($post->post_content, 'pco_calendar')
            )) {
            return;
        }

        wp_enqueue_style(
            'mypco-calendar-public',
            MYPCO_PLUGIN_URL . 'modules/calendar/public/assets/css/calendar.css',
            [],
            MYPCO_VERSION
        );

        wp_enqueue_script(
            'mypco-calendar-public',
            MYPCO_PLUGIN_URL . 'modules/calendar/public/assets/js/calendar.js',
            ['jquery'],
            MYPCO_VERSION,
            true
        );
    }

    /**
     * Render the calendar shortcode.
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_calendar_shortcode($atts) {
        $atts = shortcode_atts([
            'count' => 100,
            'view' => 'list', // Default view: list, month, gallery
        ], $atts, 'mypco_calendar');

        // Fetch data from API
        $events_data = $this->fetch_calendar_data($atts);

        if (isset($events_data['error'])) {
            return $this->render_error($events_data['error']);
        }

        // Process the data
        $processed_data = $this->process_calendar_data($events_data);

        // Pass expanded events to JavaScript
        wp_localize_script('mypco-calendar-public', 'mypcoCalendarData', [
            'expandedEvents' => $processed_data['expanded_events'],
            'currentMonth' => date('n'),
            'currentYear' => date('Y'),
        ]);

        // Pass to template and return output
        return $this->load_template('calendar-main', array_merge($processed_data, [
            'default_view' => $atts['view'],
        ]));
    }

    /**
     * Fetch calendar data from PCO API.
     */
    private function fetch_calendar_data($atts) {
        if (!$this->api_model) {
            return ['error' => 'API not configured. Please set up your Planning Center credentials.'];
        }

        $params = [
            'filter' => 'future',
            'per_page' => min((int) $atts['count'], 100),
            'include' => 'event'
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
     * Process raw calendar data into display-ready format.
     */
    private function process_calendar_data($response_data) {
        $event_instances = $response_data['data'] ?? [];
        $included_events = $response_data['included'] ?? [];

        // Build event map
        $event_map = [];
        foreach ($included_events as $item) {
            if ($item['type'] === 'Event') {
                $event_map[$item['id']] = $item['attributes'];
            }
        }

        // Sort events by start date
        usort($event_instances, function($a, $b) {
            return strcmp($a['attributes']['starts_at'], $b['attributes']['starts_at']);
        });

        // Separate featured and regular events
        $featured_events_raw = [];
        $regular_events = [];

        foreach ($event_instances as $instance) {
            $parent_id = $instance['relationships']['event']['data']['id'] ?? null;
            $parent = $event_map[$parent_id] ?? null;

            $formatted = $this->format_event_instance($instance, $parent);

            if ($parent && !empty($parent['featured'])) {
                $featured_events_raw[] = $formatted;
            } else {
                $regular_events[] = $formatted;
            }
        }

        // Deduplicate featured events (show only one per parent event for recurring)
        $featured_events = $this->deduplicate_featured_events($featured_events_raw);

        // Build expanded events for month view JavaScript
        $expanded_events = $this->build_expanded_events($regular_events, $featured_events_raw);

        // Group events by parent for gallery view
        $grouped_events = $this->group_events_for_gallery($event_instances, $event_map);

        return [
            'featured_events' => $featured_events,
            'regular_events' => $regular_events,
            'all_events' => array_merge($featured_events_raw, $regular_events),
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
     */
    private function deduplicate_featured_events($featured_events) {
        // Get settings
        $settings = get_option('mypco_calendar_settings', []);
        $max_featured = isset($settings['featured_count']) ? (int) $settings['featured_count'] : 2;
        $display_mode = isset($settings['featured_mode']) ? $settings['featured_mode'] : 'upcoming';

        // Group by parent event
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
        }

        // Process each group - take first instance but mark as recurring if multiple
        $deduplicated = [];
        foreach ($grouped as $parent_id => $data) {
            $event = $data['event'];
            $instances = $data['instances'];
            $is_recurring = count($instances) > 1;

            // Calculate date display for featured event
            if ($is_recurring) {
                // Show first instance date with recurring indicator
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

        // Limit to max featured count
        return array_slice($deduplicated, 0, $max_featured);
    }

    /**
     * Format a single event instance for display.
     */
    private function format_event_instance($instance, $parent) {
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
            $time_display = MyPCO_Date_Helper::get_time_display($starts_at, $is_all_day, $this->timezone);
            $date_key = $start_dt->format('Y-m-d');
            $month_header = $start_dt->format('F Y');
            $day_header = $start_dt->format('l, M j');
        } catch (Exception $e) {
            $date_display = 'Date Error';
            $time_display = '';
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
            'date_key' => $date_key,
            'month_header' => $month_header,
            'day_header' => $day_header,
            'location' => $location_full,
            'location_name' => $location_name,
            'registration_url' => $registration_url,
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
                'time' => $event['time_display'],
                'date' => $event['date_display'],
                'location' => $event['location'],
                'location_name' => $event['location_name'],
                'registration_url' => $event['registration_url'],
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
}
