<?php
/**
 * PFUploadWindow - used for uploading files from within a form.
 * This class is nearly identical to MediaWiki's SpecialUpload class, with
 * a few changes to remove skin CSS and HTML, and to populate the relevant
 * field in the form with the name of the uploaded form.
 *
 * @author Yaron Koren
 * @file
 * @ingroup PF
 */

use MediaWiki\MediaWikiServices;

/**
 * @ingroup PFSpecialPages
 */
class PFUploadWindow extends UnlistedSpecialPage {
	/**
	 * Constructor : initialise object
	 * Get data POSTed through the form and assign them to the object
	 * @param WebRequest|null $request Data posted.
	 */
	public function __construct( $request = null ) {
		parent::__construct( 'UploadWindow', 'upload' );
		$this->loadRequest( $request instanceof WebRequest ? $request : $this->getRequest() );
	}

	/** Misc variables */

	/** @var WebRequest|FauxRequest The request this form is supposed to handle */
	public $mRequest;
	public $mSourceType;

	/** @var UploadBase */
	public $mUpload;

	/** @var LocalFile */
	public $mLocalFile;
	public $mUploadClicked;

	protected $mTextTop;
	protected $mTextAfterSummary;

	/** User input variables from the "description" section */

	/** @var string The requested target file name */
	public $mDesiredDestName;
	public $mComment;
	public $mLicense;

	/** User input variables from the root section */
	public $mIgnoreWarning;
	public $mWatchThis;
	public $mCopyrightStatus;
	public $mCopyrightSource;

	/** Hidden variables */

	/** @var bool The user followed an "overwrite this file" link */
	public $mForReUpload;

	/** @var bool The user clicked "Cancel and return to upload form" button */
	public $mCancelUpload;
	public $mTokenOk;

	/** used by Page Forms */
	public $mInputID;
	public $mDelimiter;

	private $uploadFormTextTop;
	private $uploadFormTextAfterSummary;

	/**
	 * Initialize instance variables from request and create an Upload handler
	 *
	 * @param WebRequest $request The request to extract variables from
	 */
	protected function loadRequest( $request ) {
		$this->mRequest = $request;
		$this->mSourceType	= $request->getVal( 'wpSourceType', 'file' );
		$this->mUpload	    = UploadBase::createFromRequest( $request );
		$this->mUploadClicked     = $request->wasPosted()
			&& ( $request->getCheck( 'wpUpload' )
				|| $request->getCheck( 'wpUploadIgnoreWarning' ) );

		// Guess the desired name from the filename if not provided
		$this->mDesiredDestName = $request->getText( 'wpDestFile' );
		if ( !$this->mDesiredDestName ) {
			$this->mDesiredDestName = $request->getText( 'wpUploadFile' );
		}
		$this->mComment	   = $request->getText( 'wpUploadDescription' );
		$this->mLicense	   = $request->getText( 'wpLicense' );

		$this->mIgnoreWarning     = $request->getCheck( 'wpIgnoreWarning' )
			|| $request->getCheck( 'wpUploadIgnoreWarning' );
		$this->mWatchThis	 = $request->getBool( 'wpWatchthis' );
		$this->mCopyrightStatus   = $request->getText( 'wpUploadCopyStatus' );
		$this->mCopyrightSource   = $request->getText( 'wpUploadSource' );

		// updating a file
		$this->mForReUpload       = $request->getBool( 'wpForReUpload' );
		$this->mCancelUpload      = $request->getCheck( 'wpCancelUpload' )
		// b/w compat
			|| $request->getCheck( 'wpReUpload' );

		// If it was posted check for the token (no remote POST'ing with user credentials)
		$token = $request->getVal( 'wpEditToken' );
		if ( $this->mSourceType == 'file' && $token == null ) {
			// Skip token check for file uploads as that can't be faked via JS...
			// Some client-side tools don't expect to need to send wpEditToken
			// with their submissions, as that was new in 1.16.
			$this->mTokenOk = true;
		} else {
			$this->mTokenOk = $this->getUser()->matchEditToken( $token );
		}
		$this->mInputID	   = $request->getText( 'pfInputID' );
		$this->mDelimiter	 = $request->getText( 'pfDelimiter' );
		$this->uploadFormTextTop = '';
		$this->uploadFormTextAfterSummary = '';
	}

	/**
	 * Special page entry point
	 * @param string|null $par
	 */
	public function execute( $par ) {
		// Only output the body of the page.
		$this->getOutput()->setArticleBodyOnly( true );
		// This line is needed to get around Squid caching.
		$this->getOutput()->sendCacheControl();

		$this->setHeaders();
		$this->outputHeader();

		# Check uploading enabled
		if ( !UploadBase::isEnabled() ) {
			throw new ErrorPageError( 'uploaddisabled', 'uploaddisabledtext' );
		}

		# Check permissions
		$user = $this->getUser();
		$permissionRequired = UploadBase::isAllowed( $user );
		if ( $permissionRequired !== true ) {
			throw new PermissionsError( $permissionRequired );
		}

		# Check blocks
		if ( $this->getUser()->getBlock() ) {
			throw new UserBlockedError( $this->getUser()->getBlock() );
		}

		# Check whether we actually want to allow changing stuff
		if ( wfReadOnly() ) {
			throw new ReadOnlyError();
		}

		# Unsave the temporary file in case this was a cancelled upload
		if ( $this->mCancelUpload ) {
			if ( !$this->unsaveUploadedFile() ) {
				# Something went wrong, so unsaveUploadedFile showed a warning
				return;
			}
		}

		# Process upload or show a form
		if ( $this->mTokenOk && !$this->mCancelUpload
				&& ( $this->mUpload && $this->mUploadClicked ) ) {
			$this->processUpload();
		} else {
			# Backwards compatibility hook
			// Avoid PHP 7.1 warning from passing $this by reference
			$page = $this;
			if ( !Hooks::run( 'UploadForm:initial', [ &$page ] ) ) {
				wfDebug( "Hook 'UploadForm:initial' broke output of the upload form" );
				return;
			}

			$this->showUploadForm( $this->getUploadForm() );
		}

		# Cleanup
		if ( $this->mUpload ) {
			$this->mUpload->cleanupTempFile();
		}
	}

	/**
	 * Show the main upload form.
	 *
	 * @param PFUploadForm $form
	 */
	protected function showUploadForm( $form ) {
		# Add links if file was previously deleted
		if ( !$this->mDesiredDestName ) {
			$this->showViewDeletedLinks();
		}

		$form->show();
	}

	/**
	 * Get an UploadForm instance with title and text properly set.
	 *
	 * @param string $message HTML string to add to the form
	 * @param string $sessionKey Session key in case this is a stashed upload
	 * @param bool $hideIgnoreWarning
	 * @return PFUploadForm
	 */
	protected function getUploadForm( $message = '', $sessionKey = '', $hideIgnoreWarning = false ) {
		# Initialize form
		$form = new PFUploadForm( [
			'watch' => $this->watchCheck(),
			'forreupload' => $this->mForReUpload,
			'sessionkey' => $sessionKey,
			'hideignorewarning' => $hideIgnoreWarning,
			'texttop' => $this->uploadFormTextTop,
			'textaftersummary' => $this->uploadFormTextAfterSummary,
			'destfile' => $this->mDesiredDestName,
			'pfInputID' => $this->mInputID,
			'pfDelimiter' => $this->mDelimiter,
		] );
		$form->setTitle( $this->getPageTitle() );

		# Check the token, but only if necessary
		if ( !$this->mTokenOk && !$this->mCancelUpload
			&& ( $this->mUpload && $this->mUploadClicked )
		) {
			$form->addPreText( $this->msg( 'session_fail_preview' )->parse() );
		}

		# Add upload error message
		$form->addPreText( $message );

		# Add footer to form
		if ( !$this->msg( 'uploadfooter' )->isDisabled() ) {
			$output = $this->getOutput();
			if ( method_exists( $output, 'parseAsInterface' ) ) {
				$uploadFooter = $output->parseAsInterface( $this->msg( 'uploadfooter' )->plain() );
			} else {
				$uploadFooter = $output->parse( $this->msg( 'uploadfooter' )->plain() );
			}
			$form->addPostText( '<div id="mw-upload-footer-message">' . $uploadFooter . "</div>\n" );
		}

		return $form;
	}

	/**
	 * Shows the "view X deleted revivions link""
	 */
	protected function showViewDeletedLinks() {
		$title = Title::makeTitleSafe( NS_FILE, $this->mDesiredDestName );
		// Show a subtitle link to deleted revisions (to sysops et al only)
		if ( $title instanceof Title && ( $count = $title->isDeleted() ) > 0
			&& $this->getUser()->isAllowed( 'deletedhistory' ) ) {
			$link = $this->msg( $this->getUser()->isAllowed( 'delete' ) ? 'thisisdeleted' : 'viewdeleted' )
				->rawParams( $this->getSkin()->linkKnown(
					SpecialPage::getTitleFor( 'Undelete', $title->getPrefixedText() ),
					$this->msg( 'restorelink' )->numParams( $count )->escaped()
				)
			)->parse();
			$this->getOutput()->addHTML( "<div id=\"contentSub2\">{$link}</div>" );
		}

		// Show the relevant lines from deletion log (for still deleted files only)
		if ( $title instanceof Title && $title->isDeletedQuick() && !$title->exists() ) {
			$this->showDeletionLog( $this->getOutput(), $title->getPrefixedText() );
		}
	}

	/**
	 * Stashes the upload and shows the main upload form.
	 *
	 * Note: only errors that can be handled by changing the name or
	 * description should be redirected here. It should be assumed that the
	 * file itself is sane and has passed UploadBase::verifyFile. This
	 * essentially means that UploadBase::VERIFICATION_ERROR and
	 * UploadBase::EMPTY_FILE should not be passed here.
	 *
	 * @param string $message HTML message to be passed to mainUploadForm
	 */
	protected function recoverableUploadError( $message ) {
		$sessionKey = $this->mUpload->tryStashFile( $this->getUser() )->getStatusValue()->getValue()->getFileKey();
		$message = '<h2>' . $this->msg( 'uploadwarning' )->escaped() . "</h2>\n" .
			'<div class="errorbox">' . $message . "</div>\n";

		$form = $this->getUploadForm( $message, $sessionKey );
		$form->setSubmitText( $this->msg( 'upload-tryagain' )->text() );
		$this->showUploadForm( $form );
	}

	/**
	 * Stashes the upload, shows the main form, but adds an "continue anyway button"
	 *
	 * @param array $warnings
	 */
	protected function uploadWarning( $warnings ) {
		$sessionKey = $this->mUpload->tryStashFile( $this->getUser() )->getStatusValue()->getValue()->getFileKey();

		$warningHtml = '<h2>' . $this->msg( 'uploadwarning' )->escaped() . "</h2>\n"
			. '<ul class="warningbox">';
		foreach ( $warnings as $warning => $args ) {
				// Unlike the other warnings, this one can be worked around.
				if ( $warning == 'badfilename' ) {
					$this->mDesiredDestName = Title::makeTitle( NS_FILE, $args )->getText();
				}

				if ( $warning == 'exists' ) {
					$msg = self::getExistsWarning( $args );
				} elseif ( $warning == 'duplicate' ) {
					$msg = $this->getDupeWarning( $args );
				} elseif ( $warning == 'duplicate-archive' ) {
					$msg = "\t<li>" . $this->msg(
						'file-deleted-duplicate',
						[ Title::makeTitle( NS_FILE, $args )->getPrefixedText() ]
					)->parse() . "</li>\n";
				} else {
					if ( is_bool( $args ) ) {
						$args = [];
					} elseif ( !is_array( $args ) ) {
						$args = [ $args ];
					}
					$msg = "\t<li>" . $this->msg( $warning, $args )->parse() . "</li>\n";
				}
				$warningHtml .= $msg;
		}
		$warningHtml .= "</ul>\n";
		$warningHtml .= $this->msg( 'uploadwarning-text' )->parseAsBlock();

		$form = $this->getUploadForm( $warningHtml, $sessionKey, true );
		$form->setSubmitText( $this->msg( 'upload-tryagain' )->text() );
		$form->addButton( 'wpUploadIgnoreWarning', $this->msg( 'ignorewarning' )->text() );
		$form->addButton( 'wpCancelUpload', $this->msg( 'reuploaddesc' )->text() );

		$this->showUploadForm( $form );
	}

	/**
	 * Show the upload form with error message, but do not stash the file.
	 *
	 * @param string $message
	 */
	protected function uploadError( $message ) {
		$message = '<h2>' . $this->msg( 'uploadwarning' )->escaped() . "</h2>\n" .
			'<div class="errorbox">' . $message . "</div>\n";
		$this->showUploadForm( $this->getUploadForm( $message ) );
	}

	/**
	 * Do the upload.
	 * Checks are made in SpecialUpload::execute()
	 * @return array|bool|void
	 */
	protected function processUpload() {
		// Verify permissions
		$permErrors = $this->mUpload->verifyPermissions( $this->getUser() );
		if ( $permErrors !== true ) {
			return $this->getOutput()->showPermissionsErrorPage( $permErrors );
		}

		// Fetch the file if required
		$status = $this->mUpload->fetchFile();
		$output = $this->getOutput();
		if ( !$status->isOK() ) {
			if ( method_exists( $output, 'parseAsInterface' ) ) {
				$statusText = $output->parseAsInterface( $status->getWikiText() );
			} else {
				$statusText = $output->parse( $status->getWikiText() );
			}
			return $this->showUploadForm( $this->getUploadForm( $statusText ) );
		}

		// Avoid PHP 7.1 warning from passing $this by reference
		$page = $this;
		if ( !Hooks::run( 'UploadForm:BeforeProcessing', [ &$page ] ) ) {
			wfDebug( "Hook 'UploadForm:BeforeProcessing' broke processing the file.\n" );
			// This code path is deprecated. If you want to break upload processing
			// do so by hooking into the appropriate hooks in UploadBase::verifyUpload
			// and UploadBase::verifyFile.
			// If you use this hook to break uploading, the user will be returned
			// an empty form with no error message whatsoever.
			return;
		}

		// Upload verification
		$details = $this->mUpload->verifyUpload();
		if ( $details['status'] != UploadBase::OK ) {
			return $this->processVerificationError( $details );
		}

		$this->mLocalFile = $this->mUpload->getLocalFile();

		// Check warnings if necessary
		if ( !$this->mIgnoreWarning ) {
			$warnings = $this->mUpload->checkWarnings();
			if ( count( $warnings ) ) {
				return $this->uploadWarning( $warnings );
			}
		}

		// Get the page text if this is not a reupload
		if ( !$this->mForReUpload ) {
			$pageText = self::getInitialPageText( $this->mComment, $this->mLicense,
				$this->mCopyrightStatus, $this->mCopyrightSource );
		} else {
			$pageText = false;
		}
		$status = $this->mUpload->performUpload( $this->mComment, $pageText, $this->mWatchThis, $this->getUser() );
		if ( !$status->isGood() ) {
			if ( method_exists( $output, 'parseAsInterface' ) ) {
				$statusText = $output->parseAsInterface( $status->getWikiText() );
			} else {
				$statusText = $output->parse( $status->getWikiText() );
			}
			return $this->uploadError( $statusText );
		}

		// $this->getOutput()->redirect( $this->mLocalFile->getTitle()->getFullURL() );
		// Page Forms change - output Javascript to either
		// fill in or append to the field in original form, and
		// close the window
		# Chop off any directories in the given filename
		if ( $this->mDesiredDestName ) {
			$basename = $this->mDesiredDestName;
		} elseif ( is_a( $this->mUpload, 'UploadFromFile' ) ) {
			// MediaWiki 1.24+?
			$imageTitle = $this->mUpload->getTitle();
			$basename = $imageTitle->getText();
		} else {
			$basename = null;
		}

		$basename = str_replace( '_', ' ', $basename );
		// UTF8-decoding is needed for IE.
		// Actually, this doesn't seem to fix the encoding in IE
		// any more... and it messes up the encoding for all other
		// browsers. @TODO - fix handling in IE!
		// $basename = utf8_decode( $basename );

		$output = <<<END
		<script type="text/javascript">
		var input = parent.window.jQuery( parent.document.getElementById("{$this->mInputID}") );
END;

		if ( $this->mDelimiter == null ) {
			$output .= <<<END
		input.val( '$basename' );
		input.change();
END;
		} else {
			$output .= <<<END
		// if the current value is blank, set it to this file name;
		// if it's not blank and ends in a space or delimiter, append
		// the file name; if it ends with a normal character, append
		// both a delimiter and a file name; and add on a delimiter
		// at the end in any case
		var cur_value = parent.document.getElementById("{$this->mInputID}").value;

		if (cur_value === '') {
			input.val( '$basename' + '{$this->mDelimiter} ' );
			input.change();
		} else {
			var last_char = cur_value.charAt(cur_value.length - 1);
			if (last_char == '{$this->mDelimiter}' || last_char == ' ') {
				parent.document.getElementById("{$this->mInputID}").value += '$basename' + '{$this->mDelimiter} ';
				input.change();
			} else {
				parent.document.getElementById("{$this->mInputID}").value += '{$this->mDelimiter} $basename{$this->mDelimiter} ';
				input.change();
			}
		}

END;
		}
		$output .= <<<END
		parent.jQuery.fancybox.close( true );
	</script>

END;
		// $this->getOutput()->addHTML( $output );
		print $output;

		// Avoid PHP 7.1 warning from passing $this by reference
		$page = $this;
		Hooks::run( 'SpecialUploadComplete', [ &$page ] );
	}

	/**
	 * Get the initial image page text based on a comment and optional file status information
	 * @param string $comment
	 * @param string $license
	 * @param string $copyStatus
	 * @param string $source
	 * @return string
	 */
	public static function getInitialPageText( $comment = '', $license = '', $copyStatus = '', $source = '' ) {
		global $wgUseCopyrightUpload;
		if ( $wgUseCopyrightUpload ) {
			$licensetxt = '';
			if ( $license !== '' ) {
				$licensetxt = '== ' . wfMessage( 'license-header' )->inContentLanguage()->text() . " ==\n" . '{{' . $license . '}}' . "\n";
			}
			$pageText = '== ' . wfMessage( 'filedesc' )->inContentLanguage()->text() . " ==\n" . $comment . "\n" .
				'== ' . wfMessage( 'filestatus' )->inContentLanguage()->text() . " ==\n" . $copyStatus . "\n" .
				"$licensetxt" .
				'== ' . wfMessage( 'filesource' )->inContentLanguage()->text() . " ==\n" . $source;
		} else {
			if ( $license !== '' ) {
				$filedesc = $comment === '' ? '' : '== ' . wfMessage( 'filedesc' )->inContentLanguage()->text() . " ==\n" . $comment . "\n";
				$pageText = $filedesc .
					'== ' . wfMessage( 'license-header' )->inContentLanguage()->text() . " ==\n" . '{{' . $license . '}}' . "\n";
			} else {
				$pageText = $comment;
			}
		}
		return $pageText;
	}

	/**
	 * See if we should check the 'watch this page' checkbox on the form
	 * based on the user's preferences and whether we're being asked
	 * to create a new file or update an existing one.
	 *
	 * In the case where 'watch edits' is off but 'watch creations' is on,
	 * we'll leave the box unchecked.
	 *
	 * Note that the page target can be changed *on the form*, so our check
	 * state can get out of sync.
	 * @return bool
	 */
	protected function watchCheck() {
		if ( method_exists( MediaWikiServices::class, 'getUserOptionsLookup' ) ) {
			// MediaWiki 1.35+
			if ( MediaWikiServices::getInstance()->getUserOptionsLookup()
				->getOption( $this->getUser(), 'watchdefault' ) ) {
				// Watch all edits!
				return true;
			}
		} elseif ( $this->getUser()->getOption( 'watchdefault' ) ) {
			// Watch all edits!
			return true;
		}

		if ( method_exists( MediaWikiServices::class, 'getRepoGroup' ) ) {
			// MediaWiki 1.34+
			$local = MediaWikiServices::getInstance()->getRepoGroup()->getLocalRepo()
				->newFile( $this->mDesiredDestName );
		} else {
			$local = wfLocalFile( $this->mDesiredDestName );
		}
		if ( $local && $local->exists() ) {
			// We're uploading a new version of an existing file.
			// No creation, so don't watch it if we're not already.
			if ( method_exists( \MediaWiki\Watchlist\WatchlistManager::class, 'isWatched' ) ) {
				// MediaWiki 1.37+
				return MediaWikiServices::getInstance()->getWatchlistManager()
					->isWatched( $this->getUser(), $local->getTitle() );
			} else {
				return $this->getUser()->isWatched( $local->getTitle() );
			}
		} elseif ( method_exists( MediaWikiServices::class, 'getUserOptionsLookup' ) ) {
			// MediaWiki 1.35+
			// New page should get watched if that's our option.
			return MediaWikiServices::getInstance()->getUserOptionsLookup()
				->getOption( $this->getUser(), 'watchcreations' );
		} else {
			// New page should get watched if that's our option.
			return $this->getUser()->getOption( 'watchcreations' );
		}
	}

	/**
	 * Provides output to the user for a result of UploadBase::verifyUpload
	 *
	 * @param array $details Result of UploadBase::verifyUpload
	 * @throws MWException
	 */
	protected function processVerificationError( $details ) {
		global $wgFileExtensions;

		switch ( $details['status'] ) {
			/** Statuses that only require name changing */
			case UploadBase::MIN_LENGTH_PARTNAME:
				$this->recoverableUploadError( $this->msg( 'minlength1' )->escaped() );
				break;
			case UploadBase::ILLEGAL_FILENAME:
				$this->recoverableUploadError( $this->msg( 'illegalfilename',
					$details['filtered'] )->parse() );
				break;
			case UploadBase::OVERWRITE_EXISTING_FILE:
				$this->recoverableUploadError( $this->msg( $details['overwrite'] )->parse() );
				break;
			case UploadBase::FILETYPE_MISSING:
				$this->recoverableUploadError( $this->msg( 'filetype-missing' )->parse() );
				break;

			/** Statuses that require reuploading */
			case UploadBase::FILE_TOO_LARGE:
				$this->showUploadForm( $this->getUploadForm( $this->msg( 'file-too-large' )->escaped() ) );
				break;
			case UploadBase::EMPTY_FILE:
				$this->showUploadForm( $this->getUploadForm( $this->msg( 'emptyfile' )->escaped() ) );
				break;
			case UploadBase::FILETYPE_BADTYPE:
				$finalExt = $details['finalExt'];
				$this->uploadError(
					$this->msg( 'filetype-banned-type',
						// @todo Double escaping?
						htmlspecialchars( $finalExt ),
						implode(
							$this->msg( 'comma-separator' )->text(),
							$wgFileExtensions
						)
					)->numParams( count( $wgFileExtensions ) )->parse()
				);
				break;
			case UploadBase::VERIFICATION_ERROR:
				unset( $details['status'] );
				$code = array_shift( $details['details'] );
				$this->uploadError( $this->msg( $code, $details['details'] )->parse() );
				break;
			case UploadBase::HOOK_ABORTED:
				$error = $details['error'];
				$this->uploadError( $this->msg( $error )->parse() );
				break;
			default:
				throw new MWException( __METHOD__ . ": Unknown value `{$details['status']}`" );
		}
	}

	/**
	 * Remove a temporarily kept file stashed by saveTempUploadedFile().
	 * @private
	 * @return bool Success
	 */
	protected function unsaveUploadedFile() {
		if ( !( $this->mUpload instanceof UploadFromStash ) ) {
			return true;
		}
		$success = $this->mUpload->unsaveUploadedFile();
		if ( !$success ) {
			$this->getOutput()->showFatalError(
				$this->msg( 'filedeleteerror' )
					->params( $this->mUpload->getTempPath() )
					->escaped()
			);
			return false;
		} else {
			return true;
		}
	}

	/** Functions for formatting warnings */

	/**
	 * Formats a result of UploadBase::getExistsWarning as HTML
	 * This check is static and can be done pre-upload via AJAX
	 *
	 * @param array $exists The result of UploadBase::getExistsWarning
	 * @return string Empty string if there is no warning or an HTML fragment
	 * consisting of one or more <li> elements if there is a warning.
	 */
	public static function getExistsWarning( $exists ) {
		if ( !$exists ) {
			return '';
		}

		$file = $exists['file'];
		$filename = $file->getTitle()->getPrefixedText();
		$warning = [];

		if ( $exists['warning'] == 'exists' ) {
			// Exact match
			$warning[] = '<li>' . wfMessage( 'fileexists', $filename )->parse() . '</li>';
		} elseif ( $exists['warning'] == 'page-exists' ) {
			// Page exists but file does not
			$warning[] = '<li>' . wfMessage( 'filepageexists', $filename )->parse() . '</li>';
		} elseif ( $exists['warning'] == 'exists-normalized' ) {
			$warning[] = '<li>' . wfMessage( 'fileexists-extension', $filename,
				$exists['normalizedFile']->getTitle()->getPrefixedText() )->parse() . '</li>';
		} elseif ( $exists['warning'] == 'thumb' ) {
			// Swapped argument order compared with other messages for backwards compatibility
			$warning[] = '<li>' . wfMessage( 'fileexists-thumbnail-yes',
				$exists['thumbFile']->getTitle()->getPrefixedText(), $filename )->parse() . '</li>';
		} elseif ( $exists['warning'] == 'thumb-name' ) {
			// Image w/o '180px-' does not exists, but we do not like these filenames
			$name = $file->getName();
			$badPart = substr( $name, 0, strpos( $name, '-' ) + 1 );
			$warning[] = '<li>' . wfMessage( 'file-thumbnail-no', $badPart )->parse() . '</li>';
		} elseif ( $exists['warning'] == 'bad-prefix' ) {
			$warning[] = '<li>' . wfMessage( 'filename-bad-prefix', $exists['prefix'] )->parse() . '</li>';
		} elseif ( $exists['warning'] == 'was-deleted' ) {
			# If the file existed before and was deleted, warn the user of this
			$ltitle = SpecialPage::getTitleFor( 'Log' );
			$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
			$llink = $linkRenderer->makeKnownLink(
				$ltitle,
				wfMessage( 'deletionlog' )->escaped(),
				[],
				[
					'type' => 'delete',
					'page' => $filename
				]
			);
			$warning[] = '<li>' . wfMessage( 'filewasdeleted' )->rawParams( $llink )->parse() . '</li>';
		}

		return implode( "\n", $warning );
	}

	/**
	 * Construct a warning and a gallery from an array of duplicate files.
	 * @param File[] $dupes
	 * @return string
	 */
	public function getDupeWarning( $dupes ) {
		if ( $dupes ) {
			$out = $this->getOutput();
			$msg = "<gallery>";
			foreach ( $dupes as $file ) {
				$title = $file->getTitle();
				$msg .= $title->getPrefixedText() .
					"|" . $title->getText() . "\n";
			}
			$msg .= "</gallery>";
			$galleryText = $out->parseAsInterface( $msg );
			return "<li>" .
				$this->msg( "file-exists-duplicate" )->numParams( count( $dupes ) )->parseAsBlock() .
				$galleryText .
				"</li>\n";
		} else {
			return '';
		}
	}

}
