<?php
/**
 * Title: About / Split Layout
 * Slug: simple-church/front-page-about
 * Description: Two-column split layout with a heading on the left and body text on the right.
 * Categories: simple-church
 * Keywords: about, split, two-column, story, layout
 */
?>
<!-- wp:group {"className":"section section--light","style":{"color":{"background":"#ffffff","text":"#1a1a1a"}},"layout":{"type":"default"}} -->
<div class="wp-block-group section section--light" style="background-color:#ffffff;color:#1a1a1a">
	<!-- wp:group {"className":"section__inner","layout":{"type":"default"}} -->
	<div class="wp-block-group section__inner">
		<!-- wp:group {"className":"split","layout":{"type":"default"}} -->
		<div class="wp-block-group split">
			<!-- wp:group {"className":"split__left","layout":{"type":"default"}} -->
			<div class="wp-block-group split__left">
				<!-- wp:paragraph {"className":"section__label reveal","style":{"color":{"text":"#888888"}}} -->
				<p class="section__label reveal" style="color:#888888">About</p>
				<!-- /wp:paragraph -->

				<!-- wp:heading {"className":"section__heading reveal","style":{"color":{"text":"#1a1a1a"}}} -->
				<h2 class="wp-block-heading section__heading reveal" style="color:#1a1a1a">Built for communities that value clarity.</h2>
				<!-- /wp:heading -->
			</div>
			<!-- /wp:group -->

			<!-- wp:group {"className":"split__right","layout":{"type":"default"}} -->
			<div class="wp-block-group split__right">
				<!-- wp:paragraph {"className":"section__text reveal","style":{"color":{"text":"#666666"}}} -->
				<p class="section__text reveal" style="color:#666666">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vivamus lacinia odio vitae vestibulum vestibulum. Cras vehicula, mi eget laoreet venenatis, justo arcu scelerisque mauris, a facilisis nisi tellus vel nulla.</p>
				<!-- /wp:paragraph -->

				<!-- wp:paragraph {"className":"section__text reveal","style":{"color":{"text":"#666666"}}} -->
				<p class="section__text reveal" style="color:#666666">Proin gravida nibh vel velit auctor aliquet. Aenean sollicitudin, lorem quis bibendum auctor, nisi elit consequat ipsum, nec sagittis sem nibh id elit. Duis sed odio sit amet nibh vulputate cursus a sit amet mauris.</p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
