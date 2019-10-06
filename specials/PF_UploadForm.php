<?php
/**
 * Subclass of HTMLForm that provides the form section of Special:Upload.
 *
 * @author Yaron Koren
 * @file
 * @ingroup PF
 */

/**
 * @ingroup PFSpecialPages
 */
class PFUploadForm extends HTMLForm {
	protected $mWatch;
	protected $mForReUpload;
	protected $mSessionKey;
	protected $mHideIgnoreWarning;
	protected $mDestWarningAck;

	protected $mSourceIds;

	private $mTextTop;
	private $mTextAfterSummary;

	public function __construct( $options = [] ) {
		$this->mWatch = !empty( $options['watch'] );
		$this->mForReUpload = !empty( $options['forreupload'] );
		$this->mSessionKey = isset( $options['sessionkey'] )
				? $options['sessionkey'] : '';
		$this->mHideIgnoreWarning = !empty( $options['hideignorewarning'] );
		$this->mDestFile = isset( $options['destfile'] ) ? $options['destfile'] : '';

		$this->mTextTop = isset( $options['texttop'] ) ? $options['texttop'] : '';
		$this->mTextAfterSummary = isset( $options['textaftersummary'] ) ? $options['textaftersummary'] : '';

		$sourceDescriptor = $this->getSourceSection();
		$descriptor = $sourceDescriptor
			+ $this->getDescriptionSection()
			+ $this->getOptionsSection();

		Hooks::run( 'UploadFormInitDescriptor', [ &$descriptor ] );
		parent::__construct( $descriptor, 'upload' );

		# Set some form properties
		$this->setSubmitText( $this->msg( 'uploadbtn' )->text() );
		$this->setSubmitName( 'wpUpload' );
		$this->setSubmitTooltip( 'upload' );
		$this->setId( 'mw-upload-form' );

		# Build a list of IDs for javascript insertion
		$this->mSourceIds = [];
		foreach ( $sourceDescriptor as $key => $field ) {
			if ( !empty( $field['id'] ) ) {
				$this->mSourceIds[] = $field['id'];
			}
		}
		// added for Page Forms
		$this->addHiddenField( 'pfInputID', $options['pfInputID'] );
		$this->addHiddenField( 'pfDelimiter', $options['pfDelimiter'] );
	}

	/**
	 * Get the descriptor of the fieldset that contains the file source
	 * selection. The section is 'source'
	 *
	 * @return array Descriptor array
	 */
	protected function getSourceSection() {
		if ( $this->mSessionKey ) {
			return [
				'SessionKey' => [
					'id' => 'wpSessionKey',
					'type' => 'hidden',
					'default' => $this->mSessionKey,
				],
				'SourceType' => [
					'id' => 'wpSourceType',
					'type' => 'hidden',
					'default' => 'Stash',
				],
			];
		}

		$canUploadByUrl = UploadFromUrl::isEnabled() && $this->getUser()->isAllowed( 'upload_by_url' );
		$radio = $canUploadByUrl;
		$selectedSourceType = strtolower( $this->getRequest()->getText( 'wpSourceType', 'File' ) );

		$descriptor = [];

		if ( $this->mTextTop ) {
			$descriptor['UploadFormTextTop'] = [
				'type' => 'info',
				'section' => 'source',
				'default' => $this->mTextTop,
				'raw' => true,
			];
		}

		$maxUploadSizeFile = ini_get( 'upload_max_filesize' );
		$maxUploadSizeURL = ini_get( 'upload_max_filesize' );
		global $wgMaxUploadSize;
		if ( isset( $wgMaxUploadSize ) ) {
			if ( gettype( $wgMaxUploadSize ) == "array" ) {
				$maxUploadSizeFile = $wgMaxUploadSize['*'];
				$maxUploadSizeURL = $wgMaxUploadSize['url'];
			} else {
				$maxUploadSizeFile = $wgMaxUploadSize;
				$maxUploadSizeURL = $wgMaxUploadSize;
			}
		}

		$descriptor['UploadFile'] = [
			'class' => 'PFUploadSourceField',
			'section' => 'source',
			'type' => 'file',
			'id' => 'wpUploadFile',
			'label-message' => 'sourcefilename',
			'upload-type' => 'File',
			'radio' => &$radio,
			'help' => $this->msg( 'upload-maxfilesize',
					$this->getLanguage()->formatSize(
						wfShorthandToInteger( $maxUploadSizeFile )
					)
				)->parse() . ' ' . $this->msg( 'upload_source_file' )->escaped(),
			'checked' => $selectedSourceType == 'file'
		];
		if ( $canUploadByUrl ) {
			$descriptor['UploadFileURL'] = [
				'class' => 'UploadSourceField',
				'section' => 'source',
				'id' => 'wpUploadFileURL',
				'label-message' => 'sourceurl',
				'upload-type' => 'Url',
				'radio' => &$radio,
				'help' => $this->msg( 'upload-maxfilesize',
						$this->getLanguage()->formatSize( $maxUploadSizeURL )
					)->parse() . ' ' . $this->msg( 'upload_source_url' )->escaped(),
				'checked' => $selectedSourceType == 'url',
			];
		}
		Hooks::run( 'UploadFormSourceDescriptors', [ &$descriptor, &$radio, $selectedSourceType ] );

		$descriptor['Extensions'] = [
			'type' => 'info',
			'section' => 'source',
			'default' => $this->getExtensionsMessage(),
			'raw' => true,
		];
		return $descriptor;
	}

	/**
	 * Get the messages indicating which extensions are preferred and prohibitted.
	 *
	 * @return string HTML string containing the message
	 */
	protected function getExtensionsMessage() {
		# Print a list of allowed file extensions, if so configured. We ignore
		# MIME type here, it's incomprehensible to most people and too long.
		global $wgCheckFileExtensions, $wgStrictFileExtensions,
		$wgFileExtensions, $wgFileBlacklist;

		if ( $wgCheckFileExtensions ) {
			if ( $wgStrictFileExtensions ) {
				# Everything not permitted is banned
				$extensionsList =
					'<div id="mw-upload-permitted">' .
						$this->msg( 'upload-permitted', $this->getLanguage()->commaList( $wgFileExtensions ) )->parse() .
					"</div>\n";
			} else {
				# We have to list both preferred and prohibited
				$extensionsList =
					'<div id="mw-upload-preferred">' .
						$this->msg( 'upload-preferred', $this->getLanguage()->commaList( $wgFileExtensions ) )->parse() .
					"</div>\n" .
					'<div id="mw-upload-prohibited">' .
						$this->msg( 'upload-prohibited', $this->getLanguage()->commaList( $wgFileBlacklist ) )->parse() .
					"</div>\n";
			}
		} else {
			# Everything is permitted.
			$extensionsList = '';
		}
		return $extensionsList;
	}

	/**
	 * Get the descriptor of the fieldset that contains the file description
	 * input. The section is 'description'
	 *
	 * @return array Descriptor array
	 */
	protected function getDescriptionSection() {
		$descriptor = [
			'DestFile' => [
				'type' => 'text',
				'section' => 'description',
				'id' => 'wpDestFile',
				'label-message' => 'destfilename',
				'size' => 60,
				'default' => $this->mDestFile,
				# @todo FIXME: Hack to work around poor handling of the 'default' option in HTMLForm
				'nodata' => strval( $this->mDestFile ) !== '',
			],
			'UploadDescription' => [
				'type' => 'textarea',
				'section' => 'description',
				'id' => 'wpUploadDescription',
				'label-message' => $this->mForReUpload
					? 'filereuploadsummary'
					: 'fileuploadsummary',
				'cols' => 80,
				'rows' => 4,
			],
/*
			'EditTools' => array(
				'type' => 'edittools',
				'section' => 'description',
			),
*/
			'License' => [
				'type' => 'select',
				'class' => 'Licenses',
				'section' => 'description',
				'id' => 'wpLicense',
				'label-message' => 'license',
			],
		];

		if ( $this->mTextAfterSummary ) {
			$descriptor['UploadFormTextAfterSummary'] = [
				'type' => 'info',
				'section' => 'description',
				'default' => $this->mTextAfterSummary,
				'raw' => true,
			];
		}

		if ( $this->mForReUpload ) {
			$descriptor['DestFile']['readonly'] = true;
		}

		global $wgUseCopyrightUpload;
		if ( $wgUseCopyrightUpload ) {
			$descriptor['UploadCopyStatus'] = [
				'type' => 'text',
				'section' => 'description',
				'id' => 'wpUploadCopyStatus',
				'label-message' => 'filestatus',
			];
			$descriptor['UploadSource'] = [
				'type' => 'text',
				'section' => 'description',
				'id' => 'wpUploadSource',
				'label-message' => 'filesource',
			];
		}

		return $descriptor;
	}

	/**
	 * Get the descriptor of the fieldset that contains the upload options,
	 * such as "watch this file". The section is 'options'
	 *
	 * @return array Descriptor array
	 */
	protected function getOptionsSection() {
		$descriptor = [
			'Watchthis' => [
				'type' => 'check',
				'id' => 'wpWatchthis',
				'label-message' => 'watchthisupload',
				'section' => 'options',
			]
		];
		if ( !$this->mHideIgnoreWarning ) {
			$descriptor['IgnoreWarning'] = [
				'type' => 'check',
				'id' => 'wpIgnoreWarning',
				'label-message' => 'ignorewarnings',
				'section' => 'options',
			];
		}
		$descriptor['DestFileWarningAck'] = [
			'type' => 'hidden',
			'id' => 'wpDestFileWarningAck',
			'default' => $this->mDestWarningAck ? '1' : '',
		];

		return $descriptor;
	}

	/**
	 * Add the upload JS and show the form.
	 * @inheritDoc
	 */
	public function show() {
		// $this->addUploadJS();
		parent::show();
		// disable output - we'll print out the page manually,
		// taking the body created by the form, plus the necessary
		// Javascript files, and turning them into an HTML page
		global $wgTitle, $wgLanguageCode, $wgScriptPath,
			$wgPageFormsScriptPath,
			$wgXhtmlDefaultNamespace, $wgXhtmlNamespaces;

		$out = $this->getOutput();

		$out->disable();
		$wgTitle = SpecialPage::getTitleFor( 'Upload' );

		$text = <<<END
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="{$wgXhtmlDefaultNamespace}"
END;
		foreach ( $wgXhtmlNamespaces as $tag => $ns ) {
			$text .= "xmlns:{$tag}=\"{$ns}\" ";
		}
		$dir = PFUtils::getContLang()->isRTL() ? "rtl" : "ltr";
		$text .= "xml:lang=\"{$wgLanguageCode}\" lang=\"{$wgLanguageCode}\" dir=\"{$dir}\">";

		$text .= <<<END

<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<head>
<script src="{$wgScriptPath}/resources/lib/jquery/jquery.js"></script>
<script src="{$wgPageFormsScriptPath}/libs/PF_upload.js"></script>
</head>
<body>
{$out->getHTML()}
</body>
</html>


END;
		print $text;
		return true;
	}

	/**
	 * Empty function; submission is handled elsewhere.
	 *
	 * @return bool false
	 */
	function trySubmit() {
		return false;
	}

}
