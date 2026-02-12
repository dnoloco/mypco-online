<?php
/**
 * Topics Management Page Template
 *
 * Available variables:
 * - $topics (array)
 * - $success (string)
 */

defined('ABSPATH') || exit;

$base_url = admin_url('admin.php?page=mypco-series-topics');
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Topics', 'mypco-online'); ?></h1>
    <a href="<?php echo esc_url($base_url . '&view=edit'); ?>" class="page-title-action"><?php _e('Add New', 'mypco-online'); ?></a>

    <?php if ($success): ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php
                switch ($success) {
                    case 'topic_added':
                        _e('Topic added successfully.', 'mypco-online');
                        break;
                    case 'topic_updated':
                        _e('Topic updated successfully.', 'mypco-online');
                        break;
                    case 'topic_deleted':
                        _e('Topic deleted successfully.', 'mypco-online');
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
            <th scope="col" class="manage-column column-description"><?php _e('Description', 'mypco-online'); ?></th>
        </tr>
        </thead>
        <tbody id="the-list">
        <?php if (empty($topics)): ?>
            <tr class="no-items">
                <td class="colspanchange" colspan="2">
                    <?php _e('No topics found.', 'mypco-online'); ?>
                    <a href="<?php echo esc_url($base_url . '&view=edit'); ?>"><?php _e('Add your first topic', 'mypco-online'); ?></a>
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($topics as $topic):
                $edit_url = esc_url($base_url . '&view=edit&id=' . $topic->id);
                $delete_url = wp_nonce_url($base_url . '&action=delete_topic&id=' . $topic->id, 'mypco_delete_topic_' . $topic->id);
            ?>
                <tr>
                    <td class="name column-name has-row-actions column-primary">
                        <strong><a class="row-title" href="<?php echo $edit_url; ?>"><?php echo esc_html($topic->name); ?></a></strong>
                        <div class="row-actions">
                            <span class="edit"><a href="<?php echo $edit_url; ?>"><?php _e('Edit', 'mypco-online'); ?></a></span>
                            | <span class="trash"><a href="<?php echo esc_url($delete_url); ?>" class="submitdelete" onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this topic?', 'mypco-online'); ?>')"><?php _e('Delete', 'mypco-online'); ?></a></span>
                        </div>
                    </td>
                    <td class="description column-description">
                        <?php echo !empty($topic->description) ? esc_html(wp_trim_words($topic->description, 20)) : '<span class="mypco-no-description">&mdash;</span>'; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
