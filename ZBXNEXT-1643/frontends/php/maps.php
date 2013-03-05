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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/maps.inc.php';

$page['title'] = _('Network maps');
$page['file'] = 'maps.php';
$page['hist_arg'] = array('sysmapid');
$page['scripts'] = array();
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

if ($page['type'] == PAGE_TYPE_HTML) {
	define('ZBX_PAGE_DO_REFRESH', 1);
}

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'sysmapid' =>	array(T_ZBX_INT, O_OPT, P_SYS|P_NZERO,	DB_ID,					null),
	'mapname' =>	array(T_ZBX_STR, O_OPT, P_SYS,			null,					null),
	'fullscreen' =>	array(T_ZBX_INT, O_OPT, P_SYS,			IN('0,1'),				null),
	'favobj' =>		array(T_ZBX_STR, O_OPT, P_ACT,			null,					null),
	'favref' =>		array(T_ZBX_STR, O_OPT, P_ACT,			NOT_EMPTY,				null),
	'favid' =>		array(T_ZBX_INT, O_OPT, P_ACT,			null,					null),
	'favstate' =>	array(T_ZBX_INT, O_OPT, P_ACT,			NOT_EMPTY,				null),
	'favaction' =>	array(T_ZBX_STR, O_OPT, P_ACT,			IN("'add','remove'"),	null)
);
check_fields($fields);

/*
 * Ajax
 */
if (isset($_REQUEST['favobj'])) {
	if ($_REQUEST['favobj'] == 'hat') {
		CProfile::update('web.maps.hats.'.$_REQUEST['favref'].'.state', $_REQUEST['favstate'], PROFILE_TYPE_INT);
	}
	elseif ($_REQUEST['favobj'] == 'sysmapid') {
		$result = false;

		if ($_REQUEST['favaction'] == 'add') {
			$result = add2favorites('web.favorite.sysmapids', $_REQUEST['favid'], $_REQUEST['favobj']);
			if ($result) {
				echo '$("addrm_fav").title = "'._('Remove from favourites').'";'."\n".
					'$("addrm_fav").onclick = function() { rm4favorites("sysmapid", "'.$_REQUEST['favid'].'", 0); }'."\n";
			}
		}
		elseif ($_REQUEST['favaction'] == 'remove') {
			$result = rm4favorites('web.favorite.sysmapids', $_REQUEST['favid'], $_REQUEST['favobj']);
			if ($result) {
				echo '$("addrm_fav").title = "'._('Add to favourites').'";'."\n".
					'$("addrm_fav").onclick = function() { add2favorites("sysmapid", "'.$_REQUEST['favid'].'"); }'."\n";
			}
		}

		if ($page['type'] == PAGE_TYPE_JS && $result) {
			echo 'switchElementsClass("addrm_fav", "iconminus", "iconplus");';
		}
	}
}

if ($page['type'] == PAGE_TYPE_JS || $page['type'] == PAGE_TYPE_HTML_BLOCK) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit();
}

/*
 * Permissions
 */
$maps = API::Map()->get(array(
	'output' => array('sysmapid', 'name'),
	'nodeids' => get_current_nodeid(),
	'preservekeys' => true
));
order_result($maps, 'name');

if ($mapName = get_request('mapname')) {
	unset($_REQUEST['sysmapid']);

	foreach ($maps as $map) {
		if ($map['name'] === $mapName) {
			$_REQUEST['sysmapid'] = $map['sysmapid'];
		}
	}
}
elseif (empty($_REQUEST['sysmapid'])) {
	$_REQUEST['sysmapid'] = CProfile::get('web.maps.sysmapid');

	if (!$_REQUEST['sysmapid'] && !isset($maps[$_REQUEST['sysmapid']])) {
		if ($firstMap = reset($maps)) {
			$_REQUEST['sysmapid'] = $firstMap['sysmapid'];
		}
	}
}

if (isset($_REQUEST['sysmapid']) && !isset($maps[$_REQUEST['sysmapid']])) {
	access_deny();
}

CProfile::update('web.maps.sysmapid', $_REQUEST['sysmapid'], PROFILE_TYPE_ID);

/*
 * Display
 */
$data = array(
	'fullscreen' => get_request('fullscreen'),
	'sysmapid' => $_REQUEST['sysmapid'],
	'maps' => $maps
);

$data['map'] = API::Map()->get(array(
	'output' => API_OUTPUT_EXTEND,
	'sysmapids' => $data['sysmapid'],
	'expandUrls' => true,
	'selectSelements' => API_OUTPUT_EXTEND,
	'selectLinks' => API_OUTPUT_EXTEND,
	'preservekeys' => true
));
$data['map'] = reset($data['map']);

// render view
$mapsView = new CView('monitoring.maps', $data);
$mapsView->render();
$mapsView->show();

require_once dirname(__FILE__).'/include/page_footer.php';
