<?php
/**
 * @param  $parser Parser
 * @return bool
 */
function wfLanguageSelectorSetHook( $parser ) {
	$parser->setHook( 'languageselector', 'wfLanguageSelectorTag' );
}

function wfLanguageSelectorExtension() {
	global $wgLanguageSelectorLocation, $wgHooks;

	// We'll probably be beaten to this by the call in wfLanguageSelectorGetLanguageCode(),
	// but just in case, call this to make sure the global is properly initialised
	wfGetLanguageSelectorLanguages();

	if ( $wgLanguageSelectorLocation != LANGUAGE_SELECTOR_MANUAL && $wgLanguageSelectorLocation != LANGUAGE_SELECTOR_AT_TOP_OF_TEXT ) {
		switch ( $wgLanguageSelectorLocation ) {
			case LANGUAGE_SELECTOR_IN_TOOLBOX:
				$wgHooks['SkinTemplateToolboxEnd'][] = 'wfLanguageSelectorSkinHook';
				break;
			default:
				$wgHooks['SkinTemplateOutputPageBeforeExec'][] = 'wfLanguageSelectorSkinTemplateOutputPageBeforeExec';
				break;
		}
	}
}

function wfGetLanguageSelectorLanguages() {
	global $wgLanguageSelectorLanguages, $wgLanguageSelectorShowAll;
	if ( $wgLanguageSelectorLanguages === null ) {
		$wgLanguageSelectorLanguages = array_keys( Language::fetchLanguageNames(
			null,
			$wgLanguageSelectorShowAll === true ? 'mw': 'mwfile'
		) );
		sort( $wgLanguageSelectorLanguages );
	}

	return $wgLanguageSelectorLanguages;
}

/**
 * Hook to UserGetLanguageObject
 * @param  $user User
 * @param  $code String
 * @return bool
 */
function wfLanguageSelectorGetLanguageCode( $user, &$code ) {
	global $wgLanguageSelectorDetectLanguage,
		$wgCommandLineMode, $wgRequest, $wgContLang;

	if ( $wgCommandLineMode ) {
		return true;
	}

	$setlang = $wgRequest->getVal( 'setlang' );
	if ( $setlang && !in_array( $setlang, wfGetLanguageSelectorLanguages() ) ) {
		$setlang = null; // ignore invalid
	}

	if ( $setlang ) {
		$wgRequest->response()->setcookie( 'LanguageSelectorLanguage', $setlang );
		$requestedLanguage = $setlang;
	} else {
		$requestedLanguage = $wgRequest->getCookie( 'LanguageSelectorLanguage' );
	}

	if ( $setlang && !$user->isAnon() ) {
		if ( $setlang != $user->getOption( 'language' ) ) {
			$user->setOption( 'language', $requestedLanguage );
			$user->saveSettings();
			$code = $requestedLanguage;
		}
	}

	if ( !$wgRequest->getVal( 'uselang' ) && $user->isAnon() ) {
		if ( $wgLanguageSelectorDetectLanguage != LANGUAGE_SELECTOR_USE_CONTENT_LANG ) {
			if ( $requestedLanguage ) {
				$code = $requestedLanguage;
			} else {
				$languages = $wgRequest->getAcceptLang();

				// see if the content language is accepted by the client.
				if ( $wgLanguageSelectorDetectLanguage != LANGUAGE_SELECTOR_PREFER_CONTENT_LANG
					|| !array_key_exists( $wgContLang->getCode(), $languages ) )
				{

					$supported = wfGetLanguageSelectorLanguages();
					// look for a language that is acceptable to the client
					// and known to the wiki.
					foreach ( $languages as $reqCode => $q ) {
						if ( in_array( $reqCode, $supported ) ) {
							$code = $reqCode;
							break;
						}
					}

					// Apparently Safari sends stupid things like "de-de" only.
					// Try again with stripped codes.
					foreach ( $languages as $reqCode => $q ) {
						$stupidPHP = explode( '-', $reqCode, 2 );
						$bareCode = array_shift( $stupidPHP );
						if ( in_array( $bareCode, $supported ) ) {
							$code = $bareCode;
							break;
						}
					}
				}
			}
		}
	}

	return true;
}

/**
 * @param  $out OutputPage
 * @return bool
 */
function wfLanguageSelectorBeforePageDisplay( &$out ) {
	global $wgLanguageSelectorLocation;

	if ( $wgLanguageSelectorLocation == LANGUAGE_SELECTOR_MANUAL ) {
		return true;
	}

	if ( $wgLanguageSelectorLocation == LANGUAGE_SELECTOR_AT_TOP_OF_TEXT ) {
		$html = wfLanguageSelectorHTML( $out->getTitle() );
		$out->setIndicators( [
			'languageselector' => $html,
		] );
	}

	$out->addModules( 'ext.languageSelector' );

	return true;
}

function wfLanguageSelectorGetCacheVaryCookies( $out, &$cookies ) {
	global $wgCookiePrefix;
	$cookies[] = $wgCookiePrefix . 'LanguageSelectorLanguage';
	return true;
}

/**
 * @param $skin Skin
 * @return bool
 */
function wfLanguageSelectorSkinHook( &$skin ) {
	$html = wfLanguageSelectorHTML( $skin->getTitle() );
	print $html;
	return true;
}

/**
 * @param  $input String
 * @param  $args Array
 * @param  $parser Parser
 * @return string
 */
function wfLanguageSelectorTag( $input, $args, $parser ) {
	$style = @$args['style'];
	$class = @$args['class'];
	$selectorstyle = @$args['selectorstyle'];
	$buttonstyle = @$args['buttonstyle'];
	$showcode = @$args['showcode'];

	if ( $style ) {
		$style = htmlspecialchars( $style );
	}
	if ( $class ) {
		$class = htmlspecialchars( $class );
	}
	if ( $selectorstyle ) {
		$selectorstyle = htmlspecialchars( $selectorstyle );
	}
	if ( $buttonstyle ) {
		$buttonstyle = htmlspecialchars( $buttonstyle );
	}

	if ( $showcode ) {
		$showcode = strtolower( $showcode );
		if ( $showcode == "true" || $showcode == "yes" || $showcode == "on" ) {
			$showcode = true;
		} elseif ( $showcode == "false" || $showcode == "no" || $showcode == "off" ) {
			$showcode = false;
		} else {
			$showcode = null;
		}
	} else {
		$showcode = null;
	}

	# So that this also works with parser cache
	$parser->getOutput()->addOutputHook( 'languageselector' );

	return wfLanguageSelectorHTML( $parser->getTitle(), $style, $class, $selectorstyle, $buttonstyle, $showcode );
}

/**
 * @param  $skin Skin
 * @param  $tpl QuickTemplate
 * @return bool
 */
function wfLanguageSelectorSkinTemplateOutputPageBeforeExec( &$skin, &$tpl ) {
	global $wgLanguageSelectorLocation;

	if ( $wgLanguageSelectorLocation == LANGUAGE_SELECTOR_AS_PORTLET ) {
		$code = $skin->getLanguage()->getCode();
		$lines = array();
		foreach ( wfGetLanguageSelectorLanguages() as $ln ) {
			$lines[] = array(
				$href = $skin->getTitle()->getFullURL( 'setlang=' . $ln ),
				'text' => Language::fetchLanguageName( $ln ),
				'href' => $href,
				'id' => 'n-languageselector',
				'active' => ( $ln == $code ),
			);
		}

		$tpl->data['sidebar']['languageselector'] = $lines;
		return true;
	}

	$key = null;

	switch( $wgLanguageSelectorLocation ) {
		case LANGUAGE_SELECTOR_INTO_SITENOTICE: $key = 'sitenotice'; break;
		case LANGUAGE_SELECTOR_INTO_TITLE: $key = 'title'; break;
		case LANGUAGE_SELECTOR_INTO_SUBTITLE: $key = 'subtitle'; break;
		case LANGUAGE_SELECTOR_INTO_CATLINKS: $key = 'catlinks'; break;
	}

	if ( $key ) {
		$html = wfLanguageSelectorHTML( $skin->getTitle() );
		$tpl->set( $key, $tpl->data[ $key ] . $html );
	}

	return true;
}

/**
 * @param  $u User
 * @return bool
 */
function wfLanguageSelectorAddNewAccount( $u ) {
	global $wgUser, $wgLang;

	// inherit language;
	// if $wgUser is the created user this means remembering what the user selected
	// otherwise, it would mean inheriting the language from the user creating the account.
	if ( $wgUser === $u ) {
		$u->setOption( 'language', $wgLang->getCode() );
		$u->saveSettings();
	}

	return true;
}

/**
 * @param  $outputPage OutputPage
 * @param  $parserOutput ParserOutput
 * @param  $data
 * @return void
 */
function wfLanguageSelectorAddJavascript( $outputPage, $parserOutput, $data ) {
	$outputPage->addModules( 'ext.languageSelector' );
}

function wfLanguageSelectorHTML( Title $title, $style = null, $class = null, $selectorstyle = null, $buttonstyle = null, $showCode = null ) {
	global $wgLang, $wgScript, $wgLanguageSelectorShowCode;

	if ( $showCode === null ) {
		$showCode = $wgLanguageSelectorShowCode;
	}

	static $id = 0;
	$id += 1;

	$code = $wgLang->getCode();

	$html = '';
	$html .= Xml::openElement( 'span', array(
		'id' => 'languageselector-box-' . $id,
		'class' => 'languageselector ' . $class,
		'style' => $style
	) );
	$html .= Xml::openElement( 'form', array(
		'name' => 'languageselector-form-' . $id,
		'id' => 'languageselector-form-' . $id,
		'method' => 'get',
		'action' => $wgScript,
		'style' => 'display:inline;'
	) );
	$html .= Html::Hidden( 'title', $title->getPrefixedDBKey() );
	$html .= Xml::openElement( 'select', array(
		'name' => 'setlang',
		'id' => 'languageselector-select-' . $id,
		'style' => $selectorstyle
	) );

	foreach ( wfGetLanguageSelectorLanguages() as $ln ) {
		$name = Language::fetchLanguageName( $ln );
		if ( $showCode ) $name = LanguageCode::bcp47( $ln ) . ' - ' . $name;

		$html .= Xml::option( $name, $ln, $ln == $code );
	}

	$html .= Xml::closeElement( 'select' );
	$html .= Xml::submitButton( wfMessage( 'languageselector-setlang' )->text(),
		array( 'id' => 'languageselector-commit-' . $id, 'style' => $buttonstyle ) );
	$html .= Xml::closeElement( 'form' );
	$html .= Xml::closeElement( 'span' );

	return $html;
}
