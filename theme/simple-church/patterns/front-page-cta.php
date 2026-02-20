<?php
/**
 * Title: Call to Action
 * Slug: simple-church/front-page-cta
 * Description: A clean call-to-action section with heading, text, and button.
 * Categories: simple-church
 * Keywords: cta, call to action, button, contact
 */
?>
<!-- wp:group {"className":"section section--cta","style":{"color":{"background":"#f5f5f0","text":"#1a1a1a"}},"layout":{"type":"default"}} -->
<div class="wp-block-group section section--cta" style="background-color:#f5f5f0;color:#1a1a1a">
	<!-- wp:group {"className":"section__inner","layout":{"type":"default"}} -->
	<div class="wp-block-group section__inner">
		<!-- wp:group {"className":"cta reveal","layout":{"type":"default"}} -->
		<div class="wp-block-group cta reveal">
			<!-- wp:heading {"className":"cta__heading"} -->
			<h2 class="wp-block-heading cta__heading">Ready to get started?</h2>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"className":"cta__text"} -->
			<p class="cta__text">Let us help you build something meaningful for your community.</p>
			<!-- /wp:paragraph -->

			<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
			<div class="wp-block-buttons">
				<!-- wp:button {"className":"cta__button"} -->
				<div class="wp-block-button cta__button"><a class="wp-block-button__link wp-element-button">Get in touch</a></div>
				<!-- /wp:button -->
			</div>
			<!-- /wp:buttons -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
