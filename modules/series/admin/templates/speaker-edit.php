<?php
/**
 * Speaker Add/Edit Template
 *
 * Available variables:
 * - $speaker (object|null)
 * - $is_edit (bool)
 */

defined('ABSPATH') || exit;

$base_url = admin_url('admin.php?page=mypco-series-speakers');
$page_title = $is_edit ? __('Edit Speaker', 'mypco-online') : __('Add New Speaker', 'mypco-online');
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo esc_html($page_title); ?></h1>
    <a href="<?php echo esc_url($base_url); ?>" class="page-title-action"><?php _e('Back to Speakers', 'mypco-online'); ?></a>

    <form method="post" action="">
        <?php wp_nonce_field('mypco_save_speaker'); ?>
        <input type="hidden" name="mypco_save_speaker" value="1">
        <?php if ($is_edit): ?>
            <input type="hidden" name="speaker_id" value="<?php echo esc_attr($speaker->id); ?>">
        <?php endif; ?>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="speaker_name"><?php _e('Name', 'mypco-online'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <input type="text" name="speaker_name" id="speaker_name" class="regular-text"
                           value="<?php echo esc_attr($is_edit ? $speaker->name : ''); ?>" required>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="speaker_title"><?php _e('Title/Role', 'mypco-online'); ?></label>
                </th>
                <td>
                    <input type="text" name="speaker_title" id="speaker_title" class="regular-text"
                           value="<?php echo esc_attr($is_edit ? $speaker->title : ''); ?>"
                           placeholder="<?php esc_attr_e('e.g. Senior Pastor', 'mypco-online'); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="speaker_bio"><?php _e('Bio', 'mypco-online'); ?></label>
                </th>
                <td>
                    <textarea name="speaker_bio" id="speaker_bio" rows="5" class="large-text"><?php echo esc_textarea($is_edit ? $speaker->bio : ''); ?></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="speaker_image_url"><?php _e('Photo', 'mypco-online'); ?></label>
                </th>
                <td>
                    <div class="mypco-image-upload" data-upload-type="speakers">
                        <div class="mypco-image-preview <?php echo ($is_edit && !empty($speaker->image_url)) ? 'has-image' : ''; ?>">
                            <?php if ($is_edit && !empty($speaker->image_url)): ?>
                                <img src="<?php echo esc_url($speaker->image_url); ?>" alt="">
                            <?php endif; ?>
                        </div>
                        <input type="hidden" name="speaker_image_url" id="speaker_image_url"
                               value="<?php echo esc_url($is_edit ? $speaker->image_url : ''); ?>">
                        <button type="button" class="button mypco-upload-btn"><?php _e('Upload Image', 'mypco-online'); ?></button>
                        <button type="button" class="button mypco-remove-btn" <?php echo ($is_edit && !empty($speaker->image_url)) ? '' : 'style="display:none;"'; ?>><?php _e('Remove', 'mypco-online'); ?></button>
                    </div>
                </td>
            </tr>
        </table>

        <?php submit_button($is_edit ? __('Update Speaker', 'mypco-online') : __('Add Speaker', 'mypco-online')); ?>
    </form>
</div>
