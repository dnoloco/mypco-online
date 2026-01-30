<?php
/**
 * Calendar Display Template - Main Container
 *
 * Available variables:
 * - $featured_events (array)
 * - $regular_events (array)
 * - $all_events (array)
 * - $event_map (array)
 * - $expanded_events (array)
 * - $current_month (string)
 * - $timezone (string)
 */

defined('ABSPATH') || exit;
?>

<script>
// Immediately set saved view before content renders to prevent flicker
(function() {
    var savedView = localStorage.getItem('pco-calendar-view');
    if (savedView && savedView !== 'list' && savedView !== 'detail') {
        document.documentElement.setAttribute('data-pco-initial-view', savedView);
    }
})();
</script>
<style>
/* Hide non-active views immediately based on saved preference */
html[data-pco-initial-view="month"] .pco-view-section:not(#pco-view-month),
html[data-pco-initial-view="gallery"] .pco-view-section:not(#pco-view-gallery) {
    display: none !important;
}
html[data-pco-initial-view="month"] #pco-view-month,
html[data-pco-initial-view="gallery"] #pco-view-gallery {
    display: block !important;
}
/* Update button active states */
html[data-pco-initial-view="month"] .pco-view-btn[data-target="pco-view-list"],
html[data-pco-initial-view="gallery"] .pco-view-btn[data-target="pco-view-list"] {
    background-color: transparent !important;
    color: #666 !important;
}
html[data-pco-initial-view="month"] .pco-view-btn[data-target="pco-view-month"],
html[data-pco-initial-view="gallery"] .pco-view-btn[data-target="pco-view-gallery"] {
    background-color: #000 !important;
    color: #fff !important;
}
/* Hide sidebar for month/gallery views */
html[data-pco-initial-view="month"] .pco-sidebar,
html[data-pco-initial-view="gallery"] .pco-sidebar {
    display: none !important;
}
html[data-pco-initial-view="month"] .pco-layout-grid,
html[data-pco-initial-view="gallery"] .pco-layout-grid {
    grid-template-columns: 1fr !important;
}
</style>

<div class="pco-wrapper">
    <!-- Header with view switcher -->
    <div class="pco-header">
        <div class="pco-category-dropdown">
            <select>
                <option><?php _e('All Categories', 'mypco-online'); ?></option>
            </select>
        </div>
        <div class="pco-view-switcher">
            <button class="pco-view-btn active" data-target="pco-view-list">
                <?php _e('List', 'mypco-online'); ?>
            </button>
            <button class="pco-view-btn" data-target="pco-view-month">
                <?php _e('Month', 'mypco-online'); ?>
            </button>
            <button class="pco-view-btn" data-target="pco-view-gallery">
                <?php _e('Gallery', 'mypco-online'); ?>
            </button>
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
            <!-- List View -->
            <?php include MYPCO_PLUGIN_DIR . 'modules/calendar/public/templates/event-list.php'; ?>

            <!-- Month View -->
            <?php include MYPCO_PLUGIN_DIR . 'modules/calendar/public/templates/event-month.php'; ?>

            <!-- Gallery View -->
            <?php include MYPCO_PLUGIN_DIR . 'modules/calendar/public/templates/event-gallery.php'; ?>

            <!-- Event Detail View -->
            <?php include MYPCO_PLUGIN_DIR . 'modules/calendar/public/templates/event-detail.php'; ?>
        </div>
    </div>
</div>

<!-- Output expanded events for JavaScript -->
<script>
    window.pcoExpandedEvents = <?php echo json_encode($expanded_events); ?>;
</script>
