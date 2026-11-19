( function () {
	'use strict';

	function initPanes( root ) {
		var tabs = root.querySelectorAll( '[data-agentwelcome-pane-tab]' );
		var panes = root.querySelectorAll( '[data-agentwelcome-pane]' );

		function show( name ) {
			panes.forEach( function ( pane ) {
				pane.hidden = pane.getAttribute( 'data-agentwelcome-pane' ) !== name;
			} );

			tabs.forEach( function ( tab ) {
				var on = tab.getAttribute( 'data-agentwelcome-pane-tab' ) === name;

				tab.classList.toggle( 'button-primary', on );
				tab.setAttribute( 'aria-selected', on ? 'true' : 'false' );
			} );
		}

		tabs.forEach( function ( tab ) {
			tab.addEventListener( 'click', function () {
				show( tab.getAttribute( 'data-agentwelcome-pane-tab' ) );
			} );
		} );

		show( 'preview' );
	}

	function initScan( root ) {
		var path = root.getAttribute( 'data-path' );

		if ( root.getAttribute( 'data-running' ) !== '1' || ! window.wp || ! window.wp.apiFetch ) {
			return;
		}

		function count( name, value ) {
			var node = root.querySelector( '[data-agentwelcome-count="' + name + '"]' );

			if ( node ) {
				node.textContent = node.textContent.replace( /[\d., ]+/, String( value ) );
			}
		}

		var busy = false;
		var timer = window.setInterval( function () {
			if ( busy ) {
				return;
			}

			busy = true;
			window.wp.apiFetch( { path: path } ).then( function ( status ) {
				busy = false;
				count( 'checked', status.checked );
				count( 'in_sync', status.in_sync );
				count( 'differs', status.differs );

				if ( ! status.running ) {
					window.clearInterval( timer );
					window.location.reload();
				}
			} ).catch( function () {
				busy = false;
			} );
		}, 5000 );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var panes = document.querySelector( '[data-agentwelcome-panes]' );
		var scan = document.querySelector( '[data-agentwelcome-scan]' );

		if ( panes ) {
			initPanes( panes );
		}

		if ( scan ) {
			initScan( scan );
		}
	} );
}() );
