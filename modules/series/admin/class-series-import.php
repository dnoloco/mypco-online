<?php
/**
 * Series Import Component
 *
 * Handles importing messages (episodes) and series from Planning Center
 * Publishing into the WordPress mypco_message and mypco_series data model.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MyPCO_Series_Import {

    private $loader;
    private $api_model;

    /**
     * Option key used to store the PCO episode ID on each imported post.
     * This prevents duplicate imports and allows re-sync.
     */
    const PCO_EPISODE_META_KEY  = '_mypco_pco_episode_id';
    const PCO_SERIES_META_KEY   = '_mypco_pco_series_id';
    const IMPORT_LOG_OPTION     = 'mypco_import_log';

    public function __construct($loader, $api_model) {
        $this->loader    = $loader;
        $this->api_model = $api_model;
    }

    /**
     * Register hooks for the import functionality.
     */
    public function init() {
        $this->loader->add_action('wp_ajax_mypco_import_fetch_episodes', $this, 'ajax_fetch_episodes');
        $this->loader->add_action('wp_ajax_mypco_import_run', $this, 'ajax_run_import');
    }

    // =========================================================================
    // Settings Tab – Render
    // =========================================================================

    /**
     * Render the Import tab content on the Messages Settings page.
     */
    public function render_import_tab() {
        $names = MyPCO_Series_Module::get_custom_labels();
        $last_import = get_option(self::IMPORT_LOG_OPTION, []);
        ?>
        <div class="mypco-import-wrap">
            <h2><?php printf(esc_html__('Import %s from Planning Center', 'mypco-online'), esc_html($names['message_plural'])); ?></h2>

            <p class="description">
                <?php printf(
                    esc_html__('Fetch episodes from your Planning Center Publishing account and import them as %s in WordPress. Series, media, and descriptions are mapped automatically.', 'mypco-online'),
                    esc_html(strtolower($names['message_plural']))
                ); ?>
            </p>

            <?php if (!$this->api_model) : ?>
                <div class="notice notice-error inline" style="margin:15px 0;">
                    <p><?php esc_html_e('Planning Center API credentials are not configured. Please set them up on the Settings page first.', 'mypco-online'); ?></p>
                </div>
                <?php return; ?>
            <?php endif; ?>

            <?php if (!empty($last_import)) : ?>
                <div class="mypco-import-last-run" style="margin:15px 0;padding:10px 15px;background:#f0f6fc;border-left:4px solid #2271b1;">
                    <strong><?php esc_html_e('Last Import:', 'mypco-online'); ?></strong>
                    <?php
                    $date = isset($last_import['date']) ? $last_import['date'] : '';
                    $count = isset($last_import['count']) ? (int) $last_import['count'] : 0;
                    $skipped = isset($last_import['skipped']) ? (int) $last_import['skipped'] : 0;
                    printf(
                        esc_html__('%1$s — %2$d imported, %3$d skipped (already existed)', 'mypco-online'),
                        esc_html($date),
                        $count,
                        $skipped
                    );
                    ?>
                </div>
            <?php endif; ?>

            <!-- Step 1: Fetch -->
            <div id="mypco-import-step-fetch" class="mypco-import-step">
                <h3><?php esc_html_e('Step 1: Fetch Episodes', 'mypco-online'); ?></h3>
                <p class="description"><?php esc_html_e('Connect to Planning Center Publishing and retrieve available episodes.', 'mypco-online'); ?></p>
                <p>
                    <button type="button" id="mypco-import-fetch-btn" class="button button-primary">
                        <?php esc_html_e('Fetch from Planning Center', 'mypco-online'); ?>
                    </button>
                    <span id="mypco-import-fetch-status" class="mypco-import-status"></span>
                </p>
            </div>

            <!-- Step 2: Preview & Import -->
            <div id="mypco-import-step-preview" class="mypco-import-step" style="display:none;">
                <h3><?php esc_html_e('Step 2: Review & Import', 'mypco-online'); ?></h3>
                <p class="description">
                    <?php printf(
                        esc_html__('Select which episodes to import as %s. Episodes already imported will be skipped automatically.', 'mypco-online'),
                        esc_html(strtolower($names['message_plural']))
                    ); ?>
                </p>

                <div id="mypco-import-summary" style="margin:10px 0;"></div>

                <table class="wp-list-table widefat fixed striped" id="mypco-import-table">
                    <thead>
                        <tr>
                            <td class="manage-column column-cb check-column">
                                <input type="checkbox" id="mypco-import-select-all" checked />
                            </td>
                            <th class="manage-column"><?php esc_html_e('Title', 'mypco-online'); ?></th>
                            <th class="manage-column"><?php echo esc_html($names['series_singular']); ?></th>
                            <th class="manage-column"><?php esc_html_e('Date', 'mypco-online'); ?></th>
                            <th class="manage-column"><?php esc_html_e('Media', 'mypco-online'); ?></th>
                            <th class="manage-column"><?php esc_html_e('Status', 'mypco-online'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="mypco-import-tbody"></tbody>
                </table>

                <p style="margin-top:15px;">
                    <button type="button" id="mypco-import-run-btn" class="button button-primary">
                        <?php printf(esc_html__('Import Selected %s', 'mypco-online'), esc_html($names['message_plural'])); ?>
                    </button>
                    <span id="mypco-import-run-status" class="mypco-import-status"></span>
                </p>
            </div>

            <!-- Step 3: Results -->
            <div id="mypco-import-step-results" class="mypco-import-step" style="display:none;">
                <h3><?php esc_html_e('Import Complete', 'mypco-online'); ?></h3>
                <div id="mypco-import-results"></div>
            </div>

            <!-- Field Mapping Reference -->
            <div class="mypco-import-mapping" style="margin-top:30px;">
                <h3><?php esc_html_e('Field Mapping', 'mypco-online'); ?></h3>
                <table class="widefat fixed" style="max-width:600px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Planning Center Field', 'mypco-online'); ?></th>
                            <th><?php esc_html_e('WordPress Field', 'mypco-online'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><?php esc_html_e('Episode Title', 'mypco-online'); ?></td><td><?php echo esc_html($names['message_singular']); ?> Title</td></tr>
                        <tr><td><?php esc_html_e('Episode Description', 'mypco-online'); ?></td><td><?php echo esc_html($names['message_singular']); ?> Description + Content</td></tr>
                        <tr><td><code>published_to_library_at</code></td><td><?php echo esc_html($names['message_singular']); ?> Date</td></tr>
                        <tr><td><?php esc_html_e('Series', 'mypco-online'); ?></td><td><?php echo esc_html($names['series_singular']); ?> Taxonomy</td></tr>
                        <tr><td><?php esc_html_e('Series Artwork', 'mypco-online'); ?></td><td><?php echo esc_html($names['series_singular']); ?> Image</td></tr>
                        <tr><td><code>art</code> / <code>library_video_thumbnail_url</code></td><td><?php echo esc_html($names['message_singular']); ?> Image</td></tr>
                        <tr><td><code>library_video_url</code></td><td><?php echo esc_html($names['message_singular']); ?> Video URL</td></tr>
                        <tr><td><code>library_video_embed_code</code></td><td><?php echo esc_html($names['message_singular']); ?> Video Embed</td></tr>
                        <tr><td><code>library_audio_url</code> / <code>sermon_audio</code></td><td><?php echo esc_html($names['message_singular']); ?> Audio URL</td></tr>
                        <tr><td><code>library_video_thumbnail_url</code></td><td><?php echo esc_html($names['message_singular']); ?> Video Thumbnail</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    // =========================================================================
    // AJAX: Fetch Episodes from PCO
    // =========================================================================

    /**
     * AJAX handler to fetch episodes from Planning Center Publishing.
     */
    public function ajax_fetch_episodes() {
        check_ajax_referer('mypco_import_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'mypco-online')]);
        }

        if (!$this->api_model) {
            wp_send_json_error(['message' => __('API credentials not configured.', 'mypco-online')]);
        }

        // Fetch all episodes with series included
        $response = $this->api_model->get_all_publishing_episodes();

        if (isset($response['error'])) {
            wp_send_json_error(['message' => $response['error']]);
        }

        if (empty($response['data'])) {
            wp_send_json_error(['message' => __('No episodes found in Planning Center Publishing.', 'mypco-online')]);
        }

        // Build a series lookup from included data
        $series_map = [];
        if (!empty($response['included'])) {
            foreach ($response['included'] as $included) {
                if ($included['type'] === 'Series') {
                    $series_map[$included['id']] = $included['attributes'];
                }
            }
        }

        // Get already-imported episode IDs
        $imported_ids = $this->get_imported_episode_ids();

        // Format episodes for the frontend
        $episodes = [];
        foreach ($response['data'] as $episode) {
            $attrs = $episode['attributes'];
            $ep_id = $episode['id'];

            // Resolve series info
            $series_name = '';
            $series_id   = '';
            if (!empty($episode['relationships']['series']['data'])) {
                $series_id = $episode['relationships']['series']['data']['id'];
                if (isset($series_map[$series_id])) {
                    $series_name = $series_map[$series_id]['title'] ?? '';
                }
            }

            // Determine media availability
            $has_video = !empty($attrs['library_video_url']) || !empty($attrs['library_video_embed_code']);
            $has_audio = !empty($attrs['library_audio_url']);

            // Check sermon_audio object
            if (!$has_audio && !empty($attrs['sermon_audio']) && is_array($attrs['sermon_audio'])) {
                $has_audio = !empty($attrs['sermon_audio']['url']);
            }

            // Parse the published date
            $published_date = '';
            if (!empty($attrs['published_to_library_at'])) {
                $published_date = date('Y-m-d', strtotime($attrs['published_to_library_at']));
            } elseif (!empty($attrs['published_live_at'])) {
                $published_date = date('Y-m-d', strtotime($attrs['published_live_at']));
            }

            // Resolve artwork from the art object or thumbnail
            $artwork_url = '';
            if (!empty($attrs['art']['attributes']['url'])) {
                $artwork_url = $attrs['art']['attributes']['url'];
            } elseif (!empty($attrs['library_video_thumbnail_url'])) {
                $artwork_url = $attrs['library_video_thumbnail_url'];
            }

            $episodes[] = [
                'id'             => $ep_id,
                'title'          => $attrs['title'] ?? '',
                'description'    => $attrs['description'] ?? '',
                'published_date' => $published_date,
                'series_id'      => $series_id,
                'series_name'    => $series_name,
                'has_video'      => $has_video,
                'has_audio'      => $has_audio,
                'artwork_url'    => $artwork_url,
                'already_imported' => in_array($ep_id, $imported_ids, true),
            ];
        }

        wp_send_json_success([
            'episodes'    => $episodes,
            'series'      => $series_map,
            'total_count' => count($episodes),
        ]);
    }

    // =========================================================================
    // AJAX: Run Import
    // =========================================================================

    /**
     * AJAX handler to import selected episodes as WordPress posts.
     */
    public function ajax_run_import() {
        check_ajax_referer('mypco_import_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'mypco-online')]);
        }

        if (!$this->api_model) {
            wp_send_json_error(['message' => __('API credentials not configured.', 'mypco-online')]);
        }

        $episode_ids = isset($_POST['episode_ids']) ? array_map('sanitize_text_field', (array) $_POST['episode_ids']) : [];

        if (empty($episode_ids)) {
            wp_send_json_error(['message' => __('No episodes selected for import.', 'mypco-online')]);
        }

        $imported = 0;
        $skipped  = 0;
        $errors   = [];
        $results  = [];

        foreach ($episode_ids as $episode_id) {
            // Skip if already imported
            if ($this->get_post_by_episode_id($episode_id)) {
                $skipped++;
                $results[] = [
                    'id'     => $episode_id,
                    'status' => 'skipped',
                    'message' => __('Already imported', 'mypco-online'),
                ];
                continue;
            }

            // Fetch full episode data with resources
            $ep_response = $this->api_model->get_publishing_episode($episode_id);

            if (!$ep_response || isset($ep_response['error'])) {
                $error_msg = $ep_response['error'] ?? __('Failed to fetch episode', 'mypco-online');
                $errors[] = $error_msg;
                $results[] = [
                    'id'      => $episode_id,
                    'status'  => 'error',
                    'message' => $error_msg,
                ];
                continue;
            }

            $result = $this->import_episode($ep_response);

            if (is_wp_error($result)) {
                $errors[] = $result->get_error_message();
                $results[] = [
                    'id'      => $episode_id,
                    'status'  => 'error',
                    'message' => $result->get_error_message(),
                ];
            } else {
                $imported++;
                $results[] = [
                    'id'      => $episode_id,
                    'status'  => 'imported',
                    'post_id' => $result,
                    'message' => __('Imported successfully', 'mypco-online'),
                ];
            }
        }

        // Log the import
        update_option(self::IMPORT_LOG_OPTION, [
            'date'    => current_time('M j, Y g:i A'),
            'count'   => $imported,
            'skipped' => $skipped,
            'errors'  => count($errors),
        ]);

        wp_send_json_success([
            'imported' => $imported,
            'skipped'  => $skipped,
            'errors'   => $errors,
            'results'  => $results,
        ]);
    }

    // =========================================================================
    // Import Logic – Episode to Post Mapping
    // =========================================================================

    /**
     * Import a single episode from PCO into a mypco_message post.
     *
     * @param array $ep_response Full episode API response with included data.
     * @return int|WP_Error The created post ID, or WP_Error on failure.
     */
    private function import_episode($ep_response) {
        $episode = $ep_response['data'];
        $attrs   = $episode['attributes'];
        $ep_id   = $episode['id'];

        // Build included resources lookup
        $included_map = [];
        if (!empty($ep_response['included'])) {
            foreach ($ep_response['included'] as $inc) {
                $included_map[$inc['type']][$inc['id']] = $inc;
            }
        }

        // --- Episode fields ---
        $title       = $attrs['title'] ?? '';
        $description = $attrs['description'] ?? '';
        $published   = $attrs['published_to_library_at'] ?? ($attrs['published_live_at'] ?? '');

        // Resolve artwork from the art object or video thumbnail
        $artwork_url = '';
        if (!empty($attrs['art']['attributes']['url'])) {
            $artwork_url = $attrs['art']['attributes']['url'];
        } elseif (!empty($attrs['library_video_thumbnail_url'])) {
            $artwork_url = $attrs['library_video_thumbnail_url'];
        }

        $message_date = '';
        if ($published) {
            $message_date = date('Y-m-d', strtotime($published));
        }

        // --- Create the post ---
        $post_data = [
            'post_type'    => 'mypco_message',
            'post_title'   => sanitize_text_field($title),
            'post_content' => wp_kses_post($description),
            'post_status'  => 'publish',
            'post_date'    => $published ? date('Y-m-d H:i:s', strtotime($published)) : '',
        ];

        $post_id = wp_insert_post($post_data, true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // --- Store PCO episode ID for dedup ---
        update_post_meta($post_id, self::PCO_EPISODE_META_KEY, $ep_id);

        // --- Message meta ---
        if ($description) {
            update_post_meta($post_id, '_mypco_message_description', sanitize_textarea_field($description));
        }
        if ($message_date) {
            update_post_meta($post_id, '_mypco_message_date', $message_date);
        }
        if ($artwork_url) {
            update_post_meta($post_id, '_mypco_message_image', esc_url_raw($artwork_url));
        }

        // --- Extract media from resources ---
        $this->map_episode_resources($post_id, $ep_id, $included_map, $attrs);

        // --- Map series ---
        $this->map_episode_series($post_id, $episode, $included_map);

        return $post_id;
    }

    /**
     * Map episode resources (video/audio) to post meta.
     *
     * Resources may come from the included data or directly from episode attributes.
     */
    private function map_episode_resources($post_id, $ep_id, $included_map, $attrs) {
        $video_url = '';
        $audio_url = '';
        $video_embed = '';

        // Check episode attributes for media
        if (!empty($attrs['library_video_url'])) {
            $video_url = $attrs['library_video_url'];
        }
        if (!empty($attrs['library_video_embed_code'])) {
            $video_embed = $attrs['library_video_embed_code'];
        }
        if (!empty($attrs['library_audio_url'])) {
            $audio_url = $attrs['library_audio_url'];
        }

        // Check sermon_audio object
        if (!$audio_url && !empty($attrs['sermon_audio']) && is_array($attrs['sermon_audio'])) {
            if (!empty($attrs['sermon_audio']['url'])) {
                $audio_url = $attrs['sermon_audio']['url'];
            }
        }

        // Fall back to included EpisodeResource items
        if (isset($included_map['EpisodeResource'])) {
            foreach ($included_map['EpisodeResource'] as $resource) {
                $res_attrs = $resource['attributes'];
                $res_type  = strtolower($res_attrs['resource_type'] ?? ($res_attrs['kind'] ?? ''));
                $res_url   = $res_attrs['url'] ?? ($res_attrs['file_url'] ?? '');

                if (empty($res_url)) {
                    continue;
                }

                if (!$video_url && in_array($res_type, ['video', 'embed', 'youtube', 'vimeo'], true)) {
                    $video_url = $res_url;
                } elseif (!$audio_url && in_array($res_type, ['audio', 'podcast'], true)) {
                    $audio_url = $res_url;
                }
            }
        }

        if ($video_url) {
            update_post_meta($post_id, '_mypco_message_video', esc_url_raw($video_url));
        }
        if ($video_embed) {
            update_post_meta($post_id, '_mypco_message_video_embed', wp_kses_post($video_embed));
        }
        if ($audio_url) {
            update_post_meta($post_id, '_mypco_message_audio', esc_url_raw($audio_url));
        }

        // Store video thumbnail if available
        if (!empty($attrs['library_video_thumbnail_url'])) {
            update_post_meta($post_id, '_mypco_message_video_thumbnail', esc_url_raw($attrs['library_video_thumbnail_url']));
        }
    }

    /**
     * Map the episode's series relationship to the mypco_series taxonomy.
     */
    private function map_episode_series($post_id, $episode, $included_map) {
        if (empty($episode['relationships']['series']['data'])) {
            return;
        }

        $series_pco_id = $episode['relationships']['series']['data']['id'];
        $series_data   = $included_map['Series'][$series_pco_id] ?? null;

        if (!$series_data) {
            return;
        }

        $series_title = $series_data['attributes']['title'] ?? '';
        if (empty($series_title)) {
            return;
        }

        // Find or create the taxonomy term
        $term = get_term_by('name', $series_title, 'mypco_series');

        if (!$term) {
            $series_desc = $series_data['attributes']['description'] ?? '';
            $result = wp_insert_term($series_title, 'mypco_series', [
                'description' => sanitize_textarea_field($series_desc),
            ]);

            if (is_wp_error($result)) {
                return;
            }

            $term_id = $result['term_id'];

            // Store PCO series ID on the term
            update_term_meta($term_id, self::PCO_SERIES_META_KEY, $series_pco_id);

            // Store series artwork
            $series_artwork = $series_data['attributes']['artwork_url']
                ?? ($series_data['attributes']['image_url'] ?? '');
            if ($series_artwork) {
                update_term_meta($term_id, '_mypco_series_image', esc_url_raw($series_artwork));
            }

            // Store series start date if available
            $series_date = $series_data['attributes']['created_at'] ?? '';
            if ($series_date) {
                update_term_meta($term_id, '_mypco_series_start_date', date('Y-m-d', strtotime($series_date)));
            }
        } else {
            $term_id = $term->term_id;
        }

        wp_set_post_terms($post_id, [$term_id], 'mypco_series');
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Get all PCO episode IDs that have already been imported.
     *
     * @return array Array of episode ID strings.
     */
    private function get_imported_episode_ids() {
        global $wpdb;

        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = %s
             AND p.post_type = 'mypco_message'
             AND p.post_status != 'trash'",
            self::PCO_EPISODE_META_KEY
        ));

        return $ids ?: [];
    }

    /**
     * Find an existing WP post by its PCO episode ID.
     *
     * @param string $episode_id The PCO episode ID.
     * @return int|false Post ID, or false if not found.
     */
    private function get_post_by_episode_id($episode_id) {
        global $wpdb;

        $post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT pm.post_id FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = %s
             AND pm.meta_value = %s
             AND p.post_type = 'mypco_message'
             AND p.post_status != 'trash'
             LIMIT 1",
            self::PCO_EPISODE_META_KEY,
            $episode_id
        ));

        return $post_id ? (int) $post_id : false;
    }
}
