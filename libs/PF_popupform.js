/**
 * Javascript code to be used with extension PageForms for popup forms.
 *
 * @author Stephan Gambke
 */
/*global escape*/

// initialise
jQuery( () => {

	// register eventhandlers on 'edit' links and buttons

	// register formlink with link
	jQuery('a.popupformlink').click(function(evt){
		return ext.popupform.handlePopupFormLink( this.getAttribute('href'), this );
	});

	// register formlink with button
	jQuery( 'form.popupformlink' ).submit(function(evt){
		return ext.popupform.handlePopupFormLink( this.getAttribute( 'action' ), this );
	});

	// register forminput
	jQuery( 'form.popupforminput' ).submit(function(evt){
		return ext.popupform.handlePopupFormInput( this.getAttribute( 'action' ), this );
	});

} );

// create ext if it does not exist yet
if ( typeof( window.ext ) === "undefined" ) {
	window.ext = {};
}

window.ext.popupform = ( function() {
	let $wrapper;
	let $background;
	let container;
	let $innerContainer;
	let $iframe;
	let $content;
	let $waitIndicator;
	let instance = 0;

	let timer;
	let needsRender = true;

	let doc;

	let brokenBrowser, brokenChrome;

	const padding = 20;
	let reload;
	function fadeOut($elem, callback ) {
		// no fading for broken browsers
		if ( brokenBrowser ){
			$elem.hide();
			if ( callback ) {
				callback();
			}
		} else {
			// what an ugly hack
			if ( $elem === $waitIndicator ) {
				$elem.fadeOut( 200, callback );
			} else {
				$elem.fadeOut( callback );
			}
		}
	}

	function adjustFrameSize( animate ) {
		// set some inputs
		const oldFrameW = container.width();
		const oldFrameH = container.height();
		const oldContW = $content.width();
		const oldContH = $content.height();

		const availW = Math.floor( jQuery(window).width() * 0.8 );
		const availH = Math.floor( jQuery(window).height() * 0.8 );

		const emergencyW = Math.floor( jQuery(window).width() * 0.85 );
		const emergencyH = Math.floor( jQuery(window).height() * 0.85 );

		// FIXME: these might not be the true values
		const scrollW = 25;
		const scrollH = 25;

		// find the dimensions of the document

		const $body = $content.closest('body');
		const $html = $body.parent();

		let scrollTop = $html.scrollTop();
		let scrollLeft = $html.scrollLeft();

		$content
		.css('position', 'absolute')
		.width( 'auto' )
		.height( 'auto' );

		// set max dimensions for layout of content
		$iframe
		.width( emergencyW )
		.height( emergencyH );

		// get dimension values
		let docW = $content.width();
		let docH = $content.height();
		// On Firefox, this doesn't work for some reason, so use
		// this roundabout method to set the dimensions.
		if ( docW === 0 || docH === 0 ) {
			docW = availW * 0.95;
			docH = availH * 0.95;
		}

		// set old dimensions for layout of content
		$iframe
		.width( '100%' )
		.height( '100%' );

		$content
		.css('position', 'relative')
		.width( oldContW )
		.height( oldContH );

		const docpW = docW + 2 * padding;
		const docpH = docH + 2 * padding;

		// Flags

		const needsHScroll = docpW > emergencyW || ( docpW > emergencyW - scrollW && docpH > emergencyH );
		const needsVScroll = docpH > emergencyH || ( docpH > emergencyH - scrollH && docpW > emergencyW );

		const needsWStretch =
		( docpW > availW && docpW <= emergencyW ) && ( docpH <= emergencyH ) ||
		( docpW > availW - scrollW && docpW <= emergencyW - scrollW ) && ( docpH > emergencyH );

		const needsHStretch =
		( docpH > availH && docpH <= emergencyH ) && ( docpW <= emergencyW ) ||
		( docpH > availH - scrollH && docpH <= emergencyH - scrollH ) && ( docpW > emergencyW );

		// Outputs

		let frameW;
		let frameH;

		let contW;
		let contH;

		if ( needsWStretch ) {
			contW = docW;
			frameW = docpW;
		} else if ( docpW > availW ) { // form does not even fit with stretching
			contW = docW;
			frameW = availW;
		} else {
			//contW = Math.max( Math.min( 1.5 * docW, availW ), availW / 2 );
			contW = docW;
			frameW = docpW;
		}

		if ( needsVScroll ){
			frameW += scrollW;
		} else {
			scrollTop = 0;
		}

		if ( needsHStretch ) {
			contH = docH;
			frameH = docpH;
		} else if ( docpH > availH ) { // form does not even fit with stretching
			contH = docH;
			frameH = availH;
		} else {
			//contH = Math.min( 1.1 * docH, availH);
			contH = docH;
			frameH = docpH;
		}

		if ( needsHScroll ){
			frameH += scrollH;
		} else {
			scrollLeft = 0;
		}

		if ( frameW !== oldFrameW || frameH !== oldFrameH ) {

			$iframe[0].style.overflow="hidden";

			if ( animate ) {
				$content
				.width ( 'auto' )
				.height ( 'auto' );

				container.animate({
					width: frameW,
					height: frameH,
					top: Math.floor(( - frameH ) / 2),
					left: Math.floor(( - frameW ) / 2)
				}, {
					duration: 500,
					complete: function() {
						$iframe[0].style.overflow="visible";

						$content
						.width ( 'auto' )
						.height ( 'auto' );
					}
				});

			} else {
				container
				.width( frameW )
				.height ( frameH );

				container[0].style.top = (Math.floor(( - frameH ) / 2)) + "px";
				container[0].style.left = (Math.floor(( - frameW ) / 2)) + "px";


				setTimeout(() => {
					$iframe[0].style.overflow="visible";
				}, 100);

				$content
				.width ( 'auto' )
				.height ( 'auto' );
			}
		} else {
			$content
			.width ( 'auto' )
			.height ( 'auto' );
		}

		$html
		.css('overflow', 'auto')
		.scrollTop(Math.min(scrollTop, docpH - frameH))
		.scrollLeft(scrollLeft);

		return true;
	}

	function handleCloseFrame( event ){
		jQuery(window).unbind( "resize", adjustFrameSize );
		clearTimeout(timer);

		fadeOut( container, () => {
			$background.fadeOut( () => {
				$wrapper.remove();
			});
		});
		return false;
	}

	function fadeIn( $elem, callback ) {
		// no fading for broken browsers
		if ( brokenBrowser ){
			$elem.show();
			if ( callback ) {
				callback();
			}
		} else {
			// what an ugly hack
			if ( $elem === $waitIndicator ) {
				$elem.fadeIn( 200, callback );
			} else {
				$elem.fadeIn( callback );
			}
		}
	}

	function fadeTo($elem, time, target, callback) {
		// no fading for broken browsers
		if ( brokenBrowser ){

			if (target > 0) {
				$elem[0].style.visibility = "visible";
			} else {
				$elem[0].style.visibility = "hidden";
			}

			if ( callback ) {
				callback();
			}

		} else {
			$elem.fadeTo(time, target, callback);
		}
	}

	function showForm() {
		instance++;

		brokenChrome =
		( navigator.userAgent.includes("Chrome") &&
			navigator.platform.includes("Linux x86_64") );

		brokenBrowser = brokenChrome;

		let maxZIndex = 0;

		jQuery("*").each(function() {
			const curr = parseInt( jQuery( this ).css( "z-index" ) );
			maxZIndex = curr > maxZIndex ? curr : maxZIndex;
		});

		$wrapper = jQuery( "<div class='popupform-wrapper' >" );
		$background = jQuery( "<div class='popupform-background' >" );

		const $waitIndicatorWrapper = jQuery( "<div class='popupform-loading'>" );

		$waitIndicator = jQuery( "<div class='popupform-loadingbg'></div><div class='popupform-loadingfg'></div>" );

		const $anchor = jQuery( "<div class='popupform-anchor' >" );

		container = jQuery( "<div class='popupform-container' >" );
		$innerContainer = jQuery( "<div class='popupform-innercontainer' >" );
		$iframe = jQuery( "<iframe class='popupform-innerdocument' name='popupform-iframe" + instance + "' id='popupform-iframe" + instance + "' >");

		const $closeBtn = jQuery( "<div class='popupform-close'></div> " );

		// initially hide background and waitIndicator
		if (brokenChrome) {
			$background.css("background", "transparent");
		} else {
			$background.css("opacity", 0.0);
		}

		$waitIndicator.hide();
		container.hide();

		// insert background and wait indicator into wrapper and all into document
		$waitIndicatorWrapper
		.append( $waitIndicator );

		$innerContainer
		.append( $iframe );

		container
		.append( $closeBtn )
		.append( $innerContainer );

		$anchor
		.append(container);

		$wrapper
		.css( "z-index", maxZIndex + 1 )
		.append( $background )
		.append( $waitIndicatorWrapper )
		.append( $anchor )
		.appendTo( "body" );

		// fade background in
		if ( !brokenChrome ) {
			$background.fadeTo( 400, 0.6 );
		}
		fadeIn( $waitIndicator );

		// attach event handler to close button
		$closeBtn.click( handleCloseFrame );
	}

	function purgePage() {
		const path = location.pathname;
		// get name of the current page from the url
		const pageName = path.split("/").pop();
		return ( new mw.Api() ).post( { action: 'purge', titles: pageName } );
	}

	function handleSubmitData( event, returnedData, textStatus, XMLHttpRequest ){
		fadeOut( container, () => {
			fadeIn( $waitIndicator );
		});

		let $form = jQuery( event.target );
		const formdata = $form.serialize() + "&wpSave=" + encodeURIComponent($form.find("#wpSave").attr("value"));

		function handleInnerSubmit() {
			// find form in fake edit page
			const $innerform = jQuery("<div>" + returnedData + "</div>").find("form");

			// check if we got an error page
			if ( $innerform.length === 0 ) {

				$form.unbind( event );

				$iframe = container.find("iframe");
				doc = $iframe[0].contentWindow || $iframe[0].contentDocument;
				if (doc.document) {
					doc = doc.document;
				}

				doc.open();
				doc.write(returnedData);
				doc.close();

				handleCloseFrame();
				if ( reload ) {
					purgePage().then( ( data ) => {
						location.reload();
					} );
				}
				return false;
			}

			// Send the form data off, we do not care for the returned data
			const innerformdata = $innerform.serialize();
			jQuery.post( $innerform.attr("action"), innerformdata );

			// build new url for outer page (we have to ask for a purge)

			let url = location.href;

			// does a querystring exist?
			const start = url.indexOf("action=");

			if ( start >= 0 ) {

				const stop = url.indexOf("&", start);

				if ( stop >= 0 ) {
					url = url.slice( 0, Math.max(0, start - 1) ) + url.slice(stop + 1);
				} else {
					url = url.slice( 0, Math.max(0, start - 1) );
				}

			}

			$form = jQuery('<form action="' + url + '" method="POST"><input type="hidden" name="action" value="purge"></form>')
			.appendTo('body');

			$form
			.submit();

			fadeOut( container, () => {
				fadeIn( $waitIndicator );
			});

			return false;
		}

		// Send form data off. PF will send back a fake edit page
		//
		// Normally we should check this.action first and only if it is empty
		// revert to this.ownerDocument.URL. Tough luck, IE does not return an
		// empty action but fills in some bogus
		jQuery.post( event.target.ownerDocument.URL , formdata, handleInnerSubmit);

		return false;
	}

	function closeFrameAndFollowLink( link ){
		clearTimeout(timer);

		fadeOut( container, () => {
			fadeIn ( $waitIndicator );
			window.location.href = link;
		});
	}

	// Saw it on http://robertnyman.com/2006/04/24/get-the-rendered-style-of-an-element
	// and liked it
	function getStyle(oElm, strCssRule){
		let strValue = "";
		if(document.defaultView && document.defaultView.getComputedStyle){
			strValue = document.defaultView.getComputedStyle(oElm, "").getPropertyValue(strCssRule);
		} else if(oElm.currentStyle){
			strCssRule = strCssRule.replace(/\-(\w)/g, (strMatch, p1) => p1.toUpperCase());
			strValue = oElm.currentStyle[strCssRule];
		}
		return strValue;
	}

	function handleLoadFrame() {
		const $iframecontents = $iframe.contents();

		const containerAlreadyVisible = container.is( ':visible' );

		if ( !containerAlreadyVisible ) {
			// no need to hide it again
			if ( brokenBrowser ) {
				container[0].style.visibility = "hidden";
			} else {
				container[0].style.opacity = 0;
			}
		}

		container.show();

		// GuMaxDD has #content but keeps headlines in #gumax-content-body
		$content = $iframecontents.find("#gumax-content-body");

		// Normal skins use #content (e.g. Vector, Monobook)
		if ( $content.length === 0 ) {
			$content = $iframecontents.find("#content");
		}

		// Some skins use #mw_content (e.g. Modern)
		if ( $content.length === 0 ) {
			$content = $iframecontents.find("#mw_content");
		}

		const $iframebody = $content.closest("body");
		const $iframedoc = $iframebody.parent();

		// This is not a normal MW page (or it uses an unknown skin)
		if ( $content.length === 0 ) {
			$content = $iframebody;
		}

		// The huge left margin looks ugly in Vector - reduce it.
		// (How does this look for other skins?)
		const $siblings = $content
		.css( {
			margin: 0,
			padding: padding,
			width: "auto",
			height: "auto",
			minWidth: "0px",
			minHeight:"0px",
//			overflow: "visible",
//			position: "relative",
//			top: "0",
//			left: "0",
			border: "none"
		} )
		.parentsUntil('html')
		.css( {
			margin: 0,
			padding: 0,
			width: "auto",
			height: "auto",
			minWidth: "0px",
			minHeight: "0px",
			"float": "none", // Cavendish skin uses floating -> unfloat content
//			position: "relative",
//			top: "0",
//			left: "0",
			background: "transparent"
		})
		.addBack().siblings();

		$iframedoc.height('100%').width('100%');
		$iframebody.height('100%').width('100%');

		$siblings.each( function(){
			const $elem = jQuery(this);

			// TODO: Does this really help?
			if ( getStyle(this, "display") !== "none" && ! (
					( this.offsetLeft + $elem.outerWidth(true) < 0 ) ||		// left of document
					( this.offsetTop + $elem.outerHeight(true) < 0 ) || // above document
					( this.offsetLeft > 100000 ) ||		// right of document
					( this.offsetTop > 100000 ) // below document
					)
			) {

				jQuery(this).hide();
				//					css({
				//						height : "0px",
				//						width : "0px",
				//						minWidth : "0px",
				//						minHeight : "0px",
				//						margin : "0px",
				//						padding : "0px"
				//						border : "none",
				//						overflow: "hidden"
				//					//position: "static"
				//					});
			}
			if ( ( this.offsetLeft + $elem.outerWidth() < 0 ) ||
				( this.offsetTop + $elem.outerHeight() < 0 )
				) {
				this.style.left = (-$elem.outerWidth(true)) + "px";
				this.style.top = (-$elem.outerHeight(true)) + "px";
			}
		});
		//.children().css("position", "static");

		container.show();

		// adjust frame size to dimensions just calculated
		adjustFrameSize();

		// and attach event handler to adjust frame size every time the window
		// size changes
		jQuery( window ).resize( () => {
			adjustFrameSize();
		} );

		//interval = setInterval(adjustFrameSize, 100);

		const $form = $content.find("#pfForm");
		const innerwdw = document.getElementById( 'popupform-iframe' + instance ).contentWindow;
		const innerJ = innerwdw.jQuery;

		// if we have a form and it is not a RunQuery form
		if ($form.length > 0 && ( typeof $form[0].wpRunQuery === 'undefined') ) {
			let submitok = false;
			let innersubmitprocessed = false;

			// catch form submit event
			$form
			.bind( "submit", ( event ) => {
				var interval = setInterval(() => {
					if ( innersubmitprocessed ) {
						clearInterval( interval );
						innersubmitprocessed = false;
						if ( submitok ) {
							handleSubmitData( event );
						}
					}

				}, 10);
				event.stopPropagation();
				return false;
			});

			// catch inner form submit event
			if ( innerJ ) {
				innerwdw.jQuery($form[0])
				.bind( "submit", ( event ) => {
						submitok = ( event.result === undefined ) ? true : event.result;
						innersubmitprocessed = true;
						return false;
				});
			} else {
				submitok = true;
				innersubmitprocessed = true;
			}
		}

		if (innerJ) {
			// FIXME: Why did I put this in?
			innerwdw.jQuery( innerwdw[0] ).on('unload', (event) => false);

			//
			$content.bind( 'click', () => {
				let foundQueue = false;
				innerJ('*', $content[0]).each( function() {
					if ( innerJ(this).queue().length > 0 ) {
						foundQueue = true;
						innerJ(this).queue( function(){
							setTimeout( adjustFrameSize, 100, true );
							innerJ(this).dequeue();
						});
					}
				});
				if ( ! foundQueue ) {
					adjustFrameSize( true );
				}
				return true;
			});
		} else {
			$content.bind( 'click', () => {
					adjustFrameSize( true );
			});
		}

		// find all links. Have to use inner jQuery so event.result below
		// reflects the result of inner event handlers. We (hopefully) come last
		// in the chain of event handlers as we only attach when the frame is
		// already completely loaded, i.e. every inner event handler is already
		// attached.
		const allLinks = (innerJ)?innerJ("a[href]"):jQuery("a[href]");

		// catch 'Cancel'-Link (and other 'back'-links) and close frame instead of going back
		const backlinks = allLinks.filter('a[href="javascript:history.go(-1);"]');
		backlinks.click(handleCloseFrame);

		// promote any other links to open in main window, prevent nested browsing
		allLinks
		.not('a[href*="javascript:"]') // scripted links
		.not('a[target]')              // targeted links
		.not('a[href^="#"]')           // local links
		.not('a.pfUploadable')         // link to file upload
		.click((event) => {
			if ( event.result !== false ) { // if not already caught by somebody else
				closeFrameAndFollowLink( event.target.getAttribute('href') );
			}
			return false;
		});

		// finally show the frame, but only if it is not already visible
		if ( ! containerAlreadyVisible ) {
				fadeOut ( $waitIndicator, () => {
				fadeTo( container, 400, 1 );
			} );
		}

		return false;
	}

	function handlePopupFormInput( ptarget, elem ) {
		showForm();
		reload = $(elem).hasClass('reload');

		$iframe.on( 'load', () => {
			// attach event handler to iframe
			$iframe.bind( 'load', handleLoadFrame );
			return false;
		});

		elem.target = 'popupform-iframe' + instance;
		return true;
	}

	function handlePopupFormLink( ptarget, elem ) {
		showForm();
		reload = $(elem).hasClass('reload');
		// store initial readystate
		let readystate = $iframe.contents()[0].readyState;

		// set up timer for waiting on the document in the iframe to be dom-ready
		// this sucks, but there is no other way to catch that event
		// onload is already too late
		timer = setInterval(() => {
			// if the readystate changed
			if ( readystate !== $iframe.contents()[0].readyState ) {
				// store new readystate
				readystate = $iframe.contents()[0].readyState;

				// if dom is built but document not yet displayed
				if ( readystate === 'interactive' || readystate === 'complete' ) {
					needsRender = false; // flag that rendering is already done
					handleLoadFrame();
				}
			}
		}, 100 );

		// fallback in case we did not catch the dom-ready state
		$iframe.on('load', ( event ) => {
			if ( needsRender ) { // rendering not already done?
				handleLoadFrame( event );
			}
			needsRender = true;
		});

		if ( elem.tagName === 'FORM' ) {
			elem.target = 'popupform-iframe' + instance;
			return true;
		} else {
			const delim = ptarget.indexOf( '?' );
			const form = document.createElement("form");

			form.target = 'popupform-iframe' + instance;

			// Do we have parameters?
			if ( delim > 0 ) {
				form.action = ptarget.slice( 0, Math.max(0, delim) );
				const params = String( ptarget.slice( delim + 1 ) ).split("&");
				for ( let i = 0; i < params.length; ++i ) {

					const input = document.createElement("input");
					const param = String( params[i] ).split('=');
					input.type = 'hidden';
					input.name = decodeURIComponent( param[0] );
					input.value = decodeURIComponent( param[1] );
					form.appendChild( input );
				}
			} else {
				form.action = ptarget;
			}

			document.getElementsByTagName('body')[0].appendChild(form);
			form.submit();
			document.getElementsByTagName('body')[0].removeChild(form);
			return false;
		}
	}

	// export public funcitons
	this.handlePopupFormInput = handlePopupFormInput;
	this.handlePopupFormLink = handlePopupFormLink;
	this.adjustFrameSize = adjustFrameSize;

	return this;
}() );
