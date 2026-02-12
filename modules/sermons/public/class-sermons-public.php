<?php
/**
 * Sermons Public Component
 *
 * Handles all frontend/public functionality for the Sermons module.
 * Provides shortcodes for displaying sermons in various formats.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MyPCO_Sermons_Public {

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
        add_shortcode('mypco_messages', [$this, 'render_sermons_shortcode']);
        add_shortcode('mypco_sermons', [$this, 'render_sermons_shortcode']); // backward compat
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

        if (!has_shortcode($post->post_content, 'mypco_messages') && !has_shortcode($post->post_content, 'mypco_sermons')) {
            return;
        }

        wp_enqueue_style(
            'mypco-sermons-public',
            MYPCO_PLUGIN_URL . 'modules/sermons/public/assets/css/sermons.css',
            [],
            MYPCO_VERSION
        );

        wp_enqueue_script(
            'mypco-sermons-public',
            MYPCO_PLUGIN_URL . 'modules/sermons/public/assets/js/sermons.js',
            ['jquery'],
            MYPCO_VERSION,
            true
        );
    }

    /**
     * Get the URL for the default placeholder image.
     */
    public function get_placeholder_url() {
        return MYPCO_PLUGIN_URL . 'modules/sermons/public/assets/images/sermon-placeholder.svg';
    }

    /**
     * Render the sermons shortcode.
     *
     * If ?mypco_sermon=ID is present, renders the single sermon detail page.
     * Otherwise, renders the gallery of sermon cards.
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_sermons_shortcode($atts) {
        $atts = shortcode_atts([
            'id'      => 0,
            'count'   => 12,
            'series'  => '',
            'speaker' => '',
            'topic'   => '',
            'view'    => 'gallery',
            'orderby' => 'date',
            'order'   => 'DESC',
        ], $atts, 'mypco_messages');

        // Load centralized shortcode settings when id is provided
        $id = absint($atts['id']);
        if ($id > 0) {
            require_once MYPCO_PLUGIN_DIR . 'admin/class-mypco-shortcodes-admin.php';
            $settings = MyPCO_Shortcodes_Admin::get_shortcode_settings($id, 'mypco_sermons_list');
        } else {
            $settings = [];
        }

        // Apply settings with fallback to shortcode attributes
        $count = !empty($settings['count']) ? (int) $settings['count'] : (int) $atts['count'];
        $view = !empty($settings['view']) ? $settings['view'] : $atts['view'];
        $order = strtoupper($atts['order']) === 'ASC' ? 'ASC' : 'DESC';

        // Check for single sermon view
        $single_id = isset($_GET['mypco_sermon']) ? absint($_GET['mypco_sermon']) : 0;
        if ($single_id > 0) {
            return $this->render_single_sermon($single_id);
        }

        // Fetch sermons from database
        $sermons = $this->fetch_sermons([
            'count'   => $count,
            'series'  => $atts['series'],
            'speaker' => $atts['speaker'],
            'topic'   => $atts['topic'],
            'orderby' => $atts['orderby'],
            'order'   => $order,
        ]);

        if (empty($sermons)) {
            return '<div class="mypco-sermons-empty"><p>' . esc_html__('No messages found.', 'mypco-online') . '</p></div>';
        }

        $template = ($view === 'list') ? 'sermons-list' : 'sermons-gallery';

        return $this->load_template($template, [
            'sermons'         => $sermons,
            'view'            => $view,
            'atts'            => $atts,
            'placeholder_url' => $this->get_placeholder_url(),
        ]);
    }

    /**
     * Render a single sermon detail page.
     *
     * @param int $sermon_id Sermon ID.
     * @return string HTML output.
     */
    private function render_single_sermon($sermon_id) {
        $sermon = $this->fetch_single_sermon($sermon_id);

        if (!$sermon) {
            return '<div class="mypco-sermons-empty"><p>' . esc_html__('Message not found.', 'mypco-online') . '</p></div>';
        }

        return $this->load_template('sermon-single', [
            'sermon'          => $sermon,
            'placeholder_url' => $this->get_placeholder_url(),
        ]);
    }

    /**
     * Fetch a single sermon by ID with all joined data.
     *
     * @param int $sermon_id Sermon ID.
     * @return object|null Sermon object or null.
     */
    private function fetch_single_sermon($sermon_id) {
        global $wpdb;

        $table_sermons = $wpdb->prefix . 'mypco_sermons';
        $table_speakers = $wpdb->prefix . 'mypco_sermon_speakers';
        $table_series = $wpdb->prefix . 'mypco_sermon_series';
        $table_topics = $wpdb->prefix . 'mypco_sermon_topics';

        $query = "SELECT s.*,
                    sp.name AS speaker_name,
                    sp.title AS speaker_title,
                    sp.image_url AS speaker_image_url,
                    sr.title AS series_title,
                    sr.image_url AS series_image_url,
                    t.name AS topic_name
                  FROM {$table_sermons} s
                  LEFT JOIN {$table_speakers} sp ON s.speaker_id = sp.id
                  LEFT JOIN {$table_series} sr ON s.series_id = sr.id
                  LEFT JOIN {$table_topics} t ON s.topic_id = t.id
                  WHERE s.id = %d";

        return $wpdb->get_row($wpdb->prepare($query, $sermon_id));
    }

    /**
     * Fetch sermons from the database.
     *
     * @param array $args Query arguments.
     * @return array Array of sermon objects.
     */
    private function fetch_sermons($args) {
        global $wpdb;

        $table_sermons = $wpdb->prefix . 'mypco_sermons';
        $table_speakers = $wpdb->prefix . 'mypco_sermon_speakers';
        $table_series = $wpdb->prefix . 'mypco_sermon_series';
        $table_topics = $wpdb->prefix . 'mypco_sermon_topics';

        $where = '1=1';
        $params = [];

        // Filter by series (by ID or slug/title)
        if (!empty($args['series'])) {
            if (is_numeric($args['series'])) {
                $where .= ' AND s.series_id = %d';
                $params[] = absint($args['series']);
            } else {
                $where .= ' AND sr.title LIKE %s';
                $params[] = '%' . $wpdb->esc_like(sanitize_text_field($args['series'])) . '%';
            }
        }

        // Filter by speaker (by ID or name)
        if (!empty($args['speaker'])) {
            if (is_numeric($args['speaker'])) {
                $where .= ' AND s.speaker_id = %d';
                $params[] = absint($args['speaker']);
            } else {
                $where .= ' AND sp.name LIKE %s';
                $params[] = '%' . $wpdb->esc_like(sanitize_text_field($args['speaker'])) . '%';
            }
        }

        // Filter by topic (by ID or name)
        if (!empty($args['topic'])) {
            if (is_numeric($args['topic'])) {
                $where .= ' AND s.topic_id = %d';
                $params[] = absint($args['topic']);
            } else {
                $where .= ' AND t.name LIKE %s';
                $params[] = '%' . $wpdb->esc_like(sanitize_text_field($args['topic'])) . '%';
            }
        }

        // Order
        $order_col = 's.sermon_date';
        if ($args['orderby'] === 'title') {
            $order_col = 's.title';
        }

        $order = $args['order'] === 'ASC' ? 'ASC' : 'DESC';
        $limit = min(absint($args['count']), 100);

        $query = "SELECT s.*,
                    sp.name AS speaker_name,
                    sp.image_url AS speaker_image_url,
                    sr.title AS series_title,
                    sr.image_url AS series_image_url,
                    t.name AS topic_name
                  FROM {$table_sermons} s
                  LEFT JOIN {$table_speakers} sp ON s.speaker_id = sp.id
                  LEFT JOIN {$table_series} sr ON s.series_id = sr.id
                  LEFT JOIN {$table_topics} t ON s.topic_id = t.id
                  WHERE {$where}
                  ORDER BY {$order_col} {$order}
                  LIMIT %d";

        $params[] = $limit;

        return $wpdb->get_results($wpdb->prepare($query, $params));
    }

    /**
     * Load a template file and return output.
     */
    private function load_template($template_name, $data = []) {
        extract($data);

        ob_start();

        $template_path = MYPCO_PLUGIN_DIR . 'modules/sermons/public/templates/' . $template_name . '.php';

        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<!-- Template not found: ' . esc_html($template_name) . ' -->';
        }

        return ob_get_clean();
    }
}
