/**
 * @author Yaron Koren
 */

/* global L */

function setupMapFormInput( inputDiv, mapService ) {
	let map, marker, markers, mapCanvas, mapOptions;
	let imageHeight = null, imageWidth = null;
	let numClicks = 0, timer = null, geocoder;

	if ( mapService === "Google Maps" ) {
		mapCanvas = inputDiv.find('.pfMapCanvas')[0];
		mapOptions = {
			zoom: 1,
			center: new google.maps.LatLng(0,0)
		};
		map = new google.maps.Map(mapCanvas, mapOptions);
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

		const imageUrl = inputDiv.attr('data-image-path');
		if ( imageUrl !== undefined ) {
			imageHeight = inputDiv.attr('data-height');
			imageWidth = inputDiv.attr('data-width');
			mapOptions.crs = L.CRS.Simple;
		}

		map = L.map(mapCanvas, mapOptions);

		if ( imageUrl !== undefined ) {
			const imageBounds = [ [ 0, 0 ], [ imageHeight, imageWidth ] ];
			L.imageOverlay(imageUrl, imageBounds).addTo(map);
			map.fitBounds(imageBounds);
		} else {
			new L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', layerOptions).addTo(map);
		}

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
	} else { // if ( mapService === "OpenLayers" ) {
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

		map.zoomTo(0);
		markers = new OpenLayers.Layer.Markers( "Markers" );
		map.addLayer( markers );

		map.events.register("click", map, (e) => {
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
		});
	}

	const coordsInput = inputDiv.find('.pfCoordsInput');
	coordsInput.keypress( function( e ) {
		// Is this still necessary for IE compatibility?
		const keycode = (e.keyCode ? e.keyCode : e.which);
		if ( keycode == 13 ) {
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

	inputDiv.find('.pfAddressInput').keypress( ( e ) => {
		// Is this still necessary fro IE compatibility?
		const keycode = (e.keyCode ? e.keyCode : e.which);
		if ( keycode == 13 ) {
			setMarkerFromAddress();
			// Prevent the form from getting submitted.
			e.preventDefault();
		}
	});

	inputDiv.find('.pfLookUpAddress').click( () => {
		setMarkerFromAddress();
	});


	if ( coordsInput.val() != '' ) {
		if ( mapService == 'OpenLayers' ) {
			map.zoomTo( 14 );
		} else {
			map.setZoom( 14 );
		}
		// This has to be called after the zooming, for the OpenLayers
		// zoom to work correctly.
		setMarkerFromCoordinates();
	} else {
		if ( coordsInput.attr('data-bound-coords') ) {
			const boundCoords = coordsInput.attr('data-bound-coords');
			const coords = boundCoords.split(";");
			const boundCoords1 = coords[0];
			const lat1 = boundCoords1.split(",")[0].trim();
			const lon1 = boundCoords1.split(",")[1].trim();
			const boundCoords2 = coords[1];
			const lat2 = boundCoords2.split(",")[0].trim();
			const lon2 = boundCoords2.split(",")[1].trim();
			if ( !jQuery.isNumeric( lat1 ) || !jQuery.isNumeric( lon1 ) ||
			!jQuery.isNumeric( lat2 ) || !jQuery.isNumeric( lon2 ) ) {
				return;
			}
			if ( lat1 < -90 || lat1 > 90 || lon1 < -180 || lon1 > 180 ||
				lat2 < -90 || lat2 > 90 || lon2 < -180 || lon2 > 180 ) {
				return;
			}
			if ( mapService === "Google Maps" ) {
				const bound1 = new google.maps.LatLng(lat1, lon1);
				const bound2 = new google.maps.LatLng(lat2, lon2);
				const bounds = new google.maps.LatLngBounds();
				bounds.extend(bound1);
				bounds.extend(bound2);
				map.fitBounds(bounds);
			} else if ( mapService === "Leaflet" ){
				map.fitBounds([ [ lat1, lon1 ], [ lat2, lon2 ] ]);
			} else { // if ( mapService === "OpenLayers" ) {
				const fromProjection = new OpenLayers.Projection("EPSG:4326"); // transform from WGS 1984
				const toProjection = map.getProjectionObject(); // to Spherical Mercator Projection
				const bounds = new OpenLayers.Bounds(lon1, lat1, lon2, lat2).transform(fromProjection,toProjection);
				map.zoomToExtent(bounds);
			}
		}
	}

	function setMarkerFromAddress() {
		const currentMapName = coordsInput.attr('name');
		let addressText;
		const allFeedersForCurrentMap = jQuery('[data-feeds-to-map="' + currentMapName + '"]').map( function() {
			return $( this ).val()
		}).get();
		if ( allFeedersForCurrentMap.length > 0 ) {
			// Assemble a single string from all the address inputs that feed to this map.
			addressText = allFeedersForCurrentMap.join( ', ' );
		} else {
			// No other inputs feed to this map, so use the standard "Enter address here" input.
			addressText = inputDiv.find('.pfAddressInput input').val();
		}
		if ( mapService === "Google Maps" ) {
			geocoder.geocode( { 'address': addressText }, (results, status) => {
				if (status == google.maps.GeocoderStatus.OK) {
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

	function setMarkerFromCoordinates() {
		const coordsText = coordsInput.val();
		const coordsParts = coordsText.split(",");
		if ( coordsParts.length != 2 ) {
			coordsInput.val('');
			return;
		}
		let lat = coordsParts[0].trim();
		let lon = coordsParts[1].trim();
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
			if ( imageHeight !== null && imageWidth !== null ) {
				lat *= imageWidth / 100;
				lon *= imageWidth / 100;
			}
			const lPoint = L.latLng( lat, lon );
			leafletSetMarker( lPoint );
			if ( imageHeight == null && imageWidth == null ) {
				map.setView( lPoint, 14 );
			}
		} else { // if ( mapService === "OpenLayers" ) {
			const olPoint = toOpenLayersLonLat( map, lat, lon );
			openLayersSetMarker( olPoint );
			map.setCenter( olPoint );
		}
	}

	function toOpenLayersLonLat( maps, lat, lon ) {
		return new OpenLayers.LonLat( lon, lat ).transform(
			new OpenLayers.Projection("EPSG:4326"), // transform from WGS 1984
			maps.getProjectionObject() // to Spherical Mercator Projection
		);
	}

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

	function googleMapsSetMarker(location) {
		if (marker == undefined){
			marker = new google.maps.Marker({
				position: location,
				map: map,
				draggable: true
			});
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
		if ( marker == null) {
			marker = L.marker( location ).addTo( map );
		} else {
			marker.setLatLng( location, { draggable: true } );
		}
		marker.dragging.enable();

		function setInput() {
			let lat = marker.getLatLng().lat;
			let lng = marker.getLatLng().lng;
			if ( imageHeight == null && imageWidth == null ) {
				// Normal map.
				// Leaflet permits longitude beyond ±180, so
				// we have to normalize this here.
				// Google Maps and OpenLayers don't have this
				// issue.
				while ( lng < -180 ) {
					lng += 360;
				}
				while ( lng > 180 ) {
					lng -= 360;
				}
			} else {
				lat *= 100 / imageWidth;
				lng *= 100 / imageWidth;
			}
			const stringVal = pfRoundOffDecimal( lat ) + ', ' +
				pfRoundOffDecimal( lng );
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
mw.hook('pf.addTemplateInstance').add( ( $newInstance ) => {
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
