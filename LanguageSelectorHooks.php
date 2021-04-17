<?php
class LanguageSelectorHooks {
	public static function onRegistration() {
		global $wgLanguageSelectorDetectLanguage, $wgLanguageSelectorLocation, $wgParserOutputHooks;

		define( 'LANGUAGE_SELECTOR_USE_CONTENT_LANG', 0 ); # no detection
		define( 'LANGUAGE_SELECTOR_PREFER_CONTENT_LANG', 1 ); # use content language if accepted by the client
		define( 'LANGUAGE_SELECTOR_PREFER_CLIENT_LANG', 2 ); # use language most preferred by the client

		/**
		 * Language detection mode for anonymous visitors.
		 * Possible values:
		 * * LANGUAGE_SELECTOR_USE_CONTENT_LANG - use the $wgLanguageCode setting (default content language)
		 * * LANGUAGE_SELECTOR_PREFER_CONTENT_LANG - use the $wgLanguageCode setting, if accepted by the client
		 * * LANGUAGE_SELECTOR_PREFER_CLIENT_LANG - use the client's preferred language, if in $wgLanguageSelectorLanguages
		 */
		$wgLanguageSelectorDetectLanguage = LANGUAGE_SELECTOR_PREFER_CLIENT_LANG;

		define( 'LANGUAGE_SELECTOR_MANUAL', 0 ); # don't place anywhere
		define( 'LANGUAGE_SELECTOR_AT_TOP_OF_TEXT', 1 ); # put at the top of page content
		define( 'LANGUAGE_SELECTOR_IN_TOOLBOX', 2 ); # put into toolbox
		define( 'LANGUAGE_SELECTOR_AS_PORTLET', 3 ); # as portlet
		define( 'LANGUAGE_SELECTOR_INTO_SITENOTICE', 11 ); # put after sitenotice text
		define( 'LANGUAGE_SELECTOR_INTO_TITLE', 12 ); # put after title text
		define( 'LANGUAGE_SELECTOR_INTO_SUBTITLE', 13 ); # put after subtitle text
		define( 'LANGUAGE_SELECTOR_INTO_CATLINKS', 14 ); # put after catlinks text

		$wgLanguageSelectorLocation = LANGUAGE_SELECTOR_AT_TOP_OF_TEXT;

		$wgParserOutputHooks['languageselector'] = 'self::languageSelectorAddJavascript';
	}

	public static function extension() {
		global $wgLanguageSelectorLocation, $wgHooks;

		// We'll probably be beaten to this by the call in onUserGetLanguageObject(),
		// but just in case, call this to make sure the global is properly initialised
		self::getLanguageSelectorLanguages();

		if ( $wgLanguageSelectorLocation != LANGUAGE_SELECTOR_MANUAL && $wgLanguageSelectorLocation != LANGUAGE_SELECTOR_AT_TOP_OF_TEXT ) {
			switch ( $wgLanguageSelectorLocation ) {
				case LANGUAGE_SELECTOR_IN_TOOLBOX:
					$wgHooks['SkinAfterPortlet'][] = 'self::onSkinAfterPortlet';

					break;
				default:
					$wgHooks['SidebarBeforeOutput'][] = 'self::onSidebarBeforeOutput';

					break;
			}
		}
	}

	/**
	 * @param $parser Parser
	 */
	public static function onParserFirstCallInit( $parser ) {
		$parser->setHook( 'languageselector', [ __CLASS__, 'languageSelectorTag' ] );
	}

	public static function getLanguageSelectorLanguages() {
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
	public static function onUserGetLanguageObject( $user, &$code ) {
		global $wgLanguageSelectorDetectLanguage,
			$wgCommandLineMode, $wgRequest, $wgContLang;

		if ( $wgCommandLineMode ) {
			return true;
		}

		$setlang = $wgRequest->getVal( 'setlang' );
		if ( $setlang && !in_array( $setlang, self::getLanguageSelectorLanguages() ) ) {
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

						$supported = self::getLanguageSelectorLanguages();
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
	 * @param $out OutputPage
	 * @return bool
	 */
	public static function onBeforePageDisplay( &$out ) {
		global $wgLanguageSelectorLocation;

		if ( $wgLanguageSelectorLocation == LANGUAGE_SELECTOR_MANUAL ) {
			return true;
		}

		if ( $wgLanguageSelectorLocation == LANGUAGE_SELECTOR_AT_TOP_OF_TEXT ) {
			$html = self::languageSelectorHTML( $out->getTitle() );
			$out->setIndicators( [
				'languageselector' => $html,
			] );
		}

		$out->addModules( 'ext.languageSelector' );

		return true;
	}

	public static function onGetCacheVaryCookies( $out, &$cookies ) {
		global $wgCookiePrefix;

		$cookies[] = $wgCookiePrefix . 'LanguageSelectorLanguage';

		return true;
	}

	/**
	 * @param $skin Skin
	 * @return bool
	 */
	public static function onSkinAfterPortlet( $skin, $portlet, &$html ) {
		if ( $portlet === 'tb' ) {
			$html .= self::languageSelectorHTML( $skin->getTitle() );
		}

		return true;
	}

	/**
	 * @param $input String
	 * @param $args Array
	 * @param $parser Parser
	 * @return string
	 */
	public static function languageSelectorTag( $input, $args, $parser ) {
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

		return self::languageSelectorHTML( $parser->getTitle(), $style, $class, $selectorstyle, $buttonstyle, $showcode );
	}

	/**
	 * @param $skin Skin
	 * @param $sidebar array
	 * @return bool
	 */
	public static function onSidebarBeforeOutput( $skin, &$sidebar ) {
		global $wgLanguageSelectorLocation;

		if ( $wgLanguageSelectorLocation == LANGUAGE_SELECTOR_AS_PORTLET ) {
			$code = $skin->getLanguage()->getCode();
			$lines = [];
			foreach ( self::getLanguageSelectorLanguages() as $ln ) {
				$lines[] = [
					$href = $skin->getTitle()->getFullURL( 'setlang=' . $ln ),
					'text' => Language::fetchLanguageName( $ln ),
					'href' => $href,
					'id' => 'n-languageselector',
					'active' => ( $ln == $code ),
				];
			}

			$sidebar['languageselector'] = $lines;

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
			$html = self::languageSelectorHTML( $skin->getTitle() );
			$tpl->set( $key, $tpl->data[ $key ] . $html );
		}

		return true;
	}

	/**
	 * @param User $user
	 * @param bool $autocreated
	 */
	public static function onLocalUserCreated( $user, $autocreated ) {
		global $wgUser, $wgLang;

		// inherit language;
		// if $wgUser is the created user this means remembering what the user selected
		// otherwise, it would mean inheriting the language from the user creating the account.
		if ( $wgUser === $user ) {
			$u->setOption( 'language', $wgLang->getCode() );
			$u->saveSettings();
		}
	}

	/**
	 * @param $outputPage OutputPage
	 * @param $parserOutput ParserOutput
	 * @param $data
	 * @return void
	 */
	public static function languageSelectorAddJavascript( $outputPage, $parserOutput, $data ) {
		$outputPage->addModules( 'ext.languageSelector' );
	}

	public static function languageSelectorHTML( Title $title, $style = null, $class = null, $selectorstyle = null, $buttonstyle = null, $showCode = null ) {
		global $wgLang, $wgScript, $wgLanguageSelectorShowCode;

		if ( $showCode === null ) {
			$showCode = $wgLanguageSelectorShowCode;
		}

		static $id = 0;
		$id += 1;

		$code = $wgLang->getCode();

		$html = '';
		$html .= Xml::openElement( 'span', [
			'id' => 'languageselector-box-' . $id,
			'class' => 'languageselector ' . $class,
			'style' => $style
		] );
		$html .= Xml::openElement( 'form', [
			'name' => 'languageselector-form-' . $id,
			'id' => 'languageselector-form-' . $id,
			'method' => 'get',
			'action' => $wgScript,
			'style' => 'display:inline;'
		] );
		$html .= Html::Hidden( 'title', $title->getPrefixedDBKey() );
		$html .= Xml::openElement( 'select', [
			'name' => 'setlang',
			'id' => 'languageselector-select-' . $id,
			'style' => $selectorstyle
		] );

		foreach ( self::getLanguageSelectorLanguages() as $ln ) {
			$name = Language::fetchLanguageName( $ln );
			if ( $showCode ) $name = LanguageCode::bcp47( $ln ) . ' - ' . $name;

			$html .= Xml::option( $name, $ln, $ln == $code );
		}

		$html .= Xml::closeElement( 'select' );
		$html .= Xml::submitButton( wfMessage( 'languageselector-setlang' )->text(),
			[ 'id' => 'languageselector-commit-' . $id, 'style' => $buttonstyle ] );
		$html .= Xml::closeElement( 'form' );
		$html .= Xml::closeElement( 'span' );

		return $html;
	}
}
