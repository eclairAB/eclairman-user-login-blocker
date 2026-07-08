( function () {
	'use strict';

	var table = document.querySelector( '.dnal-table' );
	if ( ! table ) {
		return;
	}

	var tbody = table.tBodies[ 0 ];
	var headers = table.querySelectorAll( 'th.dnal-sort' );

	headers.forEach( function ( th ) {
		th.addEventListener( 'click', function () {
			var idx = Array.prototype.indexOf.call( th.parentNode.children, th );
			var type = th.getAttribute( 'data-type' );
			var asc = th.getAttribute( 'data-dir' ) !== 'asc';

			headers.forEach( function ( other ) {
				if ( other !== th ) {
					other.removeAttribute( 'data-dir' );
					other.querySelector( '.dnal-arrow' ).textContent = '';
				}
			} );

			th.setAttribute( 'data-dir', asc ? 'asc' : 'desc' );
			th.querySelector( '.dnal-arrow' ).textContent = asc ? '▲' : '▼';

			var rows = Array.prototype.slice.call( tbody.querySelectorAll( 'tr' ) );

			rows.sort( function ( a, b ) {
				var av = a.children[ idx ] ? a.children[ idx ].textContent.trim() : '';
				var bv = b.children[ idx ] ? b.children[ idx ].textContent.trim() : '';
				var cmp;

				if ( type === 'num' ) {
					cmp = ( parseFloat( av ) || 0 ) - ( parseFloat( bv ) || 0 );
				} else {
					cmp = av.localeCompare( bv, undefined, { sensitivity: 'base', numeric: true } );
				}

				return asc ? cmp : -cmp;
			} );

			rows.forEach( function ( row ) {
				tbody.appendChild( row );
			} );
		} );
	} );
} )();
