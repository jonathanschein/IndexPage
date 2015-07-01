<?php
# Alert the user that this is not a valid access point to MediaWiki if they try to access the special pages file directly.
if ( !defined( 'MEDIAWIKI' ) ) {
	echo <<<EOT
To install my extension, put the following line in LocalSettings.php:
require_once( "\$IP/extensions/IndexPage/IndexPage.php" );
EOT;
	exit( 1 );
}

$wgExtensionCredits['specialpage'][] = array(
	'path' => __FILE__,
	'name' => 'IndexPage',
	'author' => 'd3 Sistemas',
	'url' => 'https://www.mediawiki.org/wiki/Extension:IndexPage',
	'descriptionmsg' => 'indexpage-desc',
	'version' => '0.0.1',
);

$wgAutoloadClasses['SpecialIndexPage'] = __DIR__ . '/SpecialIndexPage.php'; 
$wgMessagesDirs['IndexPage'] = __DIR__ . "/i18n"; 
$wgExtensionMessagesFiles['IndexPageAlias'] = __DIR__ . '/IndexPage.alias.php'; 
$wgSpecialPages['IndexPage'] = 'SpecialIndexPage'; 