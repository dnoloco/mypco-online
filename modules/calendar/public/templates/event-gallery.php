<?php
/**
 * Calendar Gallery View Template
 *
 * Displays events in a visual gallery grid with images.
 *
 * Available variables:
 * - $all_events (array) - Array of all event objects
 * - $expanded_events (array) - Events organized by event ID for grouping recurring events
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

// Helper to group events by parent event
$events_by_parent = [];
foreach ($all_events as $event) {
    $event_id = $event['id'];
    if (!isset($events_by_parent[$event_id])) {
        $events_by_parent[$event_id] = [
            'event' => $event,
            'instances' => []
        ];
    }
    $events_by_parent[$event_id]['instances'][] = $event;
}
?>

<div id="pco-view-gallery" class="pco-view-section">
    
    <h2 class="pco-section-title pco-gallery-title">
        <?php _e('Event Gallery', 'mypco-online'); ?>
    </h2>
    
    <?php if (empty($all_events)): ?>
        
        <p class="pco-no-events">
            <?php _e('No events found to display.', 'mypco-online'); ?>
        </p>
        
    <?php else: ?>
        
        <div class="pco-gallery-grid">
            
            <?php foreach ($events_by_parent as $event_id => $event_group): 
                $event = $event_group['event'];
                $instances = $event_group['instances'];
                
                // Skip events without images for gallery view
                if (empty($event['image_url'])) {
                    continue;
                }
                
                // Determine if recurring (multiple instances)
                $is_recurring = count($instances) > 1;
                
                // Get first instance for display
                $first_instance = $instances[0];
                
                // Format date display
                try {
                    $tz = new DateTimeZone('America/Chicago');
                    $start = new DateTime($first_instance['starts_at'], new DateTimeZone('UTC'));
                    $start->setTimezone($tz);
                    
                    if ($is_recurring) {
                        $gallery_date = $start->format('M j, Y');
                    } else {
                        // Check for multi-day event
                        if ($first_instance['ends_at']) {
                            $end = new DateTime($first_instance['ends_at'], new DateTimeZone('UTC'));
                            $end->setTimezone($tz);
                            
                            if ($start->format('Y-m-d') !== $end->format('Y-m-d')) {
                                // Multi-day event
                                if ($start->format('m') === $end->format('m')) {
                                    $gallery_date = $start->format('F j') . '-' . $end->format('j, Y');
                                } else {
                                    $gallery_date = $start->format('F j') . '-' . $end->format('F j, Y');
                                }
                            } else {
                                // Single day
                                $gallery_date = $start->format('M j, Y');
                            }
                        } else {
                            $gallery_date = $start->format('M j, Y');
                        }
                    }
                    
                    $time_display = $first_instance['all_day'] ? __('All Day', 'mypco-online') : $start->format('g:i a');
                    $date_key = $start->format('Y-m-d');
                    
                } catch (Exception $e) {
                    $gallery_date = __('Date unavailable', 'mypco-online');
                    $time_display = '';
                    $date_key = '';
                }
                
                // Extract location name
                $location = $first_instance['location'];
                if (!empty($location) && strpos($location, ' - ') !== false) {
                    $location_name = trim(substr($location, 0, strpos($location, ' - ')));
                } else {
                    $location_name = $location;
                }
                
                // Prepare event data for detail view
                $event_data_json = json_encode([
                    'name' => $event['name'],
                    'description' => $event['description'],
                    'summary' => $event['summary'],
                    'image_url' => $event['image_url'],
                    'time' => $time_display,
                    'date' => $gallery_date,
                    'dateKey' => $date_key,
                    'location' => $location,
                    'location_name' => $location_name,
                    'registration_url' => $event['registration_url']
                ]);
                ?>
                
                <div class="pco-gallery-item">
                    
                    <!-- Event Image -->
                    <div class="pco-gallery-image-wrapper">
                        <img src="<?php echo esc_url($event['image_url']); ?>" 
                             class="pco-gallery-img" 
                             alt="<?php echo esc_attr($event['name']); ?>">
                        
                        <?php if ($event['featured']): ?>
                            <div class="pco-gallery-featured-badge">
                                <span class="dashicons dashicons-star-filled"></span>
                                <?php _e('Featured', 'mypco-online'); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Event Content -->
                    <div class="pco-gallery-content">
                        
                        <!-- Event Title (Clickable) -->
                        <button class="pco-event-title-btn pco-gallery-title-btn" 
                                data-event='<?php echo esc_attr($event_data_json); ?>'>
                            <strong class="pco-gallery-event-name">
                                <?php echo esc_html($event['name']); ?>
                            </strong>
                        </button>
                        
                        <!-- Event Meta -->
                        <div class="pco-gallery-meta">
                            <span class="pco-gallery-date">
                                <span class="dashicons dashicons-calendar-alt"></span>
                                <?php echo esc_html($gallery_date); ?>
                            </span>
                            
                            <?php if ($is_recurring): ?>
                                <span class="pco-gallery-recurring">
                                    <span class="dashicons dashicons-update"></span>
                                    <?php _e('Recurring', 'mypco-online'); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Event Summary -->
                        <?php if ($event['summary']): ?>
                            <p class="pco-gallery-summary">
                                <?php echo esc_html(wp_trim_words($event['summary'], 15)); ?>
                            </p>
                        <?php endif; ?>
                        
                        <!-- Location -->
                        <?php if ($location_name): ?>
                            <div class="pco-gallery-location">
                                <span class="dashicons dashicons-location"></span>
                                <?php echo esc_html($location_name); ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Badges -->
                        <div class="pco-gallery-badges">
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
    
</div>
