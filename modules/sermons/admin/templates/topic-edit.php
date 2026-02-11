<?php
/**
 * Topic Add/Edit Template
 *
 * Available variables:
 * - $topic (object|null)
 * - $is_edit (bool)
 */

defined('ABSPATH') || exit;

$base_url = admin_url('admin.php?page=mypco-sermons');
$page_title = $is_edit ? __('Edit Topic', 'mypco-online') : __('Add New Topic', 'mypco-online');
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo esc_html($page_title); ?></h1>
    <a href="<?php echo esc_url($base_url . '&view=topics'); ?>" class="page-title-action"><?php _e('Back to Topics', 'mypco-online'); ?></a>

    <form method="post" action="">
        <?php wp_nonce_field('mypco_save_topic'); ?>
        <input type="hidden" name="mypco_save_topic" value="1">
        <?php if ($is_edit): ?>
            <input type="hidden" name="topic_id" value="<?php echo esc_attr($topic->id); ?>">
        <?php endif; ?>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="topic_name"><?php _e('Name', 'mypco-online'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <input type="text" name="topic_name" id="topic_name" class="regular-text"
                           value="<?php echo esc_attr($is_edit ? $topic->name : ''); ?>" required>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="topic_description"><?php _e('Description', 'mypco-online'); ?></label>
                </th>
                <td>
                    <textarea name="topic_description" id="topic_description" rows="4" class="large-text"><?php echo esc_textarea($is_edit ? $topic->description : ''); ?></textarea>
                </td>
            </tr>
        </table>

        <?php submit_button($is_edit ? __('Update Topic', 'mypco-online') : __('Add Topic', 'mypco-online')); ?>
    </form>
</div>
