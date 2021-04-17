<?php
$wgExtensionCredits['other'][] = [
	'path'           => __FILE__,
	'name'           => 'Language Selector',
	'author'         => 'Daniel Kinzler',
	'url'            => 'https://mediawiki.org/wiki/Extension:LanguageSelector',
	'descriptionmsg' => 'languageselector-desc',
];

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

// register hook handlers
$wgHooks['LocalUserCreated'][] = 'LanguageSelectorHooks::onLocalUserCreated';
$wgHooks['BeforePageDisplay'][] = 'LanguageSelectorHooks::onBeforePageDisplay';
$wgHooks['GetCacheVaryCookies'][] = 'LanguageSelectorHooks::onGetCacheVaryCookies';
$wgHooks['ParserFirstCallInit'][] = 'LanguageSelectorHooks::onParserFirstCallInit';
$wgHooks['UserGetLanguageObject'][] = 'LanguageSelectorHooks::onUserGetLanguageObject';

$wgExtensionFunctions[] = 'LanguageSelectorHooks::extension';

$wgResourceModules['ext.languageSelector'] = [
	'scripts' => 'LanguageSelector.js',
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'LanguageSelector'
];

$wgMessagesDirs['LanguageSelector'] = __DIR__ . '/i18n';
