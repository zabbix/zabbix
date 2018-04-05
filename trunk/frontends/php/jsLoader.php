<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
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
	setlocale(LC_NUMERIC, ['C', 'POSIX', 'en', 'en_US', 'en_US.UTF-8', 'English_United States.1252', 'en_GB', 'en_GB.UTF-8']);
}

require_once dirname(__FILE__).'/include/translateDefines.inc.php';

// available scripts 'scriptFileName' => 'path relative to js/'
$availableJScripts = [
	'common.js' => '',
	'dashboard.grid.js' => '',
	'menupopup.js' => '',
	'gtlc.js' => '',
	'functions.js' => '',
	'main.js' => '',
	'dom.js' => '',
	'servercheck.js' => '',
	'flickerfreescreen.js' => '',
	'multiselect.js' => '',
	'chkbxrange.js' => '',
	// vendors
	'prototype.js' => 'vendors/',
	'jquery.js' => 'vendors/',
	'jquery-ui.js' => 'vendors/',
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
	'class.svg.canvas.js' => 'vector/',
	'class.svg.map.js' => 'vector/',
	'class.pmaster.js' => '',
	'class.cviewswitcher.js' => '',
	'init.js' => '',
	// templates
	'sysmap.tpl.js' => 'templates/',
	// page-specific scripts
	'items.js' => 'pages/',
];

$tranStrings = [
	'gtlc.js' => [
		'S_ALL_S' => _('All'),
		'S_ZOOM' => _('Zoom'),
		'S_FIXED_SMALL' => _('fixed'),
		'S_DYNAMIC_SMALL' => _('dynamic'),
		'S_NOW_SMALL' => _('now'),
		'S_YEAR_SHORT' => _x('y', 'year short'),
		'S_MONTH_SHORT' => _x('m', 'month short'),
		'S_DAY_SHORT' => _x('d', 'day short'),
		'S_HOUR_SHORT' => _x('h', 'hour short'),
		'S_MINUTE_SHORT' => _x('m', 'minute short'),
		'S_DATE_FORMAT' => DATE_TIME_FORMAT
	],
	'dashboard.grid.js' => [
		'Edit widget' => _('Edit widget'),
		'Add widget' => _('Add widget'),
		'Apply' => _('Apply'),
		'Add' => _('Add'),
		'Edit' => _('Edit'),
		'Cancel' => _('Cancel'),
		'Delete' => _('Delete'),
		'You have unsaved changes.' => _('You have unsaved changes.'),
		'Are you sure, you want to leave this page?' => _('Are you sure, you want to leave this page?'),
		'Cannot add widgets in kiosk mode' => _('Cannot add widgets in kiosk mode'),
		'Add a new widget' => _('Add a new widget')
	],
	'functions.js' => [
		'Cancel' => _('Cancel'),
		'Execute' => _('Execute'),
		'Execution confirmation' => _('Execution confirmation')
	],
	'class.calendar.js' => [
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
	],
	'class.cmap.js' => [
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
		'S_PLEASE_SELECT_TWO_ELEMENTS' => _('Please select two elements'),
		'S_DOT' => _('Dot'),
		'S_TWO_ELEMENTS_SHOULD_BE_SELECTED' => _('Two elements should be selected'),
		'S_DELETE_SELECTED_ELEMENTS_Q' => _('Delete selected elements?'),
		'S_DELETE_SELECTED_SHAPES_Q' => _('Delete selected shapes?'),
		'S_BRING_TO_FRONT' => _('Bring to front'),
		'S_BRING_FORWARD' => _('Bring forward'),
		'S_SEND_BACKWARD' => _('Send backward'),
		'S_SEND_TO_BACK' => _('Send to back'),
		'S_REMOVE' => _('Remove'),
		'S_NEW_ELEMENT' => _('New element'),
		'S_COPY' => _('Copy'),
		'S_PASTE' => _('Paste'),
		'S_PASTE_SIMPLE' => _('Paste without external links'),
		'S_INCORRECT_ELEMENT_MAP_LINK' => _('All links should have "Name" and "URL" specified'),
		'S_EACH_URL_SHOULD_HAVE_UNIQUE' => _('Each URL should have a unique name. Please make sure there is only one URL named'),
		'S_DELETE_LINKS_BETWEEN_SELECTED_ELEMENTS_Q' => _('Delete links between selected elements?'),
		'S_MACRO_EXPAND_ERROR' => _('Cannot expand macros.'),
		'S_NO_IMAGES' => 'You need to have at least one image uploaded to create map element. Images can be uploaded in Administration->General->Images section.',
		'Colour "%1$s" is not correct: expecting hexadecimal colour code (6 symbols).' => _('Colour "%1$s" is not correct: expecting hexadecimal colour code (6 symbols).')
	],
	'class.cmessages.js' => [
		'S_MUTE' => _('Mute'),
		'S_UNMUTE' => _('Unmute'),
		'S_MESSAGES' => _('Messages'),
		'S_CLEAR' => _('Clear'),
		'S_SNOOZE' => _('Snooze')
	],
	'class.cookie.js' => [
		'S_MAX_COOKIE_SIZE_REACHED' => _('We are sorry, the maximum possible number of elements to remember has been reached.')
	],
	'main.js' => [
		'S_EXPAND' => _('Expand'),
		'S_COLLAPSE' => _('Collapse'),
	],
	'multiselect.js' => [
		'No matches found' => _('No matches found'),
		'More matches found...' => _('More matches found...'),
		'type here to search' => _('type here to search'),
		'new' => _('new'),
		'Select' => _('Select')
	],
	'menupopup.js' => [
		'Acknowledge' => _('Acknowledge'),
		'Access denied' => _('Access denied'),
		'Cancel' => _('Cancel'),
		'Configuration' => _('Configuration'),
		'Create trigger' => _('Create trigger'),
		'Dashboard sharing' => _('Dashboard sharing'),
		'Delete service "%1$s"?' => _('Delete service "%1$s"?'),
		'Do you wish to replace the conditional expression?' => _('Do you wish to replace the conditional expression?'),
		'Edit trigger' => _('Edit trigger'),
		'Insert expression' => _('Insert expression'),
		'Trigger status "OK"' => _('Trigger status "OK"'),
		'Trigger status "Problem"' => _('Trigger status "Problem"'),
		'Item "%1$s"' => _('Item "%1$s"'),
		'Go to' => _('Go to'),
		'Graphs' => _('Graphs'),
		'History' => _('History'),
		'Host inventory' => _('Host inventory'),
		'Host screens' => _('Host screens'),
		'Latest data' => _('Latest data'),
		'Latest values' => _('Latest values'),
		'Last hour graph' => _('Last hour graph'),
		'Last month graph' => _('Last month graph'),
		'Last week graph' => _('Last week graph'),
		'Problems' => _('Problems'),
		'Refresh interval' => _('Refresh interval'),
		'Refresh interval multiplier' => _('Refresh interval multiplier'),
		'Scripts' => _('Scripts'),
		'Something went wrong. Please try again later!' => _('Something went wrong. Please try again later!'),
		'Submap' => _('Submap'),
		'Trigger' => _('Trigger'),
		'Triggers' => _('Triggers'),
		'Update' => _('Update'),
		'URL' => _('URL'),
		'URLs' => _('URLs'),
		'No refresh' => _('No refresh'),
		'You need permission to perform this action!' => _('You need permission to perform this action!'),
		'10 seconds' => _n('%1$s second', '%1$s seconds', 10),
		'30 seconds' => _n('%1$s second', '%1$s seconds', 30),
		'1 minute' => _n('%1$s minute', '%1$s minutes', 1),
		'2 minutes' => _n('%1$s minute', '%1$s minutes', 2),
		'10 minutes' => _n('%1$s minute', '%1$s minutes', 10),
		'15 minutes' => _n('%1$s minute', '%1$s minutes', 15)
	],
	'items.js' => [
		'To set a host interface select a single item type for all items' => _('To set a host interface select a single item type for all items'),
		'No interface found' => _('No interface found')
	],
	'class.cnavtree.js' => [
		'Edit' => _('Edit'),
		'Remove' => _('Remove'),
		'root' => _('root'),
		'Edit tree element' => _('Edit tree element'),
		'Apply' => _('Apply'),
		'Add' => _('Add'),
		'Cancel' => _('Cancel'),
		'Add child element' => _('Add child element'),
		'Add multiple maps' => _('Add multiple maps')
	]
];

if (empty($_GET['files'])) {
	$files = [
		'prototype.js',
		'jquery.js',
		'jquery-ui.js',
		'common.js',
		'class.cdebug.js',
		'class.cdate.js',
		'class.cookie.js',
		'class.curl.js',
		'class.rpc.js',
		'class.bbcode.js',
		'class.csuggest.js',
		'main.js',
		'chkbxrange.js',
		'functions.js',
		'menupopup.js',
		'init.js'
	];

	// load frontend messaging only for some pages
	if (isset($_GET['showGuiMessaging']) && $_GET['showGuiMessaging']) {
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

$etag = md5($js);
/**
 * strpos function allow to check ETag value to fix cases when web server compression is used:
 * - For case when apache server appends "-gzip" suffix to ETag.
 *   https://bz.apache.org/bugzilla/show_bug.cgi?id=39727
 *   https://bz.apache.org/bugzilla/show_bug.cgi?id=45023
 * - For case when nginx v1.7.3+ server mark ETag as weak adding "W/" prefix
 *   http://nginx.org/en/CHANGES
 */
if (array_key_exists('HTTP_IF_NONE_MATCH', $_SERVER) && strpos($_SERVER['HTTP_IF_NONE_MATCH'], $etag) !== false) {
	header('HTTP/1.1 304 Not Modified');
	header('ETag: "'.$etag.'"');
	exit;
}

if (in_array('prototype.js', $files)) {
	// This takes care of the Array toJSON incompatibility with JSON.stringify.
	$js .=
		'var _json_stringify = JSON.stringify;'.
		'JSON.stringify = function(value) {'.
			'var _array_tojson = Array.prototype.toJSON,'.
				'ret;'.
			'delete Array.prototype.toJSON;'.
			'ret = _json_stringify(value);'.
			'Array.prototype.toJSON = _array_tojson;'.
			'return ret;'.
		'};';
}

header('Content-Type: application/javascript; charset=UTF-8');
// breaks if "zlib.output_compression = On"
// header('Content-length: '.$jsLength);
header('Cache-Control: public, must-revalidate');
header('ETag: "'.$etag.'"');

echo $js;
