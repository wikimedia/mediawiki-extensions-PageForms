/**
 * Defines the applyFancytree() function, which turns an HTML "tree" of
 * checkboxes or radiobuttons into a dynamic and collapsible tree of options
 * using the Fancytree JS library.
 *
 * @author Mathias Lidal
 * @author Yaron Koren
 * @author Priyanshu Varshney
 */

( function( $, mw, pf ) {
	'use strict';

	// Attach the Fancytree widget to an existing <div id="tree"> element
	// and pass the tree options as an argument to the fancytree() function.
	jQuery.fn.applyFancytree = function() {
		var node = this;
		var selectMode = 2;
		var checkboxClass = "fancytree-checkbox";
		if (node.find(":input:radio").length) {
			selectMode = 1;
			checkboxClass = "fancytree-radio";
		}

		var newClassNames = {
			checkbox: checkboxClass,
			selected: "fancytree-selected"
		};

		node.fancytree({
			checkbox: true,
			autoScroll: true,
			minExpandLevel: 5,
			_classNames: newClassNames,
			selectMode: selectMode,
			// click event allows user to de/select the checkbox
			// by just selecting the title
			click: function(event, data) {
				var node = data.node,
				// Only for click and dblclick events
				// 'title' | 'prefix' | 'expander' | 'checkbox' | 'icon'
				targetType = data.targetType;
				if ( targetType === "expander" ) {
				data.node.toggleExpanded();
				} else if ( targetType === "checkbox" ||
					targetType === "title" ) {
					data.node.toggleSelected();
				}
				return false;
			},

			// Un/check checkboxes/radiobuttons recursively after
			// selection.
			select: function (event, data) {
				if ( data.node === undefined ) {
					return;
				}
				var inputkey = "chb-" + data.node.key;
				var checkBoxes =  node.find("[id='" + inputkey + "']");
				checkBoxes.attr("checked", !checkBoxes.attr("checked"));
			},
			// Prevent reappearing of checkbox when node is
			// collapsed.
			expand: function(select, data) {
				if ( data.node === undefined ) {
					return;
				}
				$("#chb-" + data.node.key).attr("checked",
					data.node.isSelected()).addClass("hidden");
			},

		});

		// Update real checkboxes according to selections.
		$.map(node.fancytree("getTree").getSelectedNodes(),
			function (data) {
				if ( data.node === undefined ) {
					return;
				}
				$("#chb-" + data.node.key).attr("checked", true);
				data.node.setActive();
			});
		var activeNode = node.fancytree("getTree").getActiveNode();
		if (activeNode !== null) {
			activeNode.setActive(false);
		}

	};

}( jQuery, mediaWiki, pf ) );
