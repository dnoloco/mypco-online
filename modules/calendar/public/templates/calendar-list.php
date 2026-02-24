<?php
/**
 * Standalone Calendar List View Template
 *
 * Renders only the list view without the view switcher.
 * Used by the [mypco_calendar_list] shortcode.
 *
 * Available variables:
 * - $featured_events (array)
 * - $regular_events (array)
 * - $all_events (array)
 * - $event_map (array)
 * - $expanded_events (array)
 * - $current_month (string)
 * - $timezone (string)
 * - $tags (array) - Categories/tags from Planning Center
 */

defined('ABSPATH') || exit;

// List view is always active in standalone mode
$list_active = ' active';
?>

<div class="pco-wrapper pco-wrapper-standalone" data-initial-view="list" data-standalone="list">
    <!-- Header with category filter (no view switcher) -->
    <div class="pco-header">
        <div class="pco-category-dropdown">
            <select id="pco-category-filter">
                <option value=""><?php _e('All Categories', 'mypco-online'); ?></option>
                <?php if (!empty($tags)): ?>
                    <?php
                    $current_group = '';
                    foreach ($tags as $tag):
                        if ($tag['group_name'] !== $current_group):
                            if ($current_group !== ''):
                                echo '</optgroup>';
                            endif;
                            if (!empty($tag['group_name'])):
                                $current_group = $tag['group_name'];
                                echo '<optgroup label="' . esc_attr($current_group) . '">';
                            endif;
                        endif;
                    ?>
                        <option value="<?php echo esc_attr($tag['id']); ?>">
                            <?php echo esc_html($tag['name']); ?>
                        </option>
                    <?php endforeach; ?>
                    <?php if ($current_group !== ''): ?>
                        </optgroup>
                    <?php endif; ?>
                <?php endif; ?>
            </select>
        </div>
    </div>

    <div class="pco-layout-grid">
        <!-- Sidebar with mini calendar -->
        <div class="pco-sidebar">
            <div class="pco-mini-cal">
                <div class="pco-mini-cal-header">
                    <span class="pco-mini-cal-nav" data-nav="prev" title="<?php esc_attr_e('Previous month', 'mypco-online'); ?>">&lt;</span>
                    <span class="pco-mini-cal-month-display"><?php echo esc_html($current_month); ?></span>
                    <span class="pco-mini-cal-nav" data-nav="next" title="<?php esc_attr_e('Next month', 'mypco-online'); ?>">&gt;</span>
                </div>
                <div class="pco-mini-cal-grid">
                    <span>S</span><span>M</span><span>T</span><span>W</span><span>T</span><span>F</span><span>S</span>
                </div>
            </div>
        </div>

        <!-- Main content area -->
        <div class="pco-main-content">
            <?php include MYPCO_PLUGIN_DIR . 'modules/calendar/public/templates/event-list.php'; ?>

            <!-- Event Detail View -->
            <?php include MYPCO_PLUGIN_DIR . 'modules/calendar/public/templates/event-detail.php'; ?>
        </div>
    </div>
</div>

<!-- Output expanded events for mini calendar JavaScript -->
<script>
    window.pcoExpandedEvents = <?php echo json_encode($expanded_events); ?>;
</script>
