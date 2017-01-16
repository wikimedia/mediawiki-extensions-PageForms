/**
 * @author Yaron Koren
 * @author Paladox
 */

/*jshint -W038 */

function setupMapFormInput( inputDiv, mapService ) {

	/**
	 * Round off a number to five decimal places - that's the most
	 * we need for coordinates, one would think.
	 */
	function pfRoundOffDecimal( num ) {
		return Math.round( num * 100000 ) / 100000;
	}

	var map,
		marker,
		markers;

	function googleMapsSetMarker( location ) {
		if ( marker === undefined ){
			marker = new google.maps.Marker( {
				position: location,
				map: map,
				draggable: true
			} );
			google.maps.event.addListener( marker, 'dragend', function( event ) {
				googleMapsSetMarker( event.latLng );
			});
		} else {
			marker.setPosition(location);
		}
		var stringVal = pfRoundOffDecimal( location.lat() ) + ', ' + pfRoundOffDecimal( location.lng() );
		inputDiv.find('.pfCoordsInput').val( stringVal );
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
		inputDiv.find('.pfCoordsInput').val( stringVal );
	}

	if ( mapService === "Google Maps" ) {
		var mapCanvas = inputDiv.find('.pfMapCanvas')[ 0 ];
		var mapOptions = {
			zoom: 1,
			center: new google.maps.LatLng( 0, 0 )
		};
		map = new google.maps.Map( mapCanvas, mapOptions );
		var geocoder = new google.maps.Geocoder();
		var update_timeout;

		// Let a click set the marker, while keeping the default
		// behavior (zoom and center) for double clicks.
		// Code copied from http://stackoverflow.com/a/8417447
		google.maps.event.addListener( map, 'click', function( event ) {
			update_timeout = setTimeout( function(){
				googleMapsSetMarker( event.latLng );
			}, 200 );
		});
		google.maps.event.addListener( map, 'dblclick', function( event ) {
			clearTimeout( update_timeout );
		});
	} else { // if ( mapService == "OpenLayers" ) {
		var mapCanvasID = inputDiv.find( '.pfMapCanvas' ).attr( 'id' );
		map = new OpenLayers.Map( mapCanvasID );
		map.addLayer( new OpenLayers.Layer.OSM() );
		map.zoomTo( 0 );
		markers = new OpenLayers.Layer.Markers( "Markers" );
		map.addLayer( markers );

		map.events.register( "click", map, function( e ) {
			var opx = map.getLayerPxFromViewPortPx(e.xy) ;
			var loc = map.getLonLatFromPixel( opx );
			openLayersSetMarker( loc );
		} );
	}

	function toOpenLayersLonLat( map, lat, lon ) {
		return new OpenLayers.LonLat( lon, lat ).transform(
			new OpenLayers.Projection( "EPSG:4326" ), // transform from WGS 1984
			map.getProjectionObject() // to Spherical Mercator Projection
		);
	}

	function setMarkerFromInput() {
		var coordsText = inputDiv.find('.pfCoordsInput').val();
		var coordsParts = coordsText.split(",");
		if ( coordsParts.length !== 2 ) {
			inputDiv.find('.pfCoordsInput').val('');
			return;
		}
		var lat = coordsParts[0].trim();
		var lon = coordsParts[1].trim();
		if ( !jQuery.isNumeric( lat ) || !jQuery.isNumeric( lon ) ) {
			inputDiv.find('.pfCoordsInput').val('');
			return;
		}
		if ( lat < -90 || lat > 90 || lon < -180 || lon > 180 ) {
			inputDiv.find('.pfCoordsInput').val('');
			return;
		}
		if ( mapService === "Google Maps" ) {
			var gmPoint = new google.maps.LatLng( lat, lon );
			googleMapsSetMarker( gmPoint );
			map.setCenter( gmPoint );
		} else { // if ( mapService == "OpenLayers" ) {
			var olPoint = toOpenLayersLonLat( map, lat, lon );
			openLayersSetMarker( olPoint );
			map.setCenter( olPoint, 14 );
		}
	}

	inputDiv.find('.pfUpdateMap').click( function() {
		setMarkerFromInput();
	});

	inputDiv.find('.pfCoordsInput').keypress( function( e ) {
		// Is this still necessary fro IE compatibility?
		var keycode = (e.keyCode ? e.keyCode : e.which);
		if ( keycode === 13 ) {
			setMarkerFromInput();
			// Prevent the form from getting submitted.
			e.preventDefault();
		}
	});

	function doLookup() {
		var addressText = inputDiv.find('.pfAddressInput').val(),
			alert;
		if ( mapService === "Google Maps" ) {
			geocoder.geocode( { 'address': addressText }, function(results, status) {
				if (status === google.maps.GeocoderStatus.OK) {
					map.setCenter(results[0].geometry.location);
					googleMapsSetMarker( results[0].geometry.location );
					map.setZoom(14);
				} else {
					alert("Geocode was not successful for the following reason: " + status);
				}
			});
		} // else { if ( mapService == "OpenLayers" ) {
			// Do nothing, for now - address lookup/geocode is
			// not yet enabled for OpenLayers.
		// }
	}

	inputDiv.find('.pfAddressInput').keypress( function( e ) {
		// Is this still necessary fro IE compatibility?
		var keycode = (e.keyCode ? e.keyCode : e.which);
		if ( keycode === 13 ) {
			doLookup();
			// Prevent the form from getting submitted.
			e.preventDefault();
		}
	} );

	inputDiv.find('.pfLookUpAddress').click( function() {
		doLookup();
	});


	if ( inputDiv.find('.pfCoordsInput').val() !== '' ) {
		setMarkerFromInput();
		map.setZoom(14);
	}
}

jQuery(document).ready( function() {
	jQuery(".pfGoogleMapsInput").each( function() {
		setupMapFormInput( jQuery(this), "Google Maps" );
	});
	jQuery(".pfOpenLayersInput").each( function() {
		setupMapFormInput( jQuery(this), "OpenLayers" );
	});
});
