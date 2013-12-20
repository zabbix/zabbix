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
	'groupid' =>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'view_style' =>		array(T_ZBX_INT, O_OPT, P_SYS,	IN('0,1'),	null),
	'type' =>			array(T_ZBX_INT, O_OPT, P_SYS,	IN('0,1'),	null),
	'output' =>			array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'jsscriptid' =>		array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'fullscreen' =>		array(T_ZBX_INT, O_OPT, P_SYS,	IN('0,1'),	null),
	// ajax
	'widgetName' =>		array(T_ZBX_STR, O_OPT, P_ACT,	null,		null),
	'widgetRefresh' =>	array(T_ZBX_STR, O_OPT, P_ACT,	null,		null),
	'widgetRefreshRate' => array(T_ZBX_STR, O_OPT, P_ACT, null,		null),
	'widgetSort' =>		array(T_ZBX_STR, O_OPT, P_ACT,	null,		null),
	'widgetState' =>	array(T_ZBX_STR, O_OPT, P_ACT,	null,		null),
	'favobj' =>			array(T_ZBX_STR, O_OPT, P_ACT,	null,		null),
	'favaction' =>		array(T_ZBX_STR, O_OPT, P_ACT,	IN('"add","remove"'), null),
	'favid' =>			array(T_ZBX_STR, O_OPT, P_ACT,	null,		null)
);
check_fields($fields);

/*
 * Filter
 */
$dashboardConfig = array(
	'groupids' => null,
	'maintenance' => null,
	'severity' => null,
	'extAck' => 0,
	'filterEnable' => CProfile::get('web.dashconf.filter.enable', 0)
);

if ($dashboardConfig['filterEnable'] == 1) {
	// groups
	$dashboardConfig['grpswitch'] = CProfile::get('web.dashconf.groups.grpswitch', 0);

	if ($dashboardConfig['grpswitch'] == 0) {
		// null mean all groups
		$dashboardConfig['groupids'] = null;
	}
	else {
		$dashboardConfig['groupids'] = zbx_objectValues(CFavorite::get('web.dashconf.groups.groupids'), 'value');
		$hideHostGroupIds = zbx_objectValues(CFavorite::get('web.dashconf.groups.hide.groupids'), 'value');

		if ($hideHostGroupIds) {
			// get all groups if no selected groups defined
			if (!$dashboardConfig['groupids']) {
				$dbHostGroups = API::HostGroup()->get(array(
					'nodeids' => get_current_nodeid(),
					'output' => array('groupid')
				));
				$dashboardConfig['groupids'] = zbx_objectValues($dbHostGroups, 'groupid');
			}

			$dashboardConfig['groupids'] = array_diff($dashboardConfig['groupids'], $hideHostGroupIds);

			// get available hosts
			$dbAvailableHosts = API::Host()->get(array(
				'groupids' => $dashboardConfig['groupids'],
				'output' => array('hostid')
			));
			$availableHostIds = zbx_objectValues($dbAvailableHosts, 'hostid');

			$dbDisabledHosts = API::Host()->get(array(
				'groupids' => $hideHostGroupIds,
				'output' => array('hostid')
			));
			$disabledHostIds = zbx_objectValues($dbDisabledHosts, 'hostid');

			$dashboardConfig['hostids'] = array_diff($availableHostIds, $disabledHostIds);
		}
		else {
			if (!$dashboardConfig['groupids']) {
				// null mean all groups
				$dashboardConfig['groupids'] = null;
			}
		}
	}

	// hosts
	$maintenance = CProfile::get('web.dashconf.hosts.maintenance', 1);
	$dashboardConfig['maintenance'] = ($maintenance == 0) ? 0 : null;

	// triggers
	$severity = CProfile::get('web.dashconf.triggers.severity', null);
	$dashboardConfig['severity'] = zbx_empty($severity) ? null : explode(';', $severity);
	$dashboardConfig['severity'] = zbx_toHash($dashboardConfig['severity']);

	$config = select_config();
	$dashboardConfig['extAck'] = $config['event_ack_enable'] ? CProfile::get('web.dashconf.events.extAck', 0) : 0;
}

/*
 * Actions
 */
// get fresh widget data
if (hasRequest('widgetRefresh')) {
	switch (getRequest('widgetRefresh')) {
		case WIDGET_SYSTEM_STATUS:
			$widget = make_system_status($dashboardConfig);
			$widget->show();
			break;

		case WIDGET_HOST_STATUS:
			$widget = make_hoststat_summary($dashboardConfig);
			$widget->show();
			break;

		case WIDGET_ZABBIX_STATUS:
			$widget = make_status_of_zbx();
			$widget->show();
			break;

		case WIDGET_LAST_ISSUES:
			$widget = make_latest_issues($dashboardConfig);
			$widget->show();
			break;

		case WIDGET_WEB_OVERVIEW:
			$widget = make_webmon_overview($dashboardConfig);
			$widget->show();
			break;

		case WIDGET_DISCOVERY_STATUS:
			$widget = make_discovery_status();
			$widget->show();
			break;
	}
}

if (hasRequest('widgetName')) {
	$widgetName = getRequest('widgetName');

	$widgets = array(
		WIDGET_SYSTEM_STATUS, WIDGET_ZABBIX_STATUS, WIDGET_LAST_ISSUES,
		WIDGET_WEB_OVERVIEW, WIDGET_DISCOVERY_STATUS, WIDGET_HOST_STATUS
	);

	if (in_array($widgetName, $widgets)) {
		// refresh rate
		if (hasRequest('widgetRefreshRate')) {
			$widgetRefreshRate = getRequest('widgetRefreshRate');

			CProfile::update('web.dashboard.widget.'.$widgetName.'.rf_rate', $widgetRefreshRate, PROFILE_TYPE_INT);

			echo updateWidgetRefresh('dashboard', $widgetName, 'frequency', $widgetRefreshRate)
				.updateWidgetRefresh('dashboard', $widgetName, 'restartDoll');
		}

		// widget state
		if (hasRequest('widgetState')) {
			CProfile::update('web.dashboard.widget.'.$widgetName.'.state', getRequest('widgetState'), PROFILE_TYPE_INT);
		}
	}
}

// sort
if (hasRequest('widgetSort')) {
	foreach (CJs::decodeJson(getRequest('widgetSort')) as $col => $column) {
		foreach ($column as $row => $widgetName) {
			$widgetName = str_replace('_widget', '', $widgetName);

			CProfile::update('web.dashboard.widget.'.$widgetName.'.col', $col, PROFILE_TYPE_INT);
			CProfile::update('web.dashboard.widget.'.$widgetName.'.row', $row, PROFILE_TYPE_INT);
		}
	}
}

// favorites
if (hasRequest('favobj') && hasRequest('favaction')) {
	$favouriteObject = getRequest('favobj');
	$favouriteAction = getRequest('favaction');
	$favouriteId = getRequest('favid');

	switch ($favouriteObject) {
		// favourite graphs
		case 'itemid':
		case 'graphid':
			if ($favouriteAction == 'add') {
				zbx_value2array($favouriteId);

				foreach ($favouriteId as $id) {
					CFavorite::add('web.favorite.graphids', $id, $favouriteObject);
				}
			}
			elseif ($favouriteAction == 'remove') {
				CFavorite::remove('web.favorite.graphids', $favouriteId, $favouriteObject);
			}

			$data = make_favorite_graphs();
			$data = $data->toString();

			echo 'jQuery("#'.WIDGET_FAVOURITE_GRAPHS.'").html('.zbx_jsvalue($data).'); '.
					'page_submenu["menu_graphs"] = '.zbx_jsvalue(make_graph_submenu()).';';
			break;

		// favourite maps
		case 'sysmapid':
			if ($favouriteAction == 'add') {
				zbx_value2array($favouriteId);

				foreach ($favouriteId as $id) {
					CFavorite::add('web.favorite.sysmapids', $id, $favouriteObject);
				}
			}
			elseif ($favouriteAction == 'remove') {
				CFavorite::remove('web.favorite.sysmapids', $favouriteId, $favouriteObject);
			}

			$data = make_favorite_maps();
			$data = $data->toString();

			echo 'jQuery("#'.WIDGET_FAVOURITE_MAPS.'").html('.zbx_jsvalue($data).'); '.
					'page_submenu["menu_sysmaps"] = '.zbx_jsvalue(make_sysmap_submenu()).';';
			break;

		// favourite screens, slideshows
		case 'screenid':
		case 'slideshowid':
			if ($favouriteAction == 'add') {
				zbx_value2array($favouriteId);

				foreach ($favouriteId as $id) {
					CFavorite::add('web.favorite.screenids', $id, $favouriteObject);
				}
			}
			elseif ($favouriteAction == 'remove') {
				CFavorite::remove('web.favorite.screenids', $favouriteId, $favouriteObject);
			}

			$data = make_favorite_screens();
			$data = $data->toString();

			echo 'jQuery("#'.WIDGET_FAVOURITE_SCREENS.'").html('.zbx_jsvalue($data).'); '.
					'page_submenu["menu_screens"] = '.zbx_jsvalue(make_screen_submenu()).';';
			break;
	}
}

if (in_array($page['type'], array(PAGE_TYPE_JS, PAGE_TYPE_HTML_BLOCK))) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit;
}

/*
 * Display
 */
$dashboardWidget = new CWidget(null, 'dashboard');
$dashboardWidget->setClass('header');
$dashboardWidget->addHeader(_('PERSONAL DASHBOARD'), array(
	new CIcon(
		_s('Configure (Filter %s)', $dashboardConfig['filterEnable'] ? _('Enabled') : _('Disabled')),
		$dashboardConfig['filterEnable'] ? 'iconconfig_hl' : 'iconconfig',
		'document.location = "dashconf.php";'
	),
	SPACE,
	get_icon('fullscreen', array('fullscreen' => getRequest('fullscreen'))))
);

// js menu arrays
/*$menu = $submenu = array();
make_graph_menu($menu, $submenu);
make_sysmap_menu($menu, $submenu);
make_screen_menu($menu, $submenu);*/

/*
 * Dashboard grid
 */
$dashboardGrid = array(array(), array(), array());
$widgetRefreshParams = array();

// favourite graphs
$icon = get_icon('menu', array('menu' => 'graphs'));

$favouriteGraphs = new CUIWidget(WIDGET_FAVOURITE_GRAPHS, make_favorite_graphs());
$favouriteGraphs->setState(CProfile::get('web.dashboard.widget.'.WIDGET_FAVOURITE_GRAPHS.'.state', 1));
$favouriteGraphs->setHeader(_('Favourite graphs'), $icon);
$favouriteGraphs->setFooter(new CLink(_('Graphs').' &raquo;', 'charts.php', 'highlight'), true);

$col = CProfile::get('web.dashboard.widget.'.WIDGET_FAVOURITE_GRAPHS.'.col', 0);
$row = CProfile::get('web.dashboard.widget.'.WIDGET_FAVOURITE_GRAPHS.'.row', 0);
$dashboardGrid[$col][$row] = $favouriteGraphs;

// favourite screens
$favouriteScreens = new CUIWidget(WIDGET_FAVOURITE_SCREENS, make_favorite_screens());
$favouriteScreens->setState(CProfile::get('web.dashboard.widget.'.WIDGET_FAVOURITE_SCREENS.'.state', 1));
$favouriteScreens->setHeader(_('Favourite screens'), get_icon('menu', array('menu' => 'screens')));
$favouriteScreens->setFooter(new CLink(_('Screens').' &raquo;', 'screens.php', 'highlight'), true);

$col = CProfile::get('web.dashboard.widget.'.WIDGET_FAVOURITE_SCREENS.'.col', 0);
$row = CProfile::get('web.dashboard.widget.'.WIDGET_FAVOURITE_SCREENS.'.row', 1);
$dashboardGrid[$col][$row] = $favouriteScreens;

// favourite maps
$favouriteMaps = new CUIWidget(WIDGET_FAVOURITE_MAPS, make_favorite_maps());
$favouriteMaps->setState(CProfile::get('web.dashboard.widget.'.WIDGET_FAVOURITE_MAPS.'.state', 1));
$favouriteMaps->setHeader(_('Favourite maps'), get_icon('menu', array('menu' => 'sysmaps')));
$favouriteMaps->setFooter(new CLink(_('Maps').' &raquo;', 'maps.php', 'highlight'), true);

$col = CProfile::get('web.dashboard.widget.'.WIDGET_FAVOURITE_MAPS.'.col', 0);
$row = CProfile::get('web.dashboard.widget.'.WIDGET_FAVOURITE_MAPS.'.row', 2);
$dashboardGrid[$col][$row] = $favouriteMaps;

// status of Zabbix
if (CWebUser::$data['type'] == USER_TYPE_SUPER_ADMIN) {
	$rate = CProfile::get('web.dashboard.widget.'.WIDGET_ZABBIX_STATUS.'.rf_rate', 120);

	$icon = new CIcon(_('Menu'), 'iconmenu');
	$icon->setMenuPopup(getMenuPopupRefresh(WIDGET_ZABBIX_STATUS, $rate));

	$zabbixStatus = new CUIWidget(WIDGET_ZABBIX_STATUS, new CSpan(_('Loading...'), 'textcolorstyles'));
	$zabbixStatus->setState(CProfile::get('web.dashboard.widget.'.WIDGET_ZABBIX_STATUS.'.state', 1));
	$zabbixStatus->setRefresh($rate);
	$zabbixStatus->setHeader(_('Status of Zabbix'), $icon);
	$zabbixStatus->setFooter(new CDiv(SPACE, 'textwhite', WIDGET_ZABBIX_STATUS.'_footer'));

	$col = CProfile::get('web.dashboard.widget.'.WIDGET_ZABBIX_STATUS.'.col', 1);
	$row = CProfile::get('web.dashboard.widget.'.WIDGET_ZABBIX_STATUS.'.row', 0);
	$dashboardGrid[$col][$row] = $zabbixStatus;

	$widgetRefreshParams[WIDGET_ZABBIX_STATUS] = $zabbixStatus->refreshParams;
}

// system status
$rate = CProfile::get('web.dashboard.widget.'.WIDGET_SYSTEM_STATUS.'.rf_rate', 120);

$icon = new CIcon(_('Menu'), 'iconmenu');
$icon->setMenuPopup(getMenuPopupRefresh(WIDGET_SYSTEM_STATUS, $rate));

$systemStatus = new CUIWidget(WIDGET_SYSTEM_STATUS, new CSpan(_('Loading...'), 'textcolorstyles'));
$systemStatus->setState(CProfile::get('web.dashboard.widget.'.WIDGET_SYSTEM_STATUS.'.state', 1));
$systemStatus->setRefresh($rate);
$systemStatus->setHeader(_('System status'), $icon);
$systemStatus->setFooter(new CDiv(SPACE, 'textwhite', WIDGET_SYSTEM_STATUS.'_footer'));

$col = CProfile::get('web.dashboard.widget.'.WIDGET_SYSTEM_STATUS.'.col', 1);
$row = CProfile::get('web.dashboard.widget.'.WIDGET_SYSTEM_STATUS.'.row', 1);
$dashboardGrid[$col][$row] = $systemStatus;

$widgetRefreshParams[WIDGET_SYSTEM_STATUS] = $systemStatus->refreshParams;

// host status
$rate = CProfile::get('web.dashboard.widget.'.WIDGET_HOST_STATUS.'.rf_rate', 120);

$icon = new CIcon(_('Menu'), 'iconmenu');
$icon->setMenuPopup(getMenuPopupRefresh(WIDGET_HOST_STATUS, $rate));

$hostStatus = new CUIWidget(WIDGET_HOST_STATUS, new CSpan(_('Loading...'), 'textcolorstyles'));
$hostStatus->setState(CProfile::get('web.dashboard.widget.'.WIDGET_HOST_STATUS.'.state', 1));
$hostStatus->setRefresh($rate);
$hostStatus->setHeader(_('Host status'), $icon);
$hostStatus->setFooter(new CDiv(SPACE, 'textwhite', WIDGET_HOST_STATUS.'_footer'));

$col = CProfile::get('web.dashboard.widget.'.WIDGET_HOST_STATUS.'.col', 1);
$row = CProfile::get('web.dashboard.widget.'.WIDGET_HOST_STATUS.'.row', 2);
$dashboardGrid[$col][$row] = $hostStatus;

$widgetRefreshParams[WIDGET_HOST_STATUS] = $hostStatus->refreshParams;

// last issues
$rate = CProfile::get('web.dashboard.widget.'.WIDGET_LAST_ISSUES.'.rf_rate', 120);

$icon = new CIcon(_('Menu'), 'iconmenu');
$icon->setMenuPopup(getMenuPopupRefresh(WIDGET_LAST_ISSUES, $rate));

$lastIssues = new CUIWidget(WIDGET_LAST_ISSUES, new CSpan(_('Loading...'), 'textcolorstyles'));
$lastIssues->setState(CProfile::get('web.dashboard.widget.'.WIDGET_LAST_ISSUES.'.state', 1));
$lastIssues->setRefresh($rate);
$lastIssues->setHeader(_n('Last %1$d issue', 'Last %1$d issues', DEFAULT_LATEST_ISSUES_CNT), $icon);
$lastIssues->setFooter(new CDiv(SPACE, 'textwhite', WIDGET_LAST_ISSUES.'_footer'));

$col = CProfile::get('web.dashboard.widget.'.WIDGET_LAST_ISSUES.'.col', 1);
$row = CProfile::get('web.dashboard.widget.'.WIDGET_LAST_ISSUES.'.row', 3);
$dashboardGrid[$col][$row] = $lastIssues;

$widgetRefreshParams[WIDGET_LAST_ISSUES] = $lastIssues->refreshParams;

// web monitoring
$rate = CProfile::get('web.dashboard.widget.'.WIDGET_WEB_OVERVIEW.'.rf_rate', 120);

$icon = new CIcon(_('Menu'), 'iconmenu');
$icon->setMenuPopup(getMenuPopupRefresh(WIDGET_WEB_OVERVIEW, $rate));

$webMonitoring = new CUIWidget(WIDGET_WEB_OVERVIEW, new CSpan(_('Loading...'), 'textcolorstyles'));
$webMonitoring->setState(CProfile::get('web.dashboard.widget.'.WIDGET_WEB_OVERVIEW.'.state', 1));
$webMonitoring->setRefresh($rate);
$webMonitoring->setHeader(_('Web monitoring'), $icon);
$webMonitoring->setFooter(new CDiv(SPACE, 'textwhite', WIDGET_WEB_OVERVIEW.'_footer'));

$col = CProfile::get('web.dashboard.widget.'.WIDGET_WEB_OVERVIEW.'.col', 1);
$row = CProfile::get('web.dashboard.widget.'.WIDGET_WEB_OVERVIEW.'.row', 4);
$dashboardGrid[$col][$row] = $webMonitoring;

$widgetRefreshParams[WIDGET_WEB_OVERVIEW] = $webMonitoring->refreshParams;

// discovery rules
$dbDiscoveryRules = DBfetch(DBselect(
	'SELECT COUNT(d.druleid) AS cnt'.
	' FROM drules d'.
	' WHERE d.status='.DRULE_STATUS_ACTIVE.
		andDbNode('d.druleid')
));

if ($dbDiscoveryRules['cnt'] > 0 && check_right_on_discovery()) {
	$rate = CProfile::get('web.dashboard.widget.'.WIDGET_DISCOVERY_STATUS.'.rf_rate', 120);

	$icon = new CIcon(_('Menu'), 'iconmenu');
	$icon->setMenuPopup(getMenuPopupRefresh(WIDGET_DISCOVERY_STATUS, $rate));

	$discoveryStatus = new CUIWidget(WIDGET_DISCOVERY_STATUS, new CSpan(_('Loading...'), 'textcolorstyles'));
	$discoveryStatus->setState(CProfile::get('web.dashboard.widget.'.WIDGET_DISCOVERY_STATUS.'.state', 1));
	$discoveryStatus->setRefresh($rate);
	$discoveryStatus->setHeader(_('Discovery status'), $icon);
	$discoveryStatus->setFooter(new CDiv(SPACE, 'textwhite', WIDGET_DISCOVERY_STATUS.'_footer'));

	$col = CProfile::get('web.dashboard.widget.'.WIDGET_DISCOVERY_STATUS.'.col', 1);
	$row = CProfile::get('web.dashboard.widget.'.WIDGET_DISCOVERY_STATUS.'.row', 5);
	$dashboardGrid[$col][$row] = $discoveryStatus;

	$widgetRefreshParams[WIDGET_DISCOVERY_STATUS] = $discoveryStatus->refreshParams;
}

// sort dashboard grid
foreach ($dashboardGrid as $key => $val) {
	ksort($dashboardGrid[$key]);
}

$dashboardTable = new CTable();
$dashboardTable->addRow(
	array(
		new CDiv($dashboardGrid[0], 'column'),
		new CDiv($dashboardGrid[1], 'column'),
		new CDiv($dashboardGrid[2], 'column')
	),
	'top'
);

$dashboardWidget->addItem($dashboardTable);
$dashboardWidget->show();

/*
 * Javascript
 */
// start refresh process
zbx_add_post_js('initPMaster("dashboard", '.zbx_jsvalue($widgetRefreshParams).');');

// activating blinking
zbx_add_post_js('jqBlink.blink();');

?>
<script type="text/javascript">
	function addPopupValues(list) {
		var favourites = {graphid: 1, itemid: 1, screenid: 1, slideshowid: 1, sysmapid: 1};

		if (isset(list.object, favourites)) {
			var favouriteIds = [];

			for (var i = 0; i < list.values.length; i++) {
				favouriteIds.push(list.values[i][list.object]);
			}

			sendAjaxData({
				data: {
					favobj: list.object,
					'favid[]': favouriteIds,
					favaction: 'add'
				}
			});
		}
	}
</script>
<?php
require_once dirname(__FILE__).'/include/page_footer.php';
