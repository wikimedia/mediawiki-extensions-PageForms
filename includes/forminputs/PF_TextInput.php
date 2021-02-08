<?php
/**
 * @file
 * @ingroup PF
 */

/**
 * @ingroup PFFormInput
 */
class PFTextInput extends PFFormInput {
	public static function getName() {
		return 'text';
	}

	public static function getDefaultPropTypes() {
		$defaultPropTypes = [
			'_num' => [ 'field_type' => 'number' ],
			'_uri' => [ 'field_type' => 'URL' ],
			'_ema' => [ 'field_type' => 'email' ]
		];
		if ( defined( 'SMWDataItem::TYPE_STRING' ) ) {
			// SMW < 1.9
			$defaultPropTypes['_str'] = [ 'field_type' => 'string' ];
		} else {
			$defaultPropTypes['_txt'] = [ 'field_type' => 'text' ];
		}
		return $defaultPropTypes;
	}

	public static function getOtherPropTypesHandled() {
		return [ '_wpg', '_geo' ];
	}

	public static function getDefaultPropTypeLists() {
		$defaultPropTypeLists = [
			'_num' => [ 'field_type' => 'number', 'is_list' => 'true', 'size' => '100' ],
			'_uri' => [ 'field_type' => 'URL', 'is_list' => 'true' ],
			'_ema' => [ 'field_type' => 'email', 'is_list' => 'true' ]
		];
		if ( defined( 'SMWDataItem::TYPE_STRING' ) ) {
			// SMW < 1.9
			$defaultPropTypeLists['_str'] = [ 'field_type' => 'string', 'is_list' => 'true', 'size' => '100' ];
		} else {
			$defaultPropTypeLists['_txt'] = [ 'field_type' => 'text', 'is_list' => 'true', 'size' => '100' ];
		}
		return $defaultPropTypeLists;
	}

	public static function getOtherPropTypeListsHandled() {
		return [ '_wpg' ];
	}

	public static function getDefaultCargoTypes() {
		return [
			'Integer' => [ 'field_type' => 'integer' ],
			'Float' => [ 'field_type' => 'number' ],
			'URL' => [ 'field_type' => 'URL' ],
			'Email' => [ 'field_type' => 'email' ],
			'File' => [ 'field_type' => 'string', 'uploadable' => true ],
			'String' => [ 'field_type' => 'string' ]
		];
	}

	public static function getOtherCargoTypesHandled() {
		return [ 'Page', 'Coordinates' ];
	}

	public static function getDefaultCargoTypeLists() {
		return [
			'Number' => [ 'field_type' => 'number', 'is_list' => 'true', 'size' => '100' ],
			'URL' => [ 'field_type' => 'URL', 'is_list' => 'true' ],
			'Email' => [ 'field_type' => 'email', 'is_list' => 'true' ],
			'String' => [ 'field_type' => 'text', 'is_list' => 'true', 'size' => '100' ]
		];
	}

	public static function getOtherCargoTypeListsHandled() {
		return [ 'Page' ];
	}

	/**
	 * Gets the HTML for the preview image or null if there is none.
	 *
	 * @since 2.3.3
	 *
	 * @param string $imageName
	 *
	 * @return string|null
	 */
	protected static function getPreviewImage( $imageName ) {
		$previewImage = null;

		$imageTitle = Title::newFromText( $imageName, NS_FILE );

		if ( !is_object( $imageTitle ) ) {
			return $previewImage;
		}

		$api = new ApiMain( new FauxRequest( [
			'action' => 'query',
			'format' => 'json',
			'prop' => 'imageinfo',
			'iiprop' => 'url',
			'titles' => $imageTitle->getFullText(),
			'iiurlwidth' => 200
		], true ), true );

		$api->execute();
		if ( defined( 'ApiResult::META_CONTENT' ) ) {
			$result = $api->getResult()->getResultData( null, [
				'BC' => [],
				'Types' => [],
				'Strip' => 'all',
			] );
		} else {
			$result = $api->getResultData();
		}

		$url = false;

		if ( array_key_exists( 'query', $result ) && array_key_exists( 'pages', $result['query'] ) ) {
			foreach ( $result['query']['pages'] as $page ) {
				if ( array_key_exists( 'imageinfo', $page ) ) {
					foreach ( $page['imageinfo'] as $imageInfo ) {
						$url = $imageInfo['thumburl'];
						break;
					}
				}
			}
		}

		if ( $url !== false ) {
			$previewImage = Html::element(
				'img',
				[ 'src' => $url ]
			);
		}

		return $previewImage;
	}

	public static function uploadableHTML( $input_id, $delimiter = null, $default_filename = null, $cur_value = '', array $other_args = [] ) {
		global $wgPageFormsSimpleUpload, $wgPageFormsScriptPath;

		if ( $wgPageFormsSimpleUpload ) {
			$text = "\n" . '<img class="loading" style="display:none;" src="' . $wgPageFormsScriptPath . '/skins/loading.gif"/>' . "\n";
			$buttonsText = Html::input( '',
				wfMessage( 'pf-simpleupload' )->escaped(),
				'button',
				[
					'class' => 'simpleupload_btn'
				]
			) . "\n";
			$buttonsText .= Html::input( '',
				wfMessage( 'htmlform-cloner-delete' )->escaped(),
				'button',
				[
					'class' => 'simpleupload_rmv_btn',
					'style' => 'display: none;'
				]
			) . "\n";
			$buttonsText .= Html::input( '', '', 'file',
				[
					'class' => 'simpleupload',
					'style' => 'width: 0; height: 0; overflow: hidden;'
				]
			) . "\n";

			$text .= Html::rawElement( 'span', [ 'class' => 'simpleUploadInterface' ], $buttonsText );

			return $text;
		}
		$upload_window_page = PFUtils::getSpecialPage( 'UploadWindow' );
		$query_string = "pfInputID=$input_id";
		if ( $delimiter != null ) {
			$query_string .= "&pfDelimiter=$delimiter";
		}
		if ( $default_filename != null ) {
			$query_string .= "&wpDestFile=$default_filename";
		}
		$upload_window_url = $upload_window_page->getPageTitle()->getFullURL( $query_string );
		$upload_label = wfMessage( 'upload' )->parse();
		// We need to set the size by default.
		$style = "width:650 height:500";

		$cssClasses = [ 'pfFancyBox', 'pfUploadable' ];

		$showPreview = array_key_exists( 'image preview', $other_args );

		if ( $showPreview ) {
			$cssClasses[] = 'pfImagePreview';
		}

		$linkAttrs = [
			'href' => $upload_window_url,
			'class' => implode( ' ', $cssClasses ),
			// The 'title' parameter sets the label below the
			// window; we're leaving it blank, because otherwise
			// it can by mistaken by users for a button, leading
			// to confusion.
			// 'title' => $upload_label,
			'rev' => $style,
			'data-input-id' => $input_id,
			'data-type' => 'iframe'
		];

		$text = "\t" . Html::element( 'a', $linkAttrs, $upload_label ) . "\n";

		if ( $showPreview ) {
			$text .= Html::rawElement(
				'div',
				[ 'id' => $input_id . '_imagepreview', 'class' => 'pfImagePreviewWrapper' ],
				self::getPreviewImage( $cur_value )
			);
		}

		return $text;
	}

	public static function getHTML( $cur_value, $input_name, $is_mandatory, $is_disabled, array $other_args ) {
		global $wgPageFormsTabIndex, $wgPageFormsFieldNum;

		$className = 'createboxInput';
		if ( $is_mandatory ) {
			$className .= ' mandatoryField';
		}
		if ( array_key_exists( 'class', $other_args ) ) {
			$className .= ' ' . $other_args['class'];
		}
		if ( array_key_exists( 'unique', $other_args ) ) {
			$className .= ' uniqueField';
		}
		$input_id = "input_$wgPageFormsFieldNum";
		// Set size based on pre-set size, or field type - if field
		// type is set, possibly add validation too.
		// (This special handling should only be done if the field
		// holds a single value, not a list of values.)
		$size = 35;
		$inputType = '';
		if ( array_key_exists( 'field_type', $other_args ) &&
			( !array_key_exists( 'is_list', $other_args ) ||
			!$other_args['is_list'] )
		) {
			if ( $other_args['field_type'] == 'number' ) {
				$size = 10;
				$inputType = 'number';
			} elseif ( $other_args['field_type'] == 'integer' ) {
				$size = 8;
				$inputType = 'integer';
			} elseif ( $other_args['field_type'] == 'URL' ) {
				$size = 100;
				$inputType = 'URL';
			} elseif ( $other_args['field_type'] == 'email' ) {
				$size = 45;
				$inputType = 'email';
			}
		}
		if ( array_key_exists( 'size', $other_args ) ) {
			$size = $other_args['size'];
		}

		$inputAttrs = [
			'id' => $input_id,
			'tabindex' => $wgPageFormsTabIndex,
			'class' => $className,
			'size' => $size
		];
		if ( $is_disabled ) {
			$inputAttrs['disabled'] = 'disabled';
		}
		if ( array_key_exists( 'maxlength', $other_args ) ) {
			$inputAttrs['maxlength'] = $other_args['maxlength'];
		}
		if ( array_key_exists( 'placeholder', $other_args ) ) {
			$inputAttrs['placeholder'] = $other_args['placeholder'];
		}
		if ( array_key_exists( 'autocapitalize', $other_args ) ) {
			$inputAttrs['autocapitalize'] = $other_args['autocapitalize'];
		}
		if ( array_key_exists( 'feeds to map', $other_args ) ) {
			global $wgPageFormsMapsWithFeeders;
			$targetMapName = $other_args['feeds to map'];
			$wgPageFormsMapsWithFeeders[$targetMapName] = true;
			$inputAttrs['data-feeds-to-map'] = $targetMapName;
		}
		$text = Html::input( $input_name, $cur_value, 'text', $inputAttrs );

		if ( array_key_exists( 'uploadable', $other_args ) && $other_args['uploadable'] == true ) {
			if ( array_key_exists( 'is_list', $other_args ) && $other_args['is_list'] == true ) {
				if ( array_key_exists( 'delimiter', $other_args ) ) {
					$delimiter = $other_args['delimiter'];
				} else {
					$delimiter = ',';
				}
			} else {
				$delimiter = null;
			}
			if ( array_key_exists( 'default filename', $other_args ) ) {
				$default_filename = $other_args['default filename'];
			} else {
				$default_filename = '';
			}

			$text .= self::uploadableHTML( $input_id, $delimiter, $default_filename, $cur_value, $other_args );
		}
		$spanClass = 'inputSpan';
		if ( $inputType !== '' ) {
			$spanClass .= " {$inputType}Input";
		}
		if ( $is_mandatory ) {
			$spanClass .= ' mandatoryFieldSpan';
		}
		if ( array_key_exists( 'unique', $other_args ) ) {
			$spanClass .= ' uniqueFieldSpan';
		}
		$text = Html::rawElement( 'span', [ 'class' => $spanClass ], $text );
		return $text;
	}

	public static function getParameters() {
		$params = parent::getParameters();
		$params[] = [
			'name' => 'size',
			'type' => 'int',
			'description' => wfMessage( 'pf_forminputs_size' )->text()
		];
		$params[] = [
			'name' => 'maxlength',
			'type' => 'int',
			'description' => wfMessage( 'pf_forminputs_maxlength' )->text()
		];
		$params[] = [
			'name' => 'placeholder',
			'type' => 'string',
			'description' => wfMessage( 'pf_forminputs_placeholder' )->text()
		];
		$params[] = [
			'name' => 'uploadable',
			'type' => 'boolean',
			'description' => wfMessage( 'pf_forminputs_uploadable' )->text()
		];
		$params[] = [
			'name' => 'default filename',
			'type' => 'string',
			'description' => wfMessage( 'pf_forminputs_defaultfilename' )->text()
		];
		return $params;
	}

	/**
	 * Returns the HTML code to be included in the output page for this input.
	 * @return string
	 */
	public function getHtmlText() {
		return self::getHTML(
			$this->mCurrentValue,
			$this->mInputName,
			$this->mIsMandatory,
			$this->mIsDisabled,
			$this->mOtherArgs
		);
	}
}
