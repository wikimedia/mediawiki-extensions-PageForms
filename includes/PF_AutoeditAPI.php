<?php
/**
 * @author Stephan Gambke
 * @author Yaron Koren
 * @file
 * @ingroup PageForms
 */

use MediaWiki\EditPage\EditPage;
use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;

/**
 * @ingroup PageForms
 */
class PFAutoeditAPI extends ApiBase {

	public const ACTION_FORMEDIT = 0;
	public const ACTION_SAVE = 1;
	public const ACTION_PREVIEW = 2;
	public const ACTION_DIFF = 3;

	/**
	 * Error level used when a non-recoverable error occurred.
	 */
	public const ERROR = 0;

	/**
	 * Error level used when a recoverable error occurred.
	 */
	public const WARNING = 1;

	/**
	 * Error level used to give information that might be of interest to the user.
	 */
	public const NOTICE = 2;

	/**
	 * Error level used for debug messages.
	 */
	public const DEBUG = 3;

	private $mOptions = [];

	/**
	 * @var int|null
	 */
	private $mAction;

	/**
	 * @var int|null
	 */
	private $mStatus;
	private $mIsAutoEdit = false;

	/**
	 * Converts an options string into an options array and stores it
	 *
	 * @param string $options
	 * @return array Options
	 */
	function addOptionsFromString( $options ) {
		return $this->parseDataFromQueryString( $this->mOptions, $options );
	}

	/**
	 * @return array
	 */
	function getOptions() {
		return $this->mOptions;
	}

	/**
	 * Returns the action performed by the module.
	 *
	 * Return value is either null or one of ACTION_SAVE, ACTION_PREVIEW,
	 * ACTION_FORMEDIT
	 *
	 * @return int|null
	 */
	function getAction() {
		return $this->mAction;
	}

	/**
	 * @param array $options
	 */
	function setOptions( $options ) {
		$this->mOptions = $options;
	}

	/**
	 * @param string $option
	 * @param mixed $value
	 */
	function setOption( $option, $value ) {
		$this->mOptions[$option] = $value;
	}

	/**
	 * Returns the HTTP status
	 *
	 * 200 - ok
	 * 400 - error
	 *
	 * @return int
	 */
	function getStatus() {
		return $this->mStatus;
	}

	/**
	 * Evaluates the parameters, performs the requested API query, and sets up
	 * the result.
	 *
	 * The execute() method will be invoked when an API call is processed.
	 *
	 * The result data is stored in the ApiResult object available through
	 * getResult().
	 */
	function execute() {
		$this->prepareAction();
		$this->getOutput()->enableOOUI();

		if ( PFUtils::ignoreFormName( $this->mOptions['form'] ) ) {
			$this->logMessage( $this->msg( 'pf_autoedit_invalidform', $this->mOptions['form'] )->parse() );
			return;
		}

		try {
			$this->doAction();
		} catch ( Exception $e ) {
			// This has to be Exception, not MWException, due to
			// DateTime errors and possibly others.
			$this->logMessage( PFUtils::getParser()->recursiveTagParseFully( $e->getMessage() ), $e->getCode() );
		}

		$this->finalizeResults();
		$this->setHeaders();
	}

	function prepareAction() {
		// Get options from the request, but keep the explicitly set options.
		$data = $this->getRequest()->getValues();
		$this->mOptions = PFUtils::arrayMergeRecursiveDistinct( $data, $this->mOptions );

		PFUtils::getParser()->startExternalParse(
			null,
			ParserOptions::newFromUser( $this->getUser() ),
			Parser::OT_WIKI
		);

		// MW uses the parameter 'title' instead of 'target' when submitting
		// data for formedit action => use that
		if ( !array_key_exists( 'target', $this->mOptions ) && array_key_exists( 'title', $this->mOptions ) ) {
			$this->mOptions['target'] = $this->mOptions['title'];
			unset( $this->mOptions['title'] );
		}

		// if the 'query' parameter was used, unpack the param string
		if ( array_key_exists( 'query', $this->mOptions ) ) {
			$this->addOptionsFromString( $this->mOptions['query'] );
			unset( $this->mOptions['query'] );
		}

		// if an action is explicitly set in the form data, use that
		if ( array_key_exists( 'wpSave', $this->mOptions ) ) {
			// set action to 'save' if requested
			$this->mAction = self::ACTION_SAVE;
			unset( $this->mOptions['wpSave'] );
		} elseif ( array_key_exists( 'wpPreview', $this->mOptions ) ) {
			// set action to 'preview' if requested
			$this->mAction = self::ACTION_PREVIEW;
			unset( $this->mOptions['wpPreview'] );
		} elseif ( array_key_exists( 'wpDiff', $this->mOptions ) ) {
			// set action to 'preview' if requested
			$this->mAction = self::ACTION_DIFF;
			unset( $this->mOptions['wpDiff'] );
		} elseif ( array_key_exists( 'action', $this->mOptions ) ) {
			switch ( $this->mOptions['action'] ) {
				case 'pfautoedit':
					$this->mIsAutoEdit = true;
					$this->mAction = self::ACTION_SAVE;
					break;
				case 'preview':
					$this->mAction = self::ACTION_PREVIEW;
					break;
				default:
					$this->mAction = self::ACTION_FORMEDIT;
			}
		} else {
			// set default action
			$this->mAction = self::ACTION_FORMEDIT;
		}

		$hookQuery = null;

		// ensure 'form' key exists
		if ( array_key_exists( 'form', $this->mOptions ) ) {
			$hookQuery = $this->mOptions['form'];
		} else {
			$this->mOptions['form'] = '';
		}

		// ensure 'target' key exists
		if ( array_key_exists( 'target', $this->mOptions ) ) {
			if ( $hookQuery !== null ) {
				$hookQuery .= '/' . $this->mOptions['target'];
			}
		} else {
			$this->mOptions['target'] = '';
			$this->mOptions['blankTarget'] = true;
		}

		// Normalize form and target names

		$form = Title::newFromText( $this->mOptions['form'] );
		if ( $form !== null ) {
			$this->mOptions['form'] = $form->getPrefixedText();
		}

		$target = Title::newFromText( $this->mOptions['target'] );
		if ( $target !== null ) {
			$this->mOptions['target'] = $target->getPrefixedText();
		}

		MediaWikiServices::getInstance()->getHookContainer()->run( 'PageForms::SetTargetName', [ &$this->mOptions['target'], $hookQuery ] );

		// set html return status. If all goes well, this will not be changed
		$this->mStatus = 200;
	}

	/**
	 * Get the Title object of a form suitable for editing the target page.
	 *
	 * @return Title
	 * @throws MWException
	 */
	protected function getFormTitle() {
		// if no form was explicitly specified, try for explicitly set alternate forms
		if ( $this->mOptions['form'] === '' ) {
			$this->logMessage( 'No form specified. Will try to find the default form for the target page.', self::DEBUG );

			$formNames = [];

			// try explicitly set alternative forms
			if ( array_key_exists( 'alt_form', $this->mOptions ) ) {
				// cast to array to make sure we get an array, even if only a string was sent.
				$formNames = (array)$this->mOptions['alt_form'];
			}

			// if no alternate forms were explicitly set, try finding a default form for the target page
			if ( count( $formNames ) === 0 ) {
				// if no form and and no alt forms and no target page was specified, give up
				if ( $this->mOptions['target'] === '' ) {
					throw new MWException( $this->msg( 'pf_autoedit_notargetspecified' )->parse() );
				}

				$targetTitle = Title::newFromText( $this->mOptions['target'] );

				// if the specified target title is invalid, give up
				if ( !$targetTitle instanceof Title ) {
					throw new MWException( $this->msg( 'pf_autoedit_invalidtargetspecified', $this->mOptions['target'] )->parse() );
				}

				$formNames = PFFormLinker::getDefaultFormsForPage( $targetTitle );
				if ( count( $formNames ) === 0 ) {
					throw new MWException( $this->msg( 'pf_autoedit_noformfound' )->parse() );
				}

			}

			// if more than one form was found, issue a notice and give up
			// this happens if no default form but several alternate forms are defined
			if ( count( $formNames ) > 1 ) {
				throw new MWException( $this->msg( 'pf_autoedit_toomanyformsfound' )->parse(), self::DEBUG );
			}

			$this->mOptions['form'] = $formNames[0];

			$this->logMessage( 'Using ' . $this->mOptions['form'] . ' as default form.', self::DEBUG );
		}

		$formTitle = Title::makeTitleSafe( PF_NS_FORM, $this->mOptions['form'] );

		// If the given form is not a valid title, give up.
		if ( !( $formTitle instanceof Title ) ) {
			throw new MWException( $this->msg( 'pf_autoedit_invalidform', $this->mOptions['form'] )->parse() );
		}

		// If the form page is a redirect, follow the redirect.
		if ( $formTitle->isRedirect() ) {
			$this->logMessage( 'Form ' . $this->mOptions['form'] . ' is a redirect. Finding target.', self::DEBUG );

			$formWikiPage = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $formTitle );
			$formTitle = $formWikiPage->getContent( RevisionRecord::RAW )->getRedirectTarget();

			// If it's a double-redirect, give up.
			if ( $formTitle->isRedirect() ) {
				throw new MWException( $this->msg( 'pf_autoedit_redirectlimitexeeded', $this->mOptions['form'] )->parse() );
			}
		}

		// if specified or found form does not exist (e.g. is a red link), give up
		// FIXME: Throw specialized error message, so a list of alternative forms can be shown
		if ( !$formTitle->exists() ) {
			throw new MWException( $this->msg( 'pf_autoedit_invalidform', $this->mOptions['form'] )->parse() );
		}

		return $formTitle;
	}

	protected function setupEditPage( $targetContent ) {
		global $wgRequest;
		// Find existing target article if it exists, or create a new one.
		$targetTitle = Title::newFromText( $this->mOptions['target'] );

		// If the specified target title is invalid, give up.
		if ( !$targetTitle instanceof Title ) {
			throw new MWException( $this->msg( 'pf_autoedit_invalidtargetspecified', $this->mOptions['target'] )->parse() );
		}

		$article = new Article( $targetTitle );

		// set up a normal edit page
		// we'll feed it our data to simulate a normal edit
		$editor = new EditPage( $article );

		// set up form data:
		// merge data coming from the web request on top of some defaults
		$data = array_merge(
			[
				'wpTextbox1' => $targetContent,
				'wpUnicodeCheck' => 'ℳ𝒲♥𝓊𝓃𝒾𝒸ℴ𝒹ℯ',
				'wpSummary' => '',
				'wpStarttime' => wfTimestampNow(),
				'wpEditToken' => isset( $this->mOptions[ 'token' ] ) ? $this->mOptions[ 'token' ] : $this->getUser()->getEditToken(),
				'action' => 'submit',
			],
			$this->mOptions
		);

		// Checks if the "Watch this page" checkbox is checked
		if ( $wgRequest->getCheck( 'wpWatchthis' ) ) {
			$data[ 'wpWatchthis' ] = true;
		}

		// Checks if the "Minor edit" checkbox is checked
		if ( $wgRequest->getCheck( 'wpMinoredit' ) ) {
			$data[ 'wpMinoredit' ] = true;
		}

		if ( array_key_exists( 'format', $data ) ) {
			unset( $data['format'] );
		}

		// set up a faux request with the simulated data
		$request = new FauxRequest( $data, true );

		// and import it into the edit page
		$editor->importFormData( $request );
		$editor->pfFauxRequest = $request;

		return $editor;
	}

	/**
	 * Sets the output HTML of wgOut as the module's result
	 */
	protected function setResultFromOutput() {
		// turn on output buffering
		ob_start();

		// generate preview document and write it to output buffer
		$this->getOutput()->output();

		// retrieve the preview document from output buffer
		$targetHtml = ob_get_contents();

		// clean output buffer, so MW can use it again
		ob_clean();

		// store the document as result
		$this->getResult()->addValue( null, 'result', $targetHtml );
	}

	protected function doPreview( $editor ) {
		$out = $this->getOutput();
		$previewOutput = $editor->getPreviewText();

		$hookContainer = MediaWikiServices::getInstance()->getHookContainer();
		$hookContainer->run( 'EditPage::showEditForm:initial', [ $editor, $out ] );

		$out->setRobotPolicy( 'noindex,nofollow' );

		// This hook seems slightly odd here, but makes things more
		// consistent for extensions.
		$hookContainer->run( 'OutputPageBeforeHTML', [ $out, $previewOutput ] );

		$out->addHTML( Html::rawElement( 'div', [ 'id' => 'wikiPreview' ], $previewOutput ) );

		$this->setResultFromOutput();
	}

	protected function doDiff( $editor ) {
		$editor->showDiff();
		$this->setResultFromOutput();
	}

	protected function doStore( EditPage $editor ) {
		global $wgPageFormsDelayReload;

		$title = $editor->getTitle();

		// If they used redlink=1 and the page exists, redirect to the main article and send notice
		if ( $this->getRequest()->getBool( 'redlink' ) && $title->exists() ) {
			$this->logMessage( $this->msg( 'pf_autoedit_redlinkexists' )->parse(), self::WARNING );
		}

		$user = $this->getUser();

		$services = MediaWikiServices::getInstance();
		$permManager = $services->getPermissionManager();

		if ( method_exists( $permManager, 'getPermissionStatus' ) ) {
			// MW 1.43+
			$permStatus = $permManager->getPermissionStatus( 'edit', $user, $title );

			// if this title needs to be created, user needs create rights
			if ( !$title->exists() ) {
				$permStatusForCreate = $permManager->getPermissionStatus( 'create', $user, $title );
				$permStatus->merge( $permStatusForCreate );
			}

			if ( !$permStatus->isOK() ) {
				// Auto-block user's IP if the account was "hard" blocked
				$user->spreadAnyEditBlock();

				foreach ( $permStatus->getMessages() as $errorMsg ) {
					$this->logMessage( wfMessage( $errorMsg )->parse() );
				}

				return;
			}
		} else {
			// MW < 1.43
			$permErrors = $permManager->getPermissionErrors( 'edit', $user, $title );

			// if this title needs to be created, user needs create rights
			if ( !$title->exists() ) {
				$permErrorsForCreate = $permManager->getPermissionErrors( 'create', $user, $title );
				$permErrors = array_merge( $permErrors, wfArrayDiff2( $permErrorsForCreate, $permErrors ) );
			}

			if ( $permErrors ) {
				// Auto-block user's IP if the account was "hard" blocked
				$user->spreadAnyEditBlock();

				foreach ( $permErrors as $error ) {
					$this->logMessage( call_user_func_array( 'wfMessage', $error )->parse() );
				}

				return;
			}
		}

		$resultDetails = [];

		$request = $editor->pfFauxRequest;
		if ( $this->tokenOk( $request ) ) {
			$ctx = RequestContext::getMain();
			$tempTitle = $ctx->getTitle();
			// We add an @ before the setTitle() calls to silence
			// the "Unexpected clearActionName after getActionName"
			// PHP notice that MediaWiki outputs.
			// @todo Make a real fix for this.
			// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
			@$ctx->setTitle( $title );
			$status = $editor->attemptSave( $resultDetails );
			// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
			@$ctx->setTitle( $tempTitle );
		} else {
			throw new MWException( $this->msg( 'session_fail_preview' )->parse() );
		}

		switch ( $status->value ) {
			case EditPage::AS_HOOK_ERROR_EXPECTED:
				// A hook function returned an error
				// show normal Edit page

				// remove Preview and Diff standard buttons from editor page
				$services->getHookContainer()->register( 'EditPageBeforeEditButtons', static function ( &$editor, &$buttons, &$tabindex ) {
					foreach ( array_keys( $buttons ) as $key ) {
						if ( $key !== 'save' ) {
							unset( $buttons[$key] );
						}
					}
				} );

				// Context title needed for correct Cancel link
				$editor->setContextTitle( $title );

				$editor->showEditForm();
				// success
				return false;

			case EditPage::AS_CONTENT_TOO_BIG:
				// Content too big (> $wgMaxArticleSize)
			case EditPage::AS_ARTICLE_WAS_DELETED:
				// article was deleted while editing and param wpRecreate == false or form was not posted
			case EditPage::AS_CONFLICT_DETECTED:
				// (non-resolvable) edit conflict
			case EditPage::AS_SUMMARY_NEEDED:
				// no edit summary given and the user has forceeditsummary set
				// and the user is not editting in his own userspace or
				// talkspace and wpIgnoreBlankSummary == false
			case EditPage::AS_TEXTBOX_EMPTY:
				// user tried to create a new section without content
			case EditPage::AS_MAX_ARTICLE_SIZE_EXCEEDED:
				// article is too big (> $wgMaxArticleSize), after merging in the new section
			case EditPage::AS_END:
				// WikiPage::doEdit() was unsuccessful
				throw new MWException( $this->msg( 'pf_autoedit_fail', $this->mOptions['target'] )->parse() );

			case EditPage::AS_HOOK_ERROR:
				// Article update aborted by a hook function
				$this->logMessage( 'Article update aborted by a hook function', self::DEBUG );
				return false;

			case EditPage::AS_PARSE_ERROR:
				// Can't parse content
				throw new MWException( $status->getHTML() );

			case EditPage::AS_SUCCESS_NEW_ARTICLE:
				// Article successfully created
				// @phan-suppress-next-line PhanTypePossiblyInvalidDimOffset
				$query = $resultDetails['redirect'] ? 'redirect=no' : '';
				$anchor = isset( $resultDetails['sectionanchor'] ) ? $resultDetails['sectionanchor'] : '';

				// Give extensions a chance to modify URL query on create
				$sectionanchor = null;
				$extraQuery = null;
				$services->getHookContainer()->run( 'ArticleUpdateBeforeRedirect', [ $editor->getArticle(), &$sectionanchor, &$extraQuery ] );

				// @phan-suppress-next-line PhanImpossibleCondition
				if ( $extraQuery ) {
					if ( $query !== '' ) {
						$query .= '&';
					}
					$query .= $extraQuery;
				}

				$redirect = $title->getFullURL( $query ) . $anchor;

				$returnto = Title::newFromText( $this->getRequest()->getText( 'returnto' ) );
				$reload = $this->getRequest()->getText( 'reload' );
				if ( $returnto !== null ) {
					// Purge the returnto page
					$returntoPage = $services->getWikiPageFactory()->newFromTitle( $returnto );
					if ( $returntoPage->exists() && $reload ) {
						$returntoPage->doPurge();
					}
					if ( $wgPageFormsDelayReload ) {
						$redirect = $returnto->getFullURL( [ 'forceReload' => 'true' ] );
					} else {
						$redirect = $returnto->getFullURL();
					}
				}

				$this->getOutput()->redirect( $redirect );
				$this->getResult()->addValue( null, 'redirect', $redirect );
				return false;

			case EditPage::AS_SUCCESS_UPDATE:
				// Article successfully updated
				$extraQuery = '';
				$sectionanchor = $resultDetails['sectionanchor'] ?? null;

				// Give extensions a chance to modify URL query on update
				$services->getHookContainer()->run( 'ArticleUpdateBeforeRedirect', [ $editor->getArticle(), &$sectionanchor, &$extraQuery ] );

				// @phan-suppress-next-line PhanTypePossiblyInvalidDimOffset
				if ( $resultDetails['redirect'] ) {
					// @phan-suppress-next-line PhanSuspiciousValueComparison
					if ( $extraQuery == '' ) {
						$extraQuery = 'redirect=no';
					} else {
						$extraQuery = 'redirect=no&' . $extraQuery;
					}
				}

				$redirect = $title->getFullURL( $extraQuery ) . $sectionanchor;

				$returnto = Title::newFromText( $this->getRequest()->getText( 'returnto' ) );
				$reload = $this->getRequest()->getText( 'reload' );
				if ( $returnto !== null ) {
					// Purge the returnto page
					$returntoPage = $services->getWikiPageFactory()->newFromTitle( $returnto );
					if ( $returntoPage->exists() && $reload ) {
						$returntoPage->doPurge();
					}
					if ( $wgPageFormsDelayReload ) {
						$redirect = $returnto->getFullURL( [ 'forceReload' => 'true' ] );
					} else {
						$redirect = $returnto->getFullURL();
					}
				}

				$this->getOutput()->redirect( $redirect );
				$this->getResult()->addValue( null, 'redirect', $redirect );

				return false;

			case EditPage::AS_BLANK_ARTICLE:
				// user tried to create a blank page
				$this->logMessage( 'User tried to create a blank page', self::DEBUG );
				try {
					$contextTitle = $editor->getContextTitle();
				} catch ( Exception ) {
					// getContextTitle() throws an exception
					// if there's no context title - this
					// happens when using the one-stop process.
					throw new RuntimeException( 'Error: Saving this form would result in a blank page.' );
				}

				$this->getOutput()->redirect( $contextTitle->getFullURL() );
				$this->getResult()->addValue( null, 'redirect', $contextTitle->getFullURL() );

				return false;

			case EditPage::AS_SPAM_ERROR:
				// summary contained spam according to one of the regexes in $wgSummarySpamRegex
				$match = $resultDetails['spam'] ?? '';
				if ( is_array( $match ) ) {
					$match = $this->getLanguage()->listToText( $match );
				}

				// FIXME: Include better error message
				throw new MWException( $this->msg( 'spamprotectionmatch', wfEscapeWikiText( $match ) )->parse() );

			case EditPage::AS_BLOCKED_PAGE_FOR_USER:
				// User is blocked from editing editor page
				throw new UserBlockedError( $this->getUser()->getBlock() );

			case EditPage::AS_IMAGE_REDIRECT_ANON:
				// anonymous user is not allowed to upload (User::isAllowed('upload') == false)
			case EditPage::AS_IMAGE_REDIRECT_LOGGED:
				// logged in user is not allowed to upload (User::isAllowed('upload') == false)
				throw new PermissionsError( 'upload' );

			case EditPage::AS_READ_ONLY_PAGE_ANON:
				// editor anonymous user is not allowed to edit editor page
			case EditPage::AS_READ_ONLY_PAGE_LOGGED:
				// editor logged in user is not allowed to edit editor page
				throw new PermissionsError( 'edit' );

			case EditPage::AS_READ_ONLY_PAGE:
				// wiki is in readonly mode
				throw new ReadOnlyError;

			case EditPage::AS_RATE_LIMITED:
				// rate limiter for action 'edit' was tripped
				throw new ThrottledError();

			case EditPage::AS_NO_CREATE_PERMISSION:
				// user tried to create editor page, but is not allowed to do
				// that ( Title->usercan('create') == false )
				$permission = $title->isTalkPage() ? 'createtalk' : 'createpage';
				throw new PermissionsError( $permission );

			default:
				// We don't recognize $status->value. Presumably this can only
				// happen if some other extension set the value.
				throw new MWException( $status->getHTML() );
		}
	}

	protected function finalizeResults() {
		// set response text depending on the status and the requested action
		if ( $this->mStatus === 200 ) {
			if ( array_key_exists( 'ok text', $this->mOptions ) ) {
				$targetTitle = Title::newFromText( $this->mOptions['target'] );
				$messageCache = MediaWikiServices::getInstance()->getMessageCache();
				$responseText = $messageCache->parse( $this->mOptions['ok text'], $targetTitle )->getText();
			} elseif ( $this->mAction === self::ACTION_SAVE ) {
				// We turn this into a link of the form [[:A|A]]
				// so that pages in the File: namespace won't
				// cause the actual image to be displayed.
				$targetText = ':' . $this->mOptions['target'] . '|' . $this->mOptions['target'];
				if ( array_key_exists( 'blankTarget', $this->mOptions ) ) {
					$successMsg = 'pf_autoedit_newpagesuccess';
				} else {
					$successMsg = 'pf_autoedit_success';
				}
				$responseText = $this->msg( $successMsg, $targetText, $this->mOptions['form'] )->parse();
			} else {
				$responseText = null;
			}
		} else {
			// get errortext (or use default)
			if ( array_key_exists( 'error text', $this->mOptions ) ) {
				$targetTitle = Title::newFromText( $this->mOptions['target'] );
				$messageCache = MediaWikiServices::getInstance()->getMessageCache();
				$responseText = $messageCache->parse( $this->mOptions['error text'], $targetTitle )->getText();
			} elseif ( $this->mAction === self::ACTION_SAVE ) {
				$targetText = ':' . $this->mOptions['target'] . '|' . $this->mOptions['target'];
				$responseText = $this->msg( 'pf_autoedit_fail', $targetText )->parse();
			} else {
				$responseText = null;
			}
		}

		$result = $this->getResult();

		if ( $responseText !== null ) {
			$result->addValue( null, 'responseText', $responseText );
		}

		$result->addValue( null, 'status', $this->mStatus, true );
		$result->addValue( [ 'form' ], 'title', $this->mOptions['form'] );
		$result->addValue( null, 'target', $this->mOptions['target'], true );
	}

	/**
	 * Set custom headers to attach to the answer
	 */
	protected function setHeaders() {
		if ( !headers_sent() ) {
			header( 'X-Status: ' . $this->mStatus, true, $this->mStatus );
			header( 'X-Form: ' . $this->mOptions['form'] );
			header( 'X-Target: ' . $this->mOptions['target'] );

			$redirect = $this->getOutput()->getRedirect();
			if ( $redirect ) {
				header( 'X-Location: ' . $redirect );
			}
		}
	}

	/**
	 * Generates a target name from the given target name formula
	 *
	 * This parses the formula and replaces &lt;unique number&gt; tags
	 *
	 * @param string $targetNameFormula
	 *
	 * @throws MWException
	 * @return string
	 */
	protected function generateTargetName( $targetNameFormula ) {
		$targetName = $targetNameFormula;

		// Prepend a super-page, if one was specified.
		if ( $this->getRequest()->getCheck( 'super_page' ) ) {
			$targetName = $this->getRequest()->getVal( 'super_page' ) . '/' . $targetName;
		}

		// Prepend a namespace, if one was specified.
		if ( $this->getRequest()->getCheck( 'namespace' ) ) {
			$targetName = $this->getRequest()->getVal( 'namespace' ) . ':' . $targetName;
		}

		// replace "unique number" tag with one that won't get erased by the next line
		$targetName = preg_replace( '/<unique number(.*)>/', '{num\1}', $targetName, 1 );

		// If any formula stuff is still in the name after the parsing,
		// just remove it.
		// FIXME: This is wrong. If anything is still left, something
		// should have been present in the form and wasn't. An error
		// should be raised.
		// $targetName = StringUtils::delimiterReplace( '<', '>', '', $targetName );

		// Replace spaces back with underlines, in case a magic word or
		// parser function name contains underlines - hopefully this
		// won't cause problems of its own.
		$targetName = str_replace( ' ', '_', $targetName );

		// Now run the parser on it.
		$parserOptions = ParserOptions::newFromUser( $this->getUser() );
		$targetName = PFUtils::getParser()->transformMsg(
			$targetName, $parserOptions, $this->getTitle()
		);

		$titleNumber = '';
		$isRandom = false;
		$randomNumHasPadding = false;
		$randomNumDigits = 6;

		if ( preg_match( '/{num.*}/', $targetName, $matches ) && strpos( $targetName, '{num' ) !== false ) {
			// Random number
			if ( preg_match( '/{num;random(;(0)?([1-9][0-9]*))?}/', $targetName, $matches ) ) {
				$isRandom = true;
				$randomNumHasPadding = array_key_exists( 2, $matches );
				$randomNumDigits = ( array_key_exists( 3, $matches ) ? $matches[3] : $randomNumDigits );
				$titleNumber = self::makeRandomNumber( $randomNumDigits, $randomNumHasPadding );
			} elseif ( preg_match( '/{num.*start[_]*=[_]*([^;]*).*}/', $targetName, $matches ) ) {
				// get unique number start value
				// from target name; if it's not
				// there, or it's not a positive
				// number, start it out as blank
				if ( count( $matches ) == 2 && is_numeric( $matches[1] ) && $matches[1] >= 0 ) {
					// the "start" value"
					$titleNumber = $matches[1];
				}
			} elseif ( preg_match( '/^(_?{num.*}?)*$/', $targetName, $matches ) ) {
				// the target name contains only underscores and number fields,
				// i.e. would result in an empty title without the number set
				$titleNumber = '1';
			}

			// set target title
			$targetTitle = Title::newFromText( preg_replace( '/{num.*}/', $titleNumber, $targetName ) );

			// if the specified target title is invalid, give up
			if ( !$targetTitle instanceof Title ) {
				$targetString = trim( preg_replace( '/<unique number(.*)>/', $titleNumber, $targetNameFormula ) );
				throw new MWException( $this->msg( 'pf_autoedit_invalidtargetspecified', $targetString )->parse() );
			}

			// If title exists already, cycle through numbers for
			// this tag until we find one that gives a nonexistent
			// page title.
			// We cannot use $targetTitle->exists(); it does not use
			// IDBAccessObject::READ_LATEST, which is needed to get
			// correct data from cache; use
			// $targetTitle->getArticleID() instead.
			$numAttemptsAtTitle = 0;
			while ( $targetTitle->getArticleID( IDBAccessObject::READ_LATEST ) !== 0 ) {
				$numAttemptsAtTitle++;

				if ( $isRandom ) {
					// If the set of pages is "crowded"
					// already, go one digit higher.
					if ( $numAttemptsAtTitle > 20 ) {
						$randomNumDigits++;
					}
					$titleNumber = self::makeRandomNumber( $randomNumDigits, $randomNumHasPadding );
				} elseif ( $titleNumber == "" ) {
					// If title number is blank, change it to 2;
					// otherwise, increment it, and if necessary
					// pad it with leading 0s as well.
					$titleNumber = 2;
				} else {
					$titleNumber = str_pad( $titleNumber + 1, strlen( $titleNumber ), '0', STR_PAD_LEFT );
				}

				$targetTitle = Title::newFromText( preg_replace( '/{num.*}/', $titleNumber, $targetName ) );
			}

			$targetName = $targetTitle->getPrefixedText();
		}

		return $targetName;
	}

	/**
	 * Returns a formatted (pseudo) random number
	 *
	 * @param int $numDigits the min width of the random number
	 * @param bool $hasPadding should the number should be padded with zeros instead of spaces?
	 * @return string
	 */
	static function makeRandomNumber( $numDigits = 1, $hasPadding = false ) {
		$maxValue = pow( 10, $numDigits ) - 1;
		if ( $maxValue > getrandmax() ) {
			$maxValue = getrandmax();
		}
		$value = rand( 0, $maxValue );
		$format = '%' . ( $hasPadding ? '0' : '' ) . $numDigits . 'd';
		// trim() is needed, when $hasPadding == false
		return trim( sprintf( $format, $value ) );
	}

	/**
	 * Depending on the requested action this method will try to
	 * store/preview the data in mOptions or retrieve the edit form.
	 *
	 * The form and target page will be available in mOptions after
	 * execution of the method.
	 *
	 * Errors and warnings are logged in the API result under the 'errors'
	 * key. The general request status is maintained in mStatus.
	 *
	 * @throws MWException
	 */
	public function doAction() {
		global $wgRequest, $wgPageFormsFormPrinter;

		// If the wiki is read-only, do not save.
		if ( MediaWikiServices::getInstance()->getReadOnlyMode()->isReadOnly() ) {
			if ( $this->mAction === self::ACTION_SAVE ) {
				throw new MWException( $this->msg( 'pf_autoedit_readonly', MediaWikiServices::getInstance()->getReadOnlyMode()->getReason() )->parse() );
			}

			// even if not saving notify client anyway. Might want to display a notice
			$this->logMessage( $this->msg( 'pf_autoedit_readonly', MediaWikiServices::getInstance()->getReadOnlyMode()->getReason() )->parse(), self::NOTICE );
		}

		// find the title of the form to be used
		$formTitle = $this->getFormTitle();

		// Get the form content - remove the <noinclude> tags from the text of the Form: page.
		$formContent = StringUtils::delimiterReplace(
			'<noinclude>', '</noinclude>', '',
			PFUtils::getPageText( $formTitle, RevisionRecord::RAW )
		);

		// signals that the form was submitted
		// always true, else we would not be here
		$isFormSubmitted = $this->mAction === self::ACTION_SAVE || $this->mAction === self::ACTION_PREVIEW || $this->mAction === self::ACTION_DIFF;

		// the article id of the form to be used
		$formArticleId = $formTitle->getArticleID();

		// the name of the target page; might be empty when using the one-step-process
		$targetName = $this->mOptions['target'];

		// if the target page was not specified, try finding the page name formula
		// (Why is this not done in PFFormPrinter::formHTML?)
		if ( $targetName === '' ) {
			// Parse the form to see if it has a 'page name' value set.
			if ( preg_match( '/{{{\s*info.*page name\s*=\s*(.*)}}}/msU', $formContent, $matches ) ) {
				$pageNameElements = PFUtils::getFormTagComponents( trim( $matches[1] ) );
				$targetNameFormula = $pageNameElements[0];
			} else {
				throw new MWException( $this->msg( 'pf_autoedit_notargetspecified' )->parse() );
			}

			$targetTitle = null;
		} else {
			$targetNameFormula = null;
			$targetTitle = Title::newFromText( $targetName );
		}

		$preloadContent = '';

		// save $wgRequest for later restoration
		$oldRequest = $wgRequest;
		$pageExists = false;

		if ( $targetTitle !== null && $targetTitle->exists() ) {
			if ( !$isFormSubmitted || $this->mIsAutoEdit ) {
				$preloadContent = PFUtils::getPageText( $targetTitle, RevisionRecord::RAW );
			}
			$pageExists = true;
		} elseif ( isset( $this->mOptions['preload'] ) && is_string( $this->mOptions['preload'] ) ) {
			$preloadTitle = Title::newFromText( $this->mOptions['preload'] );

			if ( $preloadTitle !== null && $preloadTitle->exists() ) {
				// the content of the page that was specified to be used for preloading
				$preloadContent = PFUtils::getPageText( $preloadTitle, RevisionRecord::RAW );
			} else {
				$this->logMessage( $this->msg( 'pf_autoedit_invalidpreloadspecified', $this->mOptions['preload'] )->parse(), self::WARNING );
			}
		}

		// Allow extensions to set/change the preload text, for new
		// pages.
		if ( !$pageExists ) {
			MediaWikiServices::getInstance()->getHookContainer()->run( 'PageForms::EditFormPreloadText', [ &$preloadContent, $targetTitle, $formTitle ] );
		} else {
			MediaWikiServices::getInstance()->getHookContainer()->run( 'PageForms::EditFormInitialText', [ &$preloadContent, $targetTitle, $formTitle ] );
		}

		// Flag to keep track of formHTML() runs.
		$formHtmlHasRun = false;

		$formContext = $this->mIsAutoEdit ? PFFormPrinter::CONTEXT_AUTOEDIT : PFFormPrinter::CONTEXT_REGULAR;

		if ( $preloadContent !== '' ) {
			// Spoof $wgRequest for PFFormPrinter::formHTML().
			$session = RequestContext::getMain()->getRequest()->getSession();
			$wgRequest = new FauxRequest( $this->mOptions, true, $session );
			// Call PFFormPrinter::formHTML() to get at the form
			// HTML of the existing page.
			[ $formHTML, $targetContent, $form_page_title, $generatedTargetNameFormula ] =
				$wgPageFormsFormPrinter->formHTML(
					// Special handling for autoedit edits -
					// otherwise, multi-instance templates
					// don't get saved, for some convoluted
					// reason.
					$formContent, ( $isFormSubmitted && !$this->mIsAutoEdit ), $pageExists,
					$formArticleId, $preloadContent, $targetName, $targetNameFormula,
					$formContext, $autocreate_query = [], $this->getUser()
				);
			$formHtmlHasRun = true;

			// Parse the data to be preloaded from the form HTML of
			// the existing page.
			$data = $this->parseDataFromHTMLFrag( $formHTML );

			// ...and merge/overwrite it with the new data.
			$this->mOptions = PFUtils::arrayMergeRecursiveDistinct( $data, $this->mOptions );
		}

		// We already preloaded stuff for saving/previewing -
		// do not do this again.
		if ( $isFormSubmitted ) {
			$preloadContent = '';
			$pageExists = false;
		} else {
			// Source of the data is a page.
			$pageExists = ( is_a( $targetTitle, 'Title' ) && $targetTitle->exists() );
		}

		// Get wikitext for submitted data and form - call formHTML(),
		// if we haven't called it already.
		if ( $preloadContent == '' ) {
			// Spoof $wgRequest for PFFormPrinter::formHTML().
			$session = RequestContext::getMain()->getRequest()->getSession();
			$wgRequest = new FauxRequest( $this->mOptions, true, $session );
			[ $formHTML, $targetContent, $generatedFormName, $generatedTargetNameFormula ] =
				$wgPageFormsFormPrinter->formHTML(
					$formContent, $isFormSubmitted, $pageExists,
					$formArticleId, $preloadContent, $targetName, $targetNameFormula,
					$formContext, $autocreate_query = [], $this->getUser()
				);
			// Restore original request.
			$wgRequest = $oldRequest;
		} else {
			$generatedFormName = $form_page_title;
		}

		if ( $generatedFormName !== '' ) {
			$this->mOptions['formtitle'] = $generatedFormName;
		}

		$this->mOptions['formHTML'] = $formHTML;

		if ( $isFormSubmitted ) {
			// If the target page was not specified, see if
			// something was generated from the target name formula.
			if ( $this->mOptions['target'] === '' ) {
				// If no name was generated, we cannot save => give up
				if ( $generatedTargetNameFormula === '' ) {
					throw new MWException( $this->msg( 'pf_autoedit_notargetspecified' )->parse() );
				}

				$this->mOptions['target'] = $this->generateTargetName( $generatedTargetNameFormula );
			}

			$contextTitle = Title::newFromText( $this->mOptions['target'] );

			// Lets other code process additional form-definition syntax
			MediaWikiServices::getInstance()->getHookContainer()->run( 'PageForms::WritePageData', [ $this->mOptions['form'], &$contextTitle, &$targetContent ] );

			$editor = $this->setupEditPage( $targetContent );

			// Perform the requested action.
			if ( $this->mAction === self::ACTION_PREVIEW ) {
				$editor->setContextTitle( $contextTitle );
				$this->doPreview( $editor );
			} elseif ( $this->mAction === self::ACTION_DIFF ) {
				$this->doDiff( $editor );
			} else {
				$this->doStore( $editor );
			}
		} elseif ( $this->mAction === self::ACTION_FORMEDIT ) {
			$out = $this->getOutput();
			$parserOutput = PFUtils::getParser()->getOutput();
			$out->addParserOutputMetadata( $parserOutput );

			$this->getResult()->addValue( [ 'form' ], 'HTML', $formHTML );
		}
	}

	private function tokenOk( WebRequest $request ) {
		$token = $request->getVal( 'wpEditToken' );
		$user = $this->getUser();
		return $user->matchEditToken( $token );
	}

	private function parseDataFromHTMLFrag( $html ) {
		$data = [];
		$doc = new DOMDocument();
		if ( LIBXML_VERSION < 20900 ) {
			// PHP < 8
			$oldVal = libxml_disable_entity_loader( true );
		}

		// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
		@$doc->loadHTML(
			'<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN" "http://www.w3.org/TR/REC-html40/loose.dtd"><html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/></head><body>'
			. $html
			. '</body></html>'
		);

		if ( LIBXML_VERSION < 20900 ) {
			// PHP < 8
			libxml_disable_entity_loader( $oldVal );
		}

		// Process input tags.
		$inputs = $doc->getElementsByTagName( 'input' );

		for ( $i = 0; $i < $inputs->length; $i++ ) {
			$input = $inputs->item( $i );
			'@phan-var DOMElement $input';/** @var DOMElement $input */
			$type = $input->getAttribute( 'type' );
			$name = trim( $input->getAttribute( 'name' ) );

			if ( !$name ) {
				continue;
			}
			if ( $input->hasAttribute( 'disabled' ) ) {
				// Remove fields from mOptions which are restricted or disabled
				// so that they do not get edited in an #autoedit call.
				$restrictedField = preg_split( "/[\[\]]/", $name, -1, PREG_SPLIT_NO_EMPTY );
				if ( $restrictedField && count( $restrictedField ) > 1 ) {
					unset( $this->mOptions[$restrictedField[0]][$restrictedField[1]] );
				}
				continue;
			}

			if ( $type === '' ) {
				$type = 'text';
			}

			switch ( $type ) {
				case 'checkbox':
				case 'radio':
					if ( $input->hasAttribute( 'checked' ) ) {
						self::addToArray( $data, $name, $input->getAttribute( 'value' ) );
					}
					break;

				// case 'button':
				case 'hidden':
				case 'image':
				case 'password':
				case 'date':
				case 'datetime':
				// case 'reset':
				// case 'submit':
				case 'text':
					self::addToArray( $data, $name, $input->getAttribute( 'value' ) );
					break;
			}
		}

		// Process select tags
		$selects = $doc->getElementsByTagName( 'select' );

		for ( $i = 0; $i < $selects->length; $i++ ) {
			$select = $selects->item( $i );
			$name = trim( $select->getAttribute( 'name' ) );

			if ( !$name || $select->hasAttribute( 'disabled' ) ) {
				// Remove fields from mOptions which are restricted or disabled
				// so that they do not get edited in an #autoedit call.
				$restrictedField = preg_split( "/[\[\]]/", $name, -1, PREG_SPLIT_NO_EMPTY );
				if ( $restrictedField ) {
					unset( $this->mOptions[$restrictedField[0]][$restrictedField[1]] );
				}
				continue;
			}

			$options = $select->getElementsByTagName( 'option' );

			// If the current $select is a radio button select
			// (i.e. not multiple) set the first option to selected
			// as default. This may be overwritten in the loop below.
			if ( $options->length > 0 && ( !$select->hasAttribute( 'multiple' ) ) ) {
				self::addToArray( $data, $name, $options->item( 0 )->getAttribute( 'value' ) );
			}

			for ( $o = 0; $o < $options->length; $o++ ) {
				if ( $options->item( $o )->hasAttribute( 'selected' ) ) {
					if ( $options->item( $o )->getAttribute( 'value' ) ) {
						self::addToArray( $data, $name, $options->item( $o )->getAttribute( 'value' ) );
					} else {
						self::addToArray( $data, $name, $options->item( $o )->nodeValue );
					}
				}
			}
		}

		// Process textarea tags
		$textareas = $doc->getElementsByTagName( 'textarea' );

		for ( $i = 0; $i < $textareas->length; $i++ ) {
			$textarea = $textareas->item( $i );
			$name = trim( $textarea->getAttribute( 'name' ) );

			if ( !$name ) {
				continue;
			}

			self::addToArray( $data, $name, $textarea->textContent );
		}

		return $data;
	}

	/**
	 * Parses data from a query string into the $data array
	 *
	 * @param array &$data
	 * @param string $queryString
	 * @return array
	 */
	private function parseDataFromQueryString( &$data, $queryString ) {
		$params = explode( '&', $queryString );

		foreach ( $params as $param ) {
			$elements = explode( '=', $param, 2 );

			$key = trim( urldecode( $elements[0] ) );
			$value = count( $elements ) > 1 ? urldecode( $elements[1] ) : null;

			if ( $key == "query" || $key == "query string" ) {
				$this->parseDataFromQueryString( $data, $value );
			} else {
				self::addToArray( $data, $key, $value );
			}
		}

		return $data;
	}

	/**
	 * This function recursively inserts the value into a tree.
	 *
	 * @param array &$array is root
	 * @param string $key identifies path to position in tree.
	 *    Format: 1stLevelName[2ndLevel][3rdLevel][...], i.e. normal array notation
	 * @param mixed $value the value to insert
	 * @param bool $toplevel if this is a toplevel value.
	 */
	public static function addToArray( &$array, $key, $value, $toplevel = true ) {
		$matches = [];
		if ( preg_match( '/^([^\[\]]*)\[([^\[\]]*)\](.*)/', $key, $matches ) ) {
			// for some reason toplevel keys get their spaces encoded by MW.
			// We have to imitate that.
			if ( $toplevel ) {
				$key = str_replace( ' ', '_', $matches[1] );
			} else {
				if ( is_numeric( $matches[1] ) && isset( $matches[2] ) ) {
					// Multiple instances are indexed like 0a,1a,2a... to differentiate
					// the inputs the form starts out with from any inputs added by the Javascript.
					// Append the character "a" only if the instance number is numeric.
					// If the key(i.e. the instance) doesn't exists then the numerically next
					// instance is created whatever be the key.
					$key = $matches[1] . 'a';
				} else {
					$key = $matches[1];
				}
			}
			// if subsequent element does not exist yet or is a string (we prefer arrays over strings)
			if ( !array_key_exists( $key, $array ) || is_string( $array[$key] ) ) {
				$array[$key] = [];
			}

			// @phan-suppress-next-line PhanTypePossiblyInvalidDimOffset
			self::addToArray( $array[$key], $matches[2] . $matches[3], $value, false );
		} else {
			if ( $key ) {
				// only add the string value if there is no child array present
				if ( !array_key_exists( $key, $array ) || !is_array( $array[$key] ) ) {
					$array[$key] = $value;
				}
			} else {
				array_push( $array, $value );
			}
		}
	}

	/**
	 * Add error message to the ApiResult
	 *
	 * @param string $msg
	 * @param int $errorLevel
	 *
	 * @return string
	 */
	private function logMessage( $msg, $errorLevel = self::ERROR ) {
		if ( $errorLevel === self::ERROR ) {
			$this->mStatus = 400;
		}

		$this->getResult()->addValue( [ 'errors' ], null, [ 'level' => $errorLevel, 'message' => $msg ] );

		return $msg;
	}

	/**
	 * Indicates whether this module requires write mode
	 * @return bool
	 */
	public function isWriteMode() {
		return true;
	}

	/**
	 * Returns the array of allowed parameters (parameter name) => (default
	 * value) or (parameter name) => (array with PARAM_* constants as keys)
	 * Don't call this function directly: use getFinalParams() to allow
	 * hooks to modify parameters as needed.
	 *
	 * @return array or false
	 */
	function getAllowedParams() {
		return [
			'form' => null,
			'target' => null,
			'query' => null,
			'preload' => null
		];
	}

	/**
	 * Returns an array of parameter descriptions.
	 * Don't call this function directly: use getFinalParamDescription() to
	 * allow hooks to modify descriptions as needed.
	 *
	 * @return array or false
	 */
	function getParamDescription() {
		return [
			'form' => 'The form to use.',
			'target' => 'The target page.',
			'query' => 'The query string.',
			'preload' => 'The name of a page to preload'
		];
	}

	/**
	 * Returns the description string for this module
	 *
	 * @return string|string[]
	 */
	function getDescription() {
		return <<<END
This module is used to remotely create or edit pages using Page Forms.

Add "template-name[field-name]=field-value" to the query string parameter, to set the value for a specific field.
To set values for more than one field use "&", or rather its URL encoded version "%26": "template-name[field-name-1]=field-value-1%26template-name[field-name-2]=field-value-2".
See the first example below.

In addition to the query parameter, any parameter in the URL of the form "template-name[field-name]=field-value" will be treated as part of the query. See the second example.
END;
	}

	/**
	 * Returns usage examples for this module.
	 *
	 * @return string|string[]
	 */
	protected function getExamples() {
		return [
			'With query parameter:    api.php?action=pfautoedit&form=form-name&target=page-name&query=template-name[field-name-1]=field-value-1%26template-name[field-name-2]=field-value-2',
			'Without query parameter: api.php?action=pfautoedit&form=form-name&target=page-name&template-name[field-name-1]=field-value-1&template-name[field-name-2]=field-value-2'
		];
	}

	/**
	 * Returns a string that identifies the version of the class.
	 * Includes the class name, the svn revision, timestamp, and
	 * last author.
	 *
	 * @return string
	 */
	function getVersion() {
		global $wgPageFormsIP;
		$gitSha1 = SpecialVersion::getGitHeadSha1( $wgPageFormsIP );
		return __CLASS__ . '-' . PF_VERSION . ( $gitSha1 !== false ) ? ' (' . substr( $gitSha1, 0, 7 ) . ')' : '';
	}

}
