<?php
/**
 * Series Add/Edit Template
 *
 * Available variables:
 * - $series (object|null)
 * - $is_edit (bool)
 */

defined('ABSPATH') || exit;

$base_url = admin_url('admin.php?page=mypco-sermon-series');
$page_title = $is_edit ? __('Edit Series', 'mypco-online') : __('Add New Series', 'mypco-online');
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo esc_html($page_title); ?></h1>
    <a href="<?php echo esc_url($base_url); ?>" class="page-title-action"><?php _e('Back to Series', 'mypco-online'); ?></a>

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
</div>
