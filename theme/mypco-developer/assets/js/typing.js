/**
 * Typing animation for the hero headline.
 *
 * Reads the headline from the [data-headline] attribute on #typed-output,
 * types it out character by character, then stops with the cursor blinking.
 * After a short pause the cursor fades out.
 *
 * @package MyPCO_Developer
 */
( function () {
	'use strict';

	const TYPING_SPEED = 80; // ms per character

	const container = document.getElementById( 'typed-output' );
	if ( ! container ) return;

	const textEl = container.querySelector( '.hero__typed-text' );
	if ( ! textEl ) return;

	const headline = container.getAttribute( 'data-headline' );
	if ( ! headline ) return;

	let charIndex = 0;

	function tick() {
		charIndex++;
		textEl.textContent = headline.substring( 0, charIndex );

		if ( charIndex === headline.length ) {
			// Typing complete — fade cursor out after a pause
			var cursor = container.querySelector( '.hero__cursor' );
			if ( cursor ) {
				setTimeout( function () {
					cursor.classList.add( 'hero__cursor--hidden' );
				}, 1500 );
			}
			return;
		}

		setTimeout( tick, TYPING_SPEED );
	}

	// Start after a short initial delay
	setTimeout( tick, 600 );
} )();
