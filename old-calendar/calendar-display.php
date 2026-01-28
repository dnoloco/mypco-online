<?php

// --- START HTML OUTPUT ---
?>
<div class="pco-wrapper">
    <div class="pco-header">
        <div class="pco-category-dropdown">
            <select>
                <option>All Categories</option>
            </select>
        </div>
        <div class="pco-view-switcher">
            <button class="pco-view-btn active" data-target="pco-view-list">List</button>
            <button class="pco-view-btn" data-target="pco-view-month">Month</button>
            <button class="pco-view-btn" data-target="pco-view-gallery">Gallery</button>
        </div>
    </div>

    <div class="pco-layout-grid">
        <div class="pco-sidebar">
            <div class="pco-mini-cal">
                <div class="pco-mini-cal-header">
                    <span class="pco-mini-cal-nav" data-nav="prev" title="Previous month"><</span>
                    <span class="pco-mini-cal-month-display"><?php echo date('F Y'); ?></span>
                    <span class="pco-mini-cal-nav" data-nav="next" title="Next month">></span>
                </div>
                <div class="pco-mini-cal-grid">
                    <span>S</span><span>M</span><span>T</span><span>W</span><span>T</span><span>F</span><span>S</span>
                </div>
            </div>
        </div>

        <div class="pco-main-content">

            <!-- LIST VIEW -->
            <div id="pco-view-list" class="pco-view-section active">
                <?php if (!empty($featured_list)) :
                    // Randomly select a featured event if multiple exist
                    $random_index = array_rand($featured_list);
                    $feat_inst = $featured_list[$random_index];
                    $f_pid = $feat_inst['relationships']['event']['data']['id'];
                    $f_attr = $event_map[$f_pid];
                    $f_img = $f_attr['image_url'] ?? '';

                    // Featured event date handling with range support
                    $f_starts_at = $feat_inst['attributes']['starts_at'];
                    $f_ends_at = $feat_inst['attributes']['ends_at'] ?? null;
                    $f_is_all_day = $feat_inst['attributes']['all_day_event'] ?? false;

                    $f_location = $feat_inst['attributes']['location'] ?? '';
                    if ($f_location && strpos($f_location, ' - ') !== false) {
                        $f_location_name = trim(substr($f_location, 0, strpos($f_location, ' - ')));
                    } else {
                        $f_location_name = $f_location;
                    }

                    // Check BOTH event instance and parent event for registration/signup URLs
                    $f_registration_url = $feat_inst['attributes']['registration_url'] ??
                        $feat_inst['attributes']['signup_url'] ??
                        $f_attr['registration_url'] ??
                        $f_attr['signup_url'] ?? '';

                    try {
                        // FIX: Added false parameter for start date
                        $f_start = pco_parse_event_date($f_starts_at, $f_is_all_day, $target_tz_object, false);

                        if ($f_ends_at) {
                            $f_end = pco_parse_event_date($f_ends_at, $f_is_all_day, $target_tz_object, true);

                            // Check if multi-day
                            if ($f_start->format('Y-m-d') !== $f_end->format('Y-m-d')) {
                                // Show range: "April 23-26, 2026"
                                if ($f_start->format('Y') === $f_end->format('Y')) {
                                    if ($f_start->format('m') === $f_end->format('m')) {
                                        $f_date = $f_start->format('F j') . '-' . $f_end->format('j, Y');
                                    } else {
                                        $f_date = $f_start->format('F j') . '-' . $f_end->format('F j, Y');
                                    }
                                } else {
                                    $f_date = $f_start->format('F j, Y') . '-' . $f_end->format('F j, Y');
                                }
                            } else {
                                // Single day
                                $f_date = $f_start->format('l, M j');
                                if (!$f_is_all_day) {
                                    $f_date .= ' at ' . $f_start->format('g:i a');
                                }
                            }
                        } else {
                            $f_date = $f_start->format('l, M j');
                            if (!$f_is_all_day) {
                                $f_date .= ' at ' . $f_start->format('g:i a');
                            }
                        }

                        $f_day_header = $f_start->format('l, M j');
                        $f_date_key = $f_start->format('Y-m-d');
                        $f_time = $f_is_all_day ? 'All Day' : $f_start->format('g:i a');

                    } catch (Exception $e) {
                        $f_date = 'Date Error';
                        $f_day_header = 'Date Error';
                        $f_time = '';
                        $f_date_key = '';
                    }

                    $f_event_data = [
                        'name' => $f_attr['name'] ?? '',
                        'description' => $f_attr['description'] ?? '',
                        'summary' => $f_attr['summary'] ?? '',
                        'image_url' => $f_img,
                        'time' => $f_time,
                        'date' => $f_date,  // FIX: Use $f_date (with range) instead of $f_day_header
                        'dateKey' => $f_date_key,
                        'location' => $f_location,
                        'location_name' => $f_location_name,
                        'registration_url' => $f_registration_url
                    ];
                    ?>
                    <div class="pco-featured-section">
                        <h2 style="font-size:1.5rem; margin-bottom:15px;">Featured</h2>
                        <div class="pco-featured-card">
                            <?php if($f_img): ?>
                                <img src="<?php echo esc_url($f_img); ?>" class="pco-featured-img" alt="<?php echo esc_attr($f_attr['name']); ?>">
                            <?php endif; ?>
                            <div class="pco-featured-content">
                                <button class="pco-featured-title-btn pco-event-title-btn" data-event-id="featured-<?php echo $feat_inst['id']; ?>" data-event='<?php echo esc_attr(json_encode($f_event_data)); ?>'>
                                    <h3><?php echo esc_html($f_attr['name']); ?></h3>
                                </button>

                                <div class="pco-featured-meta">
                                    <?php echo esc_html($f_date); ?>
                                    <?php if ($f_location_name): ?>
                                        <span class="pco-location-separator"> &middot; </span>
                                        <span class="pco-location"><?php echo esc_html($f_location_name); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="pco-badges">
                                    <span class="pco-badge is-featured">* Featured</span>
                                    <?php if($f_registration_url): ?>
                                        <span class="pco-badge pco-badge-signup">Signups available</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <h2 style="font-size:1.5rem; border-bottom:1px solid #ddd; padding-bottom:10px;">Upcoming</h2>

                <?php
                if(empty($regular_list)) {
                    echo "<p>No upcoming events.</p>";
                } else {
                    $currentMonth = '';
                    $currentDay = '';

                    foreach($regular_list as $inst) {
                        $iso = $inst['attributes']['starts_at'];
                        $ends_at = $inst['attributes']['ends_at'] ?? null;
                        $isAllDay = $inst['attributes']['all_day_event'] ?? false;

                        $monthHeader = 'N/A';
                        $dayHeader   = 'N/A';
                        $timeStr     = 'N/A';
                        $date_id     = '';

                        try {
                            $dt = pco_parse_event_date($iso, $isAllDay, $target_tz_object, false);

                            $monthHeader = $dt->format('F Y');
                            $dayHeader   = $dt->format('l, M j');
                            $date_id     = $dt->format('Y-m-d');

                            if ($isAllDay) {
                                $timeStr = 'All Day';
                            } else {
                                $timeStr = $dt->format('g:i a');
                            }
                        } catch (Exception $e) {
                            $timeStr = 'Time Error';
                            $monthHeader = 'Date Error';
                        }

                        $pid = $inst['relationships']['event']['data']['id'];
                        $evt = $event_map[$pid];

                        $location_full = $inst['attributes']['location'] ?? '';
                        $location_name = '';
                        if ($location_full) {
                            if (strpos($location_full, ' - ') !== false) {
                                $location_name = trim(substr($location_full, 0, strpos($location_full, ' - ')));
                            } else {
                                $location_name = $location_full;
                            }
                        }

                        // Check BOTH event instance and parent event for registration/signup URLs
                        // Priority: instance-specific URL first, then parent event URL
                        $inst_reg = $inst['attributes']['registration_url'] ?? null;
                        $inst_signup = $inst['attributes']['signup_url'] ?? null;
                        $evt_reg = $evt['registration_url'] ?? null;
                        $evt_signup = $evt['signup_url'] ?? null;

                        // NOTE: church_center_url is NOT included - it's just a view link, not registration
                        $registration_url = $inst_reg ?? $inst_signup ?? $evt_reg ?? $evt_signup ?? '';

                        $event_instance_id = 'event-' . $inst['id'];

                        // FIX: Calculate date display for multi-day events
                        $date_display = pco_get_date_display($iso, $ends_at, $isAllDay, $target_tz_object);

                        $event_data = [
                            'name' => $evt['name'] ?? '',
                            'description' => $evt['description'] ?? '',
                            'summary' => $evt['summary'] ?? '',
                            'image_url' => $evt['image_url'] ?? '',
                            'time' => $timeStr,
                            'date' => $date_display,  // FIX: Use calculated date display
                            'dateKey' => $date_id,
                            'location' => $location_full,
                            'location_name' => $location_name,
                            'registration_url' => $registration_url
                        ];

                        if ($monthHeader !== $currentMonth) {
                            echo '<div class="pco-month-header">' . esc_html($monthHeader) . '</div>';
                            $currentMonth = $monthHeader;
                            $currentDay = '';
                        }

                        if ($dayHeader !== $currentDay) {
                            echo '<div class="pco-event-date pco-day-header" data-date="' . esc_attr($date_id) . '">' . esc_html(strtoupper($dayHeader)) . '</div>';
                            $currentDay = $dayHeader;
                        }

                        echo '<div class="pco-event-item">';
                        echo '<button class="pco-event-title-btn" data-event-id="' . esc_attr($event_instance_id) . '" data-event=\'' . esc_attr(json_encode($event_data)) . '\'>';
                        echo '  <div class="pco-event-title">' . esc_html($evt['name']) . '</div>';
                        echo '</button>';
                        echo '<div class="pco-event-time">' . esc_html($timeStr) . '</div>';

                        if ($location_name) {
                            $maps_query = urlencode($location_full);
                            $maps_url = 'https://www.google.com/maps/search/?api=1&query=' . $maps_query;

                            echo '<a href="' . esc_url($maps_url) . '" class="pco-event-location pco-maps-link" data-maps-url="' . esc_attr($maps_url) . '" target="_blank" rel="noopener">';
                            echo '  <svg class="pco-location-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
                            echo '    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>';
                            echo '    <circle cx="12" cy="10" r="3"></circle>';
                            echo '  </svg>';
                            echo '  ' . esc_html($location_name);
                            echo '</a>';
                        }

                        if ($registration_url) {
                            echo '<div class="pco-signup-indicator">Signups available</div>';
                        }

                        echo '</div>';
                    }
                }
                ?>

                <?php
                // =====================================================
                // CREATE EXPANDED EVENTS ARRAY FOR MONTH VIEW
                // =====================================================
                $expanded_events = [];

                // First, add all REGULAR events to expanded_events
                foreach ($regular_list as $inst) {
                    $starts_at = $inst['attributes']['starts_at'];
                    $ends_at = $inst['attributes']['ends_at'] ?? null;
                    $is_all_day = $inst['attributes']['all_day_event'] ?? false;

                    $pid = $inst['relationships']['event']['data']['id'];
                    $evt = $event_map[$pid];

                    $location_full = $inst['attributes']['location'] ?? '';
                    $location_name = '';
                    if ($location_full && strpos($location_full, ' - ') !== false) {
                        $location_name = trim(substr($location_full, 0, strpos($location_full, ' - ')));
                    } else {
                        $location_name = $location_full;
                    }

                    // Check BOTH event instance and parent event for registration/signup URLs
                    $registration_url = $inst['attributes']['registration_url'] ??
                        $inst['attributes']['signup_url'] ??
                        $evt['registration_url'] ??
                        $evt['signup_url'] ?? '';

                    // Get all dates this event spans
                    if ($ends_at) {
                        $event_dates = pco_expand_multi_day_event($starts_at, $ends_at, $is_all_day, $target_tz_object);
                    } else {
                        try {
                            $dt = pco_parse_event_date($starts_at, $is_all_day, $target_tz_object, false);
                            $event_dates = [$dt->format('Y-m-d')];
                        } catch (Exception $e) {
                            $event_dates = [];
                        }
                    }

                    // Create an entry for EACH date the event spans
                    foreach ($event_dates as $date_key) {
                        try {
                            $dt = new DateTime($date_key . ' 12:00:00', $target_tz_object);
                            $time_str = $is_all_day ? 'All Day' : pco_parse_event_date($starts_at, $is_all_day, $target_tz_object, false)->format('g:i a');
                            $date_display = pco_get_date_display($starts_at, $ends_at, $is_all_day, $target_tz_object);

                            $event_data = [
                                'name' => $evt['name'] ?? '',
                                'description' => $evt['description'] ?? '',
                                'summary' => $evt['summary'] ?? '',
                                'image_url' => $evt['image_url'] ?? '',
                                'time' => $time_str,
                                'date' => $date_display,
                                'dateKey' => $date_key,
                                'location' => $location_full,
                                'location_name' => $location_name,
                                'registration_url' => $registration_url
                            ];

                            if (!isset($expanded_events[$date_key])) {
                                $expanded_events[$date_key] = [];
                            }

                            $expanded_events[$date_key][] = $event_data;
                        } catch (Exception $e) {}
                    }
                }

                // Second, add all FEATURED events to expanded_events
                foreach ($featured_list as $inst) {
                    $starts_at = $inst['attributes']['starts_at'];
                    $ends_at = $inst['attributes']['ends_at'] ?? null;
                    $is_all_day = $inst['attributes']['all_day_event'] ?? false;

                    $pid = $inst['relationships']['event']['data']['id'];
                    $evt = $event_map[$pid];

                    $location_full = $inst['attributes']['location'] ?? '';
                    $location_name = '';
                    if ($location_full && strpos($location_full, ' - ') !== false) {
                        $location_name = trim(substr($location_full, 0, strpos($location_full, ' - ')));
                    } else {
                        $location_name = $location_full;
                    }

                    // Check BOTH event instance and parent event for registration/signup URLs
                    $registration_url = $inst['attributes']['registration_url'] ??
                        $inst['attributes']['signup_url'] ??
                        $evt['registration_url'] ??
                        $evt['signup_url'] ?? '';

                    // Get all dates this event spans
                    if ($ends_at) {
                        $event_dates = pco_expand_multi_day_event($starts_at, $ends_at, $is_all_day, $target_tz_object);
                    } else {
                        try {
                            $dt = pco_parse_event_date($starts_at, $is_all_day, $target_tz_object, false);
                            $event_dates = [$dt->format('Y-m-d')];
                        } catch (Exception $e) {
                            $event_dates = [];
                        }
                    }

                    // Create an entry for EACH date the event spans
                    foreach ($event_dates as $date_key) {
                        try {
                            $dt = new DateTime($date_key . ' 12:00:00', $target_tz_object);
                            $time_str = $is_all_day ? 'All Day' : pco_parse_event_date($starts_at, $is_all_day, $target_tz_object, false)->format('g:i a');
                            $date_display = pco_get_date_display($starts_at, $ends_at, $is_all_day, $target_tz_object);

                            $event_data = [
                                'name' => $evt['name'] ?? '',
                                'description' => $evt['description'] ?? '',
                                'summary' => $evt['summary'] ?? '',
                                'image_url' => $evt['image_url'] ?? '',
                                'time' => $time_str,
                                'date' => $date_display,
                                'dateKey' => $date_key,
                                'location' => $location_full,
                                'location_name' => $location_name,
                                'registration_url' => $registration_url
                            ];

                            if (!isset($expanded_events[$date_key])) {
                                $expanded_events[$date_key] = [];
                            }

                            $expanded_events[$date_key][] = $event_data;
                        } catch (Exception $e) {}
                    }
                }
                ?>
            </div>

            <!-- MONTH VIEW -->
            <div id="pco-view-month" class="pco-view-section">
                <div class="pco-month-calendar-container">
                    <!-- Month calendar will be rendered by JavaScript -->
                </div>
            </div>

            <!-- GALLERY VIEW -->
            <div id="pco-view-gallery" class="pco-view-section">
                <div class="pco-gallery-grid">
                    <?php
                    $all_events_for_gallery = array_merge($featured_list, $regular_list);

                    // Group events by parent event ID for recurring events
                    $grouped_events = [];
                    foreach($all_events_for_gallery as $inst) {
                        $pid = $inst['relationships']['event']['data']['id'];
                        if (!isset($grouped_events[$pid])) {
                            $grouped_events[$pid] = [];
                        }
                        $grouped_events[$pid][] = $inst;
                    }

                    foreach($grouped_events as $pid => $instances):
                        $evt = $event_map[$pid];
                        $img = $evt['image_url'] ?? '';

                        // Build date range for recurring events
                        $dates = [];
                        foreach ($instances as $inst) {
                            $isAllDay = $inst['attributes']['all_day_event'] ?? false;

                            try {
                                $dt = pco_parse_event_date($inst['attributes']['starts_at'], $isAllDay, $target_tz_object, false);
                                $dates[] = $dt;
                            } catch (Exception $e) {
                                // Skip invalid dates
                            }
                        }

                        if (empty($dates)) continue;

                        // Sort dates
                        usort($dates, function($a, $b) {
                            return $a->getTimestamp() - $b->getTimestamp();
                        });

                        $first_date = $dates[0];
                        $last_date = $dates[count($dates) - 1];

                        // Format date display
                        if (count($dates) > 1) {
                            // Recurring event - show just the next date
                            $gallery_date = $first_date->format('M j, Y');
                            $is_recurring = true;
                        } else {
                            // Single event - check if it spans multiple days
                            $first_inst = $instances[0];
                            $g_ends_at = $first_inst['attributes']['ends_at'] ?? null;
                            $g_is_all_day = $first_inst['attributes']['all_day_event'] ?? false;

                            if ($g_ends_at) {
                                $gallery_date = pco_get_date_display($first_inst['attributes']['starts_at'], $g_ends_at, $g_is_all_day, $target_tz_object);
                            } else {
                                $gallery_date = $first_date->format('M j, Y');
                            }
                            $is_recurring = false;
                        }

                        // Use first instance for other data
                        $first_inst = $instances[0];
                        $g_location = $first_inst['attributes']['location'] ?? '';
                        if ($g_location && strpos($g_location, ' - ') !== false) {
                            $g_location_name = trim(substr($g_location, 0, strpos($g_location, ' - ')));
                        } else {
                            $g_location_name = $g_location;
                        }

                        // Check BOTH event instance and parent event for registration/signup URLs
                        $g_registration_url = $first_inst['attributes']['registration_url'] ??
                            $first_inst['attributes']['signup_url'] ??
                            $evt['registration_url'] ??
                            $evt['signup_url'] ?? '';
                        $g_is_featured = !empty($evt['featured']);

                        // Calculate date display for event detail
                        $g_ends_at = $first_inst['attributes']['ends_at'] ?? null;
                        $g_is_all_day = $first_inst['attributes']['all_day_event'] ?? false;
                        $g_date_display = pco_get_date_display($first_inst['attributes']['starts_at'], $g_ends_at, $g_is_all_day, $target_tz_object);

                        $g_event_data = [
                            'name' => $evt['name'] ?? '',
                            'description' => $evt['description'] ?? '',
                            'summary' => $evt['summary'] ?? '',
                            'image_url' => $img,
                            'time' => $first_inst['attributes']['all_day_event'] ? 'All Day' : $first_date->format('g:i a'),
                            'date' => $g_date_display,  // FIX: Use calculated date display
                            'dateKey' => $first_date->format('Y-m-d'),
                            'location' => $g_location,
                            'location_name' => $g_location_name,
                            'registration_url' => $g_registration_url
                        ];
                        ?>
                        <div class="pco-gallery-item">
                            <?php if($img): ?>
                                <div class="pco-gallery-image-wrapper">
                                    <img src="<?php echo esc_url($img); ?>" class="pco-gallery-img" alt="<?php echo esc_attr($evt['name']); ?>">
                                </div>
                            <?php endif; ?>
                            <div class="pco-gallery-content">
                                <button class="pco-event-title-btn pco-gallery-title-btn" data-event='<?php echo esc_attr(json_encode($g_event_data)); ?>'>
                                    <strong class="pco-gallery-event-name"><?php echo esc_html($evt['name']); ?></strong>
                                </button>
                                <div class="pco-gallery-meta">
                                    <?php echo esc_html($gallery_date); ?>
                                    <?php if ($is_recurring): ?>
                                        <span> | Recurring</span>
                                    <?php endif; ?>
                                </div>
                                <div class="pco-gallery-badges">
                                    <?php if ($g_is_featured): ?>
                                        <span class="pco-badge is-featured">* Featured</span>
                                    <?php endif; ?>
                                    <?php if($g_registration_url): ?>
                                        <span class="pco-badge pco-badge-signup">Signups available</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- EVENT DETAIL VIEW -->
            <div id="pco-view-detail" class="pco-view-section">
                <div class="pco-detail-navigation">
                    <div class="pco-detail-breadcrumb">
                        <a href="#" id="pco-detail-back" class="pco-breadcrumb-link">All Events</a>
                        <span class="pco-breadcrumb-separator">></span>
                        <span id="pco-breadcrumb-event-name"></span>
                    </div>
                    <div class="pco-detail-nav-arrows">
                        <button id="pco-detail-prev" class="pco-nav-arrow"><< Prev</button>
                        <button id="pco-detail-next" class="pco-nav-arrow">Next >></button>
                    </div>
                </div>

                <div class="pco-detail-container">
                    <div class="pco-detail-left">
                        <h1 id="pco-detail-title" class="pco-detail-title"></h1>
                        <div class="pco-detail-meta-line">
                            <span id="pco-detail-date"></span>
                            <span id="pco-detail-time"></span>
                        </div>

                        <div class="pco-detail-section">
                            <h2>Details</h2>
                            <div id="pco-detail-description" class="pco-detail-description"></div>
                        </div>
                    </div>

                    <div class="pco-detail-right">
                        <div id="pco-detail-image-container" class="pco-detail-image-container">
                            <img id="pco-detail-image" src="" alt="" class="pco-detail-image">
                        </div>

                        <div id="pco-detail-location-container" class="pco-detail-location-box">
                            <h3>LOCATION</h3>
                            <p id="pco-detail-location-text" class="pco-location-name"></p>
                            <p id="pco-detail-address" class="pco-location-address"></p>
                            <div class="pco-detail-location-buttons">
                                <div id="pco-detail-signup-container" style="display: none;">
                                    <button id="pco-detail-signup-btn" class="pco-detail-btn pco-detail-btn-outline pco-signup-link" data-signup-url="">
                                        <div>Register</div>
                                        <div style="font-size: 0.75em; opacity: 0.7; margin-top: 2px;">Planning Center</div>
                                    </button>
                                </div>
                                <a id="pco-detail-location-link" href="#" class="pco-detail-btn pco-detail-btn-outline" target="_blank" rel="noopener" data-maps-url="">Get directions</a>
                            </div>
                            <div id="pco-detail-map-container" style="margin-top: 15px; display: none;">
                                <iframe id="pco-detail-map-iframe" width="100%" height="200" frameborder="0" style="border:0; border-radius: 8px;" allowfullscreen="" loading="lazy"></iframe>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </div>


</div>
