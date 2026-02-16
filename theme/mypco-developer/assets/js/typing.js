/**
 * Typing animation for the hero section.
 *
 * Reads words from the [data-words] attribute on #typed-output,
 * types them out character by character, pauses, then deletes and moves
 * to the next word in an infinite loop.
 *
 * @package MyPCO_Developer
 */
( function () {
	'use strict';

	const TYPING_SPEED   = 80;   // ms per character typed
	const DELETING_SPEED = 50;   // ms per character deleted
	const PAUSE_AFTER    = 2000; // ms to hold the completed word
	const PAUSE_BEFORE   = 400;  // ms before typing next word

	const container = document.getElementById( 'typed-output' );
	if ( ! container ) return;

	const textEl = container.querySelector( '.hero__typed-text' );
	if ( ! textEl ) return;

	let words;
	try {
		words = JSON.parse( container.getAttribute( 'data-words' ) );
	} catch ( e ) {
		return;
	}

	if ( ! Array.isArray( words ) || words.length === 0 ) return;

	let wordIndex = 0;
	let charIndex = 0;
	let isDeleting = false;

	function tick() {
		const currentWord = words[ wordIndex ];

		if ( isDeleting ) {
			charIndex--;
			textEl.textContent = currentWord.substring( 0, charIndex );

			if ( charIndex === 0 ) {
				isDeleting = false;
				wordIndex = ( wordIndex + 1 ) % words.length;
				setTimeout( tick, PAUSE_BEFORE );
				return;
			}

			setTimeout( tick, DELETING_SPEED );
		} else {
			charIndex++;
			textEl.textContent = currentWord.substring( 0, charIndex );

			if ( charIndex === currentWord.length ) {
				isDeleting = true;
				setTimeout( tick, PAUSE_AFTER );
				return;
			}

			setTimeout( tick, TYPING_SPEED );
		}
	}

	// Start after a short initial delay
	setTimeout( tick, 800 );
} )();
