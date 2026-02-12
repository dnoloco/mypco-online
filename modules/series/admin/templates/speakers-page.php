<?php
/**
 * Speakers Management Page Template
 *
 * Available variables:
 * - $speakers (array)
 * - $success (string)
 */

defined('ABSPATH') || exit;

$base_url = admin_url('admin.php?page=mypco-series-speakers');
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Speakers', 'mypco-online'); ?></h1>
    <a href="<?php echo esc_url($base_url . '&view=edit'); ?>" class="page-title-action"><?php _e('Add New', 'mypco-online'); ?></a>

    <?php if ($success): ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php
                switch ($success) {
                    case 'speaker_added':
                        _e('Speaker added successfully.', 'mypco-online');
                        break;
                    case 'speaker_updated':
                        _e('Speaker updated successfully.', 'mypco-online');
                        break;
                    case 'speaker_deleted':
                        _e('Speaker deleted successfully.', 'mypco-online');
                        break;
                    default:
                        _e('Operation completed.', 'mypco-online');
                }
                ?>
            </p>
        </div>
    <?php endif; ?>

    <table class="wp-list-table widefat fixed striped table-view-list">
        <thead>
        <tr>
            <th scope="col" class="manage-column column-name column-primary"><?php _e('Name', 'mypco-online'); ?></th>
            <th scope="col" class="manage-column column-title"><?php _e('Title/Role', 'mypco-online'); ?></th>
            <th scope="col" class="manage-column column-image"><?php _e('Image', 'mypco-online'); ?></th>
        </tr>
        </thead>
        <tbody id="the-list">
        <?php if (empty($speakers)): ?>
            <tr class="no-items">
                <td class="colspanchange" colspan="3">
                    <?php _e('No speakers found.', 'mypco-online'); ?>
                    <a href="<?php echo esc_url($base_url . '&view=edit'); ?>"><?php _e('Add your first speaker', 'mypco-online'); ?></a>
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($speakers as $speaker):
                $edit_url = esc_url($base_url . '&view=edit&id=' . $speaker->id);
                $delete_url = wp_nonce_url($base_url . '&action=delete_speaker&id=' . $speaker->id, 'mypco_delete_speaker_' . $speaker->id);
            ?>
                <tr>
                    <td class="name column-name has-row-actions column-primary">
                        <strong><a class="row-title" href="<?php echo $edit_url; ?>"><?php echo esc_html($speaker->name); ?></a></strong>
                        <div class="row-actions">
                            <span class="edit"><a href="<?php echo $edit_url; ?>"><?php _e('Edit', 'mypco-online'); ?></a></span>
                            | <span class="trash"><a href="<?php echo esc_url($delete_url); ?>" class="submitdelete" onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this speaker?', 'mypco-online'); ?>')"><?php _e('Delete', 'mypco-online'); ?></a></span>
                        </div>
                    </td>
                    <td class="title column-title">
                        <?php echo !empty($speaker->title) ? esc_html($speaker->title) : '<span class="mypco-no-description">&mdash;</span>'; ?>
                    </td>
                    <td class="image column-image">
                        <?php if (!empty($speaker->image_url)): ?>
                            <img src="<?php echo esc_url($speaker->image_url); ?>" alt="<?php echo esc_attr($speaker->name); ?>" class="mypco-speaker-thumb" width="40" height="40">
                        <?php else: ?>
                            <span class="mypco-no-description">&mdash;</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
