/**
 * An OOUI-based widget for an autocompleting text input that uses the
 * Page Forms 'pfautocomplete' API.
 *
 * @class
 * @extends OO.ui.TextInputWidget
 *
 * @constructor
 * @param {Object} config Configuration options
 * @author Yaron Koren
 * @author Sahaj Khandelwal
 */

pf.AutocompleteWidget = function( config ) {
	this.config = config || {};
	// Parent constructor
	var textInputConfig = {
		// This turns off the local, browser-based autocompletion,
		// which would normally suggest values that the user has
		// typed before on that computer.
		autocomplete: false
	};
	if ( config.name !== undefined ) {
		textInputConfig.name = config.name;
	}
	if ( config.id !== undefined ) {
		textInputConfig.id = config.id;
	}
	if ( config.value !== undefined ) {
		textInputConfig.value = config.value;
	}
	if ( config.placeholder !== undefined ) {
		textInputConfig.placeholder = config.placeholder;
	}
	if ( config.autofocus !== undefined ) {
		textInputConfig.autofocus = config.autofocus;
	}
	OO.ui.TextInputWidget.call(this, textInputConfig);
	// Mixin constructor
	OO.ui.mixin.LookupElement.call(this, { highlightFirst: false });

	// dataCache will temporarily store entity id => entity data mappings of
	// entities, so that if we somehow then alter the text (add characters,
	// remove some) and then adjust our typing to form a known item,
	// it'll recognize it and know what the id was, without us having to
	// select it anew
	this.dataCache = {};
};

OO.inheritClass( pf.AutocompleteWidget, OO.ui.TextInputWidget );
OO.mixinClass( pf.AutocompleteWidget, OO.ui.mixin.LookupElement );

pf.AutocompleteWidget.prototype.apply = function ( element ) {
	this.config['autocompletesettings'] = element.attr('autocompletesettings')
	this.config['autocompletedatatype'] = element.attr('autocompletedatatype')
	this.setInputAttribute('name', element.attr('name'));
	this.setInputAttribute('autocompletesettings', this.config['autocompletesettings']);
	this.setInputAttribute('origname', element.attr('origname'));
	this.setInputId(element.attr('id'));
	this.setValue(element.val());
}
/**
 * @inheritdoc
 */
pf.AutocompleteWidget.prototype.getLookupRequest = function () {
	var
		value = this.getValue(),
		deferred = $.Deferred(),
		api,
		requestParams;

	api = new mw.Api();
	requestParams = {
		action: 'pfautocomplete',
		format: 'json',
		substr: value
	};
	if ( this.config.autocompletedatatype == 'category' ) {
		requestParams.category = this.config.autocompletesettings;
	} else if ( this.config.autocompletedatatype == 'namespace' ) {
		requestParams.namespace = this.config.autocompletesettings;
	} else if (this.config.autocompletedatatype == 'cargo field') {
		var data_source = this.config.autocompletesettings.split(',')[0];
		var table_and_field = data_source.split('|');
		requestParams.cargo_table = table_and_field[0];
		requestParams.cargo_field=table_and_field[1];
	}

	return api.get( requestParams );
};
/**
 * @inheritdoc
 */
pf.AutocompleteWidget.prototype.getLookupCacheDataFromResponse = function ( response ) {
	return response || [];
};
/**
 * @inheritdoc
 */
pf.AutocompleteWidget.prototype.getLookupMenuOptionsFromData = function ( data ) {
	var i,
		item,
		items = [];

	data = data.pfautocomplete;
	if ( this.maxSuggestions !== undefined ) {
		data = data.slice( 0, this.maxSuggestions - 1 );
	}
	if ( !data ) {
		return [];
	} else if ( data.length === 0 ) {
		// Generate a disabled option with a helpful message in case no results are found.
		return [
			new OO.ui.MenuOptionWidget( {
				disabled: true,
				label: mw.message( 'pf-select2-no-matches' ).text()
			} )
		];
	}
	for ( i = 0; i < data.length; i++ ) {
		item = new OO.ui.MenuOptionWidget( {
			// this data will be passed to onLookupMenuChoose when item is selected
			data: data[ i ].title,
			label: this.highlightText( data[ i ].title )
		} );
		items.push( item );
	}
	return items;
};

pf.AutocompleteWidget.prototype.highlightText = function ( suggestion ) {
	var searchTerm = this.getValue();
	var searchRegexp = new RegExp("(?![^&;]+;)(?!<[^<>]*)(" +
		searchTerm.replace(/([\^\$\(\)\[\]\{\}\*\.\+\?\|\\])/gi, "\\$1") +
		")(?![^<>]*>)(?![^&;]+;)", "gi");
	var itemLabel = suggestion;
	var loc = itemLabel.search(searchRegexp);
	var t;

	if (loc >= 0) {
		t = itemLabel.substr(0, loc) +
			 '<strong>' + itemLabel.substr(loc, searchTerm.length) + '</strong>' +
			itemLabel.substr(loc + searchTerm.length);
	} else {
		t = itemLabel;
	}

	return new OO.ui.HtmlSnippet( t );
};

pf.AutocompleteWidget.prototype.setInputAttribute = function (attr, value) {
	this.$input.attr(attr, value);
	return this;
};
