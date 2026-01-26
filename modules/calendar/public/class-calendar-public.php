<?php
/**
 * Calendar Public Component
 *
 * Handles all frontend/public functionality for the Calendar module.
 */

class MyPCO_Calendar_Public {

    private $loader;
    private $api_model;

    public function __construct($loader, $api_model) {
        $this->loader = $loader;
        $this->api_model = $api_model;
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
            MYPCO_PLUGIN_URL . 'modules/calendar/public/assets/css/calendar-public.css',
            [],
            MYPCO_VERSION
        );

        wp_enqueue_script(
            'mypco-calendar-public',
            MYPCO_PLUGIN_URL . 'modules/calendar/public/assets/js/calendar-public.js',
            ['jquery'],
            MYPCO_VERSION,
            true
        );
    }

    /**
     * Render the calendar shortcode.
     * NO HTML HERE - just fetch data, process it, and pass to template.
     */
    public function render_calendar_shortcode($atts) {
        // Parse shortcode attributes
        $atts = shortcode_atts(['count' => 100], $atts, 'mypco_calendar');

        // Fetch data from API
        $events_data = $this->fetch_calendar_data($atts);

        // Check for errors
        if (isset($events_data['error'])) {
            return $this->render_error($events_data['error']);
        }

        // Process the data
        $processed_data = $this->process_calendar_data($events_data);

        // Pass to template and return output
        return $this->load_template('calendar-display', $processed_data);
    }

    /**
     * Fetch calendar data from PCO API.
     */
    private function fetch_calendar_data($atts) {
        $params = [
            'filter' => 'future',
            'per_page' => (int) $atts['count'],
            'include' => 'event'
        ];

        $transient_key = 'mypco_calendar_v12_' . md5(serialize($atts));

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
        $featured_events = [];
        $regular_events = [];

        foreach ($event_instances as $instance) {
            $parent_id = $instance['relationships']['event']['data']['id'] ?? null;
            $parent = $event_map[$parent_id] ?? null;

            if ($parent && !empty($parent['featured'])) {
                $featured_events[] = $this->format_event_instance($instance, $parent);
            } else {
                $regular_events[] = $this->format_event_instance($instance, $parent);
            }
        }

        // Build expanded events for JavaScript
        $expanded_events = $this->build_expanded_events($event_instances, $event_map);

        return [
            'featured_events' => $featured_events,
            'regular_events' => $regular_events,
            'all_events' => array_merge($featured_events, $regular_events),
            'event_map' => $event_map,
            'expanded_events' => $expanded_events,
            'current_month' => date('F Y'),
            'timezone' => $this->api_model->get_timezone()
        ];
    }

    /**
     * Format a single event instance for display.
     */
    private function format_event_instance($instance, $parent) {
        $attr = $instance['attributes'];

        return [
            'id' => $instance['id'],
            'name' => $parent['name'] ?? 'Untitled Event',
            'starts_at' => $attr['starts_at'],
            'ends_at' => $attr['ends_at'] ?? null,
            'all_day' => $attr['all_day_event'] ?? false,
            'location' => $attr['location'] ?? '',
            'description' => $parent['description'] ?? '',
            'summary' => $parent['summary'] ?? '',
            'image_url' => $parent['image_url'] ?? '',
            'featured' => !empty($parent['featured']),
            'registration_url' => $attr['registration_url'] ?? $attr['signup_url'] ?? $parent['registration_url'] ?? $parent['signup_url'] ?? ''
        ];
    }

    /**
     * Build expanded events array for JavaScript (multi-day events).
     */
    private function build_expanded_events($event_instances, $event_map) {
        $expanded = [];

        foreach ($event_instances as $instance) {
            $parent_id = $instance['relationships']['event']['data']['id'] ?? null;
            $parent = $event_map[$parent_id] ?? null;

            if (!$parent) continue;

            $event_id = $parent['id'] ?? '';

            if (!isset($expanded[$event_id])) {
                $expanded[$event_id] = [
                    'name' => $parent['name'] ?? '',
                    'instances' => []
                ];
            }

            $expanded[$event_id]['instances'][] = $instance;
        }

        return $expanded;
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
        }

        return ob_get_clean();
    }
}
