<?php
/**
 * Sermons Admin List Page Template
 *
 * Available variables:
 * - $sermons (array)
 * - $speakers (array)
 * - $all_series (array)
 * - $filter_series (int)
 * - $filter_speaker (int)
 * - $search (string)
 * - $success (string)
 */

defined('ABSPATH') || exit;

$base_url = admin_url('admin.php?page=mypco-sermons');
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Sermons', 'mypco-online'); ?></h1>
    <a href="<?php echo esc_url($base_url . '&view=add'); ?>" class="page-title-action"><?php _e('Add New', 'mypco-online'); ?></a>

    <?php if ($success): ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php
                switch ($success) {
                    case 'sermon_added':
                        _e('Sermon added successfully.', 'mypco-online');
                        break;
                    case 'sermon_updated':
                        _e('Sermon updated successfully.', 'mypco-online');
                        break;
                    case 'sermon_deleted':
                        _e('Sermon deleted successfully.', 'mypco-online');
                        break;
                    default:
                        _e('Operation completed.', 'mypco-online');
                }
                ?>
            </p>
        </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="tablenav top">
        <form method="get" action="">
            <input type="hidden" name="page" value="mypco-sermons">

            <select name="filter_series">
                <option value="0"><?php _e('All Series', 'mypco-online'); ?></option>
                <?php foreach ($all_series as $s): ?>
                    <option value="<?php echo esc_attr($s->id); ?>" <?php selected($filter_series, $s->id); ?>>
                        <?php echo esc_html($s->title); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="filter_speaker">
                <option value="0"><?php _e('All Speakers', 'mypco-online'); ?></option>
                <?php foreach ($speakers as $sp): ?>
                    <option value="<?php echo esc_attr($sp->id); ?>" <?php selected($filter_speaker, $sp->id); ?>>
                        <?php echo esc_html($sp->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search sermons...', 'mypco-online'); ?>">

            <input type="submit" class="button" value="<?php esc_attr_e('Filter', 'mypco-online'); ?>">

            <?php if ($filter_series || $filter_speaker || $search): ?>
                <a href="<?php echo esc_url($base_url); ?>" class="button"><?php _e('Clear', 'mypco-online'); ?></a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Sermons Table -->
    <table class="wp-list-table widefat fixed striped table-view-list">
        <thead>
        <tr>
            <th scope="col" class="manage-column column-title column-primary"><?php _e('Title', 'mypco-online'); ?></th>
            <th scope="col" class="manage-column column-date"><?php _e('Date', 'mypco-online'); ?></th>
            <th scope="col" class="manage-column column-speaker"><?php _e('Speaker', 'mypco-online'); ?></th>
            <th scope="col" class="manage-column column-series"><?php _e('Series', 'mypco-online'); ?></th>
            <th scope="col" class="manage-column column-scripture"><?php _e('Scripture', 'mypco-online'); ?></th>
            <th scope="col" class="manage-column column-media"><?php _e('Media', 'mypco-online'); ?></th>
        </tr>
        </thead>
        <tbody id="the-list">
        <?php if (empty($sermons)): ?>
            <tr class="no-items">
                <td class="colspanchange" colspan="6">
                    <?php _e('No sermons found.', 'mypco-online'); ?>
                    <a href="<?php echo esc_url($base_url . '&view=add'); ?>"><?php _e('Add your first sermon', 'mypco-online'); ?></a>
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($sermons as $sermon):
                $edit_url = esc_url($base_url . '&view=edit&id=' . $sermon->id);
                $delete_url = wp_nonce_url($base_url . '&action=delete_sermon&id=' . $sermon->id, 'mypco_delete_sermon_' . $sermon->id);

                $media_icons = [];
                if (!empty($sermon->audio_url)) {
                    $media_icons[] = '<span class="dashicons dashicons-format-audio" title="' . esc_attr__('Audio', 'mypco-online') . '"></span>';
                }
                if (!empty($sermon->video_url)) {
                    $media_icons[] = '<span class="dashicons dashicons-video-alt3" title="' . esc_attr__('Video', 'mypco-online') . '"></span>';
                }
            ?>
                <tr>
                    <td class="title column-title has-row-actions column-primary">
                        <strong><a class="row-title" href="<?php echo $edit_url; ?>"><?php echo esc_html($sermon->title); ?></a></strong>
                        <div class="row-actions">
                            <span class="edit"><a href="<?php echo $edit_url; ?>"><?php _e('Edit', 'mypco-online'); ?></a></span>
                            | <span class="trash"><a href="<?php echo esc_url($delete_url); ?>" class="submitdelete" onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this sermon?', 'mypco-online'); ?>')"><?php _e('Delete', 'mypco-online'); ?></a></span>
                        </div>
                    </td>
                    <td class="date column-date">
                        <?php echo !empty($sermon->sermon_date) ? esc_html(date_i18n(get_option('date_format'), strtotime($sermon->sermon_date))) : '&mdash;'; ?>
                    </td>
                    <td class="speaker column-speaker">
                        <?php echo !empty($sermon->speaker_name) ? esc_html($sermon->speaker_name) : '<span class="mypco-no-description">&mdash;</span>'; ?>
                    </td>
                    <td class="series column-series">
                        <?php echo !empty($sermon->series_title) ? esc_html($sermon->series_title) : '<span class="mypco-no-description">&mdash;</span>'; ?>
                    </td>
                    <td class="scripture column-scripture">
                        <?php echo !empty($sermon->scripture) ? esc_html($sermon->scripture) : '<span class="mypco-no-description">&mdash;</span>'; ?>
                    </td>
                    <td class="media column-media">
                        <?php echo !empty($media_icons) ? implode(' ', $media_icons) : '<span class="mypco-no-description">&mdash;</span>'; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
        <tfoot>
        <tr>
            <th scope="col" class="manage-column column-title column-primary"><?php _e('Title', 'mypco-online'); ?></th>
            <th scope="col" class="manage-column column-date"><?php _e('Date', 'mypco-online'); ?></th>
            <th scope="col" class="manage-column column-speaker"><?php _e('Speaker', 'mypco-online'); ?></th>
            <th scope="col" class="manage-column column-series"><?php _e('Series', 'mypco-online'); ?></th>
            <th scope="col" class="manage-column column-scripture"><?php _e('Scripture', 'mypco-online'); ?></th>
            <th scope="col" class="manage-column column-media"><?php _e('Media', 'mypco-online'); ?></th>
        </tr>
        </tfoot>
    </table>

    <p class="description">
        <?php printf(
            __('Showing %d sermon(s). Use the shortcode %s to display sermons on your site.', 'mypco-online'),
            count($sermons),
            '<code>[mypco_sermons]</code>'
        ); ?>
    </p>
</div>
