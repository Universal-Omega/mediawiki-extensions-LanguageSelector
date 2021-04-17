<?php
/**
 * LanguageSelector extension - Adds a language selector on every page
 *
 * Features:
 *  * Automatic detection of the language to use for anonymous visitors
 *  * Adds selector for preferred language to every page (also works for anons)
 *
 * This extension may be combined with the Polyglot and the MultiLang extension
 * to provide more internationalization support.
 *
 * @link https://www.mediawiki.org/wiki/Extension:LanguageSelector Documentation
 *
 * @file LanguageSelector.php
 * @ingroup Extensions
 * @package MediaWiki
 * @author Daniel Kinzler (Duesentrieb), brightbyte.de
 * @copyright © 2007 Daniel Kinzler
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	echo( "This file is an extension to the MediaWiki software and cannot be used standalone.\n" );
	die( 1 );
}

$wgExtensionCredits['other'][] = [
	'path'           => __FILE__,
	'name'           => 'Language Selector',
	'author'         => 'Daniel Kinzler',
	'url'            => 'https://mediawiki.org/wiki/Extension:LanguageSelector',
	'descriptionmsg' => 'languageselector-desc',
];

define( 'LANGUAGE_SELECTOR_USE_CONTENT_LANG',    0 ); # no detection
define( 'LANGUAGE_SELECTOR_PREFER_CONTENT_LANG', 1 ); # use content language if accepted by the client
define( 'LANGUAGE_SELECTOR_PREFER_CLIENT_LANG',  2 ); # use language most preferred by the client

/**
* Language detection mode for anonymous visitors.
* Possible values:
* * LANGUAGE_SELECTOR_USE_CONTENT_LANG - use the $wgLanguageCode setting (default content language)
* * LANGUAGE_SELECTOR_PREFER_CONTENT_LANG - use the $wgLanguageCode setting, if accepted by the client
* * LANGUAGE_SELECTOR_PREFER_CLIENT_LANG - use the client's preferred language, if in $wgLanguageSelectorLanguages
*/
$wgLanguageSelectorDetectLanguage = LANGUAGE_SELECTOR_PREFER_CLIENT_LANG;

/**
* Languages to offer in the language selector. Per default, this includes all languages MediaWiki knows
* about by virtue of languages/Names.php. A shorter list may be more usable, though.
*/
$wgLanguageSelectorLanguages = null;

/**
* Determine if language codes are shown in the selector, in addition to names;
*/
$wgLanguageSelectorShowCode = false;

/**
 * Show all languages defined, not only those with a language file.
 */
$wgLanguageSelectorShowAll = false;

define( 'LANGUAGE_SELECTOR_MANUAL',    0 ); # don't place anywhere
define( 'LANGUAGE_SELECTOR_AT_TOP_OF_TEXT', 1 ); # put at the top of page content
define( 'LANGUAGE_SELECTOR_IN_TOOLBOX',  2 ); # put into toolbox
define( 'LANGUAGE_SELECTOR_AS_PORTLET', 3 ); # as portlet
define( 'LANGUAGE_SELECTOR_INTO_SITENOTICE', 11 ); # put after sitenotice text
define( 'LANGUAGE_SELECTOR_INTO_TITLE', 12 ); # put after title text
define( 'LANGUAGE_SELECTOR_INTO_SUBTITLE', 13 ); # put after subtitle text
define( 'LANGUAGE_SELECTOR_INTO_CATLINKS', 14 ); # put after catlinks text

$wgLanguageSelectorLocation = LANGUAGE_SELECTOR_AT_TOP_OF_TEXT;

// register hook handlers
$wgHooks['AddNewAccount'][] = 'wfLanguageSelectorAddNewAccount';
$wgHooks['BeforePageDisplay'][] = 'wfLanguageSelectorBeforePageDisplay';
$wgHooks['GetCacheVaryCookies'][] = 'wfLanguageSelectorGetCacheVaryCookies';
$wgHooks['ParserFirstCallInit'][] = 'wfLanguageSelectorSetHook';
$wgHooks['UserGetLanguageObject'][] = 'wfLanguageSelectorGetLanguageCode';

$wgExtensionFunctions[] = 'wfLanguageSelectorExtension';

$wgParserOutputHooks['languageselector'] = 'wfLanguageSelectorAddJavascript';

$wgResourceModules['ext.languageSelector'] = [
	'scripts' => 'LanguageSelector.js',
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'LanguageSelector'
];

$wgMessagesDirs['LanguageSelector'] = __DIR__ . '/i18n';
