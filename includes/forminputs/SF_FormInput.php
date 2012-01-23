<?php
/**
 * File holding the SFFormInput class.
 *
 * The predecessor of this file held several subclasses of SFFormInput. The
 * authors can not be sorted out with certainty anymore, thus are all listed
 * here.
 *
 * @author Yaron Koren
 * @author Jeffrey Stuckman
 * @author Matt Williamson
 * @author Patrick Nagel
 * @author Sanyam Goyal
 * @author Stephan Gambke
 * @file
 * @ingroup SF
 */

/**
 * Parent class for all form input classes.
 * @ingroup SFFormInput
 */
abstract class SFFormInput {

	protected $mInputNumber;
	protected $mCurrentValue;
	protected $mInputName;
	protected $mIsMandatory; // @deprecated, check for array_key_exists('mandatory', $this->mOtherArgs) instead
	protected $mIsDisabled;
	protected $mOtherArgs;

	protected $mJsInitFunctionData = array();
	protected $mJsValidationFunctionData = array();

	/**
	 * Constructor for the SFFormInput class.
	 *
	 * @param String $input_number
	 *		The number of the input in the form. For a simple HTML input element
	 *      this should end up in the id attribute in the format 'input_<number>'.
	 * @param String $cur_value
	 *		The current value of the input field. For a simple HTML input
	 *		element this should end up in the value attribute.
	 * @param String $input_name
	 *		The name of the input. For a simple HTML input element this should
	 *		end up in the name attribute.
	 * @param Array $other_args
	 *		An associative array of other parameters that were present in the
	 *		input definition.
	 */
	public function __construct( $input_number, $cur_value, $input_name, $disabled, $other_args ) {
		$this->mInputNumber = $input_number;
		$this->mCurrentValue = $cur_value;
		$this->mInputName = $input_name;
		$this->mOtherArgs = $other_args;
		$this->mIsDisabled = $disabled;
		$this->mIsMandatory = array_key_exists( 'mandatory', $other_args );
	}

	/**
	 * Returns the name of the input type this class handles.
	 *
	 * This is the name to be used in the field definition for the "input type"
	 * parameter.
	 *
	 * @return String The name of the input type this class handles.
	 * @fixme Should be declared abstract. Static functions cannot be abstract.
	 * Do we need this method at all? The name should be set outside this class
	 * when the input type is registered.
	 */
	public static function getName() {
		return null;
	}

	/**
	 * Returns the set of SMW property types which this input can
	 * handle. See SMW's SMW_DataValueFactory.php
	 *
	 * @return Array of Strings
	 */
	public static function getHandledPropertyTypes() {
		return null;
	}

	/**
	 * Returns the set of parameters for this form input.
	 */
	public static function getParameters() {
		$params = array();
		$params[] = array(
			'name' => 'mandatory',
			'type' => 'boolean',
			'description' => wfMsg( 'sf_forminputs_mandatory' )
		);
		$params[] = array(
			'name' => 'restricted',
			'type' => 'boolean',
			'description' => wfMsg( 'sf_forminputs_restricted' )
		);
		$params[] = array(
			'name' => 'class',
			'type' => 'string',
			'description' => wfMsg( 'sf_forminputs_class' )
		);
		$params[] = array(
			'name' => 'property',
			'type' => 'string',
			'description' => wfMsg( 'sf_forminputs_property' )
		);
		$params[] = array(
			'name' => 'default',
			'type' => 'string',
			'description' => wfMsg( 'sf_forminputs_default' )
		);
		return $params;
	}

	/**
	 * Return an array of the default parameters for this input where the
	 * parameter name is the key while the parameter value is the value.
	 *
	 * @return Array of Strings
	 */
	public function getDefaultParameters() {
		return null;
	}

	/**
	 * Returns the HTML code to be included in the output page for this input.
	 *
	 * Ideally this HTML code should provide a basic functionality even if the
	 * browser is not JavaScript capable. I.e. even without JavaScript the user
	 * should be able to input values.
	 *
	 * This function is not used yet.
	 */
	public function getHtmlText() {
		return null;
	}

	/**
	 *
	 * @return Boolean True, if this input type can handle lists
	 */
	public static function canHandleLists() {
		return false;
	}

	/**
	 * Returns the name and parameters for the initialization JavaScript
	 * function for this input type, if any.
	 *
	 * This function is not used yet.
	 */
	public function getJsInitFunctionData() {
		return $this->mJsInitFunctionData;
	}

	/**
	 * Returns the name and parameters for the validation JavaScript
	 * functions for this input type, if any.
	 *
	 * This function is not used yet.
	 */
	public function getJsValidationFunctionData() {
		return $this->mJsValidationFunctionData;
	}

	/**
	 * For each input type one or more JavaScript initialization functions may
	 * be specified.
	 *
	 * <b>This function is not used yet.</b>
	 *
	 * They are called to initialize the input after the page html has loaded
	 * (or for "multiple" templates after the page fragment has loaded).
	 *
	 * The JavaScript function specified here must be in the top level scope of
	 * the document. When it is called it will get the input's id attribute as
	 * the first parameter and the specified param as the second.
	 *
	 *
	 * Examples:
	 *
	 * Adding initFoo like this: <code>addJsInitFunctionData( "initFoo", "'bar'" );</code> will result in this JavaScript call: <code>initFoo( inputID, 'bar' );</code>.
	 *
	 * Adding initFoo like this: <code>addJsInitFunctionData( "initFoo", "array('bar', 'baz'" );</code> will result in this JavaScript call: <code>initFoo( inputID, array('bar', 'baz') );</code>.
	 *
	 *
	 * @param String $name The name of the initialization function.
	 * @param String $param The parameter passed to the initialization function.
	 */
	public function addJsInitFunctionData( $name, $param = 'null' ) {
		$this->mJsInitFunctionData[] = array( 'name' => $name, 'param' => $param );
	}

	/**
	 * For each input type one or more JavaScript validation functions may
	 * be specified.
	 *
	 * <b>Not used yet.</b>
	 *
	 * They are called to validate the input before the form is submitted for
	 * saving or preview.
	 *
	 * The JavaScript function specified here must be in the top level scope of
	 * the document. When it is called it will get the input's id attribute as
	 * the first parameter and the specified param as the second.
	 *
	 *
	 * Examples:
	 *
	 * Adding validateFoo like this: <code>addJsValidationFunctionData( "initFoo", "'bar'" );</code> will result in this JavaScript call: <code>validateFoo( inputID, 'bar' );</code>.
	 *
	 * Adding validateFoo like this: <code>addJsValidationFunctionData( "initFoo", "array('bar', 'baz'" );</code> will result in this JavaScript call: <code>validateFoo( inputID, array('bar', 'baz') );</code>.
	 *
	 *
	 * @param String $name The name of the initialization function.
	 * @param String $param The parameter passed to the initialization function.
	 */
	public function addJsValidationFunctionData( $name, $param = 'null' ) {
		$this->mJsValidationFunctionData[] = array( 'name' => $name, 'param' => $param );
	}

	/**
	 * Returns the set of SMW property types for which this input is
	 * meant to be the default one - ideally, no more than one input
	 * should declare itself the default for any specific type.
	 *
	 * @deprecated
	 * @return Array of arrays (key is the property type, value is an array of
	 *  default args to be used for this input)
	 */
	public static function getDefaultPropTypes() {
		return array();
	}

	/**
	 * Returns the set of SMW property types for which this input is
	 * meant to be the default one - ideally, no more than one input
	 * should declare itself the default for any specific type.
	 *
	 * @deprecated
	 * @return Array of arrays (key is the property type, value is an array of
	 *  default args to be used for this input)
	 */
	public static function getDefaultPropTypeLists() {
		return array();
	}

	/**
	 * Returns the set of SMW property types which this input can
	 * handle, but for which it isn't the default input.
	 *
	 * @deprecated
	 * @return Array of strings
	 */
	public static function getOtherPropTypesHandled() {
		return array();
	}

	/**
	 * Returns the set of SMW property types which this input can
	 * handle, but for which it isn't the default input.
	 *
	 * @deprecated
	 * @return Array of strings
	 */
	public static function getOtherPropTypeListsHandled() {
		return array();
	}

	/**
	 * Method to make new style input types compatible with old-style call from
	 * the SF parser.
	 *
	 * @deprecated Do not use/override this in new input type classes
	 *
	 * TODO: remove/refactor once SF uses forminput objects properly
	 */
	public static function getHTML( $cur_value, $input_name, $is_mandatory, $is_disabled, $other_args ) {

		global $sfgFieldNum, $wgOut;

		// create an input of the called class
		// TODO: get_called_class was introduced in PHP 5.3. The use of the
		// backtrace should be removed once support for PHP 5.2 is dropped.
		if ( function_exists('get_called_class') ) {
			$calledClass = get_called_class();
		} else {
			$bt = debug_backtrace();
			$calledClass = $bt[1]['args'][0][0];
		}
		
		$input = new $calledClass ( $sfgFieldNum, $cur_value, $input_name, $is_disabled, $other_args );

		// create calls to JS initialization and validation
		// TODO: This data should be transferred as a JSON blob and then be evaluated from a dedicated JS file
		$jstext = '';

		foreach ( $input->getJsInitFunctionData() as $jsInitFunctionData ) {

			$jstext .= <<<JAVASCRIPT
jQuery(function(){ jQuery('#input_$sfgFieldNum').SemanticForms_registerInputInit({$jsInitFunctionData['name']}, {$jsInitFunctionData['param']} ); });
JAVASCRIPT;
		}

		foreach ( $input->getJsValidationFunctionData() as $jsValidationFunctionData ) {

			$jstext .= <<<JAVASCRIPT
jQuery(function(){ jQuery('#input_$sfgFieldNum').SemanticForms_registerInputValidation( {$jsValidationFunctionData['name']}, {$jsValidationFunctionData['param']}); });
JAVASCRIPT;
		}

		// write JS code directly to the page's code
		$wgOut->addScript( Html::inlineScript(  $jstext ) );

		return $input->getHtmlText();
	}

}
