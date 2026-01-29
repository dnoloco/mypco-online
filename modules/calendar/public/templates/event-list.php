<?php
/**
 * Calendar List View Template
 *
 * Displays events in a list format with featured events highlighted.
 *
 * Available variables:
 * - $featured_events (array) - Array of featured event objects
 * - $regular_events (array) - Array of regular event objects
 *
 * Event object structure:
 * - id (string)
 * - name (string)
 * - starts_at (string ISO date)
 * - ends_at (string ISO date)
 * - all_day (bool)
 * - location (string)
 * - description (string)
 * - summary (string)
 * - image_url (string)
 * - featured (bool)
 * - registration_url (string)
 */

defined('ABSPATH') || exit;

// Helper function to format dates (minimal PHP allowed in templates)
if (!function_exists('mypco_format_event_date')) {
    function mypco_format_event_date($starts_at, $ends_at, $all_day) {
        try {
            $tz = new DateTimeZone('America/Chicago');
            $start = new DateTime($starts_at, new DateTimeZone('UTC'));
            $start->setTimezone($tz);
            
            if ($all_day) {
                return $start->format('l, M j');
            } else {
                return $start->format('l, M j') . ' at ' . $start->format('g:i a');
            }
        } catch (Exception $e) {
            return 'Date unavailable';
        }
    }
}

if (!function_exists('mypco_get_location_name')) {
    function mypco_get_location_name($location) {
        if (empty($location)) {
            return '';
        }
        
        // Extract location name (before " - ")
        if (strpos($location, ' - ') !== false) {
            return trim(substr($location, 0, strpos($location, ' - ')));
        }
        
        return $location;
    }
}
?>

<div id="pco-view-list" class="pco-view-section active">
    
    <!-- Featured Events Section -->
    <?php if (!empty($featured_events)): ?>
        <div class="pco-featured-section">
            <h2 class="pco-section-title pco-featured-title">
                <?php _e('Featured', 'mypco-online'); ?>
            </h2>

            <?php foreach ($featured_events as $event):
                $is_all_day = $event['is_all_day'] ?? false;
                $is_recurring = $event['is_recurring'] ?? false;
                $is_multi_day = $event['is_multi_day'] ?? false;

                // Determine date display for featured event
                if (!empty($event['featured_date_display'])) {
                    $featured_date = $event['featured_date_display'];
                } else {
                    // Simple date format: "Feb 3, 2026"
                    try {
                        $start = new DateTime($event['starts_at'], new DateTimeZone('UTC'));
                        $start->setTimezone(new DateTimeZone('America/Chicago'));
                        $featured_date = $start->format('M j, Y');
                    } catch (Exception $e) {
                        $featured_date = $event['date_display'];
                    }
                }

                $location_name = mypco_get_location_name($event['location']);
                $event_data_json = json_encode([
                    'name' => $event['name'],
                    'description' => $event['description'],
                    'summary' => $event['summary'],
                    'image_url' => $event['image_url'],
                    'date' => $featured_date,
                    'time' => $is_all_day ? 'All Day' : date('g:i a', strtotime($event['starts_at'])),
                    'location' => $event['location'],
                    'location_name' => $location_name,
                    'registration_url' => $event['registration_url']
                ]);
                ?>

                <div class="pco-featured-card">
                    <?php if ($event['image_url']): ?>
                        <div class="pco-featured-image">
                            <img src="<?php echo esc_url($event['image_url']); ?>"
                                 alt="<?php echo esc_attr($event['name']); ?>"
                                 class="pco-featured-img">
                        </div>
                    <?php endif; ?>

                    <div class="pco-featured-content">
                        <button class="pco-event-title-btn pco-featured-title-btn"
                                data-event='<?php echo esc_attr($event_data_json); ?>'>
                            <strong class="pco-featured-name">
                                <?php echo esc_html($event['name']); ?>
                            </strong>
                        </button>

                        <div class="pco-featured-meta">
                            <span class="pco-featured-date"><?php echo esc_html($featured_date); ?></span>
                            <?php if ($is_recurring): ?>
                                <span class="pco-featured-recurring">| <?php _e('Recurring', 'mypco-online'); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="pco-featured-badges">
                            <span class="pco-badge is-featured">
                                ★ <?php _e('Featured', 'mypco-online'); ?>
                            </span>

                            <?php if ($event['registration_url']): ?>
                                <span class="pco-badge pco-badge-signup">
                                    <?php _e('Signups available', 'mypco-online'); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <!-- Regular Events Section -->
    <div class="pco-events-section">
        <h2 class="pco-section-title">
            <?php _e('Upcoming Events', 'mypco-online'); ?>
        </h2>
        
        <?php if (empty($regular_events)): ?>
            <p class="pco-no-events">
                <?php _e('No upcoming events found.', 'mypco-online'); ?>
            </p>
        <?php else: ?>
            
            <?php foreach ($regular_events as $event):
                $is_all_day = $event['is_all_day'] ?? false;
                $date_display = mypco_format_event_date($event['starts_at'], $event['ends_at'], $is_all_day);
                $location_name = mypco_get_location_name($event['location']);
                $event_data_json = json_encode([
                    'name' => $event['name'],
                    'description' => $event['description'],
                    'summary' => $event['summary'],
                    'image_url' => $event['image_url'],
                    'date' => $date_display,
                    'time' => $is_all_day ? 'All Day' : date('g:i a', strtotime($event['starts_at'])),
                    'location' => $event['location'],
                    'location_name' => $location_name,
                    'registration_url' => $event['registration_url']
                ]);
                ?>
                
                <div class="pco-event-card">
                    <div class="pco-event-card-inner">
                        <?php if ($event['image_url']): ?>
                            <div class="pco-event-thumbnail">
                                <img src="<?php echo esc_url($event['image_url']); ?>" 
                                     alt="<?php echo esc_attr($event['name']); ?>"
                                     class="pco-event-thumb-img">
                            </div>
                        <?php endif; ?>
                        
                        <div class="pco-event-details">
                            <button class="pco-event-title-btn" 
                                    data-event='<?php echo esc_attr($event_data_json); ?>'>
                                <strong class="pco-event-name">
                                    <?php echo esc_html($event['name']); ?>
                                </strong>
                            </button>
                            
                            <div class="pco-event-meta">
                                <span class="pco-event-date-time">
                                    <?php echo esc_html($date_display); ?>
                                </span>
                                
                                <?php if ($location_name): ?>
                                    <span class="pco-event-location-name">
                                        <?php echo esc_html($location_name); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($event['summary']): ?>
                                <p class="pco-event-summary">
                                    <?php echo esc_html(wp_trim_words($event['summary'], 15)); ?>
                                </p>
                            <?php endif; ?>
                            
                            <?php if ($event['registration_url']): ?>
                                <div class="pco-event-actions">
                                    <span class="pco-badge pco-badge-signup">
                                        <?php _e('Signups available', 'mypco-online'); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
            <?php endforeach; ?>
            
        <?php endif; ?>
    </div>
    
</div>
