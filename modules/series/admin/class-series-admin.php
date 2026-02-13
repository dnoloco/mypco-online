<?php
/**
 * Series Admin Component
 *
 * Handles all backend/admin functionality for the Series module.
 * Provides CRUD operations for series, messages, speakers, and topics.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MyPCO_Series_Admin {

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
        $this->loader->add_filter('upload_dir', $this, 'custom_upload_dir');

        // Series Info meta box on Message post type
        $this->loader->add_action('add_meta_boxes', $this, 'add_series_info_meta_box');
        $this->loader->add_action('save_post_mypco_message', $this, 'save_series_info_meta', 10, 2);

        // Message Info meta box on Message post type
        $this->loader->add_action('add_meta_boxes', $this, 'add_message_info_meta_box');
        $this->loader->add_action('save_post_mypco_message', $this, 'save_message_info_meta', 10, 2);

        // Series taxonomy custom fields
        $this->loader->add_action('mypco_series_add_form_fields', $this, 'render_series_info_add_fields');
        $this->loader->add_action('mypco_series_edit_form_fields', $this, 'render_series_info_edit_fields');
        $this->loader->add_action('created_mypco_series', $this, 'save_series_info_term_meta');
        $this->loader->add_action('edited_mypco_series', $this, 'save_series_info_term_meta');
    }

    // =========================================================================
    // Admin Menu
    // =========================================================================

    /**
     * Add top-level Series menu with submenus.
     */
    public function add_admin_pages() {
        // Top-level menu
        add_menu_page(
            __('Series', 'mypco-online'),
            __('Series', 'mypco-online'),
            'edit_posts',
            'mypco-series',
            [$this, 'render_series_submenu'],
            'dashicons-microphone',
            26
        );

        // Submenu: Edit Series (replaces the auto-generated first item)
        add_submenu_page(
            'mypco-series',
            __('Edit Series', 'mypco-online'),
            __('Edit Series', 'mypco-online'),
            'edit_posts',
            'mypco-series',
            [$this, 'render_series_submenu']
        );

        // Submenu: Edit Speakers
        add_submenu_page(
            'mypco-series',
            __('Edit Speakers', 'mypco-online'),
            __('Edit Speakers', 'mypco-online'),
            'edit_posts',
            'mypco-series-speakers',
            [$this, 'render_speakers_submenu']
        );

        // Submenu: Edit Topics
        add_submenu_page(
            'mypco-series',
            __('Edit Topics', 'mypco-online'),
            __('Edit Topics', 'mypco-online'),
            'edit_posts',
            'mypco-series-topics',
            [$this, 'render_topics_submenu']
        );
    }

    /**
     * Enqueue admin-specific assets.
     */
    public function enqueue_admin_assets($hook) {
        $screen = get_current_screen();
        $is_module_post_type = ($screen && in_array($screen->post_type, ['mypco_message', 'mypco_speaker'], true));
        $is_module_taxonomy = ($screen && in_array($screen->taxonomy, ['mypco_series', 'mypco_service_type'], true));

        // Match our custom admin pages, post type editors, or taxonomy screens
        if (strpos($hook, 'mypco-series') === false && !$is_module_post_type && !$is_module_taxonomy) {
            return;
        }

        wp_enqueue_media();

        wp_enqueue_style(
            'mypco-series-admin',
            MYPCO_PLUGIN_URL . 'modules/series/admin/assets/css/series-admin.css',
            [],
            MYPCO_VERSION
        );

        wp_enqueue_script(
            'mypco-series-admin',
            MYPCO_PLUGIN_URL . 'modules/series/admin/assets/js/series-admin.js',
            ['jquery'],
            MYPCO_VERSION,
            true
        );
    }

    // =========================================================================
    // Submenu Render Callbacks
    // =========================================================================

    /**
     * Render the Series submenu (list, add/edit series, or add/edit message).
     */
    public function render_series_submenu() {
        $view = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : 'list';

        if ($view === 'edit' || $view === 'add_series') {
            $this->render_series_edit_page();
        } elseif ($view === 'edit_message' || $view === 'add_message') {
            $this->render_message_edit_page();
        } else {
            $this->render_series_list_page();
        }
    }

    /**
     * Render the Speakers submenu (list or add/edit).
     */
    public function render_speakers_submenu() {
        $view = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : 'list';

        if ($view === 'edit') {
            $this->render_speaker_edit_page();
        } else {
            $this->render_speakers_page();
        }
    }

    /**
     * Render the Topics submenu (list or add/edit).
     */
    public function render_topics_submenu() {
        $view = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : 'list';

        if ($view === 'edit') {
            $this->render_topic_edit_page();
        } else {
            $this->render_topics_page();
        }
    }

    // =========================================================================
    // Series List (Main Page)
    // =========================================================================

    /**
     * Render the series list page (main page).
     */
    private function render_series_list_page() {
        global $wpdb;
        $table_series = $wpdb->prefix . 'mypco_series';
        $table_messages = $wpdb->prefix . 'mypco_messages';

        $all_series = $wpdb->get_results(
            "SELECT s.*, COUNT(m.id) AS message_count
             FROM {$table_series} s
             LEFT JOIN {$table_messages} m ON m.series_id = s.id
             GROUP BY s.id
             ORDER BY s.start_date DESC"
        );

        $data = [
            'all_series' => $all_series,
            'success'    => isset($_GET['success']) ? sanitize_text_field($_GET['success']) : '',
        ];

        $this->load_template('series-page', $data);
    }

    // =========================================================================
    // Series Add/Edit (with Messages)
    // =========================================================================

    /**
     * Render the series add/edit page.
     */
    private function render_series_edit_page() {
        global $wpdb;
        $table_series = $wpdb->prefix . 'mypco_series';
        $table_messages = $wpdb->prefix . 'mypco_messages';
        $table_speakers = $wpdb->prefix . 'mypco_speakers';

        $series = null;
        $messages = [];
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;

        if ($id > 0) {
            $series = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_series} WHERE id = %d",
                $id
            ));

            if ($series) {
                // Fetch messages in this series
                $messages = $wpdb->get_results($wpdb->prepare(
                    "SELECT m.*, sp.name AS speaker_name
                     FROM {$table_messages} m
                     LEFT JOIN {$table_speakers} sp ON m.speaker_id = sp.id
                     WHERE m.series_id = %d
                     ORDER BY m.message_date DESC",
                    $id
                ));
            }
        }

        $data = [
            'series'   => $series,
            'messages' => $messages,
            'is_edit'  => ($id > 0 && $series),
        ];

        $this->load_template('series-edit', $data);
    }

    // =========================================================================
    // Message Add/Edit
    // =========================================================================

    /**
     * Render the message add/edit page.
     */
    private function render_message_edit_page() {
        global $wpdb;
        $table_messages = $wpdb->prefix . 'mypco_messages';
        $table_speakers = $wpdb->prefix . 'mypco_speakers';
        $table_series = $wpdb->prefix . 'mypco_series';
        $table_topics = $wpdb->prefix . 'mypco_topics';

        $view = sanitize_text_field($_GET['view']);
        $message = null;

        if ($view === 'edit_message') {
            $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
            if ($id > 0) {
                $message = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$table_messages} WHERE id = %d",
                    $id
                ));
            }
            if (!$message) {
                wp_redirect(admin_url('admin.php?page=mypco-series'));
                exit;
            }
        }

        // Pre-select series if coming from a series edit page
        $preselect_series = isset($_GET['series_id']) ? absint($_GET['series_id']) : 0;

        $speakers = $wpdb->get_results("SELECT id, name FROM {$table_speakers} ORDER BY name ASC");
        $all_series = $wpdb->get_results("SELECT id, title FROM {$table_series} ORDER BY title ASC");
        $topics = $wpdb->get_results("SELECT id, name FROM {$table_topics} ORDER BY name ASC");

        $data = [
            'message'          => $message,
            'speakers'         => $speakers,
            'all_series'       => $all_series,
            'topics'           => $topics,
            'is_edit'          => ($view === 'edit_message'),
            'preselect_series' => $preselect_series,
        ];

        $this->load_template('message-edit', $data);
    }

    // =========================================================================
    // Speakers
    // =========================================================================

    /**
     * Render the speakers management page.
     */
    private function render_speakers_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'mypco_speakers';

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
        $table = $wpdb->prefix . 'mypco_speakers';

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
    // Topics
    // =========================================================================

    /**
     * Render the topics management page.
     */
    private function render_topics_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'mypco_topics';

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
        $table = $wpdb->prefix . 'mypco_topics';

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
        if (isset($_POST['mypco_save_message'])) {
            $this->handle_save_message();
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

        if (isset($_GET['action']) && $_GET['action'] === 'delete_message') {
            $this->handle_delete_message();
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
     * Handle saving a message.
     */
    private function handle_save_message() {
        check_admin_referer('mypco_save_message');

        if (!current_user_can('edit_posts')) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'mypco_messages';

        $id = isset($_POST['message_id']) ? absint($_POST['message_id']) : 0;
        $series_id = absint($_POST['series_id'] ?? 0);

        $data = [
            'title'        => sanitize_text_field($_POST['message_title'] ?? ''),
            'message_date' => sanitize_text_field($_POST['message_date'] ?? ''),
            'speaker_id'   => absint($_POST['speaker_id'] ?? 0),
            'series_id'    => $series_id,
            'topic_id'     => absint($_POST['topic_id'] ?? 0),
            'scripture'    => sanitize_text_field($_POST['message_scripture'] ?? ''),
            'description'  => sanitize_textarea_field($_POST['message_description'] ?? ''),
            'audio_url'    => esc_url_raw($_POST['message_audio_url'] ?? ''),
            'video_url'    => esc_url_raw($_POST['message_video_url'] ?? ''),
            'image_url'    => esc_url_raw($_POST['message_image_url'] ?? ''),
        ];

        $format = ['%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s'];

        if ($id > 0) {
            $wpdb->update($table, $data, ['id' => $id], $format, ['%d']);
            $success = 'message_updated';
        } else {
            $data['created_at'] = current_time('mysql');
            $format[] = '%s';
            $wpdb->insert($table, $data, $format);
            $success = 'message_added';
        }

        // Redirect back to series edit page if we have a series_id
        if ($series_id > 0) {
            wp_redirect(admin_url('admin.php?page=mypco-series&view=edit&id=' . $series_id . '&success=' . $success));
        } else {
            wp_redirect(admin_url('admin.php?page=mypco-series&success=' . $success));
        }
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
        $table = $wpdb->prefix . 'mypco_speakers';

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

        wp_redirect(admin_url('admin.php?page=mypco-series-speakers&success=' . $success));
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
        $table = $wpdb->prefix . 'mypco_series';

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
            wp_redirect(admin_url('admin.php?page=mypco-series&view=edit&id=' . $id . '&success=' . $success));
        } else {
            $wpdb->insert($table, $data, $format);
            $new_id = $wpdb->insert_id;
            $success = 'series_added';
            wp_redirect(admin_url('admin.php?page=mypco-series&view=edit&id=' . $new_id . '&success=' . $success));
        }
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
        $table = $wpdb->prefix . 'mypco_topics';

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

        wp_redirect(admin_url('admin.php?page=mypco-series-topics&success=' . $success));
        exit;
    }

    /**
     * Handle deleting a message.
     */
    private function handle_delete_message() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'mypco-series') {
            return;
        }

        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        if ($id === 0) {
            return;
        }

        check_admin_referer('mypco_delete_message_' . $id);

        if (!current_user_can('edit_posts')) {
            return;
        }

        global $wpdb;
        // Get the series_id before deleting so we can redirect back
        $message = $wpdb->get_row($wpdb->prepare(
            "SELECT series_id FROM {$wpdb->prefix}mypco_messages WHERE id = %d",
            $id
        ));
        $series_id = $message ? $message->series_id : 0;

        $wpdb->delete($wpdb->prefix . 'mypco_messages', ['id' => $id], ['%d']);

        if ($series_id > 0) {
            wp_redirect(admin_url('admin.php?page=mypco-series&view=edit&id=' . $series_id . '&success=message_deleted'));
        } else {
            wp_redirect(admin_url('admin.php?page=mypco-series&success=message_deleted'));
        }
        exit;
    }

    /**
     * Handle deleting a speaker.
     */
    private function handle_delete_speaker() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'mypco-series-speakers') {
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
        $wpdb->delete($wpdb->prefix . 'mypco_speakers', ['id' => $id], ['%d']);

        wp_redirect(admin_url('admin.php?page=mypco-series-speakers&success=speaker_deleted'));
        exit;
    }

    /**
     * Handle deleting a series.
     */
    private function handle_delete_series() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'mypco-series') {
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
        $wpdb->delete($wpdb->prefix . 'mypco_series', ['id' => $id], ['%d']);

        wp_redirect(admin_url('admin.php?page=mypco-series&success=series_deleted'));
        exit;
    }

    /**
     * Handle deleting a topic.
     */
    private function handle_delete_topic() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'mypco-series-topics') {
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
        $wpdb->delete($wpdb->prefix . 'mypco_topics', ['id' => $id], ['%d']);

        wp_redirect(admin_url('admin.php?page=mypco-series-topics&success=topic_deleted'));
        exit;
    }

    // =========================================================================
    // Message Post Type – Meta Box (Series Info)
    // =========================================================================

    /**
     * Register the "Series Info" meta box on the mypco_message post type.
     */
    public function add_series_info_meta_box() {
        add_meta_box(
            'mypco_series_info',
            __('Series Info', 'mypco-online'),
            [$this, 'render_series_info_meta_box'],
            'mypco_message',
            'normal',
            'high'
        );
    }

    /**
     * Render the "Series Info" meta box fields.
     *
     * Reads from the first assigned mypco_series taxonomy term so the data
     * is shared with the term and not duplicated as post meta.
     */
    public function render_series_info_meta_box($post) {
        wp_nonce_field('mypco_series_info_meta_save', 'mypco_series_info_meta_nonce');

        $description = '';
        $start_date  = '';
        $image       = '';
        $term_name   = '';

        $terms = wp_get_post_terms($post->ID, 'mypco_series');
        if (!is_wp_error($terms) && !empty($terms)) {
            $term        = $terms[0];
            $term_name   = $term->name;
            $description = $term->description;
            $start_date  = get_term_meta($term->term_id, '_mypco_series_start_date', true);
            $image       = get_term_meta($term->term_id, '_mypco_series_image', true);
        }

        if (empty($terms) || is_wp_error($terms)) : ?>
            <p><em><?php esc_html_e('Select a Series from the Series panel to edit its info here.', 'mypco-online'); ?></em></p>
        <?php else : ?>
            <p><?php printf(
                /* translators: %s: series term name */
                esc_html__('Editing info for series: %s', 'mypco-online'),
                '<strong>' . esc_html($term_name) . '</strong>'
            ); ?></p>
            <table class="form-table">
                <tr>
                    <th><label for="mypco_series_description"><?php esc_html_e('Description', 'mypco-online'); ?></label></th>
                    <td>
                        <textarea id="mypco_series_description" name="mypco_series_description"
                                  rows="5" class="large-text"><?php echo esc_textarea($description); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th><label for="mypco_series_start_date"><?php esc_html_e('Start Date', 'mypco-online'); ?></label></th>
                    <td>
                        <input type="date" id="mypco_series_start_date" name="mypco_series_start_date"
                               value="<?php echo esc_attr($start_date); ?>" />
                    </td>
                </tr>
                <tr>
                    <th><label for="mypco_series_image"><?php esc_html_e('Image', 'mypco-online'); ?></label></th>
                    <td>
                        <input type="hidden" id="mypco_series_image" name="mypco_series_image"
                               value="<?php echo esc_url($image); ?>" />
                        <button type="button" class="button mypco-upload-image-btn"
                                data-target="#mypco_series_image"
                                data-preview="#mypco-series-image-preview"><?php esc_html_e('Select Image', 'mypco-online'); ?></button>
                        <button type="button" class="button mypco-remove-image-btn"
                                data-target="#mypco_series_image"
                                data-preview="#mypco-series-image-preview"
                                <?php echo $image ? '' : 'style="display:none;"'; ?>><?php esc_html_e('Remove Image', 'mypco-online'); ?></button>
                        <div id="mypco-series-image-preview" style="margin-top:10px;">
                            <?php if ($image) : ?>
                                <img src="<?php echo esc_url($image); ?>" style="max-width:200px;height:auto;" />
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            </table>
        <?php endif;
    }

    /**
     * Save the "Series Info" meta box data to the assigned Series taxonomy term.
     */
    public function save_series_info_meta($post_id, $post) {
        if (!isset($_POST['mypco_series_info_meta_nonce']) ||
            !wp_verify_nonce($_POST['mypco_series_info_meta_nonce'], 'mypco_series_info_meta_save')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $terms = wp_get_post_terms($post_id, 'mypco_series');
        if (is_wp_error($terms) || empty($terms)) {
            return;
        }

        $term_id = $terms[0]->term_id;

        if (isset($_POST['mypco_series_description'])) {
            wp_update_term($term_id, 'mypco_series', [
                'description' => sanitize_textarea_field($_POST['mypco_series_description']),
            ]);
        }

        if (isset($_POST['mypco_series_start_date'])) {
            update_term_meta($term_id, '_mypco_series_start_date', sanitize_text_field($_POST['mypco_series_start_date']));
        }

        if (isset($_POST['mypco_series_image'])) {
            update_term_meta($term_id, '_mypco_series_image', esc_url_raw($_POST['mypco_series_image']));
        }
    }

    // =========================================================================
    // Message Post Type – Meta Box (Message Info)
    // =========================================================================

    /**
     * Register the "Message Info" meta box on the mypco_message post type.
     */
    public function add_message_info_meta_box() {
        add_meta_box(
            'mypco_message_info',
            __('Message Info', 'mypco-online'),
            [$this, 'render_message_info_meta_box'],
            'mypco_message',
            'normal',
            'high'
        );
    }

    /**
     * Render the "Message Info" meta box fields.
     */
    public function render_message_info_meta_box($post) {
        wp_nonce_field('mypco_message_info_meta_save', 'mypco_message_info_meta_nonce');

        $description  = get_post_meta($post->ID, '_mypco_message_description', true);
        $message_date = get_post_meta($post->ID, '_mypco_message_date', true);
        $image        = get_post_meta($post->ID, '_mypco_message_image', true);
        ?>
        <table class="form-table">
            <tr>
                <th><label for="mypco_message_description"><?php esc_html_e('Description', 'mypco-online'); ?></label></th>
                <td>
                    <textarea id="mypco_message_description" name="mypco_message_description"
                              rows="5" class="large-text"><?php echo esc_textarea($description); ?></textarea>
                </td>
            </tr>
            <tr>
                <th><label for="mypco_message_date"><?php esc_html_e('Message Date', 'mypco-online'); ?></label></th>
                <td>
                    <input type="date" id="mypco_message_date" name="mypco_message_date"
                           value="<?php echo esc_attr($message_date); ?>" />
                </td>
            </tr>
            <tr>
                <th><label for="mypco_message_image"><?php esc_html_e('Image', 'mypco-online'); ?></label></th>
                <td>
                    <input type="hidden" id="mypco_message_image" name="mypco_message_image"
                           value="<?php echo esc_url($image); ?>" />
                    <button type="button" class="button mypco-upload-image-btn"
                            data-target="#mypco_message_image"
                            data-preview="#mypco-message-image-preview"><?php esc_html_e('Select Image', 'mypco-online'); ?></button>
                    <button type="button" class="button mypco-remove-image-btn"
                            data-target="#mypco_message_image"
                            data-preview="#mypco-message-image-preview"
                            <?php echo $image ? '' : 'style="display:none;"'; ?>><?php esc_html_e('Remove Image', 'mypco-online'); ?></button>
                    <div id="mypco-message-image-preview" style="margin-top:10px;">
                        <?php if ($image) : ?>
                            <img src="<?php echo esc_url($image); ?>" style="max-width:200px;height:auto;" />
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save the "Message Info" meta box data.
     */
    public function save_message_info_meta($post_id, $post) {
        if (!isset($_POST['mypco_message_info_meta_nonce']) ||
            !wp_verify_nonce($_POST['mypco_message_info_meta_nonce'], 'mypco_message_info_meta_save')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['mypco_message_description'])) {
            update_post_meta($post_id, '_mypco_message_description', sanitize_textarea_field($_POST['mypco_message_description']));
        }

        if (isset($_POST['mypco_message_date'])) {
            update_post_meta($post_id, '_mypco_message_date', sanitize_text_field($_POST['mypco_message_date']));
        }

        if (isset($_POST['mypco_message_image'])) {
            update_post_meta($post_id, '_mypco_message_image', esc_url_raw($_POST['mypco_message_image']));
        }
    }

    // =========================================================================
    // Series Taxonomy – Custom Fields
    // =========================================================================

    /**
     * Render Series Info custom fields on the "Add New Series" term form.
     *
     * Name and Description are built-in taxonomy term fields.
     * Start Date and Image are stored as term meta.
     */
    public function render_series_info_add_fields() {
        wp_nonce_field('mypco_series_info_save', 'mypco_series_info_nonce');
        ?>
        <div class="form-field">
            <label for="mypco_series_start_date"><?php esc_html_e('Start Date', 'mypco-online'); ?></label>
            <input type="date" id="mypco_series_start_date" name="mypco_series_start_date" value="" />
        </div>
        <div class="form-field">
            <label for="mypco_series_image"><?php esc_html_e('Image', 'mypco-online'); ?></label>
            <input type="url" id="mypco_series_image" name="mypco_series_image" value="" class="regular-text" />
            <button type="button" class="button mypco-upload-image-btn"
                    data-target="#mypco_series_image"><?php esc_html_e('Upload Image', 'mypco-online'); ?></button>
        </div>
        <?php
    }

    /**
     * Render Series Info custom fields on the "Edit Series" term form.
     */
    public function render_series_info_edit_fields($term) {
        wp_nonce_field('mypco_series_info_save', 'mypco_series_info_nonce');

        $start_date = get_term_meta($term->term_id, '_mypco_series_start_date', true);
        $image      = get_term_meta($term->term_id, '_mypco_series_image', true);
        ?>
        <tr class="form-field">
            <th scope="row"><label for="mypco_series_start_date"><?php esc_html_e('Start Date', 'mypco-online'); ?></label></th>
            <td>
                <input type="date" id="mypco_series_start_date" name="mypco_series_start_date"
                       value="<?php echo esc_attr($start_date); ?>" />
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="mypco_series_image"><?php esc_html_e('Image', 'mypco-online'); ?></label></th>
            <td>
                <input type="url" id="mypco_series_image" name="mypco_series_image"
                       value="<?php echo esc_url($image); ?>" class="regular-text" />
                <button type="button" class="button mypco-upload-image-btn"
                        data-target="#mypco_series_image"><?php esc_html_e('Upload Image', 'mypco-online'); ?></button>
                <?php if ($image) : ?>
                    <div style="margin-top:10px;">
                        <img src="<?php echo esc_url($image); ?>" style="max-width:200px;height:auto;" />
                    </div>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }

    /**
     * Save Series Info term meta when a Series term is created or updated.
     */
    public function save_series_info_term_meta($term_id) {
        if (!isset($_POST['mypco_series_info_nonce']) ||
            !wp_verify_nonce($_POST['mypco_series_info_nonce'], 'mypco_series_info_save')) {
            return;
        }

        if (!current_user_can('manage_categories')) {
            return;
        }

        if (isset($_POST['mypco_series_start_date'])) {
            update_term_meta($term_id, '_mypco_series_start_date', sanitize_text_field($_POST['mypco_series_start_date']));
        }

        if (isset($_POST['mypco_series_image'])) {
            update_term_meta($term_id, '_mypco_series_image', esc_url_raw($_POST['mypco_series_image']));
        }
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Customize the upload directory for series module uploads.
     *
     * When uploads come from our admin pages with a mypco_upload_type param,
     * route them into organised subdirectories:
     *   wp-content/uploads/mypco-series/speakers/
     *   wp-content/uploads/mypco-series/messages/
     *   wp-content/uploads/mypco-series/series/
     */
    public function custom_upload_dir($uploads) {
        $type = isset($_REQUEST['mypco_upload_type']) ? sanitize_key($_REQUEST['mypco_upload_type']) : '';

        $allowed = ['speakers', 'messages', 'series'];
        if (empty($type) || !in_array($type, $allowed, true)) {
            return $uploads;
        }

        $subdir = '/mypco-series/' . $type;

        $uploads['subdir'] = $subdir;
        $uploads['path']   = $uploads['basedir'] . $subdir;
        $uploads['url']    = $uploads['baseurl'] . $subdir;

        return $uploads;
    }

    /**
     * Load a template file.
     */
    private function load_template($template_name, $data = []) {
        extract($data);
        $template_path = MYPCO_PLUGIN_DIR . 'modules/series/admin/templates/' . $template_name . '.php';

        if (file_exists($template_path)) {
            include $template_path;
        }
    }
}
