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

        // Lock our meta boxes in place on the Message editor –
        // disable sortable so they cannot be dragged between areas.
        if ($('body').hasClass('post-type-mypco_message')) {
            $('.meta-box-sortables').sortable('disable');
        }

        // =====================================================================
        // Inline "Add New Speaker" from the Message editor meta box
        // Uses event delegation so it works with Gutenberg's async rendering.
        // =====================================================================

        // Toggle the add-new form
        $(document).on('click', '#mypco_toggle_add_speaker', function(e) {
            e.preventDefault();
            $('#mypco_add_speaker_form').slideToggle(150);
        });

        // Create the speaker via AJAX
        $(document).on('click', '#mypco_add_speaker_btn', function() {
            var $btn    = $(this);
            var $input  = $('#mypco_new_speaker_name');
            var $select = $('#mypco_speaker_id');
            var $status = $('#mypco_add_speaker_status');
            var name    = $.trim($input.val());

            if (!name) {
                $input.focus();
                return;
            }

            $btn.prop('disabled', true);
            $status.text('Adding…').show();

            $.post(mypcoSeriesAdmin.ajaxUrl, {
                action:       'mypco_add_speaker',
                nonce:        mypcoSeriesAdmin.addSpeakerNonce,
                speaker_name: name
            }, function(response) {
                $btn.prop('disabled', false);

                if (response.success) {
                    $select.append(
                        $('<option>', { value: response.data.id, text: response.data.name })
                    );
                    $select.val(response.data.id);
                    $input.val('');
                    $status.text('Added!').delay(2000).fadeOut();
                    $('#mypco_add_speaker_form').slideUp(150);
                } else {
                    $status.text(response.data.message || 'Error').show();
                }
            }).fail(function() {
                $btn.prop('disabled', false);
                $status.text('Request failed.').show();
            });
        });

        // Allow pressing Enter in the speaker name field to trigger the add
        $(document).on('keydown', '#mypco_new_speaker_name', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                $('#mypco_add_speaker_btn').trigger('click');
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
