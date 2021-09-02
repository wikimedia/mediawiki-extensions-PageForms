/**
 * An OOUI-based widget for an autocompleting text input
 * within the spreadsheet-style display that uses the
 * Page Forms 'pfautocomplete' API.
 *
 * @class
 * @extends OO.ui.TextInputWidget
 * @param {Object} config Configuration Options
 * @author Yash Varshney
 */
 pf.spreadsheetAutocompleteWidget = function( config ) {
	this.config = config || {};
	// Parent constructor
	var textInputConfig = {
		// This turns off the local, browser-based autocompletion,
		// which would normally suggest values that the user has
		// typed before on that computer.
		autocomplete: false
	};

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

OO.inheritClass( pf.spreadsheetAutocompleteWidget, OO.ui.TextInputWidget );
OO.mixinClass( pf.spreadsheetAutocompleteWidget, OO.ui.mixin.LookupElement );

/**
 * @inheritdoc
 */
pf.spreadsheetAutocompleteWidget.prototype.getLookupRequest = function () {
    var
		value = this.getValue(),
		deferred = $.Deferred(),
		api,
		requestParams;
	// sometimes it happens that on double clicking the cell
	// a space is automatically added to the value inside the
	// editor and hence we get "No Matches found" so we can
	// simply remove that space.
	if ( value[0] == ' ' ) {
		value = value.substring(1);
	}
	api = new mw.Api();
	requestParams = {
		action: 'pfautocomplete',
		format: 'json',
		substr: value,
	};
	if( this.config.autocompletedatatype == 'category' ) {
		requestParams.category = this.config.autocompletesettings;
	} else if ( this.config.autocompletedatatype == 'cargo field' ) {
		var table_and_field = this.config.autocompletesettings.split( '|' );
		requestParams.cargo_table = table_and_field[0];
		requestParams.cargo_field = table_and_field[1];
	} else if ( this.config.autocompletedatatype == 'property' ) {
		requestParams.property = this.config.autocompletesettings;
	} else if ( this.config.autocompletedatatype == 'concept' ) {
		requestParams.concept = this.config.autocompletesettings;
	} else if( this.config.autocompletedatatype == 'dep_on' ) {
		var dep_field_opts = this.getDependentFieldOpts( this.config.data_y, this.config.dep_on_field );

		if (dep_field_opts.prop.indexOf('|') === -1) {
			requestParams.property = dep_field_opts.prop;
			requestParams.baseprop = dep_field_opts.base_prop;
			requestParams.basevalue = dep_field_opts.base_value;
		} else {
			var cargoTableAndFieldStr = dep_field_opts.prop;
			var baseCargoTableAndFieldStr = dep_field_opts.base_prop;
			requestParams.cargo_table = cargoTableAndFieldStr.split( '|' )[0];
			requestParams.cargo_field = cargoTableAndFieldStr.split( '|' )[1];
			requestParams.base_cargo_table = baseCargoTableAndFieldStr.split('|')[0];
			requestParams.base_cargo_field = baseCargoTableAndFieldStr.split('|')[1];
		}
		requestParams.basevalue = dep_field_opts.base_value;
	}
	return api.get( requestParams );
}

/**
 * @inheritdoc
 */
 pf.spreadsheetAutocompleteWidget.prototype.getLookupCacheDataFromResponse = function ( response ) {
	return response || [];
};

/**
 * @inheritdoc
 */
 pf.spreadsheetAutocompleteWidget.prototype.getLookupMenuOptionsFromData = function ( data ) {
	var item,
		items = [];

	if ( data.error ) {
		return this.getNoMatchesOOUIMenuOptionWidget();
	}
	if( this.config.autocompletedatatype == 'category'
		|| this.config.autocompletedatatype == 'cargo field'
		|| this.config.autocompletedatatype == 'dep_on'
		|| this.config.autocompletedatatype == 'concept'
		|| this.config.autocompletedatatype == 'property' ) {
		data = data.pfautocomplete;
		if ( data.length === 0 ) {
			return this.getNoMatchesOOUIMenuOptionWidget();
		}
		for ( let i = 0; i < data.length; i++ ) {
			item = new OO.ui.MenuOptionWidget( {
				data: data[ i ].title,
				label: this.highlightText( data[ i ].title )
			} );
			items.push( item );
		}
	} else if( this.config.autocompletedatatype == 'external data' ) {
		var self = this,
			wgPageFormsEDSettings = mw.config.get('wgPageFormsEDSettings'),
			name = this.config.autocompletesettings,
			edgValues = mw.config.get('edgValues'),
			valueFilter;
        data = {};
		if ( wgPageFormsEDSettings !== null && wgPageFormsEDSettings[name].title !== undefined && wgPageFormsEDSettings[name].title !== "" ) {
			data.title = edgValues[wgPageFormsEDSettings[name].title];
			if (data.title !== undefined && data.title !== null) {
				let i = 0;
				data.title.forEach(function () {
					var wgPageFormsAutocompleteOnAllChars = mw.config.get( 'wgPageFormsAutocompleteOnAllChars' );
					if ( wgPageFormsAutocompleteOnAllChars ) {
						valueFilter = data.title[i].toLowerCase().includes(self.getValue().toLowerCase());
					} else {
						valueFilter = self.checkIfAnyWordStartsWithInputValue( data.title[i], self.getValue() );
					}
					if ( valueFilter ) {
						item = new OO.ui.MenuOptionWidget( {
							data: data.title[i], label: self.highlightText(data.title[i])
						} );
						items.push(item);
					}
					i++;
				});
				if( items.length == 0 ) {
					return this.getNoMatchesOOUIMenuOptionWidget();
				}
			}
		} else {
			// this case will possibly come when the external data can't be fetched due to wrong data provided
			return this.getNoMatchesOOUIMenuOptionWidget();
		}
	}

	return items;
};

/**
 *
 * @param {string} suggestion
 * @return {Mixed} HtmlSnipppet
 *
 */
pf.spreadsheetAutocompleteWidget.prototype.highlightText = function ( suggestion ) {
	var searchTerm = this.getValue();
	if ( searchTerm[0] == ' ' ) {
		searchTerm = searchTerm.substring(1);
	}
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
    return new OO.ui.HtmlSnippet(t);
};
/**
 * Provides an OOUI's MenuOptionWidget with a "No Matches" label
 *
 * @return {Mixed} OOUi's MenuOptionWidget
 */
pf.spreadsheetAutocompleteWidget.prototype.getNoMatchesOOUIMenuOptionWidget = function() {
	return [
		new OO.ui.MenuOptionWidget( {
			data: this.getValue(),
			label: mw.message('pf-autocomplete-no-matches').text(),
			disabled: true
		} )
	];
}

/**
 * Checks if any "word" in the given string starts with the given search term.
 *
 * @param {string} string
 *
 * @param {string} curValue
 *
 * @return {boolean}
 *
 */
pf.spreadsheetAutocompleteWidget.prototype.checkIfAnyWordStartsWithInputValue = function( string, curValue ) {
	var regex = new RegExp('\\b' + curValue.toLowerCase());
	return string.toLowerCase().match(regex) !== null;
}

/**
 * Gives dependent field options which include
 * property, base property and base value
 *
 * @param {string} data_y
 * @param {string} dep_on_field
 * @return {Object} dep_field_opts
 *
 */
pf.spreadsheetAutocompleteWidget.prototype.getDependentFieldOpts = function( data_y, dep_on_field ) {
    var dep_field_opts = {};
    var $baseElement;
	$baseElement = $('td[data-y="'+data_y+'"][origname="'+dep_on_field+'"]');
    dep_field_opts.base_value = $baseElement.html();
    dep_field_opts.base_prop = mw.config.get('wgPageFormsFieldProperties')[dep_on_field] ||
		$baseElement.attr('name');
    dep_field_opts.prop = this.config['autocompletesettings'];

    return dep_field_opts;
}
