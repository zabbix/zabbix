<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/graphs.inc.php';
require_once dirname(__FILE__).'/include/screens.inc.php';
require_once dirname(__FILE__).'/include/blocks.inc.php';

$page['title'] = _('Custom slides');
$page['file'] = 'slides.php';
$page['scripts'] = ['class.pmaster.js', 'class.calendar.js', 'gtlc.js', 'flickerfreescreen.js'];
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

define('ZBX_PAGE_DO_JS_REFRESH', 1);

ob_start();
require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'groupid' =>		[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,	null],
	'hostid' =>			[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,	null],
	'elementid' =>		[T_ZBX_INT, O_OPT, P_SYS|P_NZERO, DB_ID, null],
	'step' =>			[T_ZBX_INT, O_OPT, P_SYS,	BETWEEN(0, 65535), null],
	'period' =>			[T_ZBX_INT, O_OPT, P_SYS,	null,	null],
	'stime' =>			[T_ZBX_STR, O_OPT, P_SYS,	null,	null],
	'reset' =>			[T_ZBX_STR, O_OPT, P_SYS,	IN('"reset"'), null],
	'fullscreen' =>		[T_ZBX_INT, O_OPT, P_SYS,	IN('0,1'), null],
	// ajax
	'widgetRefresh' =>	[T_ZBX_STR, O_OPT, null,	null,	null],
	'widgetRefreshRate' => [T_ZBX_STR, O_OPT, P_ACT, null,	null],
	'favobj' =>			[T_ZBX_STR, O_OPT, P_ACT,	null,	null],
	'favid' =>			[T_ZBX_INT, O_OPT, P_ACT,	null,	null],
	'upd_counter' =>	[T_ZBX_INT, O_OPT, P_ACT,	null,	null]
];
check_fields($fields);

/*
 * Permissions
 */
$data = [];

if (getRequest('groupid') && !API::HostGroup()->isReadable([getRequest('groupid')])
		|| getRequest('hostid') && !API::Host()->isReadable([getRequest('hostid')])) {
	access_deny();
}
if (hasRequest('elementid')) {
	$data['screen'] = get_slideshow_by_slideshowid(getRequest('elementid'), PERM_READ);

	if (!$data['screen']) {
		access_deny();
	}
}
else {
	$data['screen'] = [];
}

/*
 * Actions
 */
if ((hasRequest('widgetRefresh') || hasRequest('widgetRefreshRate')) && $data['screen']) {
	$elementId = getRequest('elementid');

	$screen = getSlideshowScreens($elementId, getRequest('upd_counter'));

	// display screens
	$dbScreens = API::Screen()->get([
		'screenids' => $screen['screenid'],
		'output' => API_OUTPUT_EXTEND,
		'selectScreenItems' => API_OUTPUT_EXTEND
	]);

	if (!$dbScreens) {
		insert_js('alert("'._('No permissions').'");');
	}
	else {
		$dbScreen = reset($dbScreens);

		// get fresh widget data
		if (hasRequest('widgetRefresh')) {
			$screenBuilder = new CScreenBuilder([
				'screen' => $dbScreen,
				'mode' => SCREEN_MODE_PREVIEW,
				'profileIdx' => 'web.slides',
				'profileIdx2' => $elementId,
				'hostid' => getRequest('hostid'),
				'period' => getRequest('period'),
				'stime' => getRequest('stime')
			]);

			CScreenBuilder::insertScreenCleanJs();

			echo (new CDiv($screenBuilder->show()))
				->addClass(ZBX_STYLE_TABLE_FORMS_CONTAINER)
				->toString();

			CScreenBuilder::insertScreenStandardJs([
				'timeline' => $screenBuilder->timeline,
				'profileIdx' => $screenBuilder->profileIdx
			]);

			insertPagePostJs();
		}

		// refresh rate
		if (hasRequest('widgetRefreshRate')) {
			$widgetRefreshRate = substr(getRequest('widgetRefreshRate'), 1);

			CProfile::update('web.slides.rf_rate.'.WIDGET_SLIDESHOW, $widgetRefreshRate, PROFILE_TYPE_STR, $elementId);

			$delay = ($screen['delay'] > 0) ? $screen['delay'] : $data['screen']['delay'];

			echo 'PMasters["slideshows"].dolls["'.WIDGET_SLIDESHOW.'"].frequency('.CJs::encodeJson($delay * $widgetRefreshRate).');'."\n"
				.'PMasters["slideshows"].dolls["'.WIDGET_SLIDESHOW.'"].restartDoll();';
		}
	}
}

// filter state
if (hasRequest('favobj') && hasRequest('favid')) {
	$favouriteObject = getRequest('favobj');
	$favouriteId = getRequest('favid');

	// saving fixed/dynamic setting to profile
	if ($favouriteObject === 'timelinefixedperiod') {
		CProfile::update('web.slides.timelinefixed', $favouriteId, PROFILE_TYPE_INT);
	}
}

if ($page['type'] == PAGE_TYPE_JS || $page['type'] == PAGE_TYPE_HTML_BLOCK) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit;
}

/*
 * Display
 */
if ($data['screen']) {
	$data['elementId'] = getRequest('elementid');
	CProfile::update('web.slides.elementid', getRequest('elementid'), PROFILE_TYPE_ID);
}
else {
	$data['elementId'] = CProfile::get('web.slides.elementid');
	$data['screen'] = get_slideshow_by_slideshowid($data['elementId'], PERM_READ);

	if (!$data['screen']) {
		// Redirect to slide show list.
		ob_end_clean();
		redirect('slideconf.php');
	}
}

$data['fullscreen'] = getRequest('fullscreen');

if ($data['screen']) {
	// get groups and hosts
	if (check_dynamic_items($data['elementId'], 1)) {
		$data['isDynamicItems'] = true;

		$data['pageFilter'] = new CPageFilter([
			'groups' => [
				'monitored_hosts' => true,
				'with_items' => true
			],
			'hosts' => [
				'monitored_hosts' => true,
				'with_items' => true,
				'DDFirstLabel' => _('not selected')
			],
			'hostid' => getRequest('hostid'),
			'groupid' => getRequest('groupid')
		]);

		$data['groupid'] = $data['pageFilter']->groupid;
		$data['hostid'] = $data['pageFilter']->hostid;
	}

	// get element
	$data['element'] = get_slideshow_by_slideshowid($data['elementId'], PERM_READ);
	$data['screen']['editable'] = (bool) get_slideshow_by_slideshowid($data['elementId'], PERM_READ_WRITE);

	if ($data['screen']['delay'] > 0) {
		$data['element']['delay'] = $data['screen']['delay'];
	}

	show_messages();
}

// refresh
$data['refreshMultiplier'] = CProfile::get('web.slides.rf_rate.'.WIDGET_SLIDESHOW, 1, $data['elementId']);

// render view
$slidesView = new CView('monitoring.slides', $data);
$slidesView->render();
$slidesView->show();

require_once dirname(__FILE__).'/include/page_footer.php';
