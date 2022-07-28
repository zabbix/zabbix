<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
require_once dirname(__FILE__).'/include/locales.inc.php';
require_once dirname(__FILE__).'/include/gettextwrapper.inc.php';

setupLocale(array_key_exists('lang', $_GET) ? (string) $_GET['lang'] : 'en_GB');

require_once dirname(__FILE__).'/include/js.inc.php';

require_once dirname(__FILE__).'/include/defines.inc.php';
require_once dirname(__FILE__).'/include/classes/helpers/CCookieHelper.php';

// available scripts 'scriptFileName' => 'path relative to js/'
$available_js = [
	'common.js' => '',
	'class.dashboard.js' => '',
	'class.dashboard.page.js' => '',
	'class.dashboard.widget.placeholder.js' => '',
	'class.widget.js' => 'widgets/',
	'class.widget.iterator.js' => 'widgets/',
	'class.widget.clock.js' => 'widgets/',
	'class.widget.geomap.js' => 'widgets/',
	'class.widget.graph.js' => 'widgets/',
	'class.widget.graph-prototype.js' => 'widgets/',
	'class.widget.item.js' => 'widgets/',
	'class.widget.map.js' => 'widgets/',
	'class.widget.navtree.js' => 'widgets/',
	'class.widget.paste-placeholder.js' => 'widgets/',
	'class.widget.problems.js' => 'widgets/',
	'class.widget.problemsbysv.js' => 'widgets/',
	'class.widget.svggraph.js' => 'widgets/',
	'class.widget.trigerover.js' => 'widgets/',
	'hostinterfacemanager.js' => '',
	'hostmacrosmanager.js' => '',
	'menupopup.js' => '',
	'gtlc.js' => '',
	'functions.js' => '',
	'main.js' => '',
	'dom.js' => '',
	'servercheck.js' => '',
	'flickerfreescreen.js' => '',
	'multilineinput.js' => '',
	'multiselect.js' => '',
	'colorpicker.js' => '',
	'chkbxrange.js' => '',
	'layout.mode.js' => '',
	'textareaflexible.js' => '',
	'inputsecret.js' => '',
	'macrovalue.js' => '',
	// vendors
	'jquery.js' => 'vendors/',
	'jquery-ui.js' => 'vendors/',
	'leaflet.js' => 'vendors/Leaflet/Leaflet/',
	'leaflet.markercluster.js' => 'vendors/Leaflet/Leaflet.markercluster/',
	// classes
	'component.z-bar-gauge.js' => '',
	'component.z-select.js' => '',
	'class.base-component.js' => '',
	'class.bbcode.js' => '',
	'class.calendar.js' => '',
	'class.cdate.js' => '',
	'class.cdebug.js' => '',
	'class.cmap.js' => '',
	'class.expandable.subfilter.js' => '',
	'class.geomaps.js' => '',
	'class.localstorage.js' => '',
	'class.menu.js' => '',
	'class.menu-item.js' => '',
	'class.notifications.js' => '',
	'class.notification.js' => '',
	'class.notification.collection.js' => '',
	'class.notifications.audio.js' => '',
	'class.browsertab.js' => '',
	'class.cnavtree.js' => '',
	'class.cookie.js' => '',
	'class.coverride.js' => '',
	'class.crangecontrol.js' => '',
	'class.csuggest.js' => '',
	'class.csvggraph.js' => '',
	'class.curl.js' => '',
	'class.overlaycollection.js' => '',
	'class.overlay.js' => '',
	'class.cverticalaccordion.js' => '',
	'class.scrollable.js' => '',
	'class.sidebar.js' => '',
	'class.sortable.js' => '',
	'class.svg.canvas.js' => 'vector/',
	'class.svg.map.js' => 'vector/',
	'class.cviewswitcher.js' => '',
	'class.rpc.js' => '',
	'class.tabfilter.js' => '',
	'class.tabfilteritem.js' => '',
	'class.tagfilteritem.js' => '',
	'class.template.js' => '',
	'init.js' => '',
	'class.tab-indicators.js' => '',
	// templates
	'sysmap.tpl.js' => 'templates/',
	// page-specific scripts
	'items.js' => 'pages/',
	'report2.js' => 'pages/',
	'report4.js' => 'pages/',
	'setup.js' => 'pages/',
	'popup.condition.common.js' => 'pages/',
	'popup.operation.common.js' => 'pages/'
];

$translate_strings = [
	'gtlc.js' => [
		'S_MINUTE_SHORT' => _x('m', 'minute short')
	],
	'class.overlay.js' => [
		'Operation details' => _('Operation details')
	],
	'class.dashboard.js' => [
		'Actions' => _('Actions'),
		'Cannot add dashboard page: maximum number of %1$d dashboard pages has been added.' => _('Cannot add dashboard page: maximum number of %1$d dashboard pages has been added.'),
		'Cannot add widget: not enough free space on the dashboard.' => _('Cannot add widget: not enough free space on the dashboard.'),
		'Copy' => _('Copy'),
		'Delete' => _('Delete'),
		'Failed to paste dashboard page.' => _('Failed to paste dashboard page.'),
		'Failed to paste widget.' => _('Failed to paste widget.'),
		'Failed to update dashboard page properties.' => _('Failed to update dashboard page properties.'),
		'Failed to update dashboard properties.' => _('Failed to update dashboard properties.'),
		'Failed to update widget properties.' => _('Failed to update widget properties.'),
		'Page %1$d' => _('Page %1$d'),
		'Paste widget' => _('Paste widget'),
		'Properties' => _('Properties'),
		'Start slideshow' => _('Start slideshow'),
		'Stop slideshow' => _('Stop slideshow')
	],
	'class.dashboard.widget.placeholder.js' => [
		'Add a new widget' => _('Add a new widget'),
		'Click and drag to desired size.' => _('Click and drag to desired size.'),
		'Release to create a widget.' => _('Release to create a widget.')
	],
	'class.geomaps.js' => [
		'Severity filter' => _('Severity filter')
	],
	'class.widget.js' => [
		'10 seconds' => _n('%1$s second', '%1$s seconds', 10),
		'30 seconds' => _n('%1$s second', '%1$s seconds', 30),
		'1 minute' => _n('%1$s minute', '%1$s minutes', 1),
		'2 minutes' => _n('%1$s minute', '%1$s minutes', 2),
		'10 minutes' => _n('%1$s minute', '%1$s minutes', 10),
		'15 minutes' => _n('%1$s minute', '%1$s minutes', 15),
		'Actions' => _s('Actions'),
		'Copy' => _s('Copy'),
		'Delete' => _s('Delete'),
		'Edit' => _s('Edit'),
		'No refresh' => _('No refresh'),
		'Paste' => _s('Paste'),
		'Refresh interval' => _s('Refresh interval')
	],
	'class.widget.geomap.js' => [
		'Actions' => _('Actions'),
		'Set this view as default' => _('Set this view as default'),
		'Reset to initial view' => _('Reset to initial view'),
		'No problems' => _('No problems'),
		'Not classified' => _('Not classified'),
		'Information' => _('Information'),
		'Warning' => _('Warning'),
		'Average' => _('Average'),
		'High' => _('High'),
		'Disaster' => _('Disaster'),
		'Host' => _('Host'),
		'D' => _x('D', 'abbreviation of severity level'),
		'H' => _x('H', 'abbreviation of severity level'),
		'A' => _x('A', 'abbreviation of severity level'),
		'W' => _x('W', 'abbreviation of severity level'),
		'I' => _x('I', 'abbreviation of severity level'),
		'N' => _x('N', 'abbreviation of severity level'),
		'Navigate to default view' => _('Navigate to default view'),
		'Navigate to initial view' => _('Navigate to initial view')
	],
	'class.widget.iterator.js' => [
		'Next page' => _s('Next page'),
		'Previous page' => _s('Previous page'),
		'Widget is too small for the specified number of columns and rows.' =>
			_s('Widget is too small for the specified number of columns and rows.')
	],
	'class.widget.graph.js' => [
		'Actions' => _s('Actions'),
		'Download image' => _s('Download image')
	],
	'class.widget.navtree.js' => [
		'Add' => _s('Add'),
		'Add child element' => _s('Add child element'),
		'Add multiple maps' => _s('Add multiple maps'),
		'Apply' => _s('Apply'),
		'Cancel' => _s('Cancel'),
		'Edit' => _s('Edit'),
		'Edit tree element' => _s('Edit tree element'),
		'Remove' => _s('Remove')
	],
	'class.widget.svggraph.js' => [
		'Actions' => _s('Actions'),
		'Download image' => _s('Download image')
	],
	'functions.js' => [
		'Cancel' => _('Cancel'),
		'S_CLOSE' => _('Close'),
		'Execute' => _('Execute'),
		'Execution confirmation' => _('Execution confirmation'),
		'S_YEAR_SHORT' => _x('y', 'year short'),
		'S_MONTH_SHORT' => _x('M', 'month short'),
		'S_DAY_SHORT' => _x('d', 'day short'),
		'S_HOUR_SHORT' => _x('h', 'hour short'),
		'S_MINUTE_SHORT' => _x('m', 'minute short'),
		'Do you wish to replace the conditional expression?' => _('Do you wish to replace the conditional expression?'),
		'Success message' => _('Success message'),
		'Error message' => _('Error message'),
		'Warning message' => _('Warning message')
	],
	'class.calendar.js' => [
		'S_CALENDAR' => _('Calendar'),
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
		'S_MONDAY' => _('Monday'),
		'S_TUESDAY' => _('Tuesday'),
		'S_WEDNESDAY' => _('Wednesday'),
		'S_THURSDAY' => _('Thursday'),
		'S_FRIDAY' => _('Friday'),
		'S_SATURDAY' => _('Saturday'),
		'S_SUNDAY' => _('Sunday'),
		'S_MONDAY_SHORT_BIG' => _x('M', 'Monday short'),
		'S_TUESDAY_SHORT_BIG' => _x('T', 'Tuesday short'),
		'S_WEDNESDAY_SHORT_BIG' => _x('W', 'Wednesday short'),
		'S_THURSDAY_SHORT_BIG' => _x('T', 'Thursday short'),
		'S_FRIDAY_SHORT_BIG' => _x('F', 'Friday short'),
		'S_SATURDAY_SHORT_BIG' => _x('S', 'Saturday short'),
		'S_SUNDAY_SHORT_BIG' => _x('S', 'Sunday short')
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
		'S_TWO_MAP_ELEMENTS_SHOULD_BE_SELECTED' => _('Two map elements should be selected'),
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
		'S_COLOR_IS_NOT_CORRECT' => _('Color "%1$s" is not correct: expecting hexadecimal color code (6 symbols).')
	],
	'class.notifications.js' => [
		'S_PROBLEM_ON' => _('Problem on'),
		'S_RESOLVED' => _('Resolved'),
		'S_MUTE' => _('Mute'),
		'S_CANNOT_SUPPORT_NOTIFICATION_AUDIO' => _('Cannot support notification audio for this device.'),
		'S_UNMUTE' => _('Unmute'),
		'S_CLOSE' => _('Close'),
		'S_SNOOZE' => _('Snooze'),
		'Unexpected server error.' => _('Unexpected server error.')
	],
	'class.cookie.js' => [
		'S_MAX_COOKIE_SIZE_REACHED' => _('We are sorry, the maximum possible number of elements to remember has been reached.')
	],
	'class.coverride.js' => [
		'S_COLOR' => _('color'),
		'S_TIME_SHIFT' => _('time shift')
	],
	'class.cverticalaccordion.js' => [
		'S_COLLAPSE' => _('Collapse'),
		'S_EXPAND' => _('Expand')
	],
	'main.js' => [
		'S_EXPAND' => _('Expand'),
		'S_COLLAPSE' => _('Collapse'),
		'S_CLOSE' => _('Close')
	],
	'hostinterfacemanager.js' => [
		'Agent' => _('Agent'),
		'SNMP' => _('SNMP'),
		'JMX' => _('JMX'),
		'IPMI' => _('IPMI'),
		'No interfaces are defined.' => _('No interfaces are defined.')
	],
	'hostmacrosmanager.js' => [
		'Change' => _x('Change', 'verb'),
		'Remove' => _('Remove'),
		'Revert' => _('Revert')
	],
	'multilineinput.js' => [
		'S_N_CHAR_COUNT' => _('%1$s characters'),
		'S_N_CHAR_COUNT_REMAINING' => _('%1$s characters remaining'),
		'S_CLICK_TO_VIEW_OR_EDIT' => _('Click to view or edit'),
		'S_APPLY' => _('Apply'),
		'S_CANCEL' => _('Cancel')
	],
	'multiselect.js' => [
		'No matches found' => _('No matches found'),
		'More matches found...' => _('More matches found...'),
		'type here to search' => _('type here to search'),
		'new' => _('new'),
		'Select' => _('Select'),
		'Added, %1$s' => _x('Added, %1$s', 'screen reader'),
		'Removed, %1$s' => _x('Removed, %1$s', 'screen reader'),
		'%1$s, read only' => _x('%1$s, read only', 'screen reader'),
		'Cannot be removed' => _x('Cannot be removed', 'screen reader'),
		'Selected, %1$s in position %2$d of %3$d' => _x('Selected, %1$s in position %2$d of %3$d', 'screen reader'),
		'Selected, %1$s, read only, in position %2$d of %3$d' => _x('Selected, %1$s, read only, in position %2$d of %3$d', 'screen reader'),
		'More than %1$d matches for %2$s found' => _x('More than %1$d matches for %2$s found', 'screen reader'),
		'%1$d matches for %2$s found' => _x('%1$d matches for %2$s found', 'screen reader'),
		'%1$s preselected, use down,up arrow keys and enter to select' => _x('%1$s preselected, use down,up arrow keys and enter to select', 'screen reader')
	],
	'menupopup.js' => [
		'500 latest values' => _('500 latest values'),
		'Actions' => _('Actions'),
		'Acknowledge' => _('Acknowledge'),
		'Configuration' => _('Configuration'),
		'Clone' => _('Clone'),
		'Create new' => _('Create new'),
		'Create trigger' => _('Create trigger'),
		'Create dependent item' => _('Create dependent item'),
		'Create dependent discovery rule' => _('Create dependent discovery rule'),
		'Dashboards' => _('Dashboards'),
		'Delete' => _('Delete'),
		'Delete dashboard?' => _('Delete dashboard?'),
		'Do you wish to replace the conditional expression?' => _('Do you wish to replace the conditional expression?'),
		'Execute now' => _('Execute now'),
		'Item' => _('Item'),
		'Insert expression' => _('Insert expression'),
		'Sharing' => _('Sharing'),
		'Trigger status "OK"' => _('Trigger status "OK"'),
		'Trigger status "Problem"' => _('Trigger status "Problem"'),
		'Go to' => _('Go to'),
		'Graph' => _('Graph'),
		'Graphs' => _('Graphs'),
		'History' => _('History'),
		'Host' => _('Host'),
		'Inventory' => _('Inventory'),
		'Latest data' => _('Latest data'),
		'Latest values' => _('Latest values'),
		'Last hour graph' => _('Last hour graph'),
		'Last month graph' => _('Last month graph'),
		'Last week graph' => _('Last week graph'),
		'Problems' => _('Problems'),
		'Scripts' => _('Scripts'),
		'Submap' => _('Submap'),
		'S_TRIGGER' => _('Trigger'),
		'URL' => _('URL'),
		'URLs' => _('URLs'),
		'Values' => _('Values'),
		'Web' => _('Web'),
		'S_SELECTED_SR' => _x('%1$s, selected', 'screen reader')
	],
	'init.js' => [
		'Debug' => _('Debug'),
		'Hide debug' => _('Hide debug')
	],
	'items.js' => [
		'To set a host interface select a single item type for all items' => _('To set a host interface select a single item type for all items'),
		'No interface found' => _('No interface found'),
		'Item type does not use interface' => _('Item type does not use interface')
	],
	'colorpicker.js' => [
		'D' => _x('D', 'Default color option'),
		'S_CLOSE' => _('Close'),
		'Use default' => _('Use default')
	],
	'class.csvggraph.js' => [
		'S_DISPLAYING_FOUND' => _('Displaying %1$s of %2$s found'),
		'S_MINUTE_SHORT' => _x('m', 'minute short')
	],
	'common.js' => [
		'Cancel' => _('Cancel'),
		'Ok' => _('Ok')
	],
	'component.z-select.js' => [
		'All' => _('All')
	],
	'macrovalue.js' => [
		'Set new value' => _('Set new value'),
		'value' => _('value')
	],
	'popup.condition.common.js' => [
		'Services' => _('Services')
	]
];

$js = '';
if (empty($_GET['files'])) {
	$files = [
		'jquery.js',
		'jquery-ui.js',
		'common.js',
		'component.z-bar-gauge.js',
		'component.z-select.js',
		'class.base-component.js',
		'class.cdebug.js',
		'class.overlaycollection.js',
		'class.overlay.js',
		'class.cdate.js',
		'class.cookie.js',
		'class.curl.js',
		'class.menu.js',
		'class.menu-item.js',
		'class.rpc.js',
		'class.bbcode.js',
		'class.csuggest.js',
		'class.scrollable.js',
		'class.sidebar.js',
		'class.template.js',
		'main.js',
		'chkbxrange.js',
		'functions.js',
		'menupopup.js',
		'inputsecret.js',
		'macrovalue.js',
		'multiselect.js',
		'class.cverticalaccordion.js',
		'class.cviewswitcher.js',
		'class.tab-indicators.js',
		'hostinterfacemanager.js',
		'hostmacrosmanager.js',
		'textareaflexible.js',
		'init.js'
	];

	if (CCookieHelper::has(ZBX_SESSION_NAME)) {
		$session = json_decode(base64_decode(CCookieHelper::get(ZBX_SESSION_NAME)), true);
		$js .= 'window.ZBX_SESSION_NAME = "'.crc32($session['sessionid']).'";';
		$files[] = 'class.localstorage.js';
	}

	// load frontend messaging only for some pages
	if (array_key_exists('showGuiMessaging', $_GET) && $_GET['showGuiMessaging']) {
		$files[] = 'class.browsertab.js';
		$files[] = 'class.notification.collection.js';
		$files[] = 'class.notifications.audio.js';
		$files[] = 'class.notification.js';
		$files[] = 'class.notifications.js';
	}

	$js .= 'ZBX_NOREFERER = '.ZBX_NOREFERER.";\n";
}
else {
	$files = $_GET['files'];
}

$js .= 'if (typeof(locale) === "undefined") { var locale = {}; }'."\n";

foreach ($files as $file) {
	if (array_key_exists($file, $translate_strings)) {
		foreach ($translate_strings[$file] as $origStr => $str) {
			$js .= 'locale[\''.$origStr.'\'] = '.json_encode($str).';';
		}
	}
}

foreach ($files as $file) {
	if (array_key_exists($file, $available_js)) {
		$js .= file_get_contents('js/'.$available_js[$file].$file)."\n";
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

header('Content-Type: application/javascript; charset=UTF-8');
// breaks if "zlib.output_compression = On"
// header('Content-length: '.$jsLength);
header('Cache-Control: public, must-revalidate');
header('ETag: "'.$etag.'"');

echo $js;
