<?php
/**
 * Series Add/Edit Template
 *
 * When editing an existing series, also shows a list of messages within it.
 *
 * Available variables:
 * - $series (object|null)
 * - $messages (array) - messages in this series (only when editing)
 * - $is_edit (bool)
 */

defined('ABSPATH') || exit;

$base_url = admin_url('admin.php?page=mypco-series');
$page_title = $is_edit ? __('Edit Series', 'mypco-online') : __('Add New Series', 'mypco-online');
$success = isset($_GET['success']) ? sanitize_text_field($_GET['success']) : '';
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo esc_html($page_title); ?></h1>
    <a href="<?php echo esc_url($base_url); ?>" class="page-title-action"><?php _e('Back to Series', 'mypco-online'); ?></a>

    <?php if ($success): ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php
                switch ($success) {
                    case 'series_added':
                        _e('Series created. You can now add messages below.', 'mypco-online');
                        break;
                    case 'series_updated':
                        _e('Series updated successfully.', 'mypco-online');
                        break;
                    case 'message_added':
                        _e('Message added successfully.', 'mypco-online');
                        break;
                    case 'message_updated':
                        _e('Message updated successfully.', 'mypco-online');
                        break;
                    case 'message_deleted':
                        _e('Message deleted successfully.', 'mypco-online');
                        break;
                    default:
                        _e('Operation completed.', 'mypco-online');
                }
                ?>
            </p>
        </div>
    <?php endif; ?>

    <form method="post" action="">
        <?php wp_nonce_field('mypco_save_series'); ?>
        <input type="hidden" name="mypco_save_series" value="1">
        <?php if ($is_edit): ?>
            <input type="hidden" name="series_id" value="<?php echo esc_attr($series->id); ?>">
        <?php endif; ?>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="series_title"><?php _e('Title', 'mypco-online'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <input type="text" name="series_title" id="series_title" class="regular-text"
                           value="<?php echo esc_attr($is_edit ? $series->title : ''); ?>" required>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="series_description"><?php _e('Description', 'mypco-online'); ?></label>
                </th>
                <td>
                    <textarea name="series_description" id="series_description" rows="5" class="large-text"><?php echo esc_textarea($is_edit ? $series->description : ''); ?></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="series_image_url"><?php _e('Cover Image', 'mypco-online'); ?></label>
                </th>
                <td>
                    <div class="mypco-image-upload" data-upload-type="series">
                        <div class="mypco-image-preview <?php echo ($is_edit && !empty($series->image_url)) ? 'has-image' : ''; ?>">
                            <?php if ($is_edit && !empty($series->image_url)): ?>
                                <img src="<?php echo esc_url($series->image_url); ?>" alt="">
                            <?php endif; ?>
                        </div>
                        <input type="hidden" name="series_image_url" id="series_image_url"
                               value="<?php echo esc_url($is_edit ? $series->image_url : ''); ?>">
                        <button type="button" class="button mypco-upload-btn"><?php _e('Upload Image', 'mypco-online'); ?></button>
                        <button type="button" class="button mypco-remove-btn" <?php echo ($is_edit && !empty($series->image_url)) ? '' : 'style="display:none;"'; ?>><?php _e('Remove', 'mypco-online'); ?></button>
                        <p class="description"><?php _e('Cover image for this series.', 'mypco-online'); ?></p>
                    </div>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="series_start_date"><?php _e('Start Date', 'mypco-online'); ?></label>
                </th>
                <td>
                    <input type="date" name="series_start_date" id="series_start_date"
                           value="<?php echo esc_attr($is_edit ? $series->start_date : ''); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="series_end_date"><?php _e('End Date', 'mypco-online'); ?></label>
                </th>
                <td>
                    <input type="date" name="series_end_date" id="series_end_date"
                           value="<?php echo esc_attr($is_edit ? $series->end_date : ''); ?>">
                </td>
            </tr>
        </table>

        <?php submit_button($is_edit ? __('Update Series', 'mypco-online') : __('Add Series', 'mypco-online')); ?>
    </form>

    <?php if ($is_edit): ?>
    <!-- Messages in this Series -->
    <hr>
    <h2 class="wp-heading-inline"><?php _e('Messages in this Series', 'mypco-online'); ?></h2>
    <a href="<?php echo esc_url($base_url . '&view=add_message&series_id=' . $series->id); ?>" class="page-title-action"><?php _e('Add New Message', 'mypco-online'); ?></a>

    <table class="wp-list-table widefat fixed striped table-view-list" style="margin-top: 12px;">
        <thead>
        <tr>
            <th scope="col" class="manage-column column-title column-primary"><?php _e('Title', 'mypco-online'); ?></th>
            <th scope="col" class="manage-column column-speaker"><?php _e('Speaker', 'mypco-online'); ?></th>
            <th scope="col" class="manage-column column-date"><?php _e('Date', 'mypco-online'); ?></th>
            <th scope="col" class="manage-column column-scripture"><?php _e('Scripture', 'mypco-online'); ?></th>
            <th scope="col" class="manage-column column-media"><?php _e('Media', 'mypco-online'); ?></th>
            <th scope="col" class="manage-column column-delete" style="width:60px;"><?php _e('Delete', 'mypco-online'); ?></th>
        </tr>
        </thead>
        <tbody id="the-list">
        <?php if (empty($messages)): ?>
            <tr class="no-items">
                <td class="colspanchange" colspan="6">
                    <?php _e('No messages in this series yet.', 'mypco-online'); ?>
                    <a href="<?php echo esc_url($base_url . '&view=add_message&series_id=' . $series->id); ?>"><?php _e('Add your first message', 'mypco-online'); ?></a>
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($messages as $message):
                $edit_msg_url = esc_url($base_url . '&view=edit_message&id=' . $message->id);
                $delete_msg_url = wp_nonce_url($base_url . '&action=delete_message&id=' . $message->id, 'mypco_delete_message_' . $message->id);

                $media_icons = [];
                if (!empty($message->audio_url)) {
                    $media_icons[] = '<span class="dashicons dashicons-format-audio" title="' . esc_attr__('Audio', 'mypco-online') . '"></span>';
                }
                if (!empty($message->video_url)) {
                    $media_icons[] = '<span class="dashicons dashicons-video-alt3" title="' . esc_attr__('Video', 'mypco-online') . '"></span>';
                }
            ?>
                <tr>
                    <td class="title column-title has-row-actions column-primary">
                        <strong><a class="row-title" href="<?php echo $edit_msg_url; ?>"><?php echo esc_html($message->title); ?></a></strong>
                        <div class="row-actions">
                            <span class="edit"><a href="<?php echo $edit_msg_url; ?>"><?php _e('Edit', 'mypco-online'); ?></a></span>
                        </div>
                    </td>
                    <td class="speaker column-speaker">
                        <?php echo !empty($message->speaker_name) ? esc_html($message->speaker_name) : '<span class="mypco-no-description">&mdash;</span>'; ?>
                    </td>
                    <td class="date column-date">
                        <?php echo !empty($message->message_date) ? esc_html(date_i18n(get_option('date_format'), strtotime($message->message_date))) : '&mdash;'; ?>
                    </td>
                    <td class="scripture column-scripture">
                        <?php echo !empty($message->scripture) ? esc_html($message->scripture) : '<span class="mypco-no-description">&mdash;</span>'; ?>
                    </td>
                    <td class="media column-media">
                        <?php echo !empty($media_icons) ? implode(' ', $media_icons) : '<span class="mypco-no-description">&mdash;</span>'; ?>
                    </td>
                    <td class="delete column-delete">
                        <a href="<?php echo esc_url($delete_msg_url); ?>" class="submitdelete" onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this message?', 'mypco-online'); ?>')" title="<?php esc_attr_e('Delete', 'mypco-online'); ?>">
                            <span class="dashicons dashicons-trash"></span>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
