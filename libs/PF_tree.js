/**
 * Defines the applyJStree() function, which turns an HTML "tree" of
 * checkboxes or radiobuttons into a dynamic and collapsible tree of options
 * using the jsTree JS library.
 *
 * @param $
 * @param mw
 * @param pf
 * @author Mathias Lidal
 * @author Yaron Koren
 * @author Priyanshu Varshney
 * @author Amr El-Absy
 */

 ( function($, mw, pf) {

	pf.TreeInput = function(elem) {
		this.element = elem;
		this.id = $(this.element).attr('id');
	};

	const TreeInput_proto = new pf.TreeInput();

	TreeInput_proto.setOptions = function() {
		const data = $(this.element).attr('data');
		this.data = JSON.parse(data);
		const params = $(this.element).attr('params');
		this.params = JSON.parse(params);
		this.delimiter = this.params.delimiter;
		this.multiple = this.params.multiple;
		this.values = [];
		this.cur_value = this.params.cur_value;

		const options = {
			'plugins' :  [ 'checkbox' ],
			'core' : {
				'data' : this.data,
				'multiple': this.multiple,
				'themes' : {
					"icons": false
				}
			},
			'checkbox': {
				'three_state': false,
				'cascade': "none"
			}
		};

		return options;
	};

	TreeInput_proto.check = function( data ) {
		const $input = $(this.element).next('input.PFTree_data');

		if ( this.multiple ) {
			this.values.push( data );
			const data_string = this.values.join( this.delimiter );
			$input.attr( 'value', data_string );
		} else {
			this.values.push( data );
			$input.attr('value', data);
		}
	};

	TreeInput_proto.uncheck = function( data ) {
		const $input = $( this.element ).next( 'input.PFTree_data' );

		this.values.splice( this.values.indexOf( data ), 1 );
		const data_string = this.values.join( this.delimiter );
		$input.attr( 'value', data_string );
	};

	TreeInput_proto.setCurValue = function() {
		if ( this.cur_value !== null && this.cur_value !== undefined && this.cur_value !== "" ) {
			const $input = $( this.element ).next( 'input.PFTree_data' );

			$input.attr( 'value', this.cur_value );
			this.values = this.cur_value.split( this.delimiter );
		}
	};

	pf.TreeInput.prototype = TreeInput_proto;

} (jQuery, mediaWiki, pf) );

$.fn.extend({
	applyJSTree: function() {
		const tree = new pf.TreeInput(this);
		const options = tree.setOptions();

		$(this).jstree(options);

		$(this).bind('select_node.jstree', (evt, data) => {
			tree.check(data.node.text);
		});
		$(this).bind('deselect_node.jstree', (evt, data) => {
			tree.uncheck(data.node.text);
		});

		tree.setCurValue();
	}
});
