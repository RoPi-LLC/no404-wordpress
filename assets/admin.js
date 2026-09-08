/**
 * no404 — ayar ekranı bağlantı testi.
 *
 * Bağımlılık yok; WordPress'in jQuery sürümünden bağımsız çalışır.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var button = document.getElementById( 'no404-test-button' );
		var output = document.getElementById( 'no404-test-result' );
		var input = document.getElementById( 'no404-test-path' );

		if ( ! button || ! output || 'undefined' === typeof no404Admin ) {
			return;
		}

		function show( type, message ) {
			output.textContent = ''; // Önceki sonucu temizle (HTML ayrıştırma yok).

			var notice = document.createElement( 'div' );
			notice.className = 'notice notice-' + type + ' inline';

			var paragraph = document.createElement( 'p' );
			// textContent: sunucudan gelen mesaj asla HTML olarak yorumlanmaz.
			paragraph.textContent = message;

			notice.appendChild( paragraph );
			output.appendChild( notice );
		}

		button.addEventListener( 'click', function () {
			button.disabled = true;
			show( 'info', no404Admin.testing );

			var body = new URLSearchParams();
			body.append( 'action', no404Admin.action );
			body.append( 'nonce', no404Admin.nonce );
			body.append( 'path', input ? input.value : '' );

			fetch( no404Admin.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: body.toString()
			} )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( payload ) {
					if ( ! payload || ! payload.data || ! payload.data.message ) {
						show( 'error', no404Admin.failed );
						return;
					}

					show( payload.data.ok ? 'success' : 'error', payload.data.message );
				} )
				.catch( function () {
					show( 'error', no404Admin.failed );
				} )
				.then( function () {
					button.disabled = false;
				} );
		} );
	} );
}() );
