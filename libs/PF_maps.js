/**
 * @author Yaron Koren
 */

/* global L */

function setupMapFormInput( inputDiv, mapService ) {
	var map, marker, markers, mapCanvas, mapOptions;
	var imageHeight = null, imageWidth = null;
	var numClicks = 0, timer = null;

	if ( mapService === "Google Maps" ) {
		mapCanvas = inputDiv.find('.pfMapCanvas')[0];
		mapOptions = {
			zoom: 1,
			center: new google.maps.LatLng(0,0)
		};
		map = new google.maps.Map(mapCanvas, mapOptions);
		var geocoder = new google.maps.Geocoder();

		// Let a click set the marker, while keeping the default
		// behavior (zoom and center) for double clicks.
		// Code copied from http://stackoverflow.com/a/8417447
		google.maps.event.addListener( map, 'click', function( event ) {
			timer = setTimeout( function() {
				googleMapsSetMarker( event.latLng );
			}, 200 );
		});
		google.maps.event.addListener( map, 'dblclick', function( event ) {
			clearTimeout( timer );
		});
	} else if (mapService === "Leaflet") {
		mapCanvas = inputDiv.find('.pfMapCanvas').get(0);
		mapOptions = {
			zoom: 1,
			center: [ 0, 0 ]
		};
		var layerOptions = {
			attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
		};

		var imageUrl = inputDiv.attr('data-image-path');
		if ( imageUrl !== undefined ) {
			imageHeight = inputDiv.attr('data-height');
			imageWidth = inputDiv.attr('data-width');
			mapOptions.crs = L.CRS.Simple;
		}

		map = L.map(mapCanvas, mapOptions);

		if ( imageUrl !== undefined ) {
			var imageBounds = [ [ 0, 0 ], [ imageHeight, imageWidth ] ];
			L.imageOverlay(imageUrl, imageBounds).addTo(map);
			map.fitBounds(imageBounds);
		} else {
			new L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', layerOptions).addTo(map);
		}

		map.on( 'click', function( event ) {
			// Place/move the marker only on a single click, not a
			// double click (double clicks do a zoom).
			// Code based on https://stackoverflow.com/a/7845282
			numClicks++;
			if (numClicks === 1) {
				timer = setTimeout( function() {
					leafletSetMarker( event.latlng );
					numClicks = 0;
				});
			} else {
				clearTimeout(timer);
				numClicks = 0;
			}
		});
	} else { // if ( mapService === "OpenLayers" ) {
		var mapCanvas = inputDiv.find('.pfMapCanvas');
		var mapCanvasID = mapCanvas.attr('id');
		if ( mapCanvasID === undefined ) {
			// If no ID is set, it's probably in a multiple-
			// instance template; just set the ID to a random
			// string, so we can attach the map to it.
			mapCanvasID = mapCanvas.attr('data-origID') + '-' +
				Math.random().toString(36).substring(2, 15);
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

		map.events.register("click", map, function(e) {
			numClicks++;
			if (numClicks === 1) {
				timer = setTimeout( function() {
					var loc = map.getLonLatFromPixel( e.xy );
					openLayersSetMarker( loc );
					numClicks = 0;
				});
			} else {
				clearTimeout(timer);
				numClicks = 0;
			}
		});
	}

	var coordsInput = inputDiv.find('.pfCoordsInput');
	coordsInput.keypress( function( e ) {
		// Is this still necessary for IE compatibility?
		var keycode = (e.keyCode ? e.keyCode : e.which);
		if ( keycode == 13 ) {
			setMarkerFromCoordinates();
			// Prevent the form from getting submitted.
			e.preventDefault();
			$(this).removeClass( 'modifiedInput' )
				.parent().find('.pfCoordsInputHelpers').remove();
		}
	});

	coordsInput.keydown( function( e ) {
		if ( ! coordsInput.hasClass( 'modifiedInput' ) ) {
			coordsInput.addClass( 'modifiedInput' );
			var checkMark = $('<a></a>').addClass( 'pfCoordsCheckMark' ).css( 'color', 'green' ).html( '&#10004;' );
			var xMark = $('<a></a>').addClass( 'pfCoordsX' ).css( 'color', 'red' ).html( '&#10008;' );
			var marksDiv = $('<span></span>').addClass( 'pfCoordsInputHelpers' )
				.append( checkMark ).append( ' ' ).append( xMark );
			coordsInput.parent().append( marksDiv );

			checkMark.click( function() {
				setMarkerFromCoordinates();
				coordsInput.removeClass( 'modifiedInput' );
				marksDiv.remove();
			});

			xMark.click( function() {
				coordsInput.removeClass( 'modifiedInput' )
					.val( coordsInput.attr('data-original-value') );
				marksDiv.remove();
			});
		}
	});

	inputDiv.find('.pfAddressInput').keypress( function( e ) {
		// Is this still necessary fro IE compatibility?
		var keycode = (e.keyCode ? e.keyCode : e.which);
		if ( keycode == 13 ) {
			setMarkerFromAddress();
			// Prevent the form from getting submitted.
			e.preventDefault();
		}
	});

	inputDiv.find('.pfLookUpAddress').click( function() {
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
			var boundCoords = coordsInput.attr('data-bound-coords');
			var coords = boundCoords.split(";");
			var boundCoords1 = coords[0];
			var lat1 = boundCoords1.split(",")[0].trim();
			var lon1 = boundCoords1.split(",")[1].trim();
			var boundCoords2 = coords[1];
			var lat2 = boundCoords2.split(",")[0].trim();
			var lon2 = boundCoords2.split(",")[1].trim();
			if ( !jQuery.isNumeric( lat1 ) || !jQuery.isNumeric( lon1 ) ||
			!jQuery.isNumeric( lat2 ) || !jQuery.isNumeric( lon2 ) ) {
				return;
			}
			if ( lat1 < -90 || lat1 > 90 || lon1 < -180 || lon1 > 180 ||
				lat2 < -90 || lat2 > 90 || lon2 < -180 || lon2 > 180 ) {
				return;
			}
			var bound1 = new google.maps.LatLng(lat1, lon1);
			var bound2 = new google.maps.LatLng(lat2, lon2);
			var bounds = new google.maps.LatLngBounds();
			bounds.extend(bound1);
			bounds.extend(bound2);
			map.fitBounds(bounds);
		}
	}

	function setMarkerFromAddress() {
		var currentMapName = coordsInput.attr('name');
		var allFeedersForCurrentMap = jQuery('[data-feeds-to-map="' + currentMapName + '"]').map( function() {
			return $( this ).val()
		}).get();
		if ( allFeedersForCurrentMap.length > 0 ) {
			// Assemble a single string from all the address inputs that feed to this map.
			var addressText = allFeedersForCurrentMap.join( ', ' );
		} else {
			// No other inputs feed to this map, so use the standard "Enter address here" input.
			var addressText = inputDiv.find('.pfAddressInput').val();
		}
		if ( mapService === "Google Maps" ) {
			geocoder.geocode( { 'address': addressText }, function(results, status) {
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
			.done( function( result ) {
				if ( result.length === 0 ) {
					alert("Geocode was not successful");
					return;
				}
				var lat = result[0].lat;
				var lon = result[0].lon;
				// Use the specified bounds - this is better
				// than a preset zoom, because it handles the
				// precision correctly for countries, cities,
				// etc.
				var boundsStr = String(result[0].boundingbox);
				var vals = boundsStr.split(",");
				var bottom = vals[0];
				var top = vals[1];
				var left = vals[2];
				var right = vals[3];
				if ( mapService === "OpenLayers" ) {
					var olPoint = toOpenLayersLonLat( map, lat, lon );
					openLayersSetMarker( olPoint );
					map.setCenter( olPoint );
					var fromProjection = new OpenLayers.Projection("EPSG:4326"); // transform from WGS 1984
					var toProjection = map.getProjectionObject(); // to Spherical Mercator Projection
					var bounds = new OpenLayers.Bounds(left,bottom,right,top).transform(fromProjection,toProjection);
					map.zoomToExtent(bounds);
				} else if ( mapService === "Leaflet" ) {
					var lPoint = L.latLng( lat, lon );
					leafletSetMarker( lPoint );
					map.fitBounds([ [ bottom, left ], [ top, right ] ]);
				}
			});
		}
	}

	function setMarkerFromCoordinates() {
		var coordsText = coordsInput.val();
		var coordsParts = coordsText.split(",");
		if ( coordsParts.length != 2 ) {
			coordsInput.val('');
			return;
		}
		var lat = coordsParts[0].trim();
		var lon = coordsParts[1].trim();
		if ( !jQuery.isNumeric( lat ) || !jQuery.isNumeric( lon ) ) {
			coordsInput.val('');
			return;
		}
		if ( lat < -90 || lat > 90 || lon < -180 || lon > 180 ) {
			coordsInput.val('');
			return;
		}
		if ( mapService === "Google Maps" ) {
			var gmPoint = new google.maps.LatLng( lat, lon );
			googleMapsSetMarker( gmPoint );
			map.setCenter( gmPoint );
		} else if ( mapService === "Leaflet" ){
			if ( imageHeight !== null && imageWidth !== null ) {
				lat *= imageWidth / 100;
				lon *= imageWidth / 100;
			}
			var lPoint = L.latLng( lat, lon );
			leafletSetMarker( lPoint );
			if ( imageHeight == null && imageWidth == null ) {
				map.setView( lPoint, 14 );
			}
		} else { // if ( mapService === "OpenLayers" ) {
			var olPoint = toOpenLayersLonLat( map, lat, lon );
			openLayersSetMarker( olPoint );
			map.setCenter( olPoint );
		}
	}

	function toOpenLayersLonLat( map, lat, lon ) {
		return new OpenLayers.LonLat( lon, lat ).transform(
			new OpenLayers.Projection("EPSG:4326"), // transform from WGS 1984
			map.getProjectionObject() // to Spherical Mercator Projection
		);
	}

	/**
	 * Round off a number to five decimal places - that's the most
	 * we need for coordinates, one would think.
	 *
	 * @param num
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
			google.maps.event.addListener( marker, 'dragend', function( event ) {
				googleMapsSetMarker( event.latLng );
			});

		} else {
			marker.setPosition(location);
		}
		var stringVal = pfRoundOffDecimal( location.lat() ) + ', ' + pfRoundOffDecimal( location.lng() );
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
			var lat = marker.getLatLng().lat;
			var lng = marker.getLatLng().lng;
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
			var stringVal = pfRoundOffDecimal( lat ) + ', ' +
				pfRoundOffDecimal( lng );
			coordsInput.val( stringVal )
				.attr( 'data-original-value', stringVal )
				.removeClass( 'modifiedInput' )
				.parent().find('.pfCoordsInputHelpers').remove();
		}

		marker.off('dragend').on('dragend', function( event ) {
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
		var realLonLat = location.clone();
		realLonLat.transform(
			map.getProjectionObject(), // transform from Spherical Mercator Projection
			new OpenLayers.Projection("EPSG:4326") // to WGS 1984
		);
		var stringVal = pfRoundOffDecimal( realLonLat.lat ) + ', ' + pfRoundOffDecimal( realLonLat.lon );
		coordsInput.val( stringVal )
			.attr( 'data-original-value', stringVal )
			.removeClass( 'modifiedInput' )
			.parent().find('.pfCoordsInputHelpers').remove();
	}
}

jQuery(document).ready( function() {
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
mw.hook('pf.addTemplateInstance').add( function( $newInstance ) {
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
