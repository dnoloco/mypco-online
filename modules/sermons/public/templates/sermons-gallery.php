<?php
/**
 * Public Sermons Gallery Template
 *
 * Displays sermons as a card grid. Each card shows an image and the title.
 * Clicking a card navigates to the single sermon detail page.
 *
 * Available variables:
 * - $sermons (array) - Array of sermon objects with joined speaker/series/topic data
 * - $view (string) - Display view type
 * - $atts (array) - Shortcode attributes
 * - $placeholder_url (string) - URL to the default placeholder image
 */

defined('ABSPATH') || exit;

// Build the base URL for sermon links (current page URL without existing mypco_sermon param)
$current_url = remove_query_arg('mypco_sermon');
?>

<div class="mypco-sermons-gallery">

    <?php foreach ($sermons as $sermon):
        // Image fallback chain: sermon image → series image → placeholder
        $image_url = '';
        if (!empty($sermon->image_url)) {
            $image_url = $sermon->image_url;
        } elseif (!empty($sermon->series_image_url)) {
            $image_url = $sermon->series_image_url;
        } else {
            $image_url = $placeholder_url;
        }

        $detail_url = add_query_arg('mypco_sermon', $sermon->id, $current_url);
    ?>
        <a href="<?php echo esc_url($detail_url); ?>" class="mypco-sermon-card">
            <div class="mypco-sermon-card-image">
                <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($sermon->title); ?>" loading="lazy">
            </div>
            <div class="mypco-sermon-card-body">
                <h3 class="mypco-sermon-card-title"><?php echo esc_html($sermon->title); ?></h3>
            </div>
        </a>
    <?php endforeach; ?>

</div>
