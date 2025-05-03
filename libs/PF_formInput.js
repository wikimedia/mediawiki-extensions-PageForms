/**
 * PF_formInput.js
 *
 * JS handling for #forminput and Special:FormStart.
 *
 * @param $
 * @param mw
 * @author Yaron Koren
 */

( function( $, mw ) {

	$.fn.displayPFFormInput = function() {
		const formLayouts = [];
		const autocompleteWidgetConfig = {};
		const possibleFormsStr = this.attr('data-possible-forms');
		let menuOptions = [],
			layout;
		if ( possibleFormsStr !== undefined ) {
			const possibleForms = possibleFormsStr.split('|');
			for ( const possibleForm of possibleForms ) {
				menuOptions.push( {
					data: possibleForm,
					label: possibleForm
				} );
			}
			const formChooserText = new OO.ui.LabelWidget( {
				label: this.attr('data-form-label')
			} )
			const formChooserDropdown = new OO.ui.DropdownInputWidget( {
				name: 'form',
				options: menuOptions,
				classes: [ 'pfFormChooserDropdown' ]
			} );
			const formChooserHorizontalLayout = new OO.ui.HorizontalLayout( {
				items: [ formChooserText,  formChooserDropdown ],
			} );
			formLayouts.push(formChooserHorizontalLayout);
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
		if ( this.attr('data-size') !== undefined ) {
			autocompleteWidgetConfig['size'] = this.attr('data-size');
		}
		const createOrEditButton = new OO.ui.ButtonInputWidget( {
			type: 'submit',
			label: this.attr('data-button-label'),
			value: 'Submit',
			classes: [ 'pfCreateOrEditButton' ]
		} );
		const possibleNamespacesStr = this.attr('data-possible-namespaces');
		if ( possibleNamespacesStr !== undefined ) {
			// Special, non-OOUI-standard handling so that the
			// namespace and page name inputs can be on the same
			// line, replicating a full page name.
			const pageWithNamespaceItems = [];
			autocompleteWidgetConfig['classes'] = [ 'pfPageNameWithNamespace' ];
			const pageNameInput = new pf.AutocompleteWidget( autocompleteWidgetConfig );

			const possibleNamespaces = possibleNamespacesStr.split('|');
			menuOptions = [];
			for ( const possibleNamespace of possibleNamespaces ) {
				menuOptions.push( {
					data: possibleNamespace,
					label: possibleNamespace
				} );
			}
			const namespaceDropdown = new OO.ui.DropdownInputWidget( {
				name: 'namespace',
				options: menuOptions,
				classes: [ 'pfNamespaceDropdown' ]
			} );
			pageWithNamespaceItems.push( namespaceDropdown );
			const colonLabel = new OO.ui.LabelWidget( {
				label: ":"
			} );
			pageWithNamespaceItems.push( colonLabel );
			pageWithNamespaceItems.push( pageNameInput );
			pageWithNamespaceItems.push( createOrEditButton );
			layout = new OO.ui.HorizontalLayout( {
				items: pageWithNamespaceItems,
				classes: [ 'pfPageWithNamespace' ]
			} );
		} else {
			autocompleteWidgetConfig['classes'] = [ 'pfPageNameWithoutNamespace' ];
			const pageNameInput = new pf.AutocompleteWidget( autocompleteWidgetConfig );
			layout = new OO.ui.HorizontalLayout( {
				items: [ pageNameInput, createOrEditButton ]
			} );
		}
		formLayouts.push(layout);
		const fieldset = new OO.ui.FieldsetLayout( {
			items: formLayouts
		} );

		this.append( fieldset.$element );

		// We could put this all into an OO.ui.FormLayout instance,
		// but it would get complicated, due to the hidden inputs
		// needed, so we'll leave that to the PHP to do.
	};

	$( () => {
		$( '.pfFormInputWrapper' ).each( function() {
			$(this).displayPFFormInput();
		});
	});

}( jQuery, mediaWiki ) );
