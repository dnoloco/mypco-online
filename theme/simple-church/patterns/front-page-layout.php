<?php
/**
 * Title: Front Page Layout
 * Slug: simple-church/front-page-layout
 * Description: The complete default front page — statement, capabilities, parallax quote, about, features grid, and call-to-action. Insert this on the page set as your static front page.
 * Categories: simple-church
 * Keywords: front, page, home, default, layout, full
 */
?>
<!-- Section: Statement (dark, full-height) -->
<!-- wp:group {"className":"section section--dark section--full","layout":{"type":"default"}} -->
<div class="wp-block-group section section--dark section--full">
	<!-- wp:group {"className":"section__inner","layout":{"type":"default"}} -->
	<div class="wp-block-group section__inner">
		<!-- wp:group {"className":"reveal-group","layout":{"type":"default"}} -->
		<div class="wp-block-group reveal-group">
			<!-- wp:heading {"className":"section__heading reveal"} -->
			<h2 class="wp-block-heading section__heading reveal">An approach built on simplicity.</h2>
			<!-- /wp:heading -->
			<!-- wp:paragraph {"className":"section__text reveal"} -->
			<p class="section__text reveal">We believe in stripping away the unnecessary to reveal what matters most. Every pixel serves a purpose. Every interaction is intentional.</p>
			<!-- /wp:paragraph -->
			<!-- wp:paragraph {"className":"section__text reveal"} -->
			<p class="section__text reveal">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris.</p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->

<!-- Section: Capabilities grid (light) -->
<!-- wp:group {"className":"section section--light","layout":{"type":"default"}} -->
<div class="wp-block-group section section--light">
	<!-- wp:group {"className":"section__inner","layout":{"type":"default"}} -->
	<div class="wp-block-group section__inner">
		<!-- wp:group {"className":"reveal-group","layout":{"type":"default"}} -->
		<div class="wp-block-group reveal-group">
			<!-- wp:paragraph {"className":"section__label reveal"} -->
			<p class="section__label reveal">What we do</p>
			<!-- /wp:paragraph -->
			<!-- wp:heading {"className":"section__heading reveal"} -->
			<h2 class="wp-block-heading section__heading reveal">Capabilities</h2>
			<!-- /wp:heading -->
		</div>
		<!-- /wp:group -->
		<!-- wp:html -->
		<div class="card-grid">
			<div class="card reveal">
				<span class="card__number">01</span>
				<h3 class="card__title">Worship</h3>
				<p class="card__text">Gather together for meaningful worship experiences that inspire and encourage your congregation.</p>
			</div>
			<div class="card reveal">
				<span class="card__number">02</span>
				<h3 class="card__title">Community</h3>
				<p class="card__text">Build authentic relationships through small groups, outreach programmes, and fellowship opportunities.</p>
			</div>
			<div class="card reveal">
				<span class="card__number">03</span>
				<h3 class="card__title">Discipleship</h3>
				<p class="card__text">Equip and empower people to grow in their faith through teaching, mentoring, and resources.</p>
			</div>
			<div class="card reveal">
				<span class="card__number">04</span>
				<h3 class="card__title">Outreach</h3>
				<p class="card__text">Serve your local community and beyond with compassion, generosity, and the love of Christ.</p>
			</div>
		</div>
		<!-- /wp:html -->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->

<!-- Section: Parallax image break -->
<!-- wp:html -->
<section class="parallax-break">
	<div class="parallax-break__overlay"></div>
	<div class="parallax-break__content reveal">
		<blockquote class="parallax-break__quote">
			&ldquo;Simplicity is the ultimate sophistication.&rdquo;
		</blockquote>
	</div>
</section>
<!-- /wp:html -->

<!-- Section: About / story (split layout, light) -->
<!-- wp:group {"className":"section section--light","layout":{"type":"default"}} -->
<div class="wp-block-group section section--light">
	<!-- wp:group {"className":"section__inner","layout":{"type":"default"}} -->
	<div class="wp-block-group section__inner">
		<!-- wp:group {"className":"split","layout":{"type":"default"}} -->
		<div class="wp-block-group split">
			<!-- wp:group {"className":"split__left","layout":{"type":"default"}} -->
			<div class="wp-block-group split__left">
				<!-- wp:paragraph {"className":"section__label reveal"} -->
				<p class="section__label reveal">About</p>
				<!-- /wp:paragraph -->
				<!-- wp:heading {"className":"section__heading reveal"} -->
				<h2 class="wp-block-heading section__heading reveal">Built for communities that value clarity.</h2>
				<!-- /wp:heading -->
			</div>
			<!-- /wp:group -->
			<!-- wp:group {"className":"split__right","layout":{"type":"default"}} -->
			<div class="wp-block-group split__right">
				<!-- wp:paragraph {"className":"section__text reveal"} -->
				<p class="section__text reveal">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vivamus lacinia odio vitae vestibulum vestibulum. Cras vehicula, mi eget laoreet venenatis, justo arcu scelerisque mauris, a facilisis nisi tellus vel nulla.</p>
				<!-- /wp:paragraph -->
				<!-- wp:paragraph {"className":"section__text reveal"} -->
				<p class="section__text reveal">Proin gravida nibh vel velit auctor aliquet. Aenean sollicitudin, lorem quis bibendum auctor, nisi elit consequat ipsum, nec sagittis sem nibh id elit. Duis sed odio sit amet nibh vulputate cursus a sit amet mauris.</p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->

<!-- Section: Features grid (dark, PCO-aware via shortcode) -->
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

<!-- Section: Call to action -->
<!-- wp:group {"className":"section section--cta","layout":{"type":"default"}} -->
<div class="wp-block-group section section--cta">
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
