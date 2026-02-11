<?php
/**
 * Public Sermons List Template
 *
 * Available variables:
 * - $sermons (array) - Array of sermon objects with joined speaker/series/topic data
 * - $view (string) - Display view type
 * - $atts (array) - Shortcode attributes
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
            'thumb_url' => '', // Vimeo thumbnails require API call; fall back to sermon image
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
?>

<div class="mypco-sermons-wrapper">

    <?php foreach ($sermons as $sermon):
        $sermon_date = !empty($sermon->sermon_date)
            ? date_i18n(get_option('date_format'), strtotime($sermon->sermon_date))
            : '';

        $image_url = !empty($sermon->image_url) ? $sermon->image_url : (!empty($sermon->series_image_url) ? $sermon->series_image_url : '');
        $video = mypco_parse_video_url($sermon->video_url ?? '');

        // Use YouTube thumbnail as fallback if no sermon/series image
        if (empty($image_url) && $video && !empty($video['thumb_url'])) {
            $image_url = $video['thumb_url'];
        }

        $has_video = !empty($video);
    ?>
        <div class="mypco-sermon-item">

            <?php if ($has_video): ?>
                <!-- Video player with thumbnail overlay -->
                <div class="mypco-sermon-video-player"
                     data-embed-url="<?php echo esc_attr($video['embed_url']); ?>"
                     data-video-type="<?php echo esc_attr($video['type']); ?>">
                    <?php if ($video['type'] === 'direct'): ?>
                        <video class="mypco-sermon-video-element" controls preload="none"
                               poster="<?php echo !empty($image_url) ? esc_url($image_url) : ''; ?>">
                            <source src="<?php echo esc_url($video['embed_url']); ?>" type="video/mp4">
                        </video>
                    <?php else: ?>
                        <div class="mypco-sermon-video-thumb">
                            <?php if (!empty($image_url)): ?>
                                <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($sermon->title); ?>" loading="lazy">
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
            <?php elseif (!empty($image_url)): ?>
                <!-- Static image (no video) -->
                <div class="mypco-sermon-image">
                    <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($sermon->title); ?>" loading="lazy">
                </div>
            <?php endif; ?>

            <div class="mypco-sermon-content">
                <h3 class="mypco-sermon-title"><?php echo esc_html($sermon->title); ?></h3>

                <div class="mypco-sermon-meta">
                    <?php if (!empty($sermon_date)): ?>
                        <span class="mypco-sermon-date"><?php echo esc_html($sermon_date); ?></span>
                    <?php endif; ?>

                    <?php if (!empty($sermon->speaker_name)): ?>
                        <span class="mypco-sermon-speaker"><?php echo esc_html($sermon->speaker_name); ?></span>
                    <?php endif; ?>

                    <?php if (!empty($sermon->series_title)): ?>
                        <span class="mypco-sermon-series"><?php echo esc_html($sermon->series_title); ?></span>
                    <?php endif; ?>

                    <?php if (!empty($sermon->topic_name)): ?>
                        <span class="mypco-sermon-topic"><?php echo esc_html($sermon->topic_name); ?></span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($sermon->scripture)): ?>
                    <div class="mypco-sermon-scripture">
                        <strong><?php _e('Scripture:', 'mypco-online'); ?></strong>
                        <?php echo esc_html($sermon->scripture); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($sermon->description)): ?>
                    <div class="mypco-sermon-description">
                        <?php echo esc_html(wp_trim_words($sermon->description, 30)); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($sermon->audio_url)): ?>
                    <div class="mypco-sermon-links">
                        <a href="<?php echo esc_url($sermon->audio_url); ?>" class="mypco-sermon-link mypco-sermon-audio" target="_blank" rel="noopener noreferrer">
                            <span class="mypco-icon-audio"></span>
                            <?php _e('Listen', 'mypco-online'); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

</div>
