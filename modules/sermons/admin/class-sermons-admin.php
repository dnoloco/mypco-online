<?php
/**
 * Sermons Admin Component
 *
 * Handles all backend/admin functionality for the Sermons module.
 * Provides CRUD operations for sermons, speakers, series, and topics.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MyPCO_Sermons_Admin {

    private $loader;
    private $api_model;

    public function __construct($loader, $api_model) {
        $this->loader = $loader;
        $this->api_model = $api_model;
    }

    /**
     * Initialize admin functionality.
     */
    public function init() {
        $this->loader->add_action('admin_menu', $this, 'add_admin_pages');
        $this->loader->add_action('admin_enqueue_scripts', $this, 'enqueue_admin_assets');
        $this->loader->add_action('admin_init', $this, 'handle_form_submissions');
    }

    // =========================================================================
    // Admin Menu
    // =========================================================================

    /**
     * Add admin menu pages.
     */
    public function add_admin_pages() {
        add_submenu_page(
            'mypco-dashboard',
            __('Sermons', 'mypco-online'),
            __('Sermons', 'mypco-online'),
            'edit_posts',
            'mypco-sermons',
            [$this, 'render_page']
        );
    }

    /**
     * Enqueue admin-specific assets.
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'mypco-sermons') === false) {
            return;
        }

        wp_enqueue_style(
            'mypco-sermons-admin',
            MYPCO_PLUGIN_URL . 'modules/sermons/admin/assets/css/sermons-admin.css',
            [],
            MYPCO_VERSION
        );

        wp_enqueue_script(
            'mypco-sermons-admin',
            MYPCO_PLUGIN_URL . 'modules/sermons/admin/assets/js/sermons-admin.js',
            ['jquery'],
            MYPCO_VERSION,
            true
        );
    }

    // =========================================================================
    // Page Routing
    // =========================================================================

    /**
     * Render the admin page (routes to the correct view).
     */
    public function render_page() {
        $view = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : 'list';

        switch ($view) {
            case 'add':
            case 'edit':
                $this->render_sermon_edit_page();
                break;
            case 'speakers':
                $this->render_speakers_page();
                break;
            case 'speaker_edit':
                $this->render_speaker_edit_page();
                break;
            case 'series':
                $this->render_series_page();
                break;
            case 'series_edit':
                $this->render_series_edit_page();
                break;
            case 'topics':
                $this->render_topics_page();
                break;
            case 'topic_edit':
                $this->render_topic_edit_page();
                break;
            default:
                $this->render_sermons_list_page();
                break;
        }
    }

    // =========================================================================
    // Sermons List
    // =========================================================================

    /**
     * Render the sermons list page.
     */
    private function render_sermons_list_page() {
        global $wpdb;
        $table_sermons = $wpdb->prefix . 'mypco_sermons';
        $table_speakers = $wpdb->prefix . 'mypco_sermon_speakers';
        $table_series = $wpdb->prefix . 'mypco_sermon_series';

        // Filters
        $filter_series = isset($_GET['filter_series']) ? absint($_GET['filter_series']) : 0;
        $filter_speaker = isset($_GET['filter_speaker']) ? absint($_GET['filter_speaker']) : 0;
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

        $where = '1=1';
        $params = [];

        if ($filter_series > 0) {
            $where .= ' AND s.series_id = %d';
            $params[] = $filter_series;
        }

        if ($filter_speaker > 0) {
            $where .= ' AND s.speaker_id = %d';
            $params[] = $filter_speaker;
        }

        if (!empty($search)) {
            $where .= ' AND s.title LIKE %s';
            $params[] = '%' . $wpdb->esc_like($search) . '%';
        }

        $query = "SELECT s.*,
                    sp.name AS speaker_name,
                    sr.title AS series_title
                  FROM {$table_sermons} s
                  LEFT JOIN {$table_speakers} sp ON s.speaker_id = sp.id
                  LEFT JOIN {$table_series} sr ON s.series_id = sr.id
                  WHERE {$where}
                  ORDER BY s.sermon_date DESC";

        if (!empty($params)) {
            $sermons = $wpdb->get_results($wpdb->prepare($query, $params));
        } else {
            $sermons = $wpdb->get_results($query);
        }

        // Get all speakers and series for filter dropdowns
        $speakers = $wpdb->get_results("SELECT id, name FROM {$table_speakers} ORDER BY name ASC");
        $all_series = $wpdb->get_results("SELECT id, title FROM {$table_series} ORDER BY title ASC");

        $data = [
            'sermons'        => $sermons,
            'speakers'       => $speakers,
            'all_series'     => $all_series,
            'filter_series'  => $filter_series,
            'filter_speaker' => $filter_speaker,
            'search'         => $search,
            'success'        => isset($_GET['success']) ? sanitize_text_field($_GET['success']) : '',
        ];

        $this->load_template('sermons-page', $data);
    }

    // =========================================================================
    // Sermon Add/Edit
    // =========================================================================

    /**
     * Render the sermon add/edit page.
     */
    private function render_sermon_edit_page() {
        global $wpdb;
        $table_sermons = $wpdb->prefix . 'mypco_sermons';
        $table_speakers = $wpdb->prefix . 'mypco_sermon_speakers';
        $table_series = $wpdb->prefix . 'mypco_sermon_series';
        $table_topics = $wpdb->prefix . 'mypco_sermon_topics';

        $view = sanitize_text_field($_GET['view']);
        $sermon = null;

        if ($view === 'edit') {
            $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
            if ($id > 0) {
                $sermon = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$table_sermons} WHERE id = %d",
                    $id
                ));
            }
            if (!$sermon) {
                wp_redirect(admin_url('admin.php?page=mypco-sermons'));
                exit;
            }
        }

        $speakers = $wpdb->get_results("SELECT id, name FROM {$table_speakers} ORDER BY name ASC");
        $all_series = $wpdb->get_results("SELECT id, title FROM {$table_series} ORDER BY title ASC");
        $topics = $wpdb->get_results("SELECT id, name FROM {$table_topics} ORDER BY name ASC");

        $data = [
            'sermon'     => $sermon,
            'speakers'   => $speakers,
            'all_series' => $all_series,
            'topics'     => $topics,
            'is_edit'    => ($view === 'edit'),
        ];

        $this->load_template('sermon-edit', $data);
    }

    // =========================================================================
    // Speakers
    // =========================================================================

    /**
     * Render the speakers management page.
     */
    private function render_speakers_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'mypco_sermon_speakers';

        $speakers = $wpdb->get_results("SELECT * FROM {$table} ORDER BY name ASC");

        $data = [
            'speakers' => $speakers,
            'success'  => isset($_GET['success']) ? sanitize_text_field($_GET['success']) : '',
        ];

        $this->load_template('speakers-page', $data);
    }

    /**
     * Render the speaker add/edit page.
     */
    private function render_speaker_edit_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'mypco_sermon_speakers';

        $speaker = null;
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;

        if ($id > 0) {
            $speaker = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d",
                $id
            ));
        }

        $data = [
            'speaker' => $speaker,
            'is_edit' => ($id > 0 && $speaker),
        ];

        $this->load_template('speaker-edit', $data);
    }

    // =========================================================================
    // Series
    // =========================================================================

    /**
     * Render the series management page.
     */
    private function render_series_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'mypco_sermon_series';

        $all_series = $wpdb->get_results("SELECT * FROM {$table} ORDER BY start_date DESC");

        $data = [
            'all_series' => $all_series,
            'success'    => isset($_GET['success']) ? sanitize_text_field($_GET['success']) : '',
        ];

        $this->load_template('series-page', $data);
    }

    /**
     * Render the series add/edit page.
     */
    private function render_series_edit_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'mypco_sermon_series';

        $series = null;
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;

        if ($id > 0) {
            $series = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d",
                $id
            ));
        }

        $data = [
            'series'  => $series,
            'is_edit' => ($id > 0 && $series),
        ];

        $this->load_template('series-edit', $data);
    }

    // =========================================================================
    // Topics
    // =========================================================================

    /**
     * Render the topics management page.
     */
    private function render_topics_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'mypco_sermon_topics';

        $topics = $wpdb->get_results("SELECT * FROM {$table} ORDER BY name ASC");

        $data = [
            'topics'  => $topics,
            'success' => isset($_GET['success']) ? sanitize_text_field($_GET['success']) : '',
        ];

        $this->load_template('topics-page', $data);
    }

    /**
     * Render the topic add/edit page.
     */
    private function render_topic_edit_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'mypco_sermon_topics';

        $topic = null;
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;

        if ($id > 0) {
            $topic = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d",
                $id
            ));
        }

        $data = [
            'topic'   => $topic,
            'is_edit' => ($id > 0 && $topic),
        ];

        $this->load_template('topic-edit', $data);
    }

    // =========================================================================
    // Form Handlers
    // =========================================================================

    /**
     * Handle all form submissions.
     */
    public function handle_form_submissions() {
        if (isset($_POST['mypco_save_sermon'])) {
            $this->handle_save_sermon();
        }

        if (isset($_POST['mypco_save_speaker'])) {
            $this->handle_save_speaker();
        }

        if (isset($_POST['mypco_save_series'])) {
            $this->handle_save_series();
        }

        if (isset($_POST['mypco_save_topic'])) {
            $this->handle_save_topic();
        }

        if (isset($_GET['action']) && $_GET['action'] === 'delete_sermon') {
            $this->handle_delete_sermon();
        }

        if (isset($_GET['action']) && $_GET['action'] === 'delete_speaker') {
            $this->handle_delete_speaker();
        }

        if (isset($_GET['action']) && $_GET['action'] === 'delete_series') {
            $this->handle_delete_series();
        }

        if (isset($_GET['action']) && $_GET['action'] === 'delete_topic') {
            $this->handle_delete_topic();
        }
    }

    /**
     * Handle saving a sermon.
     */
    private function handle_save_sermon() {
        check_admin_referer('mypco_save_sermon');

        if (!current_user_can('edit_posts')) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'mypco_sermons';

        $id = isset($_POST['sermon_id']) ? absint($_POST['sermon_id']) : 0;

        $data = [
            'title'       => sanitize_text_field($_POST['sermon_title'] ?? ''),
            'sermon_date' => sanitize_text_field($_POST['sermon_date'] ?? ''),
            'speaker_id'  => absint($_POST['speaker_id'] ?? 0),
            'series_id'   => absint($_POST['series_id'] ?? 0),
            'topic_id'    => absint($_POST['topic_id'] ?? 0),
            'scripture'   => sanitize_text_field($_POST['sermon_scripture'] ?? ''),
            'description' => sanitize_textarea_field($_POST['sermon_description'] ?? ''),
            'audio_url'   => esc_url_raw($_POST['sermon_audio_url'] ?? ''),
            'video_url'   => esc_url_raw($_POST['sermon_video_url'] ?? ''),
            'image_url'   => esc_url_raw($_POST['sermon_image_url'] ?? ''),
        ];

        $format = ['%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s'];

        if ($id > 0) {
            $wpdb->update($table, $data, ['id' => $id], $format, ['%d']);
            $success = 'sermon_updated';
        } else {
            $data['created_at'] = current_time('mysql');
            $format[] = '%s';
            $wpdb->insert($table, $data, $format);
            $success = 'sermon_added';
        }

        wp_redirect(admin_url('admin.php?page=mypco-sermons&success=' . $success));
        exit;
    }

    /**
     * Handle saving a speaker.
     */
    private function handle_save_speaker() {
        check_admin_referer('mypco_save_speaker');

        if (!current_user_can('edit_posts')) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'mypco_sermon_speakers';

        $id = isset($_POST['speaker_id']) ? absint($_POST['speaker_id']) : 0;

        $data = [
            'name'      => sanitize_text_field($_POST['speaker_name'] ?? ''),
            'title'     => sanitize_text_field($_POST['speaker_title'] ?? ''),
            'bio'       => sanitize_textarea_field($_POST['speaker_bio'] ?? ''),
            'image_url' => esc_url_raw($_POST['speaker_image_url'] ?? ''),
        ];

        $format = ['%s', '%s', '%s', '%s'];

        if ($id > 0) {
            $wpdb->update($table, $data, ['id' => $id], $format, ['%d']);
            $success = 'speaker_updated';
        } else {
            $wpdb->insert($table, $data, $format);
            $success = 'speaker_added';
        }

        wp_redirect(admin_url('admin.php?page=mypco-sermons&view=speakers&success=' . $success));
        exit;
    }

    /**
     * Handle saving a series.
     */
    private function handle_save_series() {
        check_admin_referer('mypco_save_series');

        if (!current_user_can('edit_posts')) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'mypco_sermon_series';

        $id = isset($_POST['series_id']) ? absint($_POST['series_id']) : 0;

        $data = [
            'title'       => sanitize_text_field($_POST['series_title'] ?? ''),
            'description' => sanitize_textarea_field($_POST['series_description'] ?? ''),
            'image_url'   => esc_url_raw($_POST['series_image_url'] ?? ''),
            'start_date'  => sanitize_text_field($_POST['series_start_date'] ?? ''),
            'end_date'    => sanitize_text_field($_POST['series_end_date'] ?? ''),
        ];

        $format = ['%s', '%s', '%s', '%s', '%s'];

        if ($id > 0) {
            $wpdb->update($table, $data, ['id' => $id], $format, ['%d']);
            $success = 'series_updated';
        } else {
            $wpdb->insert($table, $data, $format);
            $success = 'series_added';
        }

        wp_redirect(admin_url('admin.php?page=mypco-sermons&view=series&success=' . $success));
        exit;
    }

    /**
     * Handle saving a topic.
     */
    private function handle_save_topic() {
        check_admin_referer('mypco_save_topic');

        if (!current_user_can('edit_posts')) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'mypco_sermon_topics';

        $id = isset($_POST['topic_id']) ? absint($_POST['topic_id']) : 0;

        $data = [
            'name'        => sanitize_text_field($_POST['topic_name'] ?? ''),
            'description' => sanitize_textarea_field($_POST['topic_description'] ?? ''),
        ];

        $format = ['%s', '%s'];

        if ($id > 0) {
            $wpdb->update($table, $data, ['id' => $id], $format, ['%d']);
            $success = 'topic_updated';
        } else {
            $wpdb->insert($table, $data, $format);
            $success = 'topic_added';
        }

        wp_redirect(admin_url('admin.php?page=mypco-sermons&view=topics&success=' . $success));
        exit;
    }

    /**
     * Handle deleting a sermon.
     */
    private function handle_delete_sermon() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'mypco-sermons') {
            return;
        }

        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        if ($id === 0) {
            return;
        }

        check_admin_referer('mypco_delete_sermon_' . $id);

        if (!current_user_can('edit_posts')) {
            return;
        }

        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'mypco_sermons', ['id' => $id], ['%d']);

        wp_redirect(admin_url('admin.php?page=mypco-sermons&success=sermon_deleted'));
        exit;
    }

    /**
     * Handle deleting a speaker.
     */
    private function handle_delete_speaker() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'mypco-sermons') {
            return;
        }

        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        if ($id === 0) {
            return;
        }

        check_admin_referer('mypco_delete_speaker_' . $id);

        if (!current_user_can('edit_posts')) {
            return;
        }

        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'mypco_sermon_speakers', ['id' => $id], ['%d']);

        wp_redirect(admin_url('admin.php?page=mypco-sermons&view=speakers&success=speaker_deleted'));
        exit;
    }

    /**
     * Handle deleting a series.
     */
    private function handle_delete_series() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'mypco-sermons') {
            return;
        }

        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        if ($id === 0) {
            return;
        }

        check_admin_referer('mypco_delete_series_' . $id);

        if (!current_user_can('edit_posts')) {
            return;
        }

        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'mypco_sermon_series', ['id' => $id], ['%d']);

        wp_redirect(admin_url('admin.php?page=mypco-sermons&view=series&success=series_deleted'));
        exit;
    }

    /**
     * Handle deleting a topic.
     */
    private function handle_delete_topic() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'mypco-sermons') {
            return;
        }

        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        if ($id === 0) {
            return;
        }

        check_admin_referer('mypco_delete_topic_' . $id);

        if (!current_user_can('edit_posts')) {
            return;
        }

        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'mypco_sermon_topics', ['id' => $id], ['%d']);

        wp_redirect(admin_url('admin.php?page=mypco-sermons&view=topics&success=topic_deleted'));
        exit;
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Load a template file.
     */
    private function load_template($template_name, $data = []) {
        extract($data);
        $template_path = MYPCO_PLUGIN_DIR . 'modules/sermons/admin/templates/' . $template_name . '.php';

        if (file_exists($template_path)) {
            include $template_path;
        }
    }
}
