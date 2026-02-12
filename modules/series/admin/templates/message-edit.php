<?php
/**
 * Message Add/Edit Template
 *
 * Available variables:
 * - $message (object|null) - Existing message data or null for new
 * - $speakers (array)
 * - $all_series (array)
 * - $topics (array)
 * - $is_edit (bool)
 * - $preselect_series (int) - Pre-selected series ID when adding from series page
 */

defined('ABSPATH') || exit;

$base_url = admin_url('admin.php?page=mypco-series');
$page_title = $is_edit ? __('Edit Message', 'mypco-online') : __('Add New Message', 'mypco-online');

// Determine the back URL (series edit page if we have a series context)
$back_series_id = $is_edit && $message ? $message->series_id : $preselect_series;
if ($back_series_id > 0) {
    $back_url = $base_url . '&view=edit&id=' . $back_series_id;
    $back_label = __('Back to Series', 'mypco-online');
} else {
    $back_url = $base_url;
    $back_label = __('Back to Series', 'mypco-online');
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo esc_html($page_title); ?></h1>
    <a href="<?php echo esc_url($back_url); ?>" class="page-title-action"><?php echo esc_html($back_label); ?></a>

    <form method="post" action="" class="mypco-message-form">
        <?php wp_nonce_field('mypco_save_message'); ?>
        <input type="hidden" name="mypco_save_message" value="1">
        <?php if ($is_edit): ?>
            <input type="hidden" name="message_id" value="<?php echo esc_attr($message->id); ?>">
        <?php endif; ?>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="message_title"><?php _e('Title', 'mypco-online'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <input type="text" name="message_title" id="message_title" class="large-text"
                           value="<?php echo esc_attr($is_edit ? $message->title : ''); ?>" required>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="message_date"><?php _e('Date', 'mypco-online'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <input type="date" name="message_date" id="message_date"
                           value="<?php echo esc_attr($is_edit ? $message->message_date : date('Y-m-d')); ?>" required>
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
                                <?php selected($is_edit ? $message->speaker_id : 0, $sp->id); ?>>
                                <?php echo esc_html($sp->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=mypco-series-speakers&view=edit')); ?>" class="button button-small"><?php _e('+ New Speaker', 'mypco-online'); ?></a>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="series_id"><?php _e('Series', 'mypco-online'); ?></label>
                </th>
                <td>
                    <?php
                    $selected_series = $is_edit ? $message->series_id : $preselect_series;
                    ?>
                    <select name="series_id" id="series_id">
                        <option value="0"><?php _e('&mdash; Select Series &mdash;', 'mypco-online'); ?></option>
                        <?php foreach ($all_series as $s): ?>
                            <option value="<?php echo esc_attr($s->id); ?>"
                                <?php selected($selected_series, $s->id); ?>>
                                <?php echo esc_html($s->title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
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
                                <?php selected($is_edit ? $message->topic_id : 0, $t->id); ?>>
                                <?php echo esc_html($t->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=mypco-series-topics&view=edit')); ?>" class="button button-small"><?php _e('+ New Topic', 'mypco-online'); ?></a>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="message_scripture"><?php _e('Scripture', 'mypco-online'); ?></label>
                </th>
                <td>
                    <input type="text" name="message_scripture" id="message_scripture" class="regular-text"
                           value="<?php echo esc_attr($is_edit ? $message->scripture : ''); ?>"
                           placeholder="<?php esc_attr_e('e.g. John 3:16-17', 'mypco-online'); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="message_description"><?php _e('Description', 'mypco-online'); ?></label>
                </th>
                <td>
                    <textarea name="message_description" id="message_description" rows="5" class="large-text"><?php echo esc_textarea($is_edit ? $message->description : ''); ?></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="message_audio_url"><?php _e('Audio URL', 'mypco-online'); ?></label>
                </th>
                <td>
                    <input type="url" name="message_audio_url" id="message_audio_url" class="large-text"
                           value="<?php echo esc_url($is_edit ? $message->audio_url : ''); ?>"
                           placeholder="<?php esc_attr_e('https://example.com/message-audio.mp3', 'mypco-online'); ?>">
                    <p class="description"><?php _e('Direct link to the audio file (MP3, etc.).', 'mypco-online'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="message_video_url"><?php _e('Video URL', 'mypco-online'); ?></label>
                </th>
                <td>
                    <input type="url" name="message_video_url" id="message_video_url" class="large-text"
                           value="<?php echo esc_url($is_edit ? $message->video_url : ''); ?>"
                           placeholder="<?php esc_attr_e('https://www.youtube.com/watch?v=...', 'mypco-online'); ?>">
                    <p class="description"><?php _e('YouTube, Vimeo, or direct video URL.', 'mypco-online'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="message_image_url"><?php _e('Image', 'mypco-online'); ?></label>
                </th>
                <td>
                    <div class="mypco-image-upload" data-upload-type="messages">
                        <div class="mypco-image-preview <?php echo ($is_edit && !empty($message->image_url)) ? 'has-image' : ''; ?>">
                            <?php if ($is_edit && !empty($message->image_url)): ?>
                                <img src="<?php echo esc_url($message->image_url); ?>" alt="">
                            <?php endif; ?>
                        </div>
                        <input type="hidden" name="message_image_url" id="message_image_url"
                               value="<?php echo esc_url($is_edit ? $message->image_url : ''); ?>">
                        <button type="button" class="button mypco-upload-btn"><?php _e('Upload Image', 'mypco-online'); ?></button>
                        <button type="button" class="button mypco-remove-btn" <?php echo ($is_edit && !empty($message->image_url)) ? '' : 'style="display:none;"'; ?>><?php _e('Remove', 'mypco-online'); ?></button>
                        <p class="description"><?php _e('Featured image for this message. If empty, the series image will be used.', 'mypco-online'); ?></p>
                    </div>
                </td>
            </tr>
        </table>

        <?php submit_button($is_edit ? __('Update Message', 'mypco-online') : __('Add Message', 'mypco-online')); ?>
    </form>
</div>
