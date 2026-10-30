( function () {
	'use strict';

	var config = window.mediaWebpCompressor;
	var button = document.getElementById( 'geniusbrain-media-webp-compressor-start' );
	if ( ! config || ! button ) {
		return;
	}
	var status = document.getElementById( 'geniusbrain-media-webp-compressor-status' );
	var bar = document.querySelector( '.geniusbrain-media-webp-compressor-bar' );
	var done = document.getElementById( 'geniusbrain-media-webp-compressor-done' );
	var total = document.getElementById( 'geniusbrain-media-webp-compressor-total' );
	var saved = document.getElementById( 'geniusbrain-media-webp-compressor-saved' );

	function render( data ) {
		done.textContent = data.doneText;
		total.textContent = data.totalText;
		saved.textContent = data.saved;
		bar.style.width = ( data.total ? Math.round( ( 100 * data.done ) / data.total ) : 0 ) + '%';
	}

	function finish( message ) {
		status.textContent = message;
		window.onbeforeunload = null;
	}

	function next() {
		var body = new FormData();
		body.append( 'action', config.action );
		body.append( 'nonce', config.nonce );

		fetch( config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( json ) {
				if ( ! json || ! json.success ) {
					throw new Error( 'bulk failed' );
				}
				render( json.data );
				if ( json.data.pending > 0 ) {
					next();
				} else {
					finish( config.i18n.done );
				}
			} )
			.catch( function () {
				button.disabled = false;
				finish( config.i18n.error );
			} );
	}

	button.addEventListener( 'click', function () {
		button.disabled = true;
		status.textContent = config.i18n.running;
		window.onbeforeunload = function () {
			return true;
		};
		next();
	} );
} )();
