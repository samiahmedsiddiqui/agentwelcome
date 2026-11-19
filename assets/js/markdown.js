( function () {
	'use strict';

	var SAFE_LINK = /^(https?:\/\/|\/|#|mailto:)/i;
	var INLINE = /(`+)([\s\S]+?)\1|\*\*([\s\S]+?)\*\*|\*([^*\s][^*]*?)\*|!\[([^\]]*)\]\(([^)\s]*)[^)]*\)|\[([^\]]*)\]\(([^)\s]*)[^)]*\)|\\([\\`*_{}\[\]()#+\-.!|>~])/g;
	var FENCE = /^\s*(`{3,}|~{3,})\s*([\w+-]*)\s*$/;
	var RULE = /^\s*([-*_])(\s*\1){2,}\s*$/;
	var ITEM = /^(\s*)([-*+]|\d+[.)])\s+(.*)$/;
	var DIVIDER = /^\s*\|?\s*:?-{3,}:?\s*(\|\s*:?-{3,}:?\s*)*\|?\s*$/;

	function el( tag, parent, text ) {
		var node = document.createElement( tag );

		if ( text ) {
			node.textContent = text;
		}

		if ( parent ) {
			parent.appendChild( node );
		}

		return node;
	}

	function inline( parent, text ) {
		var pattern = new RegExp( INLINE.source, 'g' );
		var match;
		var last = 0;
		var node;

		while ( ( match = pattern.exec( text ) ) !== null ) {
			if ( match.index > last ) {
				parent.appendChild( document.createTextNode( text.slice( last, match.index ) ) );
			}

			if ( match[ 1 ] ) {
				el( 'code', parent, match[ 2 ] );
			} else if ( match[ 3 ] !== undefined ) {
				inline( el( 'strong', parent ), match[ 3 ] );
			} else if ( match[ 4 ] !== undefined ) {
				inline( el( 'em', parent ), match[ 4 ] );
			} else if ( match[ 6 ] !== undefined ) {
				node = el( 'span', parent, '[image: ' + match[ 5 ] + ']' );
				node.className = 'agentwelcome-img';
			} else if ( match[ 8 ] !== undefined ) {
				node = el( 'a', parent );
				inline( node, match[ 7 ] );

				if ( SAFE_LINK.test( match[ 8 ] ) ) {
					node.setAttribute( 'href', match[ 8 ] );
					node.setAttribute( 'target', '_blank' );
					node.setAttribute( 'rel', 'noopener noreferrer' );
				}
			} else if ( match[ 9 ] ) {
				parent.appendChild( document.createTextNode( match[ 9 ] ) );
			}

			last = pattern.lastIndex;
		}

		if ( last < text.length ) {
			parent.appendChild( document.createTextNode( text.slice( last ) ) );
		}
	}

	function listItem( line ) {
		var match = ITEM.exec( line );

		return match ? { indent: match[ 1 ].replace( /\t/g, '    ' ).length, ordered: /\d/.test( match[ 2 ] ), text: match[ 3 ] } : null;
	}

	function blockStart( line ) {
		return FENCE.test( line ) || /^#{1,6}\s/.test( line ) || RULE.test( line ) || /^\s*>/.test( line ) || ITEM.test( line );
	}

	function cells( line ) {
		var text = line.trim().replace( /^\|/, '' ).replace( /\|$/, '' );
		var out = [];
		var current = '';
		var i;

		for ( i = 0; i < text.length; i++ ) {
			if ( text.charAt( i ) === '\\' && text.charAt( i + 1 ) === '|' ) {
				current += '|';
				i++;
			} else if ( text.charAt( i ) === '|' ) {
				out.push( current.trim() );
				current = '';
			} else {
				current += text.charAt( i );
			}
		}

		out.push( current.trim() );

		return out;
	}

	function list( lines, i, parent ) {
		var first = listItem( lines[ i ] );
		var root = el( first.ordered ? 'ol' : 'ul', parent );
		var current = null;
		var item;

		while ( i < lines.length ) {
			item = listItem( lines[ i ] );

			if ( item && item.indent === first.indent ) {
				current = el( 'li', root );
				inline( current, item.text );
				i++;
			} else if ( item && item.indent > first.indent && current ) {
				i = list( lines, i, current );
			} else if ( ! item && current && /^\s+\S/.test( lines[ i ] ) ) {
				current.appendChild( document.createTextNode( ' ' ) );
				inline( current, lines[ i ].trim() );
				i++;
			} else {
				break;
			}
		}

		return i;
	}

	function table( lines, i, parent ) {
		var root = el( 'table', parent );
		var head = el( 'tr', el( 'thead', root ) );
		var body = el( 'tbody', root );
		var row;

		cells( lines[ i ] ).forEach( function ( text ) {
			inline( el( 'th', head ), text );
		} );

		i += 2;

		while ( i < lines.length && lines[ i ].indexOf( '|' ) !== -1 && ! /^\s*$/.test( lines[ i ] ) ) {
			row = el( 'tr', body );
			cells( lines[ i ] ).forEach( function ( text ) {
				inline( el( 'td', row ), text );
			} );
			i++;
		}

		return i;
	}

	function blocks( lines, parent ) {
		var i = 0;
		var line;
		var match;
		var buffer;
		var inner;

		while ( i < lines.length ) {
			line = lines[ i ];

			if ( /^\s*$/.test( line ) ) {
				i++;
			} else if ( ( match = FENCE.exec( line ) ) !== null ) {
				buffer = [];
				i++;

				while ( i < lines.length && ! new RegExp( '^\\s*' + match[ 1 ].charAt( 0 ) + '{' + match[ 1 ].length + ',}\\s*$' ).test( lines[ i ] ) ) {
					buffer.push( lines[ i ] );
					i++;
				}

				i++;
				el( 'code', el( 'pre', parent ), buffer.join( '\n' ) );
			} else if ( ( match = /^(#{1,6})\s+(.*?)\s*#*\s*$/.exec( line ) ) !== null ) {
				inline( el( 'h' + match[ 1 ].length, parent ), match[ 2 ] );
				i++;
			} else if ( RULE.test( line ) ) {
				el( 'hr', parent );
				i++;
			} else if ( /^\s*>/.test( line ) ) {
				inner = [];

				while ( i < lines.length && /^\s*>/.test( lines[ i ] ) ) {
					inner.push( lines[ i ].replace( /^\s*>\s?/, '' ) );
					i++;
				}

				blocks( inner, el( 'blockquote', parent ) );
			} else if ( line.indexOf( '|' ) !== -1 && i + 1 < lines.length && DIVIDER.test( lines[ i + 1 ] ) ) {
				i = table( lines, i, parent );
			} else if ( ITEM.test( line ) ) {
				i = list( lines, i, parent );
			} else {
				buffer = [];

				while ( i < lines.length && ! /^\s*$/.test( lines[ i ] ) && ( buffer.length === 0 || ! blockStart( lines[ i ] ) ) ) {
					buffer.push( lines[ i ].trim() );
					i++;
				}

				inline( el( 'p', parent ), buffer.join( ' ' ) );
			}
		}
	}

	function metadataValue( raw ) {
		var value = raw.trim();

		if ( value.charAt( 0 ) === '"' ) {
			try {
				value = JSON.parse( value );
			} catch ( e ) {
				return raw.trim();
			}
		}

		return typeof value === 'string' ? value : raw.trim();
	}

	function metadata( lines, root, asList ) {
		var details = el( 'details', root );
		var pairs = [];
		var match;
		var list;
		var value;
		var node;
		var k;

		el( 'summary', details, 'Metadata' );

		if ( asList ) {
			for ( k = 0; k < lines.length; k++ ) {
				match = /^([A-Za-z_][\w-]*):\s+(.*)$/.exec( lines[ k ] );

				if ( match === null ) {
					pairs = null;
					break;
				}

				pairs.push( [ match[ 1 ], metadataValue( match[ 2 ] ) ] );
			}
		}

		if ( ! asList || pairs === null || pairs.length === 0 ) {
			el( 'pre', details, lines.join( '\n' ) );
			return;
		}

		details.setAttribute( 'open', '' );
		list = el( 'dl', details );
		list.className = 'agentwelcome-meta';

		pairs.forEach( function ( pair ) {
			el( 'dt', list, pair[ 0 ] );
			node = el( 'dd', list );
			value = pair[ 1 ];

			if ( /^https?:\/\//i.test( value ) ) {
				node = el( 'a', node, value );
				node.setAttribute( 'href', value );
				node.setAttribute( 'target', '_blank' );
				node.setAttribute( 'rel', 'noopener noreferrer' );
			} else {
				node.textContent = value;
			}
		} );
	}

	function render( markdown, root, options ) {
		var lines = markdown.replace( /\r\n?/g, '\n' ).split( '\n' );
		var end = -1;
		var k;

		root.textContent = '';

		if ( lines[ 0 ] === '---' ) {
			for ( k = 1; k < lines.length; k++ ) {
				if ( lines[ k ] === '---' ) {
					end = k;
					break;
				}
			}
		}

		if ( end > 0 ) {
			metadata( lines.slice( 1, end ), root, !! options && options.metadata === 'list' );
			lines = lines.slice( end + 1 );
		}

		blocks( lines, root );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var source = document.getElementById( 'agentwelcome-markdown' );
		var target = document.getElementById( 'agentwelcome-rendered' );

		if ( ! source || ! target ) {
			return;
		}

		render( typeof source.value === 'string' ? source.value : source.textContent, target, { metadata: target.getAttribute( 'data-agentwelcome-metadata' ) } );

		if ( source.hasAttribute( 'data-agentwelcome-hide-source' ) ) {
			source.hidden = true;
		}
	} );

	window.agentwelcomeRenderMarkdown = render;
}() );
