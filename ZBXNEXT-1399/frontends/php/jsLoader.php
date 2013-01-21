<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


// get language translations
require_once dirname(__FILE__).'/include/gettextwrapper.inc.php';
require_once dirname(__FILE__).'/include/js.inc.php';
require_once dirname(__FILE__).'/include/locales.inc.php';

// if we must provide language constants on language different from English
if (isset($_GET['lang'])) {
	if (function_exists('bindtextdomain')) {
		// initializing gettext translations depending on language selected by user
		$locales = zbx_locale_variants($_GET['lang']);
		foreach ($locales as $locale) {
			putenv('LC_ALL='.$locale);
			putenv('LANG='.$locale);
			putenv('LANGUAGE='.$locale);
			if (setlocale(LC_ALL, $locale)) {
				break;
			}
		}
		bindtextdomain('frontend', 'locale');
		bind_textdomain_codeset('frontend', 'UTF-8');
		textdomain('frontend');
	}
	// numeric Locale to default
	setlocale(LC_NUMERIC, array('C', 'POSIX', 'en', 'en_US', 'en_US.UTF-8', 'English_United States.1252', 'en_GB', 'en_GB.UTF-8'));
}

// available scripts 'scriptFileName' => 'path relative to js/'
$availableJScripts = array(
	'common.js' => '',
	'menu.js' => '',
	'gtlc.js' => '',
	'functions.js' => '',
	'main.js' => '',
	'dom.js' => '',
	'servercheck.js' => '',
	'flickerfreescreen.js' => '',
	// vendors
	'prototype.js' => '',
	'jquery.js' => 'jquery/',
	'jquery-ui.js' => 'jquery/',
	'activity-indicator.js' => 'vendors/',
	'chosen.jquery.js' => 'vendors/chosen/',
	'chosen.js' => 'vendors/chosen/',
	// classes
	'class.bbcode.js' => '',
	'class.calendar.js' => '',
	'class.cdate.js' => '',
	'class.cdebug.js' => '',
	'class.cmap.js' => '',
	'class.cmessages.js' => '',
	'class.cookie.js' => '',
	'class.cscreen.js' => '',
	'class.csuggest.js' => '',
	'class.cswitcher.js' => '',
	'class.ctree.js' => '',
	'class.curl.js' => '',
	'class.rpc.js' => '',
	'class.pmaster.js' => '',
	'class.cviewswitcher.js' => '',
	'init.js' => '',
	// templates
	'sysmap.tpl.js' => 'templates/'
);

$tranStrings = array(
	'gtlc.js' => array(
		'S_ALL_S' => _('All'),
		'S_ZOOM' => _('Zoom'),
		'S_FIXED_SMALL' => _('fixed'),
		'S_DYNAMIC_SMALL' => _('dynamic'),
		'S_NOW_SMALL' => _('now'),
		'S_YEAR_SHORT' => _x('y', 'year short'),
		'S_MONTH_SHORT' => _x('m', 'month short'),
		'S_WEEK_SHORT' => _x('w', 'week short'),
		'S_DAY_SHORT' => _x('d', 'day short'),
		'S_HOUR_SHORT' => _x('h', 'hour short'),
		'S_MINUTE_SHORT' => _x('m', 'minute short')
	),
	'functions.js' => array(
		'DO_YOU_REPLACE_CONDITIONAL_EXPRESSION_Q' => _('Do you wish to replace the conditional expression?'),
		'S_INSERT_MACRO' => _('Insert macro'),
		'S_CREATE_LOG_TRIGGER' => _('Create trigger'),
		'S_DELETE' => _('Delete'),
		'S_DELETE_KEYWORD_Q' => _('Delete keyword?'),
		'S_DELETE_EXPRESSION_Q' => _('Delete expression?'),
		'Simple graphs' => _('Simple graphs'),
		'History' => _('History'),
		'History and simple graphs' => _('History and simple graphs'),
		'Triggers' => _('Triggers'),
		'Events' => _('Events'),
	),
	'class.calendar.js' => array(
		'S_JANUARY' => _('January'),
		'S_FEBRUARY' => _('February'),
		'S_MARCH' => _('March'),
		'S_APRIL' => _('April'),
		'S_MAY' => _('May'),
		'S_JUNE' => _('June'),
		'S_JULY' => _('July'),
		'S_AUGUST' => _('August'),
		'S_SEPTEMBER' => _('September'),
		'S_OCTOBER' => _('October'),
		'S_NOVEMBER' => _('November'),
		'S_DECEMBER' => _('December'),
		'S_MONDAY_SHORT_BIG' => _x('M', 'Monday short'),
		'S_TUESDAY_SHORT_BIG' => _x('T', 'Tuesday short'),
		'S_WEDNESDAY_SHORT_BIG' => _x('W', 'Wednesday short'),
		'S_THURSDAY_SHORT_BIG' => _x('T', 'Thursday short'),
		'S_FRIDAY_SHORT_BIG' => _x('F', 'Friday short'),
		'S_SATURDAY_SHORT_BIG' => _x('S', 'Saturday short'),
		'S_SUNDAY_SHORT_BIG' => _x('S', 'Sunday short'),
		'S_NOW' => _('Now'),
		'S_DONE' => _('Done'),
		'S_TIME' => _('Time')
	),
	'class.cmap.js' => array(
		'S_ON' => _('On'),
		'S_OFF' => _('Off'),
		'S_HIDDEN' => _('Hidden'),
		'S_SHOWN' => _('Shown'),
		'S_HOST' => _('Host'),
		'S_MAP' => _('Map'),
		'S_TRIGGER' => _('Trigger'),
		'S_HOST_GROUP' => _('Host group'),
		'S_IMAGE' => _('Image'),
		'S_DEFAULT' => _('Default'),
		'S_CLOSE' => _('Close'),
		'S_PLEASE_SELECT_TWO_ELEMENTS' => _('Please select two elements'),
		'S_DOT' => _('Dot'),
		'S_TWO_ELEMENTS_SHOULD_BE_SELECTED' => _('Two elements should be selected'),
		'S_DELETE_SELECTED_ELEMENTS_Q' => _('Delete selected elements?'),
		'S_NEW_ELEMENT' => _('New element'),
		'S_INCORRECT_ELEMENT_MAP_LINK' => _('All links should have "Name" and "URL" specified'),
		'S_EACH_URL_SHOULD_HAVE_UNIQUE' => _('Each URL should have a unique name. Please make sure there is only one URL named'),
		'S_DELETE_LINKS_BETWEEN_SELECTED_ELEMENTS_Q' => _('Delete links between selected elements?'),
		'S_NO_IMAGES' => 'You need to have at least one image uploaded to create map element. Images can be uploaded in Administration->General->Images section.',
		'S_ICONMAP_IS_NOT_ENABLED' => _('Iconmap is not enabled'),
		'Colour "%1$s" is not correct: expecting hexadecimal colour code (6 symbols).' => _('Colour "%1$s" is not correct: expecting hexadecimal colour code (6 symbols).')
	),
	'class.cmessages.js' => array(
		'S_MUTE' => _('Mute'),
		'S_UNMUTE' => _('Unmute'),
		'S_MESSAGES' => _('Messages'),
		'S_CLEAR' => _('Clear'),
		'S_SNOOZE' => _('Snooze'),
		'S_MOVE' => _('Move')
	),
	'class.cookie.js' => array(
		'S_MAX_COOKIE_SIZE_REACHED' => _('We are sorry, the maximum possible number of elements to remember has been reached.')
	),
	'main.js' => array(
		'S_CLOSE' => _('Close'),
		'S_NO_ELEMENTS_SELECTED' => _('No elements selected!')
	),
	'init.js' => array(
		'Host screens' => _('Host screens'),
		'Go to' => _('Go to'),
		'Latest data' => _('Latest data'),
		'Scripts' => _('Scripts'),
		'Host inventories' => _('Host inventories'),
		'Add service' => _('Add service'),
		'Edit service' => _('Edit service'),
		'Delete service' => _('Delete service'),
		'Delete the selected service?' => _('Delete the selected service?')
	),
	'chosen.jquery.js' => array(
		'Select some options' => _('Select some options'),
		'Select option' => _('Select option'),
		'No results match' => _('No results match')
	),
	'chosen.jquery.js' => array(
		'Keep typing...' => _('Keep typing...'),
		'Looking for' => _('Looking for')
	)
);

if (empty($_GET['files'])) {
	$files = array(
		'prototype.js',
		'jquery.js',
		'jquery-ui.js',
		'activity-indicator.js',
		'common.js',
		'class.cdebug.js',
		'class.cdate.js',
		'class.cookie.js',
		'class.curl.js',
		'class.rpc.js',
		'class.bbcode.js',
		'class.csuggest.js',
		'main.js',
		'functions.js',
		'menu.js',
		'init.js'
	);

	// load frontend messaging only for pages with menus
	if (isset($_GET['isMenu']) && $_GET['isMenu']) {
		$files[] = 'class.cmessages.js';
	}
}
else {
	$files = $_GET['files'];
}

$js = 'if (typeof(locale) == "undefined") { var locale = {}; }'."\n";
foreach ($files as $file) {
	if (isset($tranStrings[$file])) {
		foreach ($tranStrings[$file] as $origStr => $str) {
			$js .= "locale['".$origStr."'] = ".zbx_jsvalue($str).";";
		}
	}
}

foreach ($files as $file) {
	if (isset($availableJScripts[$file])) {
		$js .= file_get_contents('js/'.$availableJScripts[$file].$file)."\n";
	}
}

$jsLength = strlen($js);
$etag = md5($jsLength);
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == $etag) {
	header('HTTP/1.1 304 Not Modified');
	header('ETag: '.$etag);
	exit();
}

header('Content-type: text/javascript; charset=UTF-8');
// breaks if "zlib.output_compression = On"
// header('Content-length: '.$jsLength);
header('Cache-Control: public, must-revalidate');
header('ETag: '.$etag);

echo $js;
