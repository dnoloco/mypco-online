/**
 * Sermons Public JavaScript
 *
 * Handles inline video playback - replaces thumbnail with embedded iframe
 * when the play button is clicked.
 */
(function($) {
    'use strict';

    $(document).ready(function() {

        // Play button click: swap thumbnail for embedded iframe
        $('.mypco-sermon-video-thumb').on('click', function() {
            var $player = $(this).closest('.mypco-sermon-video-player');
            var embedUrl = $player.data('embed-url');
            var videoType = $player.data('video-type');

            if (!embedUrl) {
                return;
            }

            var iframe = $('<iframe>', {
                src: embedUrl,
                frameborder: '0',
                allow: 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture',
                allowfullscreen: true
            });

            // Replace the thumbnail with the iframe
            $(this).replaceWith(iframe);
        });
    });
})(jQuery);
