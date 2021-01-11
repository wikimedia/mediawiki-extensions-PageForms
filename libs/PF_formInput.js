/**
 * PF_formInput.js
 *
 * JS handling for #forminput and Special:FormStart.
 *
 * @author Yaron Koren
 */

( function ( $, mw ) {

	$.fn.displayPFFormInput = function() {
		var formLayouts = [];
		var autocompleteWidgetConfig = {};
		var possibleFormsStr = this.attr('data-possible-forms');
		if ( possibleFormsStr !== undefined ) {
			var menuOptions = [ {} ];
			var possibleForms = possibleFormsStr.split('|');
			for ( var possibleForm of possibleForms ) {
				menuOptions.push( {
					data: possibleForm,
					label: possibleForm
				} );
			}
			var formChooserDropdown = new OO.ui.DropdownInputWidget( {
				name: 'form',
				options: menuOptions
			} );
			var formChooserLayout = new OO.ui.FieldLayout( formChooserDropdown, {
				label: this.attr('data-form-label'),
				align: 'top'
			} );
			formLayouts.push(formChooserLayout);
		} else {
			// Only provide autofocus if there's no "form" dropdown -
			// otherwise, it might be confusing.
			if ( this.attr('data-autofocus') !== undefined ) {
				autocompleteWidgetConfig['autofocus'] = this.attr('data-autofocus');
			}
		}

		if ( this.attr('data-default-value') !== undefined ) {
			autocompleteWidgetConfig['value'] = this.attr('data-default-value');
		}
		if ( this.attr('data-autocomplete-data-type') !== undefined ) {
			autocompleteWidgetConfig['autocompletedatatype'] = this.attr('data-autocomplete-data-type');
		}
		if ( this.attr('data-autocomplete-settings') !== undefined ) {
			autocompleteWidgetConfig['autocompletesettings'] = this.attr('data-autocomplete-settings');
		}
		if ( this.attr('data-placeholder') !== undefined ) {
			autocompleteWidgetConfig['placeholder'] = this.attr('data-placeholder');
		}
		if ( this.attr('data-autocapitalize') !== undefined ) {
			autocompleteWidgetConfig['autocapitalize'] = this.attr('data-autocapitalize');
		}
		var pageNameInput = new pf.AutocompleteWidget( autocompleteWidgetConfig );
		var createOrEditButton = new OO.ui.ButtonInputWidget( {
			type: 'submit',
			label: this.attr('data-button-label'),
			value: 'Submit'
		} );
		var possibleNamespacesStr = this.attr('data-possible-namespaces');
		if ( possibleNamespacesStr !== undefined ) {
			// Special, non-OOUI-standard handling so that the
			// namespace and page name inputs can be on the same
			// line, replicating a full page name.
			var pageWithNamespaceItems = [];

			var possibleNamespaces = possibleNamespacesStr.split('|');
			menuOptions = [];
			for ( var possibleNamespace of possibleNamespaces ) {
				menuOptions.push( {
					data: possibleNamespace,
					label: possibleNamespace
				} );
			}
			var namespaceDropdown = new OO.ui.DropdownInputWidget( {
				name: 'namespace',
				options: menuOptions,
				classes: [ 'pfNamespaceDropdown' ]
			} );
			pageWithNamespaceItems.push( namespaceDropdown );
			var colonLabel = new OO.ui.LabelWidget( {
				label: ":"
			} );
			pageWithNamespaceItems.push( colonLabel );
			pageWithNamespaceItems.push( pageNameInput );
			pageWithNamespaceItems.push( createOrEditButton );
			var layout = new OO.ui.HorizontalLayout( {
				items: pageWithNamespaceItems,
				classes: [ 'pfPageWithNamespace' ]
			} );
		} else {
			var layout = new OO.ui.ActionFieldLayout(
				pageNameInput,
				createOrEditButton,
				{
					align: 'top'
				}
			);
		}
		formLayouts.push(layout);
		var fieldset = new OO.ui.FieldsetLayout( {
			items: formLayouts
		} );

		this.append( fieldset.$element );

		// We could put this all into an OO.ui.FormLayout instance,
		// but it would get complicated, due to the hidden inputs
		// needed, so we'll leave that to the PHP to do.
	};

	$(document).ready( function() {
		$( '.pfFormInputWrapper' ).each( function() {
			$(this).displayPFFormInput();
		});
	});

}( jQuery, mediaWiki ) );
