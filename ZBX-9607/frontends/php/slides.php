<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
$page['hist_arg'] = array('elementid');
$page['scripts'] = array('class.pmaster.js', 'class.calendar.js', 'gtlc.js', 'flickerfreescreen.js');
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

define('ZBX_PAGE_DO_JS_REFRESH', 1);

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'groupid' =>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,	null),
	'hostid' =>			array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,	null),
	'elementid' =>		array(T_ZBX_INT, O_OPT, P_SYS|P_NZERO, DB_ID, null),
	'step' =>			array(T_ZBX_INT, O_OPT, P_SYS,	BETWEEN(0, 65535), null),
	'period' =>			array(T_ZBX_INT, O_OPT, P_SYS,	null,	null),
	'stime' =>			array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
	'reset' =>			array(T_ZBX_STR, O_OPT, P_SYS,	IN('"reset"'), null),
	'fullscreen' =>		array(T_ZBX_INT, O_OPT, P_SYS,	IN('0,1'), null),
	// ajax
	'widgetRefresh' =>	array(T_ZBX_STR, O_OPT, null,	null,	null),
	'widgetRefreshRate' => array(T_ZBX_STR, O_OPT, P_ACT, null,	null),
	'filterState' =>	array(T_ZBX_INT, O_OPT, P_ACT, null,	null),
	'favobj' =>			array(T_ZBX_STR, O_OPT, P_ACT,	null,	null),
	'favid' =>			array(T_ZBX_INT, O_OPT, P_ACT,	null,	null),
	'favaction' =>		array(T_ZBX_STR, O_OPT, P_ACT,	IN('"add","remove"'), null),
	'upd_counter' =>	array(T_ZBX_INT, O_OPT, P_ACT,	null,	null)
);
check_fields($fields);

/*
 * Permissions
 */
$dbSlideshow = null;

if (getRequest('groupid') && !API::HostGroup()->isReadable(array(getRequest('groupid')))
		|| getRequest('hostid') && !API::Host()->isReadable(array(getRequest('hostid')))) {
	access_deny();
}
if (hasRequest('elementid')) {
	$dbSlideshow = get_slideshow_by_slideshowid(getRequest('elementid'));

	if (!$dbSlideshow) {
		access_deny();
	}
}

/*
 * Actions
 */
if ((hasRequest('widgetRefresh') || hasRequest('widgetRefreshRate')) && $dbSlideshow) {
	$elementId = getRequest('elementid');

	$screen = getSlideshowScreens($elementId, getRequest('upd_counter'));

	// display screens
	$dbScreens = API::Screen()->get(array(
		'screenids' => $screen['screenid'],
		'output' => API_OUTPUT_EXTEND,
		'selectScreenItems' => API_OUTPUT_EXTEND
	));

	if (!$dbScreens) {
		insert_js('alert("'._('No permissions').'");');
	}
	else {
		$dbScreen = reset($dbScreens);

		// get fresh widget data
		if (hasRequest('widgetRefresh')) {
			$screenBuilder = new CScreenBuilder(array(
				'screen' => $dbScreen,
				'mode' => SCREEN_MODE_PREVIEW,
				'profileIdx' => 'web.slides',
				'profileIdx2' => $elementId,
				'hostid' => getRequest('hostid'),
				'period' => getRequest('period'),
				'stime' => getRequest('stime')
			));

			CScreenBuilder::insertScreenCleanJs();

			echo $screenBuilder->show()->toString();

			CScreenBuilder::insertScreenStandardJs(array(
				'timeline' => $screenBuilder->timeline,
				'profileIdx' => $screenBuilder->profileIdx
			));

			insertPagePostJs();
		}

		// refresh rate
		if (hasRequest('widgetRefreshRate')) {
			$widgetRefreshRate = substr(getRequest('widgetRefreshRate'), 1);

			CProfile::update('web.slides.rf_rate.'.WIDGET_SLIDESHOW, $widgetRefreshRate, PROFILE_TYPE_STR, $elementId);

			$delay = ($screen['delay'] > 0) ? $screen['delay'] : $dbSlideshow['delay'];

			echo 'PMasters["slideshows"].dolls["'.WIDGET_SLIDESHOW.'"].frequency('.CJs::encodeJson($delay * $widgetRefreshRate).');'."\n"
				.'PMasters["slideshows"].dolls["'.WIDGET_SLIDESHOW.'"].restartDoll();';
		}
	}
}

// filter state
if (hasRequest('filterState')) {
	CProfile::update('web.slides.filter.state', getRequest('filterState'), PROFILE_TYPE_INT);
}

if (hasRequest('favobj') && hasRequest('favid')) {
	$favouriteObject = getRequest('favobj');
	$favouriteId = getRequest('favid');

	// favourites
	if (hasRequest('favaction') && in_array($favouriteObject, array('screenid', 'slideshowid'))) {
		$result = false;

		DBstart();

		if (getRequest('favaction') === 'add') {
			$result = CFavorite::add('web.favorite.screenids', $favouriteId, $favouriteObject);
			if ($result) {
				echo '$("addrm_fav").title = "'._('Remove from').' '._('Favourites').'";'."\n"
					.'$("addrm_fav").onclick = function() { rm4favorites("'.$favouriteObject.'", "'.$favouriteId.'"); };'."\n";
			}
		}
		else {
			$result = CFavorite::remove('web.favorite.screenids', $favouriteId, $favouriteObject);
			if ($result) {
				echo '$("addrm_fav").title = "'._('Add to').' '._('Favourites').'";'."\n"
					.'$("addrm_fav").onclick = function() { add2favorites("'.$favouriteObject.'", "'.$favouriteId.'"); };'."\n";
			}
		}

		$result = DBend($result);

		if ($page['type'] == PAGE_TYPE_JS && $result) {
			echo 'switchElementClass("addrm_fav", "iconminus", "iconplus");';
		}
	}

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
$data = array(
	'fullscreen' => getRequest('fullscreen'),
	'elementId' => getRequest('elementid', CProfile::get('web.slides.elementid')),
	'slideshows' => array()
);

// get slideshows
$dbSlideshows = DBselect('SELECT s.slideshowid,s.name FROM slideshows s');

while ($dbSlideshow = DBfetch($dbSlideshows)) {
	if (slideshow_accessible($dbSlideshow['slideshowid'], PERM_READ)) {
		$data['slideshows'][$dbSlideshow['slideshowid']] = $dbSlideshow;
	}
};
order_result($data['slideshows'], 'name');

if (!isset($data['slideshows'][$data['elementId']])) {
	$slideshow = reset($data['slideshows']);

	$data['elementId'] = $slideshow['slideshowid'];
}

CProfile::update('web.slides.elementid', $data['elementId'], PROFILE_TYPE_ID);

// get screen
$data['screen'] = $data['elementId'] ? getSlideshowScreens($data['elementId'], 0) : array();

if ($data['screen']) {
	// get groups and hosts
	if (check_dynamic_items($data['elementId'], 1)) {
		$data['isDynamicItems'] = true;

		$data['pageFilter'] = new CPageFilter(array(
			'groups' => array(
				'monitored_hosts' => true,
				'with_items' => true
			),
			'hosts' => array(
				'monitored_hosts' => true,
				'with_items' => true,
				'DDFirstLabel' => _('not selected')
			),
			'hostid' => getRequest('hostid'),
			'groupid' => getRequest('groupid')
		));

		$data['groupid'] = $data['pageFilter']->groupid;
		$data['hostid'] = $data['pageFilter']->hostid;
	}

	// get element
	$data['element'] = get_slideshow_by_slideshowid($data['elementId']);

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
