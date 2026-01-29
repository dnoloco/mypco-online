/**
 * MyPCO Calendar JavaScript
 *
 * Handles calendar view switching, mini calendar, month view rendering,
 * and event detail display.
 */

(function($) {
    'use strict';

    // Calendar state
    var state = {
        currentView: 'list',
        previousView: 'list',
        currentMonth: new Date().getMonth(),
        currentYear: new Date().getFullYear(),
        expandedEvents: window.pcoExpandedEvents || window.mypcoCalendarData?.expandedEvents || {},
        allEventButtons: []
    };

    /**
     * Initialize the calendar
     */
    function init() {
        // Cache event buttons for navigation
        state.allEventButtons = $('.pco-event-title-btn').toArray();

        // Initialize components
        initViewSwitcher();
        initMiniCalendar();
        initMonthCalendar();
        initEventDetail();
        initEventNavigation();

        // Set initial view from data attribute if available
        var defaultView = $('.pco-wrapper').data('default-view') || 'list';
        if (defaultView !== 'list') {
            switchView(defaultView);
        }
    }

    /**
     * View Switcher - handles switching between List, Month, Gallery views
     */
    function initViewSwitcher() {
        $(document).on('click', '.pco-view-btn', function(e) {
            e.preventDefault();
            var target = $(this).data('target');

            if (target) {
                var viewName = target.replace('pco-view-', '');
                switchView(viewName);
            }
        });
    }

    /**
     * Switch to a specific view
     */
    function switchView(viewName) {
        // Update button states
        $('.pco-view-btn').removeClass('active');
        $('.pco-view-btn[data-target="pco-view-' + viewName + '"]').addClass('active');

        // Hide all views
        $('.pco-view-section').removeClass('active');

        // Show target view
        $('#pco-view-' + viewName).addClass('active');

        // Track view state - save where we came from when going to detail view
        if (viewName === 'detail' && state.currentView !== 'detail') {
            state.previousView = state.currentView;
        }
        state.currentView = viewName;

        // Toggle body class for detail view (hides sidebar)
        if (viewName === 'detail') {
            $('body').addClass('pco-detail-active');
        } else {
            $('body').removeClass('pco-detail-active');
        }

        // Render month calendar if switching to month view
        if (viewName === 'month') {
            renderMonthCalendar();
        }
    }

    /**
     * Mini Calendar - sidebar navigation calendar
     */
    function initMiniCalendar() {
        // Navigation buttons
        $(document).on('click', '.pco-mini-cal-nav', function() {
            var nav = $(this).data('nav');

            if (nav === 'prev') {
                state.currentMonth--;
                if (state.currentMonth < 0) {
                    state.currentMonth = 11;
                    state.currentYear--;
                }
            } else {
                state.currentMonth++;
                if (state.currentMonth > 11) {
                    state.currentMonth = 0;
                    state.currentYear++;
                }
            }

            renderMiniCalendar();
            renderMonthCalendar();
        });

        // Initial render
        renderMiniCalendar();
    }

    /**
     * Render the mini calendar grid
     */
    function renderMiniCalendar() {
        var months = ['January', 'February', 'March', 'April', 'May', 'June',
                      'July', 'August', 'September', 'October', 'November', 'December'];

        // Update header
        $('.pco-mini-cal-month-display').text(months[state.currentMonth] + ' ' + state.currentYear);

        // Get first day of month and total days
        var firstDay = new Date(state.currentYear, state.currentMonth, 1).getDay();
        var daysInMonth = new Date(state.currentYear, state.currentMonth + 1, 0).getDate();
        var today = new Date();

        // Build grid HTML
        var html = '<span>S</span><span>M</span><span>T</span><span>W</span><span>T</span><span>F</span><span>S</span>';

        // Empty cells before first day
        for (var i = 0; i < firstDay; i++) {
            html += '<span class="pco-mini-cal-empty"></span>';
        }

        // Day cells
        for (var day = 1; day <= daysInMonth; day++) {
            var dateKey = state.currentYear + '-' +
                          String(state.currentMonth + 1).padStart(2, '0') + '-' +
                          String(day).padStart(2, '0');

            var classes = ['pco-mini-cal-day'];

            // Check if today
            if (today.getDate() === day &&
                today.getMonth() === state.currentMonth &&
                today.getFullYear() === state.currentYear) {
                classes.push('is-today');
            }

            // Check if has events
            if (state.expandedEvents[dateKey] && state.expandedEvents[dateKey].length > 0) {
                classes.push('has-events');
            }

            html += '<span class="' + classes.join(' ') + '" data-date="' + dateKey + '">' + day + '</span>';
        }

        $('.pco-mini-cal-grid').html(html);

        // Add click handler for days with events
        $('.pco-mini-cal-day.has-events').on('click', function() {
            var dateKey = $(this).data('date');
            scrollToDate(dateKey);
        });
    }

    /**
     * Scroll to a specific date in list view
     */
    function scrollToDate(dateKey) {
        // Switch to list view if not already
        if (state.currentView !== 'list') {
            switchView('list');
        }

        // Find and scroll to the date header
        var $dateHeader = $('.pco-day-header[data-date="' + dateKey + '"]');
        if ($dateHeader.length) {
            $('html, body').animate({
                scrollTop: $dateHeader.offset().top - 100
            }, 300);

            // Highlight briefly
            $dateHeader.addClass('highlight');
            setTimeout(function() {
                $dateHeader.removeClass('highlight');
            }, 2000);
        }
    }

    /**
     * Month Calendar - full month view
     */
    function initMonthCalendar() {
        // Navigation for month view
        $(document).on('click', '.pco-month-nav', function() {
            var nav = $(this).data('nav');

            if (nav === 'prev') {
                state.currentMonth--;
                if (state.currentMonth < 0) {
                    state.currentMonth = 11;
                    state.currentYear--;
                }
            } else {
                state.currentMonth++;
                if (state.currentMonth > 11) {
                    state.currentMonth = 0;
                    state.currentYear++;
                }
            }

            renderMiniCalendar();
            renderMonthCalendar();
        });
    }

    /**
     * Render the full month calendar
     */
    function renderMonthCalendar() {
        var months = ['January', 'February', 'March', 'April', 'May', 'June',
                      'July', 'August', 'September', 'October', 'November', 'December'];
        var days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

        var firstDay = new Date(state.currentYear, state.currentMonth, 1).getDay();
        var daysInMonth = new Date(state.currentYear, state.currentMonth + 1, 0).getDate();
        var today = new Date();

        var html = '<div class="pco-month-calendar">';

        // Header with navigation
        html += '<div class="pco-month-header-nav">';
        html += '<button class="pco-month-nav" data-nav="prev">&lt; Prev</button>';
        html += '<h3>' + months[state.currentMonth] + ' ' + state.currentYear + '</h3>';
        html += '<button class="pco-month-nav" data-nav="next">Next &gt;</button>';
        html += '</div>';

        // Day headers
        html += '<div class="pco-month-days-header">';
        for (var i = 0; i < 7; i++) {
            html += '<div class="pco-month-day-name">' + days[i] + '</div>';
        }
        html += '</div>';

        // Calendar grid
        html += '<div class="pco-month-grid">';

        // Empty cells before first day
        for (var i = 0; i < firstDay; i++) {
            html += '<div class="pco-month-cell pco-month-cell-empty"></div>';
        }

        // Day cells
        for (var day = 1; day <= daysInMonth; day++) {
            var dateKey = state.currentYear + '-' +
                          String(state.currentMonth + 1).padStart(2, '0') + '-' +
                          String(day).padStart(2, '0');

            var classes = ['pco-month-cell'];
            var events = state.expandedEvents[dateKey] || [];

            // Check if today
            if (today.getDate() === day &&
                today.getMonth() === state.currentMonth &&
                today.getFullYear() === state.currentYear) {
                classes.push('is-today');
            }

            html += '<div class="' + classes.join(' ') + '" data-date="' + dateKey + '">';
            html += '<div class="pco-month-cell-day">' + day + '</div>';

            // Show events (limit to 3)
            if (events.length > 0) {
                html += '<div class="pco-month-cell-events">';
                var displayCount = Math.min(events.length, 3);

                for (var e = 0; e < displayCount; e++) {
                    var evt = events[e];
                    html += '<div class="pco-month-event" data-event=\'' + JSON.stringify(evt).replace(/'/g, '&#39;') + '\'>';
                    html += '<span class="pco-month-event-time">' + escapeHtml(evt.time) + '</span> ';
                    html += '<span class="pco-month-event-name">' + escapeHtml(evt.name) + '</span>';
                    html += '</div>';
                }

                if (events.length > 3) {
                    html += '<div class="pco-month-more">+' + (events.length - 3) + ' more</div>';
                }

                html += '</div>';
            }

            html += '</div>';
        }

        html += '</div></div>';

        $('.pco-month-calendar-container').html(html);

        // Add click handlers for month events
        $('.pco-month-event').on('click', function(e) {
            e.stopPropagation();
            var eventData = $(this).data('event');
            if (eventData) {
                showEventDetail(eventData);
            }
        });

        // Click on day cell to see all events
        $('.pco-month-cell').on('click', function() {
            var dateKey = $(this).data('date');
            if (state.expandedEvents[dateKey] && state.expandedEvents[dateKey].length > 0) {
                scrollToDate(dateKey);
            }
        });
    }

    /**
     * Event Detail View
     */
    function initEventDetail() {
        // Click on event title to show detail
        $(document).on('click', '.pco-event-title-btn', function(e) {
            e.preventDefault();
            var eventData = $(this).data('event');

            if (typeof eventData === 'string') {
                try {
                    eventData = JSON.parse(eventData);
                } catch (err) {
                    console.error('Failed to parse event data:', err);
                    return;
                }
            }

            if (eventData) {
                showEventDetail(eventData);
            }
        });

        // Back button
        $(document).on('click', '#pco-detail-back', function(e) {
            e.preventDefault();
            switchView(state.previousView);
        });

        // Registration/signup button
        $(document).on('click', '#pco-detail-signup-btn', function() {
            var url = $(this).data('signup-url');
            if (url) {
                window.open(url, '_blank');
            }
        });

        // Location link
        $(document).on('click', '#pco-detail-location-link', function(e) {
            // Let the default link behavior happen
        });
    }

    /**
     * Show event detail view
     */
    function showEventDetail(eventData) {
        // Populate detail view
        $('#pco-detail-title').text(eventData.name || '');
        $('#pco-breadcrumb-event-name').text(eventData.name || '');

        // Format date/time display (e.g., "Sunday, February 1, 10–11:15am")
        var dateTimeDisplay = '';
        if (eventData.date) {
            dateTimeDisplay = eventData.date;
            if (eventData.time && eventData.time !== 'All Day') {
                dateTimeDisplay += ', ' + eventData.time;
            } else if (eventData.time === 'All Day') {
                dateTimeDisplay += ' (All Day)';
            }
        }
        $('#pco-detail-datetime').text(dateTimeDisplay);

        // Description - use description or summary
        var description = eventData.description || eventData.summary || 'No description available.';
        $('#pco-detail-description').html(description);

        // Image
        if (eventData.image_url) {
            $('#pco-detail-image').attr('src', eventData.image_url).attr('alt', eventData.name);
            $('#pco-detail-image-container').show();
        } else {
            $('#pco-detail-image-container').hide();
        }

        // Categories (if available)
        if (eventData.categories && eventData.categories.length > 0) {
            var categoriesHtml = '';
            eventData.categories.forEach(function(cat) {
                categoriesHtml += '<span class="pco-detail-category-badge">' + escapeHtml(cat) + '</span>';
            });
            $('#pco-detail-categories').html(categoriesHtml);
            $('#pco-detail-categories-container').show();
        } else {
            $('#pco-detail-categories-container').hide();
        }

        // Location
        if (eventData.location) {
            var locationName = eventData.location_name || eventData.location;
            var address = '';

            // Parse address if available (format: "Location Name - Address")
            if (eventData.location.indexOf(' - ') !== -1) {
                address = eventData.location.substring(eventData.location.indexOf(' - ') + 3);
            }

            $('#pco-detail-location-name').text(locationName);
            $('#pco-detail-location-address').html(address.replace(/, /g, '<br>'));

            // Google Maps links
            var mapsQuery = encodeURIComponent(eventData.location);
            var mapsUrl = 'https://www.google.com/maps/search/?api=1&query=' + mapsQuery;
            var directionsUrl = 'https://www.google.com/maps/dir/?api=1&destination=' + mapsQuery;

            $('#pco-detail-show-map').show();
            $('#pco-detail-directions').attr('href', directionsUrl).show();

            $('#pco-detail-location-container').show();
        } else {
            $('#pco-detail-location-container').hide();
        }

        // Registration button
        if (eventData.registration_url) {
            $('#pco-detail-signup-btn').attr('href', eventData.registration_url);
            $('#pco-detail-signup-container').show();
        } else {
            $('#pco-detail-signup-container').hide();
        }

        // Switch to detail view
        switchView('detail');

        // Scroll to top
        window.scrollTo(0, 0);
    }

    /**
     * Event Navigation (prev/next)
     */
    function initEventNavigation() {
        $(document).on('click', '#pco-detail-prev', function() {
            navigateEvent(-1);
        });

        $(document).on('click', '#pco-detail-next', function() {
            navigateEvent(1);
        });
    }

    /**
     * Navigate to prev/next event
     */
    function navigateEvent(direction) {
        var currentTitle = $('#pco-detail-title').text();
        var currentIndex = -1;

        // Find current event in the list
        for (var i = 0; i < state.allEventButtons.length; i++) {
            var $btn = $(state.allEventButtons[i]);
            var eventData = $btn.data('event');

            if (typeof eventData === 'string') {
                try {
                    eventData = JSON.parse(eventData);
                } catch (e) {
                    continue;
                }
            }

            if (eventData && eventData.name === currentTitle) {
                currentIndex = i;
                break;
            }
        }

        // Navigate to next/prev
        var newIndex = currentIndex + direction;
        if (newIndex >= 0 && newIndex < state.allEventButtons.length) {
            var $newBtn = $(state.allEventButtons[newIndex]);
            var newEventData = $newBtn.data('event');

            if (typeof newEventData === 'string') {
                try {
                    newEventData = JSON.parse(newEventData);
                } catch (e) {
                    return;
                }
            }

            if (newEventData) {
                showEventDetail(newEventData);
            }
        }
    }

    /**
     * Utility: Escape HTML
     */
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Initialize when DOM is ready
    $(document).ready(function() {
        if ($('.pco-wrapper').length) {
            init();
        }
    });

})(jQuery);
