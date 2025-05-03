/**
 * @author Yaron Koren
 * @author Paladox
 */

/* global L */

function setupMapFormInput( inputDiv, mapService ) {

	/**
	 * Round off a number to five decimal places - that's the most
	 * we need for coordinates, one would think.
	 *
	 * @param {Mixed} num
	 * @return {Mixed}
	 */
	function pfRoundOffDecimal( num ) {
		return Math.round( num * 100000 ) / 100000;
	}

	let map, marker, markers, mapCanvas, mapOptions, geocoder;
	let numClicks = 0, timer = null;

	const coordsInput = inputDiv.find('.pfCoordsInput');

	function googleMapsSetMarker( location ) {
		if ( marker === undefined ){
			marker = new google.maps.Marker( {
				position: location,
				map: map,
				draggable: true
			} );
			google.maps.event.addListener( marker, 'dragend', ( event ) => {
				googleMapsSetMarker( event.latLng );
			});
		} else {
			marker.setPosition(location);
		}
		const stringVal = pfRoundOffDecimal( location.lat() ) + ', ' + pfRoundOffDecimal( location.lng() );
		coordsInput.val( stringVal )
			.attr( 'data-original-value', stringVal )
			.removeClass( 'modifiedInput' )
			.parent().find('.pfCoordsInputHelpers').remove();

	}

	function leafletSetMarker( location ) {
		if ( marker === null) {
			marker = L.marker( location ).addTo( map );
		} else {
			marker.setLatLng( location, { draggable: true } );
		}
		marker.dragging.enable();

		function setInput() {
			const stringVal = pfRoundOffDecimal( marker.getLatLng().lat ) + ', ' +
				pfRoundOffDecimal( marker.getLatLng().lng );
			coordsInput.val( stringVal )
				.attr( 'data-original-value', stringVal )
				.removeClass( 'modifiedInput' )
				.parent().find('.pfCoordsInputHelpers').remove();
		}

		marker.off('dragend').on('dragend', ( event ) => {
			setInput();
		});
		setInput();
	}

	function openLayersSetMarker( location ) {
		// OpenLayers does not have a real marker move
		// option - instead, just delete the old marker
		// and add a new one.
		markers.clearMarkers();
		marker = new OpenLayers.Marker( location );
		markers.addMarker( marker );

		// Transform the coordinates back, in order to display them.
		const realLonLat = location.clone();
		realLonLat.transform(
			map.getProjectionObject(), // transform from Spherical Mercator Projection
			new OpenLayers.Projection("EPSG:4326") // to WGS 1984
		);
		const stringVal = pfRoundOffDecimal( realLonLat.lat ) + ', ' + pfRoundOffDecimal( realLonLat.lon );
		coordsInput.val( stringVal )
			.attr( 'data-original-value', stringVal )
			.removeClass( 'modifiedInput' )
			.parent().find('.pfCoordsInputHelpers').remove();
	}

	if ( mapService === "Google Maps" ) {
		mapCanvas = inputDiv.find('.pfMapCanvas')[ 0 ];
		mapOptions = {
			zoom: 1,
			center: new google.maps.LatLng( 0, 0 )
		};
		map = new google.maps.Map( mapCanvas, mapOptions );
		geocoder = new google.maps.Geocoder();

		// Let a click set the marker, while keeping the default
		// behavior (zoom and center) for double clicks.
		// Code copied from http://stackoverflow.com/a/8417447
		google.maps.event.addListener( map, 'click', ( event ) => {
			timer = setTimeout( () => {
				googleMapsSetMarker( event.latLng );
			}, 200 );
		});
		google.maps.event.addListener( map, 'dblclick', ( event ) => {
			clearTimeout( timer );
		});
	} else if (mapService === "Leaflet") {
		mapCanvas = inputDiv.find('.pfMapCanvas').get(0);
		mapOptions = {
			zoom: 1,
			center: [ 0, 0 ]
		};
		const layerOptions = {
			attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
		};

		map = L.map(mapCanvas, mapOptions);
		new L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', layerOptions).addTo(map);

		map.on( 'click', ( event ) => {
			// Place/move the marker only on a single click, not a
			// double click (double clicks do a zoom).
			// Code based on https://stackoverflow.com/a/7845282
			numClicks++;
			if (numClicks === 1) {
				timer = setTimeout( () => {
					leafletSetMarker( event.latlng );
					numClicks = 0;
				});
			} else {
				clearTimeout(timer);
				numClicks = 0;
			}
		});
	} else { // if ( mapService == "OpenLayers" ) {
		mapCanvas = inputDiv.find('.pfMapCanvas');
		let mapCanvasID = mapCanvas.attr('id');
		if ( mapCanvasID === undefined ) {
			// If no ID is set, it's probably in a multiple-
			// instance template; just set the ID to a random
			// string, so we can attach the map to it.
			mapCanvasID = mapCanvas.attr('data-origID') + '-' +
				Math.random().toString(36).slice(2, 15);
			mapCanvas.attr('ID', mapCanvasID);
		}
		map = new OpenLayers.Map( mapCanvasID );
		// We do this more complex initialization, rather than just
		// calling OpenLayers.Layer.OSM(), so that the tiles will be
		// loaded via either HTTP or HTTPS, depending on what we are
		// using.
		map.addLayer( new OpenLayers.Layer.OSM(
			"OpenStreetMap",
			// Official OSM tileset as protocol-independent URLs
			[
				'//a.tile.openstreetmap.org/${z}/${x}/${y}.png',
				'//b.tile.openstreetmap.org/${z}/${x}/${y}.png',
				'//c.tile.openstreetmap.org/${z}/${x}/${y}.png'
			],
			null
		) );
		map.zoomTo( 0 );
		markers = new OpenLayers.Layer.Markers( "Markers" );
		map.addLayer( markers );

		map.events.register( "click", map, ( e ) => {
			numClicks++;
			if (numClicks === 1) {
				timer = setTimeout( () => {
					const loc = map.getLonLatFromPixel( e.xy );
					openLayersSetMarker( loc );
					numClicks = 0;
				});
			} else {
				clearTimeout(timer);
				numClicks = 0;
			}
		} );
	}

	function toOpenLayersLonLat( maps, lat, lon ) {
		return new OpenLayers.LonLat( lon, lat ).transform(
			new OpenLayers.Projection( "EPSG:4326" ), // transform from WGS 1984
			maps.getProjectionObject() // to Spherical Mercator Projection
		);
	}

	function setMarkerFromCoordinates() {
		const coordsText = coordsInput.val();
		const coordsParts = coordsText.split(",");
		if ( coordsParts.length !== 2 ) {
			coordsInput.val('');
			return;
		}
		const lat = coordsParts[0].trim();
		const lon = coordsParts[1].trim();
		if ( !jQuery.isNumeric( lat ) || !jQuery.isNumeric( lon ) ) {
			coordsInput.val('');
			return;
		}
		if ( lat < -90 || lat > 90 || lon < -180 || lon > 180 ) {
			coordsInput.val('');
			return;
		}
		if ( mapService === "Google Maps" ) {
			const gmPoint = new google.maps.LatLng( lat, lon );
			googleMapsSetMarker( gmPoint );
			map.setCenter( gmPoint );
		} else if ( mapService === "Leaflet" ){
			const lPoint = L.latLng( lat, lon );
			leafletSetMarker( lPoint );
			map.setView( lPoint, 14 );
		} else { // if ( mapService === "OpenLayers" ) {
			const olPoint = toOpenLayersLonLat( map, lat, lon );
			openLayersSetMarker( olPoint );
			map.setCenter( olPoint, 14 );
		}
	}

	coordsInput.keypress( function( e ) {
		// Is this still necessary fro IE compatibility?
		const keycode = (e.keyCode ? e.keyCode : e.which);
		if ( keycode === 13 ) {
			setMarkerFromCoordinates();
			// Prevent the form from getting submitted.
			e.preventDefault();
			$(this).removeClass( 'modifiedInput' )
				.parent().find('.pfCoordsInputHelpers').remove();

		}
	});

	coordsInput.keydown( ( e ) => {
		if ( ! coordsInput.hasClass( 'modifiedInput' ) ) {
			coordsInput.addClass( 'modifiedInput' );
			const $checkMark = $('<a></a>').addClass( 'pfCoordsCheckMark' ).css( 'color', 'green' ).html( '&#10004;' );
			const $xMark = $('<a></a>').addClass( 'pfCoordsX' ).css( 'color', 'red' ).html( '&#10008;' );
			const $marksDiv = $('<span></span>').addClass( 'pfCoordsInputHelpers' )
				.append( $checkMark ).append( ' ' ).append( $xMark );
			coordsInput.parent().append( $marksDiv );

			$checkMark.click( () => {
				setMarkerFromCoordinates();
				coordsInput.removeClass( 'modifiedInput' );
				$marksDiv.remove();
			});

			$xMark.click( () => {
				coordsInput.removeClass( 'modifiedInput' )
					.val( coordsInput.attr('data-original-value') );
				$marksDiv.remove();
			});
		}
	});

	function setMarkerFromAddress() {
		let addressText = inputDiv.find('.pfAddressInput input').val(),
			alert;
		if ( mapService === "Google Maps" ) {
			map.setZoom(14);
			geocoder.geocode( { 'address': addressText }, (results, status) => {
				if (status === google.maps.GeocoderStatus.OK) {
					map.setCenter(results[0].geometry.location);
					googleMapsSetMarker( results[0].geometry.location );
					map.setZoom(14);
				} else {
					alert("Geocode was not successful for the following reason: " + status);
				}
			});
		 } else { // Leaflet, OpenLayers
			$.ajax( 'https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent( addressText ) )
			.done( ( result ) => {
				if ( result.length === 0 ) {
					alert("Geocode was not successful");
					return;
				}
				const lat = result[0].lat;
				const lon = result[0].lon;
				// Use the specified bounds - this is better
				// than a preset zoom, because it handles the
				// precision correctly for countries, cities,
				// etc.
				const boundsStr = String(result[0].boundingbox);
				const vals = boundsStr.split(",");
				const bottom = vals[0];
				const top = vals[1];
				const left = vals[2];
				const right = vals[3];
				if ( mapService === "OpenLayers" ) {
					const olPoint = toOpenLayersLonLat( map, lat, lon );
					openLayersSetMarker( olPoint );
					map.setCenter( olPoint );
					const fromProjection = new OpenLayers.Projection("EPSG:4326"); // transform from WGS 1984
					const toProjection = map.getProjectionObject(); // to Spherical Mercator Projection
					const bounds = new OpenLayers.Bounds(left,bottom,right,top).transform(fromProjection,toProjection);
					map.zoomToExtent(bounds);
				} else if ( mapService === "Leaflet" ) {
					const lPoint = L.latLng( lat, lon );
					leafletSetMarker( lPoint );
					map.fitBounds([ [ bottom, left ], [ top, right ] ]);
				}
			});
		}
	}

	inputDiv.find('.pfAddressInput').keypress( ( e ) => {
		// Is this still necessary fro IE compatibility?
		const keycode = (e.keyCode ? e.keyCode : e.which);
		if ( keycode === 13 ) {
			setMarkerFromAddress();
			// Prevent the form from getting submitted.
			e.preventDefault();
		}
	} );

	inputDiv.find('.pfLookUpAddress').click( () => {
		setMarkerFromAddress();
	});


	if ( coordsInput.val() !== '' ) {
		setMarkerFromCoordinates();
	}
}

$( () => {
	jQuery(".pfGoogleMapsInput").each( function() {
		// Ignore the hidden "starter" div in multiple-instance templates.
		if ( $(this).closest(".multipleTemplateStarter").length > 0 ) {
			return;
		}
		setupMapFormInput( jQuery(this), "Google Maps" );
	});
	jQuery(".pfLeafletInput").each( function() {
		if ( $(this).closest(".multipleTemplateStarter").length > 0 ) {
			return;
		}
		setupMapFormInput( jQuery(this), "Leaflet" );
	});
	jQuery(".pfOpenLayersInput").each( function() {
		if ( $(this).closest(".multipleTemplateStarter").length > 0 ) {
			return;
		}
		setupMapFormInput( jQuery(this), "OpenLayers" );
	});
});

// Activate maps in a new instance of a multiple-instance template.
mw.hook('pf.addTemplateInstance').add(( $newInstance ) => {
	$newInstance.find(".pfGoogleMapsInput").each( function() {
		setupMapFormInput( jQuery(this), "Google Maps" );
	});
	$newInstance.find(".pfLeafletInput").each( function() {
		setupMapFormInput( jQuery(this), "Leaflet" );
	});
	$newInstance.find(".pfOpenLayersInput").each( function() {
		setupMapFormInput( jQuery(this), "OpenLayers" );
	});
});
