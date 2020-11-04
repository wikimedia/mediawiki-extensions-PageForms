<?php
/**
 * The predecessor of this file held several subclasses of PFFormInput. The
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
 * @ingroup PF
 */

/**
 * Parent class for all form input classes.
 * @ingroup PFFormInput
 */
abstract class PFFormInput {

	protected $mInputNumber;
	protected $mCurrentValue;
	protected $mInputName;
	protected $mIsMandatory; // @deprecated, check for array_key_exists('mandatory', $this->mOtherArgs) instead
	protected $mIsDisabled;
	protected $mOtherArgs;

	protected $mJsInitFunctionData = [];
	protected $mJsValidationFunctionData = [];

	/**
	 * @param string $input_number The number of the input in the form. For a simple HTML input
	 *  element this should end up in the id attribute in the format 'input_<number>'.
	 * @param string $cur_value The current value of the input field. For a simple HTML input
	 *  element this should end up in the value attribute.
	 * @param string $input_name The name of the input. For a simple HTML input element this should
	 *  end up in the name attribute.
	 * @param bool $disabled Is this input disabled?
	 * @param array $other_args An associative array of other parameters that were present in the
	 *  input definition.
	 */
	public function __construct( $input_number, $cur_value, $input_name, $disabled, array $other_args ) {
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
	 * @return string The name of the input type this class handles.
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
	 * @return string[]
	 */
	public static function getHandledPropertyTypes() {
		return null;
	}

	/**
	 * Returns the set of parameters for this form input.
	 * @return array[]
	 */
	public static function getParameters() {
		$params = [];
		$params['mandatory'] = [
			'name' => 'mandatory',
			'type' => 'boolean',
			'description' => wfMessage( 'pf_forminputs_mandatory' )->text()
		];
		$params['restricted'] = [
			'name' => 'restricted',
			'type' => 'boolean',
			'description' => wfMessage( 'pf_forminputs_restricted' )->text()
		];
		$params['class'] = [
			'name' => 'class',
			'type' => 'string',
			'description' => wfMessage( 'pf_forminputs_class' )->text()
		];
		if ( defined( 'SMW_VERSION' ) ) {
			$params['property'] = [
				'name' => 'property',
				'type' => 'string',
				'description' => wfMessage( 'pf_forminputs_property' )->text()
			];
		}
		$params['default'] = [
			'name' => 'default',
			'type' => 'string',
			'description' => wfMessage( 'pf_forminputs_default' )->text()
		];
		return $params;
	}

	/**
	 * @param string $key
	 * @param array &$configVars
	 * @param array $functionData
	 * @param string $input_id
	 * @return array
	 */
	private static function updateFormInputJsFunctionData( $key, &$configVars, $functionData, $input_id ) {
		if ( array_key_exists( $key, $configVars ) ) {
			$functionDataArray = $configVars[ $key ];
		} else {
			$functionDataArray = [];
		}
		$functionDataArray[ $input_id ] = $functionData;
		return $functionDataArray;
	}

	/**
	 * Return an array of the default parameters for this input where the
	 * parameter name is the key while the parameter value is the value.
	 *
	 * @return string[]
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
	 * @return null
	 */
	public function getHtmlText() {
		return null;
	}

	/**
	 *
	 * @return bool True, if this input type can handle lists
	 */
	public static function canHandleLists() {
		return false;
	}

	/**
	 * Returns the name and parameters for the initialization JavaScript
	 * function for this input type, if any.
	 *
	 * This function is not used yet.
	 * @return array[]
	 */
	public function getJsInitFunctionData() {
		return $this->mJsInitFunctionData;
	}

	/**
	 * Returns the name and parameters for the validation JavaScript
	 * functions for this input type, if any.
	 *
	 * This function is not used yet.
	 * @return array[]
	 */
	public function getJsValidationFunctionData() {
		return $this->mJsValidationFunctionData;
	}

	/**
	 * Returns the names of the resource modules this input type uses.
	 *
	 * Returns the names of the modules as an array or - if there is only one
	 * module - as a string.
	 *
	 * @return null|string|array
	 */
	public function getResourceModuleNames() {
		return null;
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
	 * @param string $name The name of the initialization function.
	 * @param string|null $param The parameter passed to the initialization function.
	 */
	public function addJsInitFunctionData( $name, $param = null ) {
		if ( is_string( $param ) ) {
			$param = json_decode( $param );
		}
		$this->mJsInitFunctionData[] = [ 'name' => $name, 'param' => $param ];
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
	 * @param string $name The name of the initialization function.
	 * @param string $param The parameter passed to the initialization function.
	 */
	public function addJsValidationFunctionData( $name, $param = 'null' ) {
		$this->mJsValidationFunctionData[] = [ 'name' => $name, 'param' => $param ];
	}

	/**
	 * Returns the set of SMW property types for which this input is
	 * meant to be the default one - ideally, no more than one input
	 * should declare itself the default for any specific type.
	 *
	 * @deprecated
	 * @return array[] key is the property type, value is an array of
	 *  default args to be used for this input
	 */
	public static function getDefaultPropTypes() {
		return [];
	}

	/**
	 * Returns the set of SMW property types for which this input is
	 * meant to be the default one - ideally, no more than one input
	 * should declare itself the default for any specific type.
	 *
	 * @deprecated
	 * @return array[] key is the property type, value is an array of
	 *  default args to be used for this input
	 */
	public static function getDefaultPropTypeLists() {
		return [];
	}

	/**
	 * Returns the set of SMW property types which this input can
	 * handle, but for which it isn't the default input.
	 *
	 * @deprecated
	 * @return string[]
	 */
	public static function getOtherPropTypesHandled() {
		return [];
	}

	/**
	 * Returns the set of SMW property types which this input can
	 * handle, but for which it isn't the default input.
	 *
	 * @deprecated
	 * @return string[]
	 */
	public static function getOtherPropTypeListsHandled() {
		return [];
	}

	// Now the same set of methods, but for Cargo instead of SMW.
	public static function getDefaultCargoTypes() {
		return [];
	}

	public static function getDefaultCargoTypeLists() {
		return [];
	}

	public static function getOtherCargoTypesHandled() {
		return [];
	}

	public static function getOtherCargoTypeListsHandled() {
		return [];
	}

	/**
	 * Add the necessary JavaScript for this input.
	 */
	public function addJavaScript() {
		global $wgOut;

		// @TODO - the first works better for Special:RunQuery, and the
		// second better for Special:FormEdit? Try to find some solution
		// that always works correctly.
		// $output = $wgParser->getOutput();
		$output = $wgOut;
		$modules = $this->getResourceModuleNames();

		// Register modules for the input.
		if ( $modules !== null ) {
			$output->addModules( $modules );
		}

		if ( $this->getJsInitFunctionData() || $this->getJsValidationFunctionData() ) {
			$input_id = $this->mInputName == 'pf_free_text' ? 'pf_free_text' : 'input_' . $this->mInputNumber;
			$configVars = $output->getJsConfigVars();

			$initFunctionData = self::updateFormInputJsFunctionData( 'ext.pf.initFunctionData', $configVars, $this->getJsInitFunctionData(), $input_id );
			$validationFunctionData = self::updateFormInputJsFunctionData( 'ext.pf.validationFunctionData', $configVars, $this->getJsValidationFunctionData(), $input_id );

			$output->addJsConfigVars( [
				'ext.pf.initFunctionData' => $initFunctionData,
				'ext.pf.validationFunctionData' => $validationFunctionData
			] );
		}
	}

}
