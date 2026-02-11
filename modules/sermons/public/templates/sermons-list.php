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
?>

<div class="mypco-sermons-wrapper">

    <?php foreach ($sermons as $sermon):
        $sermon_date = !empty($sermon->sermon_date)
            ? date_i18n(get_option('date_format'), strtotime($sermon->sermon_date))
            : '';

        $image_url = !empty($sermon->image_url) ? $sermon->image_url : (!empty($sermon->series_image_url) ? $sermon->series_image_url : '');
    ?>
        <div class="mypco-sermon-item">

            <?php if (!empty($image_url)): ?>
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

                <div class="mypco-sermon-links">
                    <?php if (!empty($sermon->audio_url)): ?>
                        <a href="<?php echo esc_url($sermon->audio_url); ?>" class="mypco-sermon-link mypco-sermon-audio" target="_blank" rel="noopener noreferrer">
                            <span class="mypco-icon-audio"></span>
                            <?php _e('Listen', 'mypco-online'); ?>
                        </a>
                    <?php endif; ?>

                    <?php if (!empty($sermon->video_url)): ?>
                        <a href="<?php echo esc_url($sermon->video_url); ?>" class="mypco-sermon-link mypco-sermon-video" target="_blank" rel="noopener noreferrer">
                            <span class="mypco-icon-video"></span>
                            <?php _e('Watch', 'mypco-online'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

</div>
