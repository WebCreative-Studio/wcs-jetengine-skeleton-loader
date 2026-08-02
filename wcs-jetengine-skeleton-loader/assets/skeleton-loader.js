( function () {
	'use strict';

	function isLoading( grid ) {
		return grid.classList.contains( 'jet-listing-grid--lazy-load' ) && ! grid.classList.contains( 'jet-listing-grid--lazy-load-completed' );
	}

	function isFiltering( wrapper ) {
		return wrapper.dataset.wcsSkeletonFilters === 'yes' && !! document.querySelector( '.jet-filters-loading' );
	}

	function breakpoint() {
		if ( matchMedia( '(max-width: 767px)' ).matches ) {
			return 'mobile';
		}

		if ( matchMedia( '(max-width: 1024px)' ).matches ) {
			return 'tablet';
		}

		return 'desktop';
	}

	function configuredColumns( wrapper ) {
		var current = breakpoint();
		var value = current === 'mobile' ? wrapper.dataset.wcsSkeletonColumnsMobile : ( current === 'tablet' ? wrapper.dataset.wcsSkeletonColumnsTablet : wrapper.dataset.wcsSkeletonColumnsDesktop );
		return Math.min( 12, parseInt( value, 10 ) || ( current === 'mobile' ? 1 : ( current === 'tablet' ? 2 : 3 ) ) );
	}

	function columns( wrapper, grid ) {
		var items = grid.querySelector( '.jet-listing-grid__items' );
		var style = items && getComputedStyle( items );
		var tracks = style ? style.gridTemplateColumns.trim() : '';
		var columnsFromCss = tracks && tracks !== 'none' ? tracks.split( /\s+/ ).filter( Boolean ) : [];

		if ( columnsFromCss.length ) {
			return Math.min( 12, columnsFromCss.length );
		}

		var cssVariable = style ? parseInt( style.getPropertyValue( '--columns' ), 10 ) : 0;
		return cssVariable > 0 ? Math.min( 12, cssVariable ) : configuredColumns( wrapper );
	}

	function scroll( wrapper, count ) {
		if ( breakpoint() === 'mobile' && wrapper.dataset.wcsSkeletonScrollMobile === 'yes' ) {
			return { width: wrapper.dataset.wcsSkeletonScrollWidthMobile || '75%', gap: wrapper.dataset.wcsSkeletonScrollGapMobile || '5px', cards: 3 };
		}

		if ( breakpoint() === 'tablet' && wrapper.dataset.wcsSkeletonScrollTablet === 'yes' ) {
			return { width: wrapper.dataset.wcsSkeletonScrollWidthTablet || '40%', gap: wrapper.dataset.wcsSkeletonScrollGapTablet || '16px', cards: 3 };
		}

		return wrapper.dataset.wcsSkeletonCarousel === 'yes' ? { width: 100 / count + '%', gap: '0px', cards: count } : null;
	}

	function storageKey( wrapper ) {
		var id = Array.prototype.find.call( wrapper.classList, function ( className ) {
			return className.indexOf( 'elementor-element-' ) === 0;
		} ) || 'listing';

		return 'wcs-skeleton-size:' + location.pathname + ':' + id + ':' + breakpoint();
	}

	function savedRatio( wrapper ) {
		try {
			var saved = JSON.parse( sessionStorage.getItem( storageKey( wrapper ) ) || 'null' );
			return saved && Number.isFinite( saved.ratio ) && saved.ratio > 0 ? saved.ratio : 0;
		} catch ( error ) {
			return 0;
		}
	}

	function rememberCardSize( wrapper, grid ) {
		var items = grid.querySelector( '.jet-listing-grid__items' );
		if ( ! items ) {
			return;
		}

		var cards = Array.prototype.filter.call( items.querySelectorAll( '.jet-listing-grid__item' ), function ( card ) {
			var rect = card.getBoundingClientRect();
			return rect.width > 0 && rect.height > 0;
		} );

		if ( ! cards.length ) {
			return;
		}

		var widest = 0;
		var tallest = 0;
		cards.forEach( function ( card ) {
			var rect = card.getBoundingClientRect();
			widest = Math.max( widest, rect.width );
			tallest = Math.max( tallest, rect.height );
		} );

		if ( widest < 1 || tallest < 1 ) {
			return;
		}

		try {
			sessionStorage.setItem( storageKey( wrapper ), JSON.stringify( { ratio: tallest / widest } ) );
		} catch ( error ) {
			// Private mode or a blocked storage policy only disables this enhancement.
		}
	}

	function applySavedSize( wrapper, skeleton ) {
		var ratio = savedRatio( wrapper );
		if ( ! ratio ) {
			return;
		}

		requestAnimationFrame( function () {
			var card = skeleton.querySelector( '.wcs-jetengine-skeleton__card' );
			if ( ! card || ! skeleton.isConnected ) {
				return;
			}

			var height = Math.round( card.getBoundingClientRect().width * ratio );
			if ( height >= 120 && height <= 1600 ) {
				skeleton.style.setProperty( '--wcs-skeleton-card-height', height + 'px' );
			}
		} );
	}

	function createSkeleton( wrapper, grid ) {
		var count = columns( wrapper, grid );
		var cardCount = Math.min( 12, count * 2 );
		var scrollSettings = scroll( wrapper, count );
		var skeleton = document.createElement( 'div' );

		skeleton.className = 'wcs-jetengine-skeleton';
		skeleton.setAttribute( 'aria-hidden', 'true' );
		skeleton.style.gridTemplateColumns = 'repeat(' + count + ', minmax(0, 1fr))';

		if ( scrollSettings ) {
			skeleton.classList.add( 'wcs-jetengine-skeleton--scroll' );
			skeleton.style.removeProperty( 'grid-template-columns' );
			skeleton.style.setProperty( '--wcs-skeleton-scroll-width', scrollSettings.width );
			skeleton.style.setProperty( '--wcs-skeleton-scroll-gap', scrollSettings.gap );
			cardCount = scrollSettings.cards;
		}

		for ( var index = 0; index < cardCount; index++ ) {
			var card = document.createElement( 'div' );
			card.className = 'wcs-jetengine-skeleton__card';
			card.innerHTML = '<span class="wcs-jetengine-skeleton__media"></span><span class="wcs-jetengine-skeleton__line"></span><span class="wcs-jetengine-skeleton__line wcs-jetengine-skeleton__line--short"></span><span class="wcs-jetengine-skeleton__pills"></span>';
			skeleton.appendChild( card );
		}

		grid.appendChild( skeleton );
		applySavedSize( wrapper, skeleton );
	}

	function sync( wrapper ) {
		var grid = wrapper.querySelector( '.jet-listing-grid' );
		if ( ! grid ) {
			return;
		}

		var active = isLoading( grid ) || isFiltering( wrapper );
		var existing = grid.querySelector( '.wcs-jetengine-skeleton' );

		if ( ! active ) {
			grid.removeAttribute( 'aria-busy' );
			if ( existing ) {
				existing.remove();
			}
			rememberCardSize( wrapper, grid );
			return;
		}

		grid.setAttribute( 'aria-busy', 'true' );
		if ( ! existing ) {
			createSkeleton( wrapper, grid );
		}
	}

	function init() {
		var wrappers = document.querySelectorAll( '.wcs-jetengine-skeleton-enabled' );
		wrappers.forEach( function ( wrapper ) {
			var grid = wrapper.querySelector( '.jet-listing-grid' );
			if ( ! grid ) {
				return;
			}

			sync( wrapper );
			new MutationObserver( function () {
				sync( wrapper );
			} ).observe( grid, { attributes: true, attributeFilter: [ 'class' ] } );
		} );

		new MutationObserver( function () {
			wrappers.forEach( sync );
		} ).observe( document.documentElement, { attributes: true, subtree: true, attributeFilter: [ 'class' ] } );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init, { once: true } );
	} else {
		init();
	}
}() );
