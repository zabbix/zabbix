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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/blocks.inc.php';

$page['title'] = _('Dashboard');
$page['file'] = 'dashboard.php';
$page['hist_arg'] = array();
$page['scripts'] = array('class.pmaster.js');
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

require_once dirname(__FILE__).'/include/page_header.php';

//	VAR		TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'groupid' =>	array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'view_style' =>	array(T_ZBX_INT, O_OPT, P_SYS,	IN('0,1'),	null),
	'type' =>		array(T_ZBX_INT, O_OPT, P_SYS,	IN('0,1'),	null),
	'output' =>		array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'jsscriptid' =>	array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'fullscreen' =>	array(T_ZBX_INT, O_OPT, P_SYS,	IN('0,1'),	null),
	// ajax
	'favobj' =>		array(T_ZBX_STR, O_OPT, P_ACT,	null,		null),
	'favref' =>		array(T_ZBX_STR, O_OPT, P_ACT,	null,		null),
	'favid' =>		array(T_ZBX_INT, O_OPT, P_ACT,	null,		null),
	'favcnt' =>		array(T_ZBX_INT, O_OPT, null,	null,		null),
	'pmasterid' =>	array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'favaction' =>	array(T_ZBX_STR, O_OPT, P_ACT,	IN("'add','remove','refresh','flop','sort'"), null),
	'favstate' =>	array(T_ZBX_INT, O_OPT, P_ACT,	NOT_EMPTY,	'isset({favaction})&&"flop"=={favaction}'),
	'favdata' =>	array(T_ZBX_STR, O_OPT, null,	null,		null)
);
check_fields($fields);

/*
 * Filter
 */
$dashconf = array(
	'groupids' => null,
	'maintenance' => null,
	'severity' => null,
	'extAck' => 0,
	'filterEnable' => CProfile::get('web.dashconf.filter.enable', 0)
);

if ($dashconf['filterEnable'] == 1) {
	// groups
	$dashconf['grpswitch'] = CProfile::get('web.dashconf.groups.grpswitch', 0);

	if ($dashconf['grpswitch'] == 0) {
		// null mean all groups
		$dashconf['groupids'] = null;
	}
	else {
		$dashconf['groupids'] = zbx_objectValues(CFavorite::get('web.dashconf.groups.groupids'), 'value');
		$hideGroupIds = zbx_objectValues(CFavorite::get('web.dashconf.groups.hide.groupids'), 'value');

		if ($hideGroupIds) {
			// get all groups if no selected groups defined
			if (empty($dashconf['groupids'])) {
				$groups = API::HostGroup()->get(array(
					'nodeids' => get_current_nodeid(),
					'output' => array('groupid')
				));
				$dashconf['groupids'] = zbx_objectValues($groups, 'groupid');
			}

			$dashconf['groupids'] = array_diff($dashconf['groupids'], $hideGroupIds);

			// get available hosts
			$availableHosts = API::Host()->get(array(
				'groupids' => $dashconf['groupids'],
				'output' => array('hostid')
			));
			$availableHostIds = zbx_objectValues($availableHosts, 'hostid');

			$disabledHosts = API::Host()->get(array(
				'groupids' => $hideGroupIds,
				'output' => array('hostid')
			));
			$disabledHostIds = zbx_objectValues($disabledHosts, 'hostid');

			$dashconf['hostids'] = array_diff($availableHostIds, $disabledHostIds);
		}
		else {
			if (empty($dashconf['groupids'])) {
				// null mean all groups
				$dashconf['groupids'] = null;
			}
		}
	}

	// hosts
	$maintenance = CProfile::get('web.dashconf.hosts.maintenance', 1);
	$dashconf['maintenance'] = ($maintenance == 0) ? 0 : null;

	// triggers
	$severity = CProfile::get('web.dashconf.triggers.severity', null);
	$dashconf['severity'] = zbx_empty($severity) ? null : explode(';', $severity);
	$dashconf['severity'] = zbx_toHash($dashconf['severity']);

	$config = select_config();
	$dashconf['extAck'] = $config['event_ack_enable'] ? CProfile::get('web.dashconf.events.extAck', 0) : 0;
}

/*
 * Actions
 */
if (isset($_REQUEST['favobj'])) {
	$_REQUEST['pmasterid'] = get_request('pmasterid', 'mainpage');

	if ($_REQUEST['favobj'] == 'hat') {
		if ($_REQUEST['favaction'] == 'flop') {
			$widgetName = substr($_REQUEST['favref'], 4);

			CProfile::update('web.dashboard.widget.'.$widgetName.'.state', $_REQUEST['favstate'], PROFILE_TYPE_INT);
		}
		elseif (getRequest('favaction') == 'sort') {
			$favdata = CJs::decodeJson(getRequest('favdata'));

			foreach ($favdata as $col => $column) {
				foreach ($column as $row => $widgetName) {
					$widgetName = substr($widgetName, 4, -7);

					CProfile::update('web.dashboard.widget.'.$widgetName.'.col', $col, PROFILE_TYPE_INT);
					CProfile::update('web.dashboard.widget.'.$widgetName.'.row', $row, PROFILE_TYPE_INT);
				}
			}
		}
		elseif ($_REQUEST['favaction'] == 'refresh') {
			switch ($_REQUEST['favref']) {
				case 'hat_syssum':
					$widget = make_system_status($dashconf);
					$widget->show();
					break;

				case 'hat_hoststat':
					$widget = make_hoststat_summary($dashconf);
					$widget->show();
					break;

				case 'hat_stszbx':
					$widget = make_status_of_zbx();
					$widget->show();
					break;

				case 'hat_lastiss':
					$widget = make_latest_issues($dashconf);
					$widget->show();
					break;

				case 'hat_webovr':
					$widget = make_webmon_overview($dashconf);
					$widget->show();
					break;

				case 'hat_dscvry':
					$widget = make_discovery_status();
					$widget->show();
					break;
			}
		}
	}

	if ($_REQUEST['favobj'] == 'set_rf_rate') {
		$refs = array('hat_syssum', 'hat_stszbx', 'hat_lastiss', 'hat_webovr', 'hat_dscvry', 'hat_hoststat');

		if (str_in_array($_REQUEST['favref'], $refs)) {
			$widgetName = substr($_REQUEST['favref'], 4);

			CProfile::update('web.dashboard.widget.'.$widgetName.'.rf_rate', $_REQUEST['favcnt'], PROFILE_TYPE_INT);
			$_REQUEST['favcnt'] = CProfile::get('web.dashboard.widget.'.$widgetName.'.rf_rate', 60);

			echo get_update_doll_script('mainpage', $_REQUEST['favref'], 'frequency', $_REQUEST['favcnt'])
				.get_update_doll_script('mainpage', $_REQUEST['favref'], 'stopDoll')
				.get_update_doll_script('mainpage', $_REQUEST['favref'], 'startDoll');

			$menu = $submenu = array();

			make_refresh_menu('mainpage', $_REQUEST['favref'], $_REQUEST['favcnt'], null, $menu, $submenu);

			echo 'page_menu["menu_'.$_REQUEST['favref'].'"] = '.zbx_jsvalue($menu['menu_'.$_REQUEST['favref']]).';';
		}
	}

	if ($page['type'] == PAGE_TYPE_JS) {
		// favorite graphs
		if (str_in_array($_REQUEST['favobj'], array('itemid', 'graphid'))) {
			$result = false;

			if ($_REQUEST['favaction'] == 'add') {
				zbx_value2array($_REQUEST['favid']);

				foreach ($_REQUEST['favid'] as $sourceId) {
					$result = CFavorite::add('web.favorite.graphids', $sourceId, $_REQUEST['favobj']);
				}
			}
			elseif ($_REQUEST['favaction'] == 'remove') {
				$result = CFavorite::remove('web.favorite.graphids', $_REQUEST['favid'], $_REQUEST['favobj']);
			}

			if ($result) {
				$dataHtml = make_favorite_graphs();

				echo '$("hat_favgrph").update('.zbx_jsvalue($dataHtml->toString()).'); '.
					'page_submenu["menu_graphs"] = '.zbx_jsvalue(make_graph_submenu()).';';
			}
		}
		// favorite maps
		elseif ($_REQUEST['favobj'] == 'sysmapid') {
			$result = false;

			if ($_REQUEST['favaction'] == 'add') {
				zbx_value2array($_REQUEST['favid']);

				foreach ($_REQUEST['favid'] as $sourceId) {
					$result = CFavorite::add('web.favorite.sysmapids', $sourceId, $_REQUEST['favobj']);
				}
			}
			elseif ($_REQUEST['favaction'] == 'remove') {
				$result = CFavorite::remove('web.favorite.sysmapids', $_REQUEST['favid'], $_REQUEST['favobj']);
			}

			if ($result) {
				$dataHtml = make_favorite_maps();

				echo '$("hat_favmap").update('.zbx_jsvalue($dataHtml->toString()).'); '.
					'page_submenu["menu_sysmaps"] = '.zbx_jsvalue(make_sysmap_submenu()).';';
			}
		}
		// favorite screens, slideshows
		elseif (str_in_array($_REQUEST['favobj'], array('screenid', 'slideshowid'))) {
			$result = false;

			if ($_REQUEST['favaction'] == 'add') {
				zbx_value2array($_REQUEST['favid']);

				foreach ($_REQUEST['favid'] as $sourceId) {
					$result = CFavorite::add('web.favorite.screenids', $sourceId, $_REQUEST['favobj']);
				}
			}
			elseif ($_REQUEST['favaction'] == 'remove') {
				$result = CFavorite::remove('web.favorite.screenids', $_REQUEST['favid'], $_REQUEST['favobj']);
			}

			if ($result) {
				$dataHtml = make_favorite_screens();

				echo '$("hat_favscr").update('.zbx_jsvalue($dataHtml->toString()).'); '.
					'page_submenu["menu_screens"] = '.zbx_jsvalue(make_screen_submenu()).';';
			}
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
$dashboardWidget = new CWidget('dashboard_wdgt');
$dashboardWidget->setClass('header');
$dashboardWidget->addHeader(_('PERSONAL DASHBOARD'), array(
	new CIcon(
		_s('Configure (Filter %s)', $dashconf['filterEnable'] ? _('Enabled') : _('Disabled')),
		$dashconf['filterEnable'] ? 'iconconfig_hl' : 'iconconfig',
		"document.location = 'dashconf.php';"
	),
	SPACE,
	get_icon('fullscreen', array('fullscreen' => $_REQUEST['fullscreen'])))
);

// js menu arrays
$menu = $submenu = array();
make_graph_menu($menu, $submenu);
make_sysmap_menu($menu, $submenu);
make_screen_menu($menu, $submenu);

make_refresh_menu('mainpage', 'hat_syssum', CProfile::get('web.dashboard.widget.syssum.rf_rate', 60), null, $menu, $submenu);
make_refresh_menu('mainpage', 'hat_hoststat', CProfile::get('web.dashboard.widget.hoststat.rf_rate', 60), null, $menu, $submenu);
make_refresh_menu('mainpage', 'hat_stszbx', CProfile::get('web.dashboard.widget.stszbx.rf_rate', 60), null, $menu, $submenu);
make_refresh_menu('mainpage', 'hat_lastiss', CProfile::get('web.dashboard.widget.lastiss.rf_rate', 60), null, $menu, $submenu);
make_refresh_menu('mainpage', 'hat_webovr', CProfile::get('web.dashboard.widget.webovr.rf_rate', 60), null, $menu, $submenu);
make_refresh_menu('mainpage', 'hat_dscvry', CProfile::get('web.dashboard.widget.dscvry.rf_rate', 60), null, $menu, $submenu);

insert_js('var page_menu='.zbx_jsvalue($menu).";\n".'var page_submenu='.zbx_jsvalue($submenu).";\n");

/*
 * Columns
 */
$columns = array(array(), array(), array());

// refresh tabs
$refreshTabs = array(
	array('id' => 'hat_syssum', 'frequency' => CProfile::get('web.dashboard.widget.syssum.rf_rate', 120)),
	array('id' => 'hat_stszbx', 'frequency' => CProfile::get('web.dashboard.widget.stszbx.rf_rate', 120)),
	array('id' => 'hat_lastiss', 'frequency' => CProfile::get('web.dashboard.widget.lastiss.rf_rate', 60)),
	array('id' => 'hat_webovr', 'frequency' => CProfile::get('web.dashboard.widget.webovr.rf_rate', 60)),
	array('id' => 'hat_hoststat', 'frequency' => CProfile::get('web.dashboard.widget.hoststat.rf_rate', 60))
);

// favorite graphs
$favouriteGraphs = new CUIWidget(
	'hat_favgrph',
	make_favorite_graphs(),
	CProfile::get('web.dashboard.widget.favgrph.state', 1)
);
$favouriteGraphs->setHeader(_('Favourite graphs'), get_icon('menu', array('menu' => 'graphs')));
$favouriteGraphs->setFooter(new CLink(_('Graphs').' &raquo;', 'charts.php', 'highlight'), true);

$col = CProfile::get('web.dashboard.widget.favgrph.col', 0);
$row = CProfile::get('web.dashboard.widget.favgrph.row', 0);
$columns[$col][$row] = $favouriteGraphs;

// favorite screens
$favouriteScreens = new CUIWidget(
	'hat_favscr',
	make_favorite_screens(),
	CProfile::get('web.dashboard.widget.favscr.state', 1)
);
$favouriteScreens->setHeader(_('Favourite screens'), get_icon('menu', array('menu' => 'screens')));
$favouriteScreens->setFooter(new CLink(_('Screens').' &raquo;', 'screens.php', 'highlight'), true);

$col = CProfile::get('web.dashboard.widget.favscr.col', 0);
$row = CProfile::get('web.dashboard.widget.favscr.row', 1);
$columns[$col][$row] = $favouriteScreens;

// favorite maps
$favouriteMaps = new CUIWidget(
	'hat_favmap',
	make_favorite_maps(),
	CProfile::get('web.dashboard.widget.favmap.state', 1)
);
$favouriteMaps->setHeader(_('Favourite maps'), get_icon('menu', array('menu' => 'sysmaps')));
$favouriteMaps->setFooter(new CLink(_('Maps').' &raquo;', 'maps.php', 'highlight'), true);

$col = CProfile::get('web.dashboard.widget.favmap.col', 0);
$row = CProfile::get('web.dashboard.widget.favmap.row', 2);
$columns[$col][$row] = $favouriteMaps;

// status of Zabbix
if (CWebUser::$data['type'] == USER_TYPE_SUPER_ADMIN) {
	$zabbixStatus = new CUIWidget(
		'hat_stszbx',
		new CSpan(_('Loading...'), 'textcolorstyles'),
		CProfile::get('web.dashboard.widget.stszbx.state', 1)
	);
	$zabbixStatus->setHeader(_('Status of Zabbix'), get_icon('menu', array('menu' => 'hat_stszbx')));
	$zabbixStatus->setFooter(new CDiv(SPACE, 'textwhite', 'hat_stszbx_footer'));

	$col = CProfile::get('web.dashboard.widget.stszbx.col', 1);
	$row = CProfile::get('web.dashboard.widget.stszbx.row', 0);
	$columns[$col][$row] = $zabbixStatus;
}

// system status
$systemStatus = new CUIWidget(
	'hat_syssum',
	new CSpan(_('Loading...'), 'textcolorstyles'),
	CProfile::get('web.dashboard.widget.syssum.state', 1)
);
$systemStatus->setHeader(_('System status'), get_icon('menu', array('menu' => 'hat_syssum')));
$systemStatus->setFooter(new CDiv(SPACE, 'textwhite', 'hat_syssum_footer'));

$col = CProfile::get('web.dashboard.widget.syssum.col', 1);
$row = CProfile::get('web.dashboard.widget.syssum.row', 1);
$columns[$col][$row] = $systemStatus;

// host status
$hostStatus = new CUIWidget(
	'hat_hoststat',
	new CSpan(_('Loading...'), 'textcolorstyles'),
	CProfile::get('web.dashboard.widget.hoststat.state', 1)
);
$hostStatus->setHeader(_('Host status'), get_icon('menu', array('menu' => 'hat_hoststat')));
$hostStatus->setFooter(new CDiv(SPACE, 'textwhite', 'hat_hoststat_footer'));

$col = CProfile::get('web.dashboard.widget.hoststat.col', 1);
$row = CProfile::get('web.dashboard.widget.hoststat.row', 2);
$columns[$col][$row] = $hostStatus;

// last issues
$lastIssues = new CUIWidget(
	'hat_lastiss',
	new CSpan(_('Loading...'), 'textcolorstyles'),
	CProfile::get('web.dashboard.widget.lastiss.state', 1)
);
$lastIssues->setHeader(
	_n('Last %1$d issue', 'Last %1$d issues', DEFAULT_LATEST_ISSUES_CNT),
	get_icon('menu', array('menu' => 'hat_lastiss'))
);
$lastIssues->setFooter(new CDiv(SPACE, 'textwhite', 'hat_lastiss_footer'));

$col = CProfile::get('web.dashboard.widget.lastiss.col', 1);
$row = CProfile::get('web.dashboard.widget.lastiss.row', 3);
$columns[$col][$row] = $lastIssues;

// web monitoring
$webMonitoring = new CUIWidget(
	'hat_webovr',
	new CSpan(_('Loading...'), 'textcolorstyles'),
	CProfile::get('web.dashboard.widget.webovr.state', 1)
);
$webMonitoring->setHeader(_('Web monitoring'), get_icon('menu', array('menu' => 'hat_webovr')));
$webMonitoring->setFooter(new CDiv(SPACE, 'textwhite', 'hat_webovr_footer'));

$col = CProfile::get('web.dashboard.widget.webovr.col', 1);
$row = CProfile::get('web.dashboard.widget.webovr.row', 4);
$columns[$col][$row] = $webMonitoring;

// discovery rules
$dbDiscoveryRules = DBfetch(DBselect(
	'SELECT COUNT(d.druleid) AS cnt'.
	' FROM drules d'.
	' WHERE d.status='.DRULE_STATUS_ACTIVE.
		andDbNode('d.druleid')
));
if ($dbDiscoveryRules['cnt'] > 0 && check_right_on_discovery()) {
	$refreshTabs[] = array(
		'id' => 'hat_dscvry',
		'frequency' => CProfile::get('web.dashboard.widget.dscvry.rf_rate', 60)
	);

	$discoveryStatus = new CUIWidget(
		'hat_dscvry',
		new CSpan(_('Loading...'), 'textcolorstyles'),
		CProfile::get('web.dashboard.widget.dscvry.state', 1)
	);
	$discoveryStatus->setHeader(_('Discovery status'), get_icon('menu', array('menu' => 'hat_dscvry')));
	$discoveryStatus->setFooter(new CDiv(SPACE, 'textwhite', 'hat_dscvry_footer'));

	$col = CProfile::get('web.dashboard.widget.dscvry.col', 1);
	$row = CProfile::get('web.dashboard.widget.dscvry.row', 5);
	$columns[$col][$row] = $discoveryStatus;
}

add_doll_objects($refreshTabs);
foreach ($columns as $key => $val) {
	ksort($columns[$key]);
}

$dashboardTable = new CTable();
$dashboardTable->addRow(
	array(
		new CDiv($columns[0], 'column'),
		new CDiv($columns[1], 'column'),
		new CDiv($columns[2], 'column')
	),
	'top'
);

$dashboardWidget->addItem($dashboardTable);
$dashboardWidget->show();

// activating blinking
zbx_add_post_js('jqBlink.blink();');

?>
<script type="text/javascript">
	function addPopupValues(list) {
		var favorites = {graphid: 1, itemid: 1, screenid: 1, slideshowid: 1, sysmapid: 1};

		if (isset(list.object, favorites)) {
			var favouriteIds = [];

			for (var i = 0; i < list.values.length; i++) {
				favouriteIds.push(list.values[i][list.object]);
			}

			send_params({
				favobj: list.object,
				'favid[]': favouriteIds,
				favaction: 'add'
			});
		}
	}
</script>
<?php
require_once dirname(__FILE__).'/include/page_footer.php';
