<?php
/**
 * Single Sermon Detail Template
 *
 * Layout: Video (large, top) → Audio link → Sermon info (description, speaker, date, series)
 *
 * Available variables:
 * - $sermon (object) - Sermon object with joined speaker/series/topic data
 * - $placeholder_url (string) - URL to the default placeholder image
 */

defined('ABSPATH') || exit;

/**
 * Parse a video URL into an embeddable URL and thumbnail.
 */
if (!function_exists('mypco_parse_video_url')):
function mypco_parse_video_url($url) {
    if (empty($url)) {
        return null;
    }

    // YouTube: various URL formats
    $youtube_id = null;
    if (preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/|v\/|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $matches)) {
        $youtube_id = $matches[1];
    }

    if ($youtube_id) {
        return [
            'type'      => 'youtube',
            'id'        => $youtube_id,
            'embed_url' => 'https://www.youtube-nocookie.com/embed/' . $youtube_id . '?autoplay=1&rel=0',
            'thumb_url' => 'https://img.youtube.com/vi/' . $youtube_id . '/hqdefault.jpg',
        ];
    }

    // Vimeo
    if (preg_match('/vimeo\.com\/(?:video\/)?(\d+)/', $url, $matches)) {
        return [
            'type'      => 'vimeo',
            'id'        => $matches[1],
            'embed_url' => 'https://player.vimeo.com/video/' . $matches[1] . '?autoplay=1',
            'thumb_url' => '',
        ];
    }

    // Direct video file (mp4, webm, etc.)
    if (preg_match('/\.(mp4|webm|ogg)(\?|$)/i', $url)) {
        return [
            'type'      => 'direct',
            'id'        => '',
            'embed_url' => $url,
            'thumb_url' => '',
        ];
    }

    return null;
}
endif;

$video = mypco_parse_video_url($sermon->video_url ?? '');
$has_video = !empty($video);

// Image for video thumbnail fallback
$image_url = '';
if (!empty($sermon->image_url)) {
    $image_url = $sermon->image_url;
} elseif (!empty($sermon->series_image_url)) {
    $image_url = $sermon->series_image_url;
}

// YouTube thumb as last resort for video poster
if (empty($image_url) && $video && !empty($video['thumb_url'])) {
    $image_url = $video['thumb_url'];
}

$sermon_date = !empty($sermon->sermon_date)
    ? date_i18n(get_option('date_format'), strtotime($sermon->sermon_date))
    : '';

$back_url = remove_query_arg('mypco_sermon');
?>

<div class="mypco-sermon-single">

    <!-- Back link -->
    <a href="<?php echo esc_url($back_url); ?>" class="mypco-sermon-back">&larr; <?php _e('All Messages', 'mypco-online'); ?></a>

    <!-- Sermon title -->
    <h2 class="mypco-sermon-single-title"><?php echo esc_html($sermon->title); ?></h2>

    <!-- Video (large, full-width) -->
    <?php if ($has_video): ?>
        <div class="mypco-sermon-single-video"
             data-embed-url="<?php echo esc_attr($video['embed_url']); ?>"
             data-video-type="<?php echo esc_attr($video['type']); ?>">
            <?php if ($video['type'] === 'direct'): ?>
                <video class="mypco-sermon-single-video-element" controls preload="none"
                       poster="<?php echo !empty($image_url) ? esc_url($image_url) : ''; ?>">
                    <source src="<?php echo esc_url($video['embed_url']); ?>" type="video/mp4">
                </video>
            <?php else: ?>
                <div class="mypco-sermon-video-thumb">
                    <?php if (!empty($image_url)): ?>
                        <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($sermon->title); ?>">
                    <?php else: ?>
                        <div class="mypco-sermon-video-placeholder"></div>
                    <?php endif; ?>
                    <button class="mypco-sermon-play-btn" aria-label="<?php esc_attr_e('Play video', 'mypco-online'); ?>">
                        <svg viewBox="0 0 68 48" width="68" height="48">
                            <path class="mypco-play-bg" d="M66.52 7.74c-.78-2.93-2.49-5.41-5.42-6.19C55.79.13 34 0 34 0S12.21.13 6.9 1.55c-2.93.78-4.63 3.26-5.42 6.19C.06 13.05 0 24 0 24s.06 10.95 1.48 16.26c.78 2.93 2.49 5.41 5.42 6.19C12.21 47.87 34 48 34 48s21.79-.13 27.1-1.55c2.93-.78 4.64-3.26 5.42-6.19C67.94 34.95 68 24 68 24s-.06-10.95-1.48-16.26z" fill="#212121" fill-opacity="0.8"/>
                            <path d="M45 24L27 14v20" fill="#fff"/>
                        </svg>
                    </button>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Audio link -->
    <?php if (!empty($sermon->audio_url)): ?>
        <div class="mypco-sermon-single-audio">
            <a href="<?php echo esc_url($sermon->audio_url); ?>" class="mypco-sermon-link mypco-sermon-audio" target="_blank" rel="noopener noreferrer">
                <span class="mypco-icon-audio"></span>
                <?php _e('Listen to Audio', 'mypco-online'); ?>
            </a>
        </div>
    <?php endif; ?>

    <!-- Sermon info -->
    <div class="mypco-sermon-single-info">

        <?php if (!empty($sermon->description)): ?>
            <div class="mypco-sermon-single-description">
                <?php echo wpautop(esc_html($sermon->description)); ?>
            </div>
        <?php endif; ?>

        <div class="mypco-sermon-single-meta">
            <?php if (!empty($sermon->speaker_name)): ?>
                <div class="mypco-sermon-single-meta-row">
                    <span class="mypco-sermon-single-label"><?php _e('Speaker', 'mypco-online'); ?></span>
                    <span class="mypco-sermon-single-value"><?php echo esc_html($sermon->speaker_name); ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($sermon_date)): ?>
                <div class="mypco-sermon-single-meta-row">
                    <span class="mypco-sermon-single-label"><?php _e('Date', 'mypco-online'); ?></span>
                    <span class="mypco-sermon-single-value"><?php echo esc_html($sermon_date); ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($sermon->series_title)): ?>
                <div class="mypco-sermon-single-meta-row">
                    <span class="mypco-sermon-single-label"><?php _e('Series', 'mypco-online'); ?></span>
                    <span class="mypco-sermon-single-value"><?php echo esc_html($sermon->series_title); ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($sermon->scripture)): ?>
                <div class="mypco-sermon-single-meta-row">
                    <span class="mypco-sermon-single-label"><?php _e('Scripture', 'mypco-online'); ?></span>
                    <span class="mypco-sermon-single-value"><?php echo esc_html($sermon->scripture); ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($sermon->topic_name)): ?>
                <div class="mypco-sermon-single-meta-row">
                    <span class="mypco-sermon-single-label"><?php _e('Topic', 'mypco-online'); ?></span>
                    <span class="mypco-sermon-single-value"><?php echo esc_html($sermon->topic_name); ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>
