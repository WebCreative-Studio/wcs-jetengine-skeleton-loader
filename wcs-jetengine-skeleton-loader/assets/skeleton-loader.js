( function () {
	'use strict';

	function isLoading( grid ) {
		return grid.classList.contains( 'jet-listing-grid--lazy-load' ) && ! grid.classList.contains( 'jet-listing-grid--lazy-load-completed' );
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

	function savedMeasurement( wrapper ) {
		try {
			return JSON.parse( sessionStorage.getItem( storageKey( wrapper ) ) || 'null' );
		} catch ( error ) {
			return null;
		}
	}

	function validRect( rect ) {
		return rect && [ 'left', 'top', 'width', 'height' ].every( function ( key ) {
			return Number.isFinite( rect[ key ] ) && rect[ key ] >= 0 && rect[ key ] <= 1.5;
		} ) && rect.width > 0 && rect.height > 0;
	}

	function savedProfile( measurement ) {
		if ( ! measurement || ! measurement.profile || ! validRect( measurement.profile.media ) || ! Array.isArray( measurement.profile.lines ) ) {
			return null;
		}

		var lines = measurement.profile.lines.filter( validRect ).slice( 0, 4 );
		return { media: measurement.profile.media, lines: lines };
	}

	function relativeRect( rect, parentRect ) {
		return {
			left: ( rect.left - parentRect.left ) / parentRect.width,
			top: ( rect.top - parentRect.top ) / parentRect.height,
			width: rect.width / parentRect.width,
			height: rect.height / parentRect.height
		};
	}

	function visibleRect( element ) {
		var rect = element.getBoundingClientRect();
		return rect.width > 8 && rect.height > 8 ? rect : null;
	}

	function cardProfile( card ) {
		var cardRect = visibleRect( card );
		if ( ! cardRect ) {
			return null;
		}

		var mediaElement = null;
		var mediaRect = null;
		Array.prototype.forEach.call( card.querySelectorAll( 'img, video, [style*="background"]' ), function ( element ) {
			var rect = visibleRect( element );
			if ( ! rect || ( mediaRect && rect.width * rect.height <= mediaRect.width * mediaRect.height ) ) {
				return;
			}

			mediaElement = element;
			mediaRect = rect;
		} );

		if ( ! mediaElement || ! mediaRect || mediaRect.width * mediaRect.height < cardRect.width * cardRect.height * 0.1 ) {
			return null;
		}

		var textBlocks = [];
		Array.prototype.forEach.call( card.querySelectorAll( 'h1, h2, h3, h4, h5, h6, p, a, span, div' ), function ( element ) {
			var text = element.textContent.trim();
			var rect = visibleRect( element );
			var childHasText = Array.prototype.some.call( element.children, function ( child ) {
				return child.textContent.trim().length > 0;
			} );

			if ( ! text || text.length > 160 || ! rect || childHasText || mediaElement.contains( element ) || rect.top < mediaRect.bottom - 4 || rect.height > 80 ) {
				return;
			}

			var relative = relativeRect( rect, cardRect );
			if ( relative.width < 0.06 || relative.height < 0.01 ) {
				return;
			}

			var duplicate = textBlocks.some( function ( block ) {
				return Math.abs( block.left - relative.left ) < 0.02 && Math.abs( block.top - relative.top ) < 0.02 && Math.abs( block.width - relative.width ) < 0.02;
			} );
			if ( ! duplicate ) {
				textBlocks.push( relative );
			}
		} );

		textBlocks.sort( function ( first, second ) {
			return first.top === second.top ? first.left - second.left : first.top - second.top;
		} );

		return {
			media: relativeRect( mediaRect, cardRect ),
			lines: textBlocks.slice( 0, 4 )
		};
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

		var profile = cardProfile( cards[ 0 ] );
		try {
			sessionStorage.setItem( storageKey( wrapper ), JSON.stringify( { ratio: tallest / widest, profile: profile } ) );
		} catch ( error ) {
			// Private mode or a blocked storage policy only disables this enhancement.
		}
	}

	function applySavedSize( wrapper, skeleton ) {
		var measurement = savedMeasurement( wrapper );
		var ratio = measurement && Number.isFinite( measurement.ratio ) && measurement.ratio > 0 ? measurement.ratio : 0;
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

	function profileCard( profile ) {
		var card = document.createElement( 'div' );
		card.className = 'wcs-jetengine-skeleton__card wcs-jetengine-skeleton__card--profiled';

		var media = document.createElement( 'span' );
		media.className = 'wcs-jetengine-skeleton__media';
		media.style.cssText = 'left:' + profile.media.left * 100 + '%;top:' + profile.media.top * 100 + '%;width:' + profile.media.width * 100 + '%;height:' + profile.media.height * 100 + '%;';
		card.appendChild( media );

		profile.lines.forEach( function ( block ) {
			var line = document.createElement( 'span' );
			line.className = 'wcs-jetengine-skeleton__line';
			line.style.cssText = 'left:' + block.left * 100 + '%;top:' + block.top * 100 + '%;width:' + block.width * 100 + '%;height:' + block.height * 100 + '%;margin:0;';
			card.appendChild( line );
		} );

		return card;
	}

	function createSkeleton( wrapper, grid ) {
		var count = columns( wrapper, grid );
		var cardCount = Math.min( 12, count * 2 );
		var scrollSettings = scroll( wrapper, count );
		var measurement = savedMeasurement( wrapper );
		var profile = savedProfile( measurement );
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
			var card = profile ? profileCard( profile ) : document.createElement( 'div' );
			if ( ! profile ) {
				card.className = 'wcs-jetengine-skeleton__card';
				card.innerHTML = '<span class="wcs-jetengine-skeleton__media"></span><span class="wcs-jetengine-skeleton__line"></span><span class="wcs-jetengine-skeleton__line wcs-jetengine-skeleton__line--short"></span><span class="wcs-jetengine-skeleton__pills"></span>';
			}
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

		var active = isLoading( grid );
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
