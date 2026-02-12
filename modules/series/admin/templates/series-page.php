<?php
/**
 * Series List Page Template (Main Page)
 *
 * Available variables:
 * - $all_series (array) - includes message_count
 * - $success (string)
 */

defined('ABSPATH') || exit;

$base_url = admin_url('admin.php?page=mypco-series');
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Series', 'mypco-online'); ?></h1>
    <a href="<?php echo esc_url($base_url . '&view=add_series'); ?>" class="page-title-action"><?php _e('Add New', 'mypco-online'); ?></a>

    <p class="mypco-page-description"><?php _e('Manage your message series here. Create a series, then add messages within it.', 'mypco-online'); ?></p>

    <?php if ($success): ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php
                switch ($success) {
                    case 'series_added':
                        _e('Series added successfully.', 'mypco-online');
                        break;
                    case 'series_updated':
                        _e('Series updated successfully.', 'mypco-online');
                        break;
                    case 'series_deleted':
                        _e('Series deleted successfully.', 'mypco-online');
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

    <table class="wp-list-table widefat fixed striped table-view-list">
        <thead>
        <tr>
            <th scope="col" class="manage-column column-title column-primary"><?php _e('Title', 'mypco-online'); ?></th>
            <th scope="col" class="manage-column column-description"><?php _e('Description', 'mypco-online'); ?></th>
            <th scope="col" class="manage-column column-messages" style="width:100px;"><?php _e('Messages', 'mypco-online'); ?></th>
            <th scope="col" class="manage-column column-dates"><?php _e('Dates', 'mypco-online'); ?></th>
            <th scope="col" class="manage-column column-image"><?php _e('Image', 'mypco-online'); ?></th>
            <th scope="col" class="manage-column column-delete" style="width:60px;"><?php _e('Delete', 'mypco-online'); ?></th>
        </tr>
        </thead>
        <tbody id="the-list">
        <?php if (empty($all_series)): ?>
            <tr class="no-items">
                <td class="colspanchange" colspan="6">
                    <?php _e('No series found.', 'mypco-online'); ?>
                    <a href="<?php echo esc_url($base_url . '&view=add_series'); ?>"><?php _e('Add your first series', 'mypco-online'); ?></a>
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($all_series as $series):
                $edit_url = esc_url($base_url . '&view=edit&id=' . $series->id);
                $delete_url = wp_nonce_url($base_url . '&action=delete_series&id=' . $series->id, 'mypco_delete_series_' . $series->id);

                $date_display = '';
                if (!empty($series->start_date)) {
                    $date_display = date_i18n(get_option('date_format'), strtotime($series->start_date));
                    if (!empty($series->end_date)) {
                        $date_display .= ' &ndash; ' . date_i18n(get_option('date_format'), strtotime($series->end_date));
                    }
                }
            ?>
                <tr>
                    <td class="title column-title has-row-actions column-primary">
                        <strong><a class="row-title" href="<?php echo $edit_url; ?>"><?php echo esc_html($series->title); ?></a></strong>
                        <div class="row-actions">
                            <span class="edit"><a href="<?php echo $edit_url; ?>"><?php _e('Edit', 'mypco-online'); ?></a></span>
                        </div>
                    </td>
                    <td class="description column-description">
                        <?php echo !empty($series->description) ? esc_html(wp_trim_words($series->description, 15)) : '<span class="mypco-no-description">&mdash;</span>'; ?>
                    </td>
                    <td class="messages column-messages">
                        <?php echo absint($series->message_count); ?>
                    </td>
                    <td class="dates column-dates">
                        <?php echo !empty($date_display) ? $date_display : '<span class="mypco-no-description">&mdash;</span>'; ?>
                    </td>
                    <td class="image column-image">
                        <?php if (!empty($series->image_url)): ?>
                            <img src="<?php echo esc_url($series->image_url); ?>" alt="<?php echo esc_attr($series->title); ?>" class="mypco-series-thumb" width="60" height="40">
                        <?php else: ?>
                            <span class="mypco-no-description">&mdash;</span>
                        <?php endif; ?>
                    </td>
                    <td class="delete column-delete">
                        <a href="<?php echo esc_url($delete_url); ?>" class="submitdelete" onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this series and all its messages?', 'mypco-online'); ?>')" title="<?php esc_attr_e('Delete', 'mypco-online'); ?>">
                            <span class="dashicons dashicons-trash"></span>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
