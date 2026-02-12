<?php
/**
 * Sermon Add/Edit Template
 *
 * Available variables:
 * - $sermon (object|null) - Existing sermon data or null for new
 * - $speakers (array)
 * - $all_series (array)
 * - $topics (array)
 * - $is_edit (bool)
 */

defined('ABSPATH') || exit;

$base_url = admin_url('admin.php?page=mypco-sermons');
$page_title = $is_edit ? __('Edit Sermon', 'mypco-online') : __('Add New Sermon', 'mypco-online');
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo esc_html($page_title); ?></h1>
    <a href="<?php echo esc_url($base_url); ?>" class="page-title-action"><?php _e('Back to Sermons', 'mypco-online'); ?></a>

    <form method="post" action="" class="mypco-sermon-form">
        <?php wp_nonce_field('mypco_save_sermon'); ?>
        <input type="hidden" name="mypco_save_sermon" value="1">
        <?php if ($is_edit): ?>
            <input type="hidden" name="sermon_id" value="<?php echo esc_attr($sermon->id); ?>">
        <?php endif; ?>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="sermon_title"><?php _e('Title', 'mypco-online'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <input type="text" name="sermon_title" id="sermon_title" class="large-text"
                           value="<?php echo esc_attr($is_edit ? $sermon->title : ''); ?>" required>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sermon_date"><?php _e('Date', 'mypco-online'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <input type="date" name="sermon_date" id="sermon_date"
                           value="<?php echo esc_attr($is_edit ? $sermon->sermon_date : date('Y-m-d')); ?>" required>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="speaker_id"><?php _e('Speaker', 'mypco-online'); ?></label>
                </th>
                <td>
                    <select name="speaker_id" id="speaker_id">
                        <option value="0"><?php _e('&mdash; Select Speaker &mdash;', 'mypco-online'); ?></option>
                        <?php foreach ($speakers as $sp): ?>
                            <option value="<?php echo esc_attr($sp->id); ?>"
                                <?php selected($is_edit ? $sermon->speaker_id : 0, $sp->id); ?>>
                                <?php echo esc_html($sp->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=mypco-sermon-speakers&view=edit')); ?>" class="button button-small"><?php _e('+ New Speaker', 'mypco-online'); ?></a>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="series_id"><?php _e('Series', 'mypco-online'); ?></label>
                </th>
                <td>
                    <select name="series_id" id="series_id">
                        <option value="0"><?php _e('&mdash; Select Series &mdash;', 'mypco-online'); ?></option>
                        <?php foreach ($all_series as $s): ?>
                            <option value="<?php echo esc_attr($s->id); ?>"
                                <?php selected($is_edit ? $sermon->series_id : 0, $s->id); ?>>
                                <?php echo esc_html($s->title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=mypco-sermon-series&view=edit')); ?>" class="button button-small"><?php _e('+ New Series', 'mypco-online'); ?></a>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="topic_id"><?php _e('Topic', 'mypco-online'); ?></label>
                </th>
                <td>
                    <select name="topic_id" id="topic_id">
                        <option value="0"><?php _e('&mdash; Select Topic &mdash;', 'mypco-online'); ?></option>
                        <?php foreach ($topics as $t): ?>
                            <option value="<?php echo esc_attr($t->id); ?>"
                                <?php selected($is_edit ? $sermon->topic_id : 0, $t->id); ?>>
                                <?php echo esc_html($t->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=mypco-sermon-topics&view=edit')); ?>" class="button button-small"><?php _e('+ New Topic', 'mypco-online'); ?></a>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sermon_scripture"><?php _e('Scripture', 'mypco-online'); ?></label>
                </th>
                <td>
                    <input type="text" name="sermon_scripture" id="sermon_scripture" class="regular-text"
                           value="<?php echo esc_attr($is_edit ? $sermon->scripture : ''); ?>"
                           placeholder="<?php esc_attr_e('e.g. John 3:16-17', 'mypco-online'); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sermon_description"><?php _e('Description', 'mypco-online'); ?></label>
                </th>
                <td>
                    <textarea name="sermon_description" id="sermon_description" rows="5" class="large-text"><?php echo esc_textarea($is_edit ? $sermon->description : ''); ?></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sermon_audio_url"><?php _e('Audio URL', 'mypco-online'); ?></label>
                </th>
                <td>
                    <input type="url" name="sermon_audio_url" id="sermon_audio_url" class="large-text"
                           value="<?php echo esc_url($is_edit ? $sermon->audio_url : ''); ?>"
                           placeholder="<?php esc_attr_e('https://example.com/sermon-audio.mp3', 'mypco-online'); ?>">
                    <p class="description"><?php _e('Direct link to the sermon audio file (MP3, etc.).', 'mypco-online'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sermon_video_url"><?php _e('Video URL', 'mypco-online'); ?></label>
                </th>
                <td>
                    <input type="url" name="sermon_video_url" id="sermon_video_url" class="large-text"
                           value="<?php echo esc_url($is_edit ? $sermon->video_url : ''); ?>"
                           placeholder="<?php esc_attr_e('https://www.youtube.com/watch?v=...', 'mypco-online'); ?>">
                    <p class="description"><?php _e('YouTube, Vimeo, or direct video URL.', 'mypco-online'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sermon_image_url"><?php _e('Image', 'mypco-online'); ?></label>
                </th>
                <td>
                    <div class="mypco-image-upload" data-upload-type="sermons">
                        <div class="mypco-image-preview <?php echo ($is_edit && !empty($sermon->image_url)) ? 'has-image' : ''; ?>">
                            <?php if ($is_edit && !empty($sermon->image_url)): ?>
                                <img src="<?php echo esc_url($sermon->image_url); ?>" alt="">
                            <?php endif; ?>
                        </div>
                        <input type="hidden" name="sermon_image_url" id="sermon_image_url"
                               value="<?php echo esc_url($is_edit ? $sermon->image_url : ''); ?>">
                        <button type="button" class="button mypco-upload-btn"><?php _e('Upload Image', 'mypco-online'); ?></button>
                        <button type="button" class="button mypco-remove-btn" <?php echo ($is_edit && !empty($sermon->image_url)) ? '' : 'style="display:none;"'; ?>><?php _e('Remove', 'mypco-online'); ?></button>
                        <p class="description"><?php _e('Featured image for this sermon. If empty, the series image will be used.', 'mypco-online'); ?></p>
                    </div>
                </td>
            </tr>
        </table>

        <?php submit_button($is_edit ? __('Update Sermon', 'mypco-online') : __('Add Sermon', 'mypco-online')); ?>
    </form>
</div>
