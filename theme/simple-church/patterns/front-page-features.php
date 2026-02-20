<?php
/**
 * Title: Features Grid
 * Slug: simple-church/front-page-features
 * Description: Dark section with a features heading and the PCO-aware module grid. When the MyPCO Online plugin is active, cards show live Planning Center data.
 * Categories: simple-church
 * Keywords: features, modules, grid, dark, pco
 */
?>
<!-- wp:group {"className":"section section--dark","layout":{"type":"default"}} -->
<div class="wp-block-group section section--dark">
	<!-- wp:group {"className":"section__inner","layout":{"type":"default"}} -->
	<div class="wp-block-group section__inner">
		<!-- wp:group {"className":"reveal-group","layout":{"type":"default"}} -->
		<div class="wp-block-group reveal-group">
			<!-- wp:paragraph {"className":"section__label reveal"} -->
			<p class="section__label reveal">Features</p>
			<!-- /wp:paragraph -->

			<!-- wp:heading {"className":"section__heading reveal"} -->
			<h2 class="wp-block-heading section__heading reveal">Everything you need, nothing you don't.</h2>
			<!-- /wp:heading -->
		</div>
		<!-- /wp:group -->

		<!-- wp:shortcode -->
		[simple_church_features]
		<!-- /wp:shortcode -->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
