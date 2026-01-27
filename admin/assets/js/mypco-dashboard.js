/**
 * MyPCO Admin Dashboard JavaScript
 * 
 * Handles dashboard widget drag-and-drop functionality
 * Uses WordPress's native postbox.js system
 */

(function($) {
    'use strict';
    
    /**
     * Initialize dashboard widgets
     */
    function initDashboard() {
        // Get the page hook from the body class
        var pagenow = $('#dashboard-widgets').data('pagenow');
        
        if (!pagenow) {
            console.error('MyPCO Dashboard: Missing pagenow data attribute');
            return;
        }
        
        // Initialize postboxes (drag-drop, collapse, etc.)
        postboxes.add_postbox_toggles(pagenow);
        
        // Set up sortable with AJAX save
        setupSortable(pagenow);
        
        // Set up collapse/expand save
        setupCollapseToggle(pagenow);
    }
    
    /**
     * Set up jQuery UI Sortable for metaboxes
     */
    function setupSortable(pagenow) {
        $('.meta-box-sortables').each(function() {
            var $sortable = $(this);
            
            // Check if already sortable
            if ($sortable.hasClass('ui-sortable')) {
                return;
            }
            
            // Make sortable
            $sortable.sortable({
                placeholder: 'sortable-placeholder',
                connectWith: '.meta-box-sortables',
                items: '.postbox',
                handle: '.hndle',
                cursor: 'move',
                delay: 150,
                distance: 2,
                tolerance: 'pointer',
                forcePlaceholderSize: true,
                helper: 'clone',
                opacity: 0.65,
                
                // Save order after sort
                stop: function(event, ui) {
                    // Let the DOM settle
                    setTimeout(function() {
                        saveMetaBoxOrder(pagenow);
                    }, 100);
                }
            });
        });
    }
    
    /**
     * Set up collapse/expand toggle with save
     */
    function setupCollapseToggle(pagenow) {
        // Watch for postbox toggle events
        $(document).on('postbox-toggled', function(event, postbox) {
            // Save collapsed state
            saveMetaBoxOrder(pagenow);
        });
        
        // Backup: Also watch for clicks on toggle buttons
        $('.postbox .handlediv, .postbox .hndle').on('click', function() {
            setTimeout(function() {
                saveMetaBoxOrder(pagenow);
            }, 100);
        });
    }
    
    /**
     * Save metabox order via AJAX
     */
    function saveMetaBoxOrder(pagenow) {
        // Build order data
        var order = {};
        
        $('.meta-box-sortables').each(function() {
            var context = $(this).attr('id');
            if (context) {
                // Get IDs of all postboxes in this sortable area
                var boxes = [];
                $(this).find('.postbox').each(function() {
                    boxes.push(this.id);
                });
                order[context] = boxes.join(',');
            }
        });
        
        // Build hidden/closed data
        var hidden = [];
        $('.postbox').each(function() {
            if ($(this).hasClass('closed')) {
                hidden.push(this.id);
            }
        });
        
        // Send AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mypco_save_dashboard_order',
                order: order,
                hidden: hidden.join(','),
                page: pagenow,
                _ajax_nonce: $('#mypco-dashboard-nonce').val()
            },
            success: function(response) {
                if (response.success) {
                    // Optionally show a brief "saved" indicator
                    showSavedIndicator();
                } else {
                    console.error('MyPCO Dashboard: Failed to save order', response);
                }
            },
            error: function(xhr, status, error) {
                console.error('MyPCO Dashboard: AJAX error', error);
            }
        });
    }
    
    /**
     * Show brief "saved" indicator
     */
    function showSavedIndicator() {
        var $indicator = $('<div class="mypco-saved-indicator">Saved</div>');
        $indicator.css({
            position: 'fixed',
            bottom: '20px',
            right: '20px',
            padding: '10px 20px',
            background: '#00a32a',
            color: '#fff',
            borderRadius: '4px',
            boxShadow: '0 2px 5px rgba(0,0,0,0.2)',
            zIndex: 999999,
            opacity: 0
        });
        
        $('body').append($indicator);
        
        // Fade in
        $indicator.animate({ opacity: 1 }, 200, function() {
            // Stay visible briefly
            setTimeout(function() {
                // Fade out
                $indicator.animate({ opacity: 0 }, 200, function() {
                    $indicator.remove();
                });
            }, 1000);
        });
    }
    
    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        initDashboard();
    });
    
})(jQuery);
