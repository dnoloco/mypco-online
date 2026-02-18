/**
 * Rotating hero headlines with typing animation.
 *
 * Reads slide data from the [data-slides] attribute on #hero-rotator.
 * Each slide contains a headline, a set of typing words, and gradient colours.
 * The script types through all words for a slide, then fades to the next
 * slide's headline and repeats in an infinite loop.
 *
 * @package MyPCO_Developer
 */
( function () {
	'use strict';

	/* ------------------------------------------------------------------
	   Configuration
	   ------------------------------------------------------------------ */
	const TYPING_SPEED   = 80;   // ms per character typed
	const DELETING_SPEED = 50;   // ms per character deleted
	const PAUSE_AFTER    = 2000; // ms to hold the completed word
	const PAUSE_BEFORE   = 400;  // ms before typing next word
	const FADE_DURATION  = 500;  // ms for headline fade transition

	/* ------------------------------------------------------------------
	   DOM references
	   ------------------------------------------------------------------ */
	const rotator    = document.getElementById( 'hero-rotator' );
	if ( ! rotator ) return;

	const headlineEl = document.getElementById( 'hero-headline' );
	const typedEl    = document.getElementById( 'hero-typed-text' );
	if ( ! headlineEl || ! typedEl ) return;

	/* ------------------------------------------------------------------
	   Parse slides data
	   ------------------------------------------------------------------ */
	let slides;
	try {
		slides = JSON.parse( rotator.getAttribute( 'data-slides' ) );
	} catch ( e ) {
		return;
	}

	if ( ! Array.isArray( slides ) || slides.length === 0 ) return;

	/* ------------------------------------------------------------------
	   State
	   ------------------------------------------------------------------ */
	let slideIndex = 0;
	let wordIndex  = 0;
	let charIndex  = 0;
	let isDeleting = false;

	/* ------------------------------------------------------------------
	   Helpers
	   ------------------------------------------------------------------ */

	/**
	 * Apply a slide's headline text and gradient colours.
	 */
	function applySlide( index ) {
		const slide = slides[ index ];
		headlineEl.textContent = slide.headline;

		const gradient = 'linear-gradient(90deg, ' + slide.colorStart + ', ' + slide.colorEnd + ')';
		rotator.style.setProperty( '--hero-gradient', gradient );
		rotator.style.setProperty( '--hero-color-end', slide.colorEnd );
	}

	/**
	 * Fade the headline out, swap content, then fade back in.
	 * Returns a Promise that resolves when the transition is complete.
	 */
	function transitionHeadline( nextIndex ) {
		return new Promise( function ( resolve ) {
			// Fade out
			headlineEl.classList.add( 'hero__static--fading' );

			setTimeout( function () {
				applySlide( nextIndex );

				// Swap class to trigger fade-in
				headlineEl.classList.remove( 'hero__static--fading' );
				headlineEl.classList.add( 'hero__static--entering' );

				// Allow a frame for the class to take effect, then remove it
				requestAnimationFrame( function () {
					requestAnimationFrame( function () {
						headlineEl.classList.remove( 'hero__static--entering' );
						setTimeout( resolve, FADE_DURATION );
					} );
				} );
			}, FADE_DURATION );
		} );
	}

	/**
	 * Move to the next slide with a fade transition.
	 */
	function advanceSlide() {
		slideIndex = ( slideIndex + 1 ) % slides.length;
		wordIndex  = 0;
		charIndex  = 0;

		transitionHeadline( slideIndex ).then( function () {
			setTimeout( tick, PAUSE_BEFORE );
		} );
	}

	/* ------------------------------------------------------------------
	   Main typing loop
	   ------------------------------------------------------------------ */
	function tick() {
		var currentSlide = slides[ slideIndex ];
		var currentWord  = currentSlide.words[ wordIndex ];

		if ( isDeleting ) {
			charIndex--;
			typedEl.textContent = currentWord.substring( 0, charIndex );

			if ( charIndex === 0 ) {
				isDeleting = false;
				wordIndex++;

				// All words in this slide are done — move to next slide
				if ( wordIndex >= currentSlide.words.length ) {
					if ( slides.length > 1 ) {
						advanceSlide();
					} else {
						// Single slide: loop the words
						wordIndex = 0;
						setTimeout( tick, PAUSE_BEFORE );
					}
					return;
				}

				setTimeout( tick, PAUSE_BEFORE );
				return;
			}

			setTimeout( tick, DELETING_SPEED );
		} else {
			charIndex++;
			typedEl.textContent = currentWord.substring( 0, charIndex );

			if ( charIndex === currentWord.length ) {
				isDeleting = true;
				setTimeout( tick, PAUSE_AFTER );
				return;
			}

			setTimeout( tick, TYPING_SPEED );
		}
	}

	/* ------------------------------------------------------------------
	   Initialise
	   ------------------------------------------------------------------ */
	applySlide( 0 );
	setTimeout( tick, 800 );
} )();
