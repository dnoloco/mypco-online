<?php
/**
 * Series Admin Component
 *
 * Handles all backend/admin functionality for the Series module.
 * Provides meta boxes for the Message and Speaker post types,
 * custom fields for the Series taxonomy, and inline AJAX speaker creation.
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
        $this->loader->add_action('admin_enqueue_scripts', $this, 'enqueue_admin_assets');
        $this->loader->add_filter('upload_dir', $this, 'custom_upload_dir');

        // Meta boxes on Message post type (order determines display order)
        $this->loader->add_action('add_meta_boxes', $this, 'add_message_info_meta_box');
        $this->loader->add_action('save_post_mypco_message', $this, 'save_message_info_meta', 10, 2);

        $this->loader->add_action('add_meta_boxes', $this, 'add_scripture_meta_box');
        $this->loader->add_action('save_post_mypco_message', $this, 'save_scripture_meta', 10, 2);

        $this->loader->add_action('add_meta_boxes', $this, 'add_media_meta_box');
        $this->loader->add_action('save_post_mypco_message', $this, 'save_media_meta', 10, 2);

        $this->loader->add_action('add_meta_boxes', $this, 'add_speaker_meta_box');
        $this->loader->add_action('save_post_mypco_message', $this, 'save_speaker_meta', 10, 2);

        $this->loader->add_action('add_meta_boxes', $this, 'add_series_info_meta_box');
        $this->loader->add_action('save_post_mypco_message', $this, 'save_series_info_meta', 10, 2);

        // Force meta box display order (overrides any saved user preference)
        $this->loader->add_filter('get_user_option_meta-box-order_mypco_message', $this, 'force_meta_box_order');

        // Meta boxes on Speaker post type
        $this->loader->add_action('add_meta_boxes', $this, 'add_speaker_details_meta_box');
        $this->loader->add_action('save_post_mypco_speaker', $this, 'save_speaker_details_meta', 10, 2);

        // AJAX: create speaker from Message editor meta box
        $this->loader->add_action('wp_ajax_mypco_add_speaker', $this, 'ajax_add_speaker');

        // Series taxonomy custom fields
        $this->loader->add_action('mypco_series_add_form_fields', $this, 'render_series_info_add_fields');
        $this->loader->add_action('mypco_series_edit_form_fields', $this, 'render_series_info_edit_fields');
        $this->loader->add_action('created_mypco_series', $this, 'save_series_info_term_meta');
        $this->loader->add_action('edited_mypco_series', $this, 'save_series_info_term_meta');
    }

    // =========================================================================
    // Admin Assets
    // =========================================================================

    /**
     * Enqueue admin-specific assets.
     */
    public function enqueue_admin_assets($hook) {
        $screen = get_current_screen();
        $is_module_post_type = ($screen && in_array($screen->post_type, ['mypco_message', 'mypco_speaker'], true));
        $is_module_taxonomy = ($screen && in_array($screen->taxonomy, ['mypco_series', 'mypco_service_type'], true));

        if (!$is_module_post_type && !$is_module_taxonomy) {
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

        $localize_data = [
            'ajaxUrl'         => admin_url('admin-ajax.php'),
            'addSpeakerNonce' => wp_create_nonce('mypco_add_speaker'),
        ];

        // Include Bible data only on the message editor
        if ($screen && $screen->post_type === 'mypco_message') {
            $localize_data['bibleData'] = include MYPCO_PLUGIN_DIR . 'modules/series/admin/bible-data.php';
            $localize_data['i18n'] = [
                'selectBook' => __('Select Book', 'mypco-online'),
                'chapter'    => __('Chapter', 'mypco-online'),
                'verseStart' => __('Start Verse', 'mypco-online'),
                'verseEnd'   => __('End Verse', 'mypco-online'),
            ];
        }

        wp_localize_script('mypco-series-admin', 'mypcoSeriesAdmin', $localize_data);
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
            <table class="form-table mypco-meta-table">
                <tr>
                    <th><label for="mypco_series_description"><?php esc_html_e('Description', 'mypco-online'); ?></label></th>
                    <td>
                        <textarea id="mypco_series_description" name="mypco_series_description"
                                  rows="4"><?php echo esc_textarea($description); ?></textarea>
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
    // Message Post Type – Meta Box (Speaker)
    // =========================================================================

    /**
     * Register the "Speaker" meta box on the mypco_message post type.
     */
    public function add_speaker_meta_box() {
        add_meta_box(
            'mypco_speaker_meta',
            __('Message Speaker', 'mypco-online'),
            [$this, 'render_speaker_meta_box'],
            'mypco_message',
            'normal',
            'high'
        );
    }

    /**
     * Render the "Speaker" meta box with a dropdown and toggle-able inline add.
     */
    public function render_speaker_meta_box($post) {
        wp_nonce_field('mypco_speaker_meta_save', 'mypco_speaker_meta_nonce');

        $current_speaker = get_post_meta($post->ID, '_mypco_speaker_id', true);

        $speakers = get_posts([
            'post_type'      => 'mypco_speaker',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'post_status'    => 'publish',
        ]);
        ?>
        <table class="form-table mypco-meta-table">
            <tr>
                <th><label for="mypco_speaker_id"><?php esc_html_e('Speaker', 'mypco-online'); ?></label></th>
                <td>
                    <select name="mypco_speaker_id" id="mypco_speaker_id">
                        <option value=""><?php esc_html_e('Select a Speaker', 'mypco-online'); ?></option>
                        <?php foreach ($speakers as $speaker) : ?>
                            <option value="<?php echo (int) $speaker->ID; ?>" <?php selected($current_speaker, $speaker->ID); ?>>
                                <?php echo esc_html($speaker->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <a href="#" id="mypco_toggle_add_speaker" style="display:inline-block;margin-left:8px;font-size:12px;">
                        <?php esc_html_e('Add New Speaker', 'mypco-online'); ?>
                    </a>
                    <div id="mypco_add_speaker_form" style="display:none;margin-top:8px;">
                        <div class="mypco-field-with-button">
                            <input type="text" id="mypco_new_speaker_name" class="regular-text"
                                   placeholder="<?php esc_attr_e('Speaker name', 'mypco-online'); ?>" />
                            <input type="button" id="mypco_add_speaker_btn" class="button"
                                   value="<?php esc_attr_e('Add New Speaker', 'mypco-online'); ?>" />
                        </div>
                        <span id="mypco_add_speaker_status" style="display:none;font-style:italic;font-size:12px;margin-left:4px;"></span>
                    </div>
                    <p class="description"><?php esc_html_e('Choose the speaker for this message.', 'mypco-online'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save the selected speaker ID as post meta.
     */
    public function save_speaker_meta($post_id, $post) {
        if (!isset($_POST['mypco_speaker_meta_nonce']) ||
            !wp_verify_nonce($_POST['mypco_speaker_meta_nonce'], 'mypco_speaker_meta_save')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $speaker_id = isset($_POST['mypco_speaker_id']) ? absint($_POST['mypco_speaker_id']) : 0;

        if ($speaker_id > 0) {
            update_post_meta($post_id, '_mypco_speaker_id', $speaker_id);
        } else {
            delete_post_meta($post_id, '_mypco_speaker_id');
        }
    }

    /**
     * AJAX: create a new mypco_speaker post from the Message editor meta box.
     */
    public function ajax_add_speaker() {
        check_ajax_referer('mypco_add_speaker', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Permission denied.', 'mypco-online')]);
        }

        $name = isset($_POST['speaker_name']) ? sanitize_text_field($_POST['speaker_name']) : '';
        if (empty($name)) {
            wp_send_json_error(['message' => __('Speaker name is required.', 'mypco-online')]);
        }

        $post_id = wp_insert_post([
            'post_type'   => 'mypco_speaker',
            'post_title'  => $name,
            'post_status' => 'publish',
        ], true);

        if (is_wp_error($post_id)) {
            wp_send_json_error(['message' => $post_id->get_error_message()]);
        }

        wp_send_json_success([
            'id'   => $post_id,
            'name' => $name,
        ]);
    }

    // =========================================================================
    // Speaker Post Type – Meta Box (Speaker Details)
    // =========================================================================

    /**
     * Register the "Speaker Details" meta box on the mypco_speaker post type.
     */
    public function add_speaker_details_meta_box() {
        add_meta_box(
            'mypco_speaker_details',
            __('Speaker Details', 'mypco-online'),
            [$this, 'render_speaker_details_meta_box'],
            'mypco_speaker',
            'normal',
            'high'
        );
    }

    /**
     * Render the "Speaker Details" meta box fields.
     */
    public function render_speaker_details_meta_box($post) {
        wp_nonce_field('mypco_speaker_details_meta_save', 'mypco_speaker_details_meta_nonce');

        $title_role = get_post_meta($post->ID, '_mypco_speaker_title', true);
        $image      = get_post_meta($post->ID, '_mypco_speaker_image', true);
        $links      = get_post_meta($post->ID, '_mypco_speaker_links', true);

        if (!is_array($links) || empty($links)) {
            $links = [['label' => '', 'url' => '']];
        }
        ?>
        <table class="form-table mypco-meta-table">
            <tr>
                <th><label for="mypco_speaker_title"><?php esc_html_e('Title / Role', 'mypco-online'); ?></label></th>
                <td>
                    <input type="text" id="mypco_speaker_title" name="mypco_speaker_title"
                           value="<?php echo esc_attr($title_role); ?>" class="regular-text"
                           placeholder="<?php esc_attr_e('e.g. Senior Pastor', 'mypco-online'); ?>" />
                </td>
            </tr>
            <tr>
                <th><label for="mypco_speaker_image"><?php esc_html_e('Photo', 'mypco-online'); ?></label></th>
                <td>
                    <input type="hidden" id="mypco_speaker_image" name="mypco_speaker_image"
                           value="<?php echo esc_url($image); ?>" />
                    <div class="mypco-field-with-button">
                        <button type="button" class="button mypco-upload-image-btn"
                                data-target="#mypco_speaker_image"
                                data-preview="#mypco-speaker-image-preview"><?php esc_html_e('Select Image', 'mypco-online'); ?></button>
                        <button type="button" class="button mypco-remove-image-btn"
                                data-target="#mypco_speaker_image"
                                data-preview="#mypco-speaker-image-preview"
                                <?php echo $image ? '' : 'style="display:none;"'; ?>><?php esc_html_e('Remove Image', 'mypco-online'); ?></button>
                    </div>
                    <div id="mypco-speaker-image-preview" style="margin-top:10px;">
                        <?php if ($image) : ?>
                            <img src="<?php echo esc_url($image); ?>" style="max-width:200px;height:auto;" />
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('Links', 'mypco-online'); ?></th>
                <td>
                    <div id="mypco-speaker-links">
                        <?php foreach ($links as $i => $link) : ?>
                            <div class="mypco-speaker-link-row" data-index="<?php echo (int) $i; ?>">
                                <input type="text" name="mypco_speaker_links[<?php echo (int) $i; ?>][label]"
                                       class="regular-text mypco-link-label"
                                       value="<?php echo esc_attr($link['label'] ?? ''); ?>"
                                       placeholder="<?php esc_attr_e('Label (e.g. Facebook)', 'mypco-online'); ?>" />
                                <input type="url" name="mypco_speaker_links[<?php echo (int) $i; ?>][url]"
                                       class="regular-text mypco-link-url"
                                       value="<?php echo esc_url($link['url'] ?? ''); ?>"
                                       placeholder="<?php esc_attr_e('https://...', 'mypco-online'); ?>" />
                                <button type="button" class="button mypco-remove-speaker-link"
                                        title="<?php esc_attr_e('Remove', 'mypco-online'); ?>">&times;</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p style="margin-top:8px;">
                        <button type="button" class="button" id="mypco-add-speaker-link">
                            <?php esc_html_e('Add Link', 'mypco-online'); ?>
                        </button>
                    </p>
                    <p class="description"><?php esc_html_e('Add links to social profiles, websites, or other resources.', 'mypco-online'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save the "Speaker Details" meta box data.
     */
    public function save_speaker_details_meta($post_id, $post) {
        if (!isset($_POST['mypco_speaker_details_meta_nonce']) ||
            !wp_verify_nonce($_POST['mypco_speaker_details_meta_nonce'], 'mypco_speaker_details_meta_save')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Title / Role
        if (isset($_POST['mypco_speaker_title'])) {
            update_post_meta($post_id, '_mypco_speaker_title', sanitize_text_field($_POST['mypco_speaker_title']));
        }

        // Photo
        if (isset($_POST['mypco_speaker_image'])) {
            $image = esc_url_raw($_POST['mypco_speaker_image']);
            if ($image) {
                update_post_meta($post_id, '_mypco_speaker_image', $image);
            } else {
                delete_post_meta($post_id, '_mypco_speaker_image');
            }
        }

        // Links
        $links = [];
        if (isset($_POST['mypco_speaker_links']) && is_array($_POST['mypco_speaker_links'])) {
            foreach ($_POST['mypco_speaker_links'] as $entry) {
                $label = isset($entry['label']) ? sanitize_text_field($entry['label']) : '';
                $url   = isset($entry['url']) ? esc_url_raw($entry['url']) : '';
                if (!empty($url)) {
                    $links[] = ['label' => $label, 'url' => $url];
                }
            }
        }

        if (!empty($links)) {
            update_post_meta($post_id, '_mypco_speaker_links', $links);
        } else {
            delete_post_meta($post_id, '_mypco_speaker_links');
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
        <table class="form-table mypco-meta-table">
            <tr>
                <th><label for="mypco_message_description"><?php esc_html_e('Description', 'mypco-online'); ?></label></th>
                <td>
                    <textarea id="mypco_message_description" name="mypco_message_description"
                              rows="4"><?php echo esc_textarea($description); ?></textarea>
                    <p class="description"><?php esc_html_e('A short summary of the message.', 'mypco-online'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="mypco_message_date"><?php esc_html_e('Date', 'mypco-online'); ?></label></th>
                <td>
                    <input type="date" id="mypco_message_date" name="mypco_message_date"
                           value="<?php echo esc_attr($message_date); ?>" />
                    <p class="description"><?php esc_html_e('The date this message was delivered.', 'mypco-online'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="mypco_message_image"><?php esc_html_e('Image', 'mypco-online'); ?></label></th>
                <td>
                    <input type="hidden" id="mypco_message_image" name="mypco_message_image"
                           value="<?php echo esc_url($image); ?>" />
                    <div class="mypco-field-with-button">
                        <button type="button" class="button mypco-upload-image-btn"
                                data-target="#mypco_message_image"
                                data-preview="#mypco-message-image-preview"><?php esc_html_e('Select Image', 'mypco-online'); ?></button>
                        <button type="button" class="button mypco-remove-image-btn"
                                data-target="#mypco_message_image"
                                data-preview="#mypco-message-image-preview"
                                <?php echo $image ? '' : 'style="display:none;"'; ?>><?php esc_html_e('Remove Image', 'mypco-online'); ?></button>
                    </div>
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
    // Message Post Type – Meta Box (Media)
    // =========================================================================

    /**
     * Register the "Media" meta box on the mypco_message post type.
     */
    public function add_media_meta_box() {
        add_meta_box(
            'mypco_media_meta',
            __('Message Media', 'mypco-online'),
            [$this, 'render_media_meta_box'],
            'mypco_message',
            'normal',
            'high'
        );
    }

    /**
     * Render the "Message Media" meta box fields (audio + video).
     */
    public function render_media_meta_box($post) {
        wp_nonce_field('mypco_media_meta_save', 'mypco_media_meta_nonce');

        $audio = get_post_meta($post->ID, '_mypco_message_audio', true);
        $video = get_post_meta($post->ID, '_mypco_message_video', true);
        ?>
        <table class="form-table mypco-meta-table">
            <tr>
                <th><label for="mypco_message_audio"><?php esc_html_e('Audio', 'mypco-online'); ?></label></th>
                <td>
                    <div class="mypco-field-with-button">
                        <input type="url" id="mypco_message_audio" name="mypco_message_audio"
                               value="<?php echo esc_url($audio); ?>" class="regular-text" />
                        <button type="button" class="button mypco-upload-media-btn"
                                data-target="#mypco_message_audio"
                                data-media-type="audio"><?php esc_html_e('Add or Upload File', 'mypco-online'); ?></button>
                        <button type="button" class="button mypco-remove-media-btn"
                                data-target="#mypco_message_audio"
                                <?php echo $audio ? '' : 'style="display:none;"'; ?>><?php esc_html_e('Remove', 'mypco-online'); ?></button>
                    </div>
                    <p class="description"><?php esc_html_e('Enter a URL or upload an audio file.', 'mypco-online'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="mypco_message_video"><?php esc_html_e('Video', 'mypco-online'); ?></label></th>
                <td>
                    <div class="mypco-field-with-button">
                        <input type="url" id="mypco_message_video" name="mypco_message_video"
                               value="<?php echo esc_url($video); ?>" class="regular-text" />
                        <button type="button" class="button mypco-upload-media-btn"
                                data-target="#mypco_message_video"
                                data-media-type="video"><?php esc_html_e('Add or Upload File', 'mypco-online'); ?></button>
                        <button type="button" class="button mypco-remove-media-btn"
                                data-target="#mypco_message_video"
                                <?php echo $video ? '' : 'style="display:none;"'; ?>><?php esc_html_e('Remove', 'mypco-online'); ?></button>
                    </div>
                    <p class="description"><?php esc_html_e('Enter a URL or upload a video file.', 'mypco-online'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save the "Media" meta box data.
     */
    public function save_media_meta($post_id, $post) {
        if (!isset($_POST['mypco_media_meta_nonce']) ||
            !wp_verify_nonce($_POST['mypco_media_meta_nonce'], 'mypco_media_meta_save')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['mypco_message_audio'])) {
            $audio = esc_url_raw($_POST['mypco_message_audio']);
            if ($audio) {
                update_post_meta($post_id, '_mypco_message_audio', $audio);
            } else {
                delete_post_meta($post_id, '_mypco_message_audio');
            }
        }

        if (isset($_POST['mypco_message_video'])) {
            $video = esc_url_raw($_POST['mypco_message_video']);
            if ($video) {
                update_post_meta($post_id, '_mypco_message_video', $video);
            } else {
                delete_post_meta($post_id, '_mypco_message_video');
            }
        }
    }

    // =========================================================================
    // Message Post Type – Meta Box (Scripture)
    // =========================================================================

    /**
     * Register the "Scripture" meta box on the mypco_message post type.
     */
    public function add_scripture_meta_box() {
        add_meta_box(
            'mypco_scripture_meta',
            __('Message Scripture', 'mypco-online'),
            [$this, 'render_scripture_meta_box'],
            'mypco_message',
            'normal',
            'high'
        );
    }

    /**
     * Render the "Message Scripture" meta box with repeatable passage rows.
     *
     * Each passage has cascading dropdowns: Book, Chapter, Start Verse, End Verse.
     * JavaScript populates the options from localised Bible data.
     */
    public function render_scripture_meta_box($post) {
        wp_nonce_field('mypco_scripture_meta_save', 'mypco_scripture_meta_nonce');

        $scriptures = get_post_meta($post->ID, '_mypco_message_scriptures', true);
        if (!is_array($scriptures) || empty($scriptures)) {
            $scriptures = [['book' => '', 'chapter' => '', 'verse_start' => '', 'verse_end' => '']];
        }
        ?>
        <table class="form-table mypco-meta-table">
            <tr>
                <th><?php esc_html_e('Passages', 'mypco-online'); ?></th>
                <td>
                    <div id="mypco-scripture-passages">
                        <?php foreach ($scriptures as $i => $scripture) : ?>
                        <div class="mypco-scripture-row" data-index="<?php echo (int) $i; ?>">
                            <select name="mypco_scriptures[<?php echo (int) $i; ?>][book]" class="mypco-scripture-book"
                                    data-value="<?php echo esc_attr($scripture['book'] ?? ''); ?>">
                                <option value=""><?php esc_html_e('Select Book', 'mypco-online'); ?></option>
                            </select>
                            <select name="mypco_scriptures[<?php echo (int) $i; ?>][chapter]" class="mypco-scripture-chapter"
                                    data-value="<?php echo esc_attr($scripture['chapter'] ?? ''); ?>" disabled>
                                <option value=""><?php esc_html_e('Chapter', 'mypco-online'); ?></option>
                            </select>
                            <select name="mypco_scriptures[<?php echo (int) $i; ?>][verse_start]" class="mypco-scripture-verse-start"
                                    data-value="<?php echo esc_attr($scripture['verse_start'] ?? $scripture['verse'] ?? ''); ?>" disabled>
                                <option value=""><?php esc_html_e('Start Verse', 'mypco-online'); ?></option>
                            </select>
                            <span class="mypco-scripture-dash">&ndash;</span>
                            <select name="mypco_scriptures[<?php echo (int) $i; ?>][verse_end]" class="mypco-scripture-verse-end"
                                    data-value="<?php echo esc_attr($scripture['verse_end'] ?? ''); ?>" disabled>
                                <option value=""><?php esc_html_e('End Verse', 'mypco-online'); ?></option>
                            </select>
                            <button type="button" class="button mypco-remove-scripture"
                                    title="<?php esc_attr_e('Remove', 'mypco-online'); ?>">&times;</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <p style="margin-top:8px;">
                        <button type="button" class="button" id="mypco-add-scripture">
                            <?php esc_html_e('Add Passage', 'mypco-online'); ?>
                        </button>
                    </p>
                    <p class="description"><?php esc_html_e('Select a book, chapter, and optional verse range for each passage.', 'mypco-online'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save the "Scripture" meta box data.
     */
    public function save_scripture_meta($post_id, $post) {
        if (!isset($_POST['mypco_scripture_meta_nonce']) ||
            !wp_verify_nonce($_POST['mypco_scripture_meta_nonce'], 'mypco_scripture_meta_save')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $scriptures = [];

        if (isset($_POST['mypco_scriptures']) && is_array($_POST['mypco_scriptures'])) {
            foreach ($_POST['mypco_scriptures'] as $entry) {
                $book        = isset($entry['book']) ? sanitize_text_field($entry['book']) : '';
                $chapter     = isset($entry['chapter']) ? absint($entry['chapter']) : 0;
                $verse_start = isset($entry['verse_start']) ? absint($entry['verse_start']) : 0;
                $verse_end   = isset($entry['verse_end']) ? absint($entry['verse_end']) : 0;

                if (!empty($book)) {
                    $scriptures[] = [
                        'book'        => $book,
                        'chapter'     => $chapter,
                        'verse_start' => $verse_start,
                        'verse_end'   => $verse_end,
                    ];
                }
            }
        }

        if (!empty($scriptures)) {
            update_post_meta($post_id, '_mypco_message_scriptures', $scriptures);
        } else {
            delete_post_meta($post_id, '_mypco_message_scriptures');
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
     * Force meta box display order on the mypco_message editor.
     *
     * WordPress saves meta box positions per-user. This filter overrides
     * that saved preference so our boxes always appear in a fixed order.
     */
    public function force_meta_box_order($order) {
        return [
            'normal'   => 'mypco_message_info,mypco_scripture_meta,mypco_media_meta,mypco_speaker_meta,mypco_series_info',
            'side'     => '',
            'advanced' => '',
        ];
    }

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
}
