<?php
/**
 * Calendar Event Detail View Template
 *
 * Displays detailed information for a single event.
 * This view is dynamically populated by JavaScript when a user clicks on an event.
 *
 * Note: Event data is populated via JavaScript using the data-event attributes
 * from other views. This template provides the structure and placeholders.
 */

defined('ABSPATH') || exit;
?>

<div id="pco-view-detail" class="pco-view-section">
    
    <!-- Navigation Breadcrumb and Arrows -->
    <div class="pco-detail-navigation">
        
        <!-- Breadcrumb -->
        <div class="pco-detail-breadcrumb">
            <a href="#" id="pco-detail-back" class="pco-breadcrumb-link">
                <span class="dashicons dashicons-arrow-left-alt2"></span>
                <?php _e('All Events', 'mypco-online'); ?>
            </a>
            <span class="pco-breadcrumb-separator">›</span>
            <span id="pco-breadcrumb-event-name" class="pco-breadcrumb-current">
                <!-- Event name populated by JavaScript -->
            </span>
        </div>
        
        <!-- Previous/Next Navigation -->
        <div class="pco-detail-nav-arrows">
            <button id="pco-detail-prev" class="pco-nav-arrow" title="<?php esc_attr_e('Previous Event', 'mypco-online'); ?>">
                <span class="dashicons dashicons-arrow-left-alt2"></span>
                <?php _e('Prev', 'mypco-online'); ?>
            </button>
            <button id="pco-detail-next" class="pco-nav-arrow" title="<?php esc_attr_e('Next Event', 'mypco-online'); ?>">
                <?php _e('Next', 'mypco-online'); ?>
                <span class="dashicons dashicons-arrow-right-alt2"></span>
            </button>
        </div>
        
    </div>
    
    <!-- Event Detail Container -->
    <div class="pco-detail-container">
        
        <!-- Left Column - Event Information -->
        <div class="pco-detail-left">
            
            <!-- Event Title -->
            <h1 id="pco-detail-title" class="pco-detail-title">
                <!-- Event title populated by JavaScript -->
            </h1>
            
            <!-- Date and Time -->
            <div class="pco-detail-meta-line">
                <div class="pco-detail-meta-item">
                    <span class="dashicons dashicons-calendar-alt"></span>
                    <span id="pco-detail-date" class="pco-detail-date">
                        <!-- Date populated by JavaScript -->
                    </span>
                </div>
                <div class="pco-detail-meta-item">
                    <span class="dashicons dashicons-clock"></span>
                    <span id="pco-detail-time" class="pco-detail-time">
                        <!-- Time populated by JavaScript -->
                    </span>
                </div>
            </div>
            
            <!-- Event Description Section -->
            <div class="pco-detail-section">
                <h2 class="pco-detail-section-title">
                    <?php _e('Details', 'mypco-online'); ?>
                </h2>
                <div id="pco-detail-description" class="pco-detail-description">
                    <!-- Description populated by JavaScript -->
                    <p class="pco-detail-loading">
                        <?php _e('Loading event details...', 'mypco-online'); ?>
                    </p>
                </div>
            </div>
            
        </div>
        
        <!-- Right Column - Image, Location, and Actions -->
        <div class="pco-detail-right">
            
            <!-- Event Image -->
            <div id="pco-detail-image-container" class="pco-detail-image-container" style="display: none;">
                <img id="pco-detail-image" 
                     src="" 
                     alt="" 
                     class="pco-detail-image">
            </div>
            
            <!-- Location Information -->
            <div id="pco-detail-location-container" class="pco-detail-location-box">
                <h3 class="pco-detail-location-heading">
                    <?php _e('LOCATION', 'mypco-online'); ?>
                </h3>
                
                <!-- Location Name -->
                <p id="pco-detail-location-text" class="pco-location-name">
                    <!-- Location name populated by JavaScript -->
                </p>
                
                <!-- Location Address -->
                <p id="pco-detail-address" class="pco-location-address">
                    <!-- Address populated by JavaScript -->
                </p>
                
                <!-- Action Buttons -->
                <div class="pco-detail-location-buttons">
                    
                    <!-- Registration/Signup Button -->
                    <div id="pco-detail-signup-container" style="display: none;">
                        <button id="pco-detail-signup-btn" 
                                class="pco-detail-btn pco-detail-btn-primary pco-signup-link" 
                                data-signup-url="">
                            <div class="pco-btn-text">
                                <?php _e('Register', 'mypco-online'); ?>
                            </div>
                            <div class="pco-btn-subtext">
                                <?php _e('Planning Center', 'mypco-online'); ?>
                            </div>
                        </button>
                    </div>
                    
                    <!-- Get Directions Button -->
                    <a id="pco-detail-location-link" 
                       href="#" 
                       class="pco-detail-btn pco-detail-btn-outline" 
                       target="_blank" 
                       rel="noopener" 
                       data-maps-url=""
                       style="display: none;">
                        <span class="dashicons dashicons-location"></span>
                        <?php _e('Get directions', 'mypco-online'); ?>
                    </a>
                    
                </div>
                
                <!-- Embedded Map (Optional) -->
                <div id="pco-detail-map-container" class="pco-detail-map-container" style="display: none;">
                    <iframe id="pco-detail-map-iframe" 
                            width="100%" 
                            height="200" 
                            frameborder="0" 
                            style="border:0; border-radius: 8px;" 
                            allowfullscreen="" 
                            loading="lazy">
                    </iframe>
                </div>
                
            </div>
            
        </div>
        
    </div>
    
</div>
