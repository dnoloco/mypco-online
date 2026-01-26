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
            <h2 class="pco-section-title">
                <?php _e('Featured Events', 'mypco-online'); ?>
            </h2>
            
            <?php foreach ($featured_events as $event): 
                $date_display = mypco_format_event_date($event['starts_at'], $event['ends_at'], $event['all_day']);
                $location_name = mypco_get_location_name($event['location']);
                $event_data_json = json_encode([
                    'name' => $event['name'],
                    'description' => $event['description'],
                    'summary' => $event['summary'],
                    'image_url' => $event['image_url'],
                    'date' => $date_display,
                    'time' => $event['all_day'] ? 'All Day' : date('g:i a', strtotime($event['starts_at'])),
                    'location' => $event['location'],
                    'location_name' => $location_name,
                    'registration_url' => $event['registration_url']
                ]);
                ?>
                
                <div class="pco-featured-event-card">
                    <?php if ($event['image_url']): ?>
                        <div class="pco-featured-event-image">
                            <img src="<?php echo esc_url($event['image_url']); ?>" 
                                 alt="<?php echo esc_attr($event['name']); ?>"
                                 class="pco-featured-img">
                        </div>
                    <?php endif; ?>
                    
                    <div class="pco-featured-event-content">
                        <button class="pco-event-title-btn" 
                                data-event='<?php echo esc_attr($event_data_json); ?>'>
                            <strong class="pco-featured-event-name">
                                <?php echo esc_html($event['name']); ?>
                            </strong>
                        </button>
                        
                        <div class="pco-featured-event-meta">
                            <span class="pco-event-date">
                                <span class="dashicons dashicons-calendar-alt"></span>
                                <?php echo esc_html($date_display); ?>
                            </span>
                            
                            <?php if ($location_name): ?>
                                <span class="pco-event-location">
                                    <span class="dashicons dashicons-location"></span>
                                    <?php echo esc_html($location_name); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($event['summary']): ?>
                            <p class="pco-featured-event-summary">
                                <?php echo esc_html(wp_trim_words($event['summary'], 25)); ?>
                            </p>
                        <?php endif; ?>
                        
                        <div class="pco-event-badges">
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
                $date_display = mypco_format_event_date($event['starts_at'], $event['ends_at'], $event['all_day']);
                $location_name = mypco_get_location_name($event['location']);
                $event_data_json = json_encode([
                    'name' => $event['name'],
                    'description' => $event['description'],
                    'summary' => $event['summary'],
                    'image_url' => $event['image_url'],
                    'date' => $date_display,
                    'time' => $event['all_day'] ? 'All Day' : date('g:i a', strtotime($event['starts_at'])),
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
