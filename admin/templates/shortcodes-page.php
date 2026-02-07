<?php
/**
 * Shortcodes Admin Page Template
 *
 * Renders the shortcode list view or edit/new view.
 *
 * List view variables:
 * - $shortcodes (array) - All shortcode configurations
 * - $types (array) - Shortcode type definitions
 * - $modules (array) - Module key => name pairs
 * - $count_all (int)
 * - $counts_by_module (array)
 * - $current_filter (string)
 * - $settings_saved (bool)
 * - $deleted (bool)
 * - $bulk_deleted (int)
 * - $page_url (string)
 *
 * Edit view variables:
 * - $action (string) - 'edit' or 'new'
 * - $id (int) - Shortcode ID (0 for new)
 * - $shortcode (array) - Shortcode settings
 * - $type_slug (string) - Shortcode type slug
 * - $type_def (array) - Shortcode type definition
 * - $types (array)
 * - $page_url (string)
 */

defined('ABSPATH') || exit;

// Determine which view to render
$is_edit_view = isset($action) && in_array($action, ['edit', 'new']);
?>

<div class="wrap mypco-shortcodes-admin">

<?php if ($is_edit_view): ?>
    <?php // ================================================================
          // EDIT / NEW SHORTCODE VIEW
          // ================================================================ ?>

    <h1>
        <?php if ($action === 'edit'): ?>
            <?php printf(__('Edit Shortcode #%d', 'mypco-online'), $id); ?>
        <?php else: ?>
            <?php _e('Add New Shortcode', 'mypco-online'); ?>
        <?php endif; ?>
        <a href="<?php echo esc_url($page_url); ?>" class="page-title-action"><?php _e('Back to Shortcodes', 'mypco-online'); ?></a>
    </h1>

    <hr class="wp-header-end">

    <?php if ($action === 'edit' && $id > 0): ?>
        <div class="mypco-shortcode-preview-bar">
            <strong><?php _e('Shortcode:', 'mypco-online'); ?></strong>
            <?php $preview_code = '[' . $type_def['tag'] . ' id="' . $id . '"]'; ?>
            <code id="shortcode-preview"><?php echo esc_html($preview_code); ?></code>
            <button type="button" class="button button-small mypco-copy-btn" data-copy="<?php echo esc_attr($preview_code); ?>">
                <?php _e('Copy', 'mypco-online'); ?>
            </button>
        </div>
    <?php endif; ?>

    <form method="post" action="">
        <?php wp_nonce_field('mypco_save_module_shortcode'); ?>
        <input type="hidden" name="mypco_save_module_shortcode" value="1">
        <input type="hidden" name="shortcode_id" value="<?php echo esc_attr($id); ?>">
        <input type="hidden" name="shortcode_type" value="<?php echo esc_attr($type_slug); ?>">

        <!-- General Settings -->
        <div class="card">
            <h2><?php _e('General Settings', 'mypco-online'); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="shortcode_description"><?php _e('Description', 'mypco-online'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="shortcode_description" name="shortcode_description"
                               value="<?php echo esc_attr($shortcode['description'] ?? ''); ?>"
                               class="large-text" placeholder="<?php esc_attr_e('e.g., Homepage calendar widget', 'mypco-online'); ?>">
                        <p class="description">
                            <?php _e('A description to help you remember what this shortcode is used for.', 'mypco-online'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Module', 'mypco-online'); ?></th>
                    <td>
                        <strong><?php echo esc_html($type_def['module_name']); ?></strong>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Shortcode Type', 'mypco-online'); ?></th>
                    <td>
                        <code><?php echo esc_html($type_def['tag']); ?></code>
                        <p class="description">
                            <?php echo esc_html($type_def['description']); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <?php // ---- Module-specific settings ---- ?>
        <?php if (!empty($type_def['fields'])): ?>
            <div class="card">
                <h2><?php printf(__('%s Settings', 'mypco-online'), esc_html($type_def['name'])); ?></h2>

                <table class="form-table">
                    <?php foreach ($type_def['fields'] as $field): ?>
                        <tr>
                            <th scope="row">
                                <label for="<?php echo esc_attr($field['key']); ?>"><?php echo esc_html($field['label']); ?></label>
                            </th>
                            <td>
                                <?php
                                $value = $shortcode[$field['key']] ?? ($type_def['defaults'][$field['key']] ?? '');
                                switch ($field['type']):
                                    case 'text': ?>
                                        <input type="text" id="<?php echo esc_attr($field['key']); ?>"
                                               name="<?php echo esc_attr($field['key']); ?>"
                                               value="<?php echo esc_attr($value); ?>"
                                               class="regular-text"
                                               <?php if (!empty($field['placeholder'])): ?>placeholder="<?php echo esc_attr($field['placeholder']); ?>"<?php endif; ?>>
                                        <?php break;
                                    case 'number': ?>
                                        <input type="number" id="<?php echo esc_attr($field['key']); ?>"
                                               name="<?php echo esc_attr($field['key']); ?>"
                                               value="<?php echo esc_attr($value); ?>"
                                               <?php if (isset($field['min'])): ?>min="<?php echo esc_attr($field['min']); ?>"<?php endif; ?>
                                               <?php if (isset($field['max'])): ?>max="<?php echo esc_attr($field['max']); ?>"<?php endif; ?>
                                               <?php if (isset($field['step'])): ?>step="<?php echo esc_attr($field['step']); ?>"<?php endif; ?>
                                               class="small-text">
                                        <?php if (!empty($field['after'])): ?>
                                            <?php echo esc_html($field['after']); ?>
                                        <?php endif; ?>
                                        <?php break;
                                    case 'select': ?>
                                        <select id="<?php echo esc_attr($field['key']); ?>"
                                                name="<?php echo esc_attr($field['key']); ?>">
                                            <?php foreach ($field['options'] as $opt_val => $opt_label): ?>
                                                <option value="<?php echo esc_attr($opt_val); ?>" <?php selected($value, $opt_val); ?>>
                                                    <?php echo esc_html($opt_label); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php break;
                                    case 'checkbox': ?>
                                        <label>
                                            <input type="checkbox" id="<?php echo esc_attr($field['key']); ?>"
                                                   name="<?php echo esc_attr($field['key']); ?>"
                                                   value="1" <?php checked($value); ?>>
                                            <?php if (!empty($field['description'])): ?>
                                                <?php echo esc_html($field['description']); ?>
                                            <?php endif; ?>
                                        </label>
                                        <?php break;
                                endswitch; ?>

                                <?php if (!empty($field['description']) && $field['type'] !== 'checkbox'): ?>
                                    <p class="description"><?php echo esc_html($field['description']); ?></p>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php endif; ?>

        <?php // ---- Display Options ---- ?>
        <div class="card">
            <h2><?php _e('Display Options', 'mypco-online'); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="custom_class"><?php _e('Custom CSS Class', 'mypco-online'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="custom_class" name="custom_class"
                               value="<?php echo esc_attr($shortcode['custom_class'] ?? ''); ?>"
                               class="regular-text" placeholder="<?php esc_attr_e('my-custom-class', 'mypco-online'); ?>">
                        <p class="description">
                            <?php _e('Add a CSS class to the shortcode wrapper for custom styling.', 'mypco-online'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <?php // ---- Styling ---- ?>
        <div class="card">
            <h2><?php _e('Styling', 'mypco-online'); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="primary_color"><?php _e('Primary Color', 'mypco-online'); ?></label>
                    </th>
                    <td>
                        <input type="color" id="primary_color" name="primary_color"
                               value="<?php echo esc_attr($shortcode['primary_color'] ?? '#333333'); ?>">
                        <span class="mypco-color-preview"><?php echo esc_html($shortcode['primary_color'] ?? '#333333'); ?></span>
                        <p class="description">
                            <?php _e('Used for accent elements like badges and links.', 'mypco-online'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="text_color"><?php _e('Text Color', 'mypco-online'); ?></label>
                    </th>
                    <td>
                        <input type="color" id="text_color" name="text_color"
                               value="<?php echo esc_attr($shortcode['text_color'] ?? '#333333'); ?>">
                        <span class="mypco-color-preview"><?php echo esc_html($shortcode['text_color'] ?? '#333333'); ?></span>
                        <p class="description">
                            <?php _e('Main text color for headings and body text.', 'mypco-online'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="background_color"><?php _e('Background Color', 'mypco-online'); ?></label>
                    </th>
                    <td>
                        <input type="color" id="background_color" name="background_color"
                               value="<?php echo esc_attr($shortcode['background_color'] ?? '#ffffff'); ?>">
                        <span class="mypco-color-preview"><?php echo esc_html($shortcode['background_color'] ?? '#ffffff'); ?></span>
                        <p class="description">
                            <?php _e('Background color for cards and list items.', 'mypco-online'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="border_radius"><?php _e('Border Radius', 'mypco-online'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="border_radius" name="border_radius"
                               value="<?php echo esc_attr($shortcode['border_radius'] ?? 8); ?>"
                               min="0" max="30" step="1" class="small-text"> px
                        <p class="description">
                            <?php _e('Rounded corners for cards, badges, and buttons.', 'mypco-online'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <p class="submit">
            <button type="submit" class="button button-primary">
                <?php echo ($action === 'edit') ? esc_html__('Save Settings', 'mypco-online') : esc_html__('Create Shortcode', 'mypco-online'); ?>
            </button>
            <a href="<?php echo esc_url($page_url); ?>" class="button"><?php _e('Cancel', 'mypco-online'); ?></a>
        </p>
    </form>

    <script>
    (function($) {
        'use strict';

        // Copy shortcode to clipboard
        $('.mypco-copy-btn').on('click', function() {
            var text = $(this).data('copy');
            var $btn = $(this);

            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function() {
                    $btn.text('<?php echo esc_js(__('Copied!', 'mypco-online')); ?>');
                    setTimeout(function() {
                        $btn.text('<?php echo esc_js(__('Copy', 'mypco-online')); ?>');
                    }, 2000);
                });
            }
        });

        // Update color preview text
        $('input[type="color"]').on('input change', function() {
            $(this).next('.mypco-color-preview').text($(this).val());
        });
    })(jQuery);
    </script>

<?php else: ?>
    <?php // ================================================================
          // LIST VIEW (MAIN PAGE)
          // ================================================================ ?>

    <h1 class="wp-heading-inline"><?php _e('Shortcodes', 'mypco-online'); ?></h1>

    <hr class="wp-header-end">

    <!-- Add New Shortcode (inline) -->
    <div class="mypco-add-new-bar">
        <?php
        // Group types by module for the optgroup dropdown
        $grouped_types = [];
        foreach ($types as $slug => $type) {
            $mod = $type['module'];
            if (!isset($grouped_types[$mod])) {
                $grouped_types[$mod] = [];
            }
            $grouped_types[$mod][$slug] = $type;
        }
        ?>
        <select id="mypco-new-shortcode-type">
            <option value=""><?php _e('Select shortcode type...', 'mypco-online'); ?></option>
            <?php foreach ($grouped_types as $mod_key => $mod_types): ?>
                <optgroup label="<?php echo esc_attr($mod_types[array_key_first($mod_types)]['module_name']); ?>">
                    <?php foreach ($mod_types as $slug => $type): ?>
                        <option value="<?php echo esc_attr($slug); ?>">
                            <?php echo esc_html($type['name']); ?> &mdash; [<?php echo esc_html($type['tag']); ?>]
                        </option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endforeach; ?>
        </select>
        <a href="#" id="mypco-add-new-btn" class="button button-primary"><?php _e('Add New', 'mypco-online'); ?></a>
    </div>

    <?php if ($settings_saved): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Shortcode settings saved successfully!', 'mypco-online'); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($deleted): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Shortcode deleted.', 'mypco-online'); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($bulk_deleted)): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php printf(_n('%d shortcode deleted.', '%d shortcodes deleted.', $bulk_deleted, 'mypco-online'), $bulk_deleted); ?></p>
        </div>
    <?php endif; ?>

    <!-- Module Filter Links -->
    <ul class="subsubsub">
        <li class="all">
            <a href="<?php echo esc_url($page_url); ?>" <?php echo empty($current_filter) ? 'class="current" aria-current="page"' : ''; ?>>
                <?php _e('All', 'mypco-online'); ?>
                <span class="count">(<?php echo esc_html($count_all); ?>)</span>
            </a>
        </li>
        <?php $mod_index = 0; ?>
        <?php foreach ($modules as $mod_key => $mod_name): ?>
            <?php if ($counts_by_module[$mod_key] > 0 || $current_filter === $mod_key): ?>
                | <li class="<?php echo esc_attr($mod_key); ?>">
                    <a href="<?php echo esc_url(add_query_arg('module_filter', $mod_key, $page_url)); ?>"
                       <?php echo ($current_filter === $mod_key) ? 'class="current" aria-current="page"' : ''; ?>>
                        <?php echo esc_html($mod_name); ?>
                        <span class="count">(<?php echo esc_html($counts_by_module[$mod_key]); ?>)</span>
                    </a>
                </li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ul>

    <form method="post" id="mypco-shortcodes-form">
        <?php wp_nonce_field('mypco_bulk_module_shortcodes'); ?>
        <input type="hidden" name="mypco_bulk_module_shortcodes" value="1">

        <!-- Top Tablenav -->
        <div class="tablenav top">
            <div class="alignleft actions bulkactions">
                <label for="bulk-action-selector-top" class="screen-reader-text"><?php _e('Select bulk action', 'mypco-online'); ?></label>
                <select name="bulk_action" id="bulk-action-selector-top">
                    <option value="-1"><?php _e('Bulk actions', 'mypco-online'); ?></option>
                    <option value="trash"><?php _e('Move to Trash', 'mypco-online'); ?></option>
                </select>
                <input type="submit" class="button action" value="<?php esc_attr_e('Apply', 'mypco-online'); ?>">
            </div>
            <div class="tablenav-pages one-page">
                <span class="displaying-num"><?php printf(_n('%s item', '%s items', count($shortcodes), 'mypco-online'), count($shortcodes)); ?></span>
            </div>
            <br class="clear">
        </div>

        <table class="wp-list-table widefat fixed striped table-view-list">
            <thead>
            <tr>
                <td id="cb" class="manage-column column-cb check-column">
                    <input id="cb-select-all-1" type="checkbox">
                </td>
                <th scope="col" class="manage-column column-shortcode column-primary"><?php _e('Shortcode', 'mypco-online'); ?></th>
                <th scope="col" class="manage-column column-description"><?php _e('Description', 'mypco-online'); ?></th>
                <th scope="col" class="manage-column column-module"><?php _e('Module', 'mypco-online'); ?></th>
                <th scope="col" class="manage-column column-type"><?php _e('Type', 'mypco-online'); ?></th>
            </tr>
            </thead>
            <tbody id="the-list">
            <?php if (empty($shortcodes)): ?>
                <tr class="no-items">
                    <td class="colspanchange" colspan="5"><?php _e('No shortcodes found. Click "Add New" to create one.', 'mypco-online'); ?></td>
                </tr>
            <?php else: ?>
                <?php foreach ($shortcodes as $sc_id => $sc): ?>
                    <?php
                    $sc_type_slug = $sc['shortcode_type'] ?? '';
                    $sc_type = isset($types[$sc_type_slug]) ? $types[$sc_type_slug] : null;
                    $sc_tag = $sc_type ? $sc_type['tag'] : $sc_type_slug;
                    $sc_code = '[' . $sc_tag . ' id="' . $sc_id . '"]';
                    $sc_description = !empty($sc['description']) ? $sc['description'] : '';
                    $module_name = $sc_type ? $sc_type['module_name'] : __('Unknown', 'mypco-online');
                    $type_name = $sc_type ? $sc_type['name'] : $sc_type_slug;
                    $edit_url = esc_url($page_url . '&action=edit&id=' . $sc_id);
                    $trash_url = esc_url(wp_nonce_url($page_url . '&action=delete&id=' . $sc_id, 'mypco_delete_module_shortcode_' . $sc_id));
                    ?>
                    <tr id="shortcode-<?php echo esc_attr($sc_id); ?>">
                        <th scope="row" class="check-column">
                            <input id="cb-select-<?php echo esc_attr($sc_id); ?>" type="checkbox" name="shortcode_ids[]" value="<?php echo esc_attr($sc_id); ?>">
                        </th>
                        <td class="shortcode column-shortcode has-row-actions column-primary" data-colname="<?php esc_attr_e('Shortcode', 'mypco-online'); ?>">
                            <strong>
                                <a class="row-title" href="<?php echo $edit_url; ?>">
                                    <code><?php echo esc_html($sc_code); ?></code>
                                </a>
                            </strong>
                            <div class="row-actions">
                                <span class="edit">
                                    <a href="<?php echo $edit_url; ?>"><?php _e('Edit', 'mypco-online'); ?></a>
                                </span>
                                <span class="copy">
                                    | <a href="#" class="mypco-copy-link" data-copy="<?php echo esc_attr($sc_code); ?>"><?php _e('Copy', 'mypco-online'); ?></a>
                                </span>
                                <span class="trash">
                                    | <a href="<?php echo $trash_url; ?>" class="submitdelete" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to delete this shortcode?', 'mypco-online')); ?>');"><?php _e('Trash', 'mypco-online'); ?></a>
                                </span>
                            </div>
                            <button type="button" class="toggle-row"><span class="screen-reader-text"><?php _e('Show more details', 'mypco-online'); ?></span></button>
                        </td>
                        <td class="description column-description" data-colname="<?php esc_attr_e('Description', 'mypco-online'); ?>">
                            <?php if (!empty($sc_description)): ?>
                                <?php echo esc_html($sc_description); ?>
                            <?php else: ?>
                                <span class="mypco-no-description">&mdash;</span>
                            <?php endif; ?>
                        </td>
                        <td class="module column-module" data-colname="<?php esc_attr_e('Module', 'mypco-online'); ?>">
                            <?php echo esc_html($module_name); ?>
                        </td>
                        <td class="type column-type" data-colname="<?php esc_attr_e('Type', 'mypco-online'); ?>">
                            <?php echo esc_html($type_name); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
            <tfoot>
            <tr>
                <td class="manage-column column-cb check-column">
                    <input id="cb-select-all-2" type="checkbox">
                </td>
                <th scope="col" class="manage-column column-shortcode column-primary"><?php _e('Shortcode', 'mypco-online'); ?></th>
                <th scope="col" class="manage-column column-description"><?php _e('Description', 'mypco-online'); ?></th>
                <th scope="col" class="manage-column column-module"><?php _e('Module', 'mypco-online'); ?></th>
                <th scope="col" class="manage-column column-type"><?php _e('Type', 'mypco-online'); ?></th>
            </tr>
            </tfoot>
        </table>

        <!-- Bottom Tablenav -->
        <div class="tablenav bottom">
            <div class="alignleft actions bulkactions">
                <label for="bulk-action-selector-bottom" class="screen-reader-text"><?php _e('Select bulk action', 'mypco-online'); ?></label>
                <select name="bulk_action2" id="bulk-action-selector-bottom">
                    <option value="-1"><?php _e('Bulk actions', 'mypco-online'); ?></option>
                    <option value="trash"><?php _e('Move to Trash', 'mypco-online'); ?></option>
                </select>
                <input type="submit" class="button action" value="<?php esc_attr_e('Apply', 'mypco-online'); ?>">
            </div>
            <div class="tablenav-pages one-page">
                <span class="displaying-num"><?php printf(_n('%s item', '%s items', count($shortcodes), 'mypco-online'), count($shortcodes)); ?></span>
            </div>
            <br class="clear">
        </div>
    </form>

    <script>
    (function($) {
        'use strict';

        // Select all checkboxes
        $('#cb-select-all-1, #cb-select-all-2').on('change', function() {
            var isChecked = $(this).prop('checked');
            $('input[name="shortcode_ids[]"]').prop('checked', isChecked);
            $('#cb-select-all-1, #cb-select-all-2').prop('checked', isChecked);
        });

        // Sync bulk action selectors
        $('#bulk-action-selector-top').on('change', function() {
            $('#bulk-action-selector-bottom').val($(this).val());
        });
        $('#bulk-action-selector-bottom').on('change', function() {
            $('#bulk-action-selector-top').val($(this).val());
            $('select[name="bulk_action"]').val($(this).val());
        });

        // Copy shortcode link
        $('.mypco-copy-link').on('click', function(e) {
            e.preventDefault();
            var text = $(this).data('copy');
            var $link = $(this);
            var originalText = $link.text();

            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function() {
                    $link.text('<?php echo esc_js(__('Copied!', 'mypco-online')); ?>');
                    setTimeout(function() {
                        $link.text(originalText);
                    }, 2000);
                });
            }
        });

        // Add New shortcode dropdown
        $('#mypco-add-new-btn').on('click', function(e) {
            e.preventDefault();
            var selected = $('#mypco-new-shortcode-type').val();
            if (!selected) {
                $('#mypco-new-shortcode-type').focus();
                return;
            }
            window.location.href = '<?php echo esc_js($page_url); ?>&action=new&type=' + selected;
        });
    })(jQuery);
    </script>

<?php endif; ?>

</div>
