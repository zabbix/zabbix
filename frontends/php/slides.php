<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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
	'reset' =>			array(T_ZBX_STR, O_OPT, P_SYS,	IN("'reset'"), null),
	'fullscreen' =>		array(T_ZBX_INT, O_OPT, P_SYS,	IN('0,1'), null),
	// ajax
	'favobj' =>			array(T_ZBX_STR, O_OPT, P_ACT,	null,	null),
	'favref' =>			array(T_ZBX_STR, O_OPT, P_ACT,	NOT_EMPTY, null),
	'favid' =>			array(T_ZBX_INT, O_OPT, P_ACT,	null,	null),
	'favcnt' =>			array(T_ZBX_STR, O_OPT, null,	null,	null),
	'pmasterid' =>		array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
	// actions
	'favaction' =>		array(T_ZBX_STR, O_OPT, P_ACT,	IN("'add','remove','refresh','flop'"), null),
	'favstate' =>		array(T_ZBX_INT, O_OPT, P_ACT,	NOT_EMPTY, 'isset({favaction})&&"flop"=={favaction}'),
	'upd_counter' =>	array(T_ZBX_INT, O_OPT, P_ACT,	null,	null)
);
check_fields($fields);

/*
 * Permissions
 */
if (get_request('groupid') && !API::HostGroup()->isReadable(array($_REQUEST['groupid']))
		|| get_request('hostid') && !API::Host()->isReadable(array($_REQUEST['hostid']))) {
	access_deny();
}
if (get_request('elementid')) {
	$slideshow = get_slideshow_by_slideshowid($_REQUEST['elementid']);
	if (!$slideshow) {
		access_deny();
	}
}

/*
 * Actions
 */
if (isset($_REQUEST['favobj'])) {
	$_REQUEST['pmasterid'] = get_request('pmasterid', 'mainpage');

	if ($_REQUEST['favobj'] == 'filter') {
		CProfile::update('web.slides.filter.state', $_REQUEST['favstate'], PROFILE_TYPE_INT);
	}
	elseif (str_in_array($_REQUEST['favobj'], array('screenid', 'slideshowid'))) {
		$result = false;
		if ($_REQUEST['favaction'] == 'add') {
			$result = CFavorite::add('web.favorite.screenids', $_REQUEST['favid'], $_REQUEST['favobj']);
			if ($result) {
				echo '$("addrm_fav").title = "'._('Remove from').' '._('Favourites').'";'."\n".
					'$("addrm_fav").onclick = function() { rm4favorites("'.$_REQUEST['favobj'].'", "'.$_REQUEST['favid'].'", 0); };'."\n";
			}
		}
		elseif ($_REQUEST['favaction'] == 'remove') {
			$result = CFavorite::remove('web.favorite.screenids', $_REQUEST['favid'], $_REQUEST['favobj']);
			if ($result) {
				echo '$("addrm_fav").title = "'._('Add to').' '._('Favourites').'";'."\n".
					'$("addrm_fav").onclick = function() { add2favorites("'.$_REQUEST['favobj'].'", "'.$_REQUEST['favid'].'"); };'."\n";
			}
		}
		if ($page['type'] == PAGE_TYPE_JS && $result) {
			echo 'switchElementsClass("addrm_fav", "iconminus", "iconplus");';
		}
	}
	elseif ($_REQUEST['favobj'] == 'hat') {
		if ($_REQUEST['favref'] == 'hat_slides') {
			$elementid = get_request('elementid');

			if (!is_null($elementid)) {
				$slideshow = get_slideshow_by_slideshowid($elementid);
				$screen = get_slideshow($elementid, get_request('upd_counter'));
				$screens = API::Screen()->get(array(
					'screenids' => $screen['screenid']
				));

				if (empty($screens)) {
					insert_js('alert("'._('No permissions').'");');
				}
				else {
					$page['type'] = PAGE_TYPE_JS;

					// display screens
					$screens = API::Screen()->get(array(
						'screenids' => $screen['screenid'],
						'output' => API_OUTPUT_EXTEND,
						'selectScreenItems' => API_OUTPUT_EXTEND
					));
					$currentScreen = reset($screens);

					$screenBuilder = new CScreenBuilder(array(
						'screen' => $currentScreen,
						'mode' => SCREEN_MODE_PREVIEW,
						'profileIdx' => 'web.slides',
						'profileIdx2' => $elementid,
						'period' => get_request('period'),
						'stime' => get_request('stime')
					));

					CScreenBuilder::insertScreenCleanJs();

					echo $screenBuilder->show()->toString();

					CScreenBuilder::insertScreenStandardJs(array(
						'timeline' => $screenBuilder->timeline,
						'profileIdx' => $screenBuilder->profileIdx
					));

					insertPagePostJs();

					// insert slide show refresh js
					$refresh = ($screen['delay'] > 0) ? $screen['delay'] : $slideshow['delay'];
					$refresh_multipl = CProfile::get('web.slides.rf_rate.hat_slides', 1, $elementid);

					$script = get_update_doll_script('mainpage', $_REQUEST['favref'], 'frequency', $refresh * $refresh_multipl)."\n";
					$script .= get_update_doll_script('mainpage', $_REQUEST['favref'], 'restartDoll')."\n";
					insert_js($script);
				}
			}
			else {
				echo SBR._('No slide shows defined.');
			}
		}
	}
	elseif ($_REQUEST['favobj'] == 'set_rf_rate') {
		if (str_in_array($_REQUEST['favref'], array('hat_slides'))) {
			$elementid = get_request('elementid');

			CProfile::update('web.slides.rf_rate.hat_slides', $_REQUEST['favcnt'], PROFILE_TYPE_STR, $elementid);

			$menu = array();
			$submenu = array();

			make_refresh_menu('mainpage', $_REQUEST['favref'], $_REQUEST['favcnt'], array('elementid' => $elementid), $menu, $submenu, 2);
			echo 'page_menu["menu_'.$_REQUEST['favref'].'"] = '.zbx_jsvalue($menu['menu_'.$_REQUEST['favref']]).';'."\n";
		}
	}

	// saving fixed/dynamic setting to profile
	if ($_REQUEST['favobj'] == 'timelinefixedperiod') {
		if (isset($_REQUEST['favid'])) {
			CProfile::update('web.slides.timelinefixed', $_REQUEST['favid'], PROFILE_TYPE_INT);
		}
	}
}
if ($page['type'] == PAGE_TYPE_JS || $page['type'] == PAGE_TYPE_HTML_BLOCK) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit();
}

/*
 * Display
 */
$data = array(
	'fullscreen' => $_REQUEST['fullscreen'],
	'slideshows' => array()
);

// get slideshows
$db_slideshows = DBselect(
	'SELECT s.slideshowid,s.name'.
	' FROM slideshows s'.
	whereDbNode('s.slideshowid')
);
while ($slideshow = DBfetch($db_slideshows)) {
	if (slideshow_accessible($slideshow['slideshowid'], PERM_READ)) {
		$data['slideshows'][$slideshow['slideshowid']] = $slideshow;
	}
};
order_result($data['slideshows'], 'name');

// get element id
$data['elementid'] = get_request('elementid', CProfile::get('web.slides.elementid', null));

CProfile::update('web.slides.elementid', $data['elementid'], PROFILE_TYPE_ID);

if (!isset($data['slideshows'][$data['elementid']])) {
	$slideshow = reset($data['slideshows']);
	$data['elementid'] = $slideshow['slideshowid'];
}

// get screen
$data['screen'] = empty($data['elementid']) ? array() : get_slideshow($data['elementid'], 0);
if (!empty($data['screen'])) {
	// get groups and hosts
	if (check_dynamic_items($data['elementid'], 1)) {
		$data['isDynamicItems'] = true;

		$data['pageFilter'] = new CPageFilter(array(
			'groups' => array(
				'monitored_hosts' => true,
				'with_items' => true
			),
			'hosts' => array(
				'monitored_hosts' => true,
				'with_items' => true,
				'DDFirstLabel' => _('Default')
			),
			'hostid' => get_request('hostid', null),
			'groupid' => get_request('groupid', null)
		));
	}

	// get element
	$data['element'] = get_slideshow_by_slideshowid($data['elementid']);
	if ($data['screen']['delay'] > 0) {
		$data['element']['delay'] = $data['screen']['delay'];
	}

	show_messages();

	// js menu
	$data['menu'] = array();
	$data['submenu'] = array();
	$data['refresh_multiplier'] = CProfile::get('web.slides.rf_rate.hat_slides', 1, $data['elementid']);

	if (empty($data['refresh_multiplier'])) {
		$data['refresh_multiplier'] = 1;
		CProfile::update('web.slides.rf_rate.hat_slides', $data['refresh_multiplier'], PROFILE_TYPE_STR, $data['elementid']);
	}

	make_refresh_menu('mainpage', 'hat_slides', $data['refresh_multiplier'], array('elementid' => $data['elementid']), $data['menu'], $data['submenu'], 2);
}

// render view
$slidesView = new CView('monitoring.slides', $data);
$slidesView->render();
$slidesView->show();

require_once dirname(__FILE__).'/include/page_footer.php';
