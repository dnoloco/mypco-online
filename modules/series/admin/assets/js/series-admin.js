/**
 * Series Admin JavaScript
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Confirm delete actions
        $('.submitdelete').on('click', function(e) {
            if (!confirm($(this).data('confirm') || 'Are you sure?')) {
                e.preventDefault();
            }
        });

        // =====================================================================
        // Image Upload via WordPress Media Library
        // =====================================================================

        // Pattern 1: .mypco-image-upload wrapper (existing admin templates)
        $('.mypco-image-upload').each(function() {
            var $wrap      = $(this);
            var $input     = $wrap.find('input[type="hidden"]');
            var $preview   = $wrap.find('.mypco-image-preview');
            var $uploadBtn = $wrap.find('.mypco-upload-btn');
            var $removeBtn = $wrap.find('.mypco-remove-btn');
            var uploadType = $wrap.data('upload-type') || '';

            $uploadBtn.on('click', function(e) {
                e.preventDefault();

                // Set custom upload directory param before opening the frame
                if (uploadType && wp.Uploader) {
                    wp.Uploader.defaults.multipart_params.mypco_upload_type = uploadType;
                }

                var frame = wp.media({
                    title: $uploadBtn.text(),
                    button: { text: 'Use this image' },
                    multiple: false,
                    library: { type: 'image' }
                });

                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    var url = attachment.sizes && attachment.sizes.large
                        ? attachment.sizes.large.url
                        : attachment.url;

                    $input.val(url);
                    $preview.html('<img src="' + url + '" alt="">').addClass('has-image');
                    $removeBtn.show();
                });

                frame.on('close', function() {
                    // Clean up the custom param so normal uploads aren't affected
                    if (wp.Uploader) {
                        delete wp.Uploader.defaults.multipart_params.mypco_upload_type;
                    }
                });

                frame.open();
            });

            $removeBtn.on('click', function(e) {
                e.preventDefault();
                $input.val('');
                $preview.html('').removeClass('has-image');
                $removeBtn.hide();
            });
        });

        // Pattern 2: data-target / data-preview buttons (meta box + taxonomy forms)
        $(document).on('click', '.mypco-upload-image-btn', function(e) {
            e.preventDefault();

            var $btn     = $(this);
            var $target  = $($btn.data('target'));
            var $preview = $btn.data('preview') ? $($btn.data('preview')) : null;
            var $remove  = $btn.siblings('.mypco-remove-image-btn');

            var frame = wp.media({
                title: $btn.text(),
                button: { text: 'Use this image' },
                multiple: false,
                library: { type: 'image' }
            });

            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                var url = attachment.sizes && attachment.sizes.medium
                    ? attachment.sizes.medium.url
                    : attachment.url;

                $target.val(url);

                if ($preview && $preview.length) {
                    $preview.html('<img src="' + url + '" style="max-width:200px;height:auto;" />');
                }

                if ($remove.length) {
                    $remove.show();
                }
            });

            frame.open();
        });

        $(document).on('click', '.mypco-remove-image-btn', function(e) {
            e.preventDefault();

            var $btn     = $(this);
            var $target  = $($btn.data('target'));
            var $preview = $btn.data('preview') ? $($btn.data('preview')) : null;

            $target.val('');

            if ($preview && $preview.length) {
                $preview.html('');
            }

            $btn.hide();
        });
    });
})(jQuery);
