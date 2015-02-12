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


$this->addJSfile('js/class.pmaster.js');

$dashboardWidget = new CWidget(null, 'dashboard');
$dashboardWidget->setClass('header');

$icon = new CIcon(
	_s('Configure (Filter %s)', $data['filter_enabled'] == 1 ? _('Enabled') : _('Disabled')),
	$data['filter_enabled'] == 1 ? 'iconconfig_hl' : 'iconconfig'
);
$icon->addAction('onclick', 'document.location = "dashconf.php";');

$dashboardWidget->addHeader(_('PERSONAL DASHBOARD'), array(
	$icon,
	SPACE,
	get_icon('fullscreen', array('fullscreen' => $data['fullscreen']))
));

/*
 * Dashboard grid
 */
$dashboardGrid = array(array(), array(), array());
$widgetRefreshParams = array();

// favourite graphs
$icon = new CIcon(_('Menu'), 'iconmenu');
$icon->setAttribute('id', 'favouriteGraphs');
$icon->setMenuPopup(CMenuPopupHelper::getFavouriteGraphs());

$favouriteGraphs = new CCollapsibleUiWidget(WIDGET_FAVOURITE_GRAPHS, $data['favourite_graphs']);
$favouriteGraphs->open = (bool) CProfile::get('web.dashboard.widget.'.WIDGET_FAVOURITE_GRAPHS.'.state', true);
$favouriteGraphs->setHeader(_('Favourite graphs'), $icon);
$favouriteGraphs->setFooter(new CLink(_('Graphs').' &raquo;', 'charts.php', 'highlight'), true);

$col = CProfile::get('web.dashboard.widget.'.WIDGET_FAVOURITE_GRAPHS.'.col', 0);
$row = CProfile::get('web.dashboard.widget.'.WIDGET_FAVOURITE_GRAPHS.'.row', 0);
$dashboardGrid[$col][$row] = $favouriteGraphs;

// favourite maps
$icon = new CIcon(_('Menu'), 'iconmenu');
$icon->setAttribute('id', 'favouriteMaps');
$icon->setMenuPopup(CMenuPopupHelper::getFavouriteMaps());

$favouriteMaps = new CCollapsibleUiWidget(WIDGET_FAVOURITE_MAPS, $data['favourite_maps']);
$favouriteMaps->open = (bool) CProfile::get('web.dashboard.widget.'.WIDGET_FAVOURITE_MAPS.'.state', true);
$favouriteMaps->setHeader(_('Favourite maps'), $icon);
$favouriteMaps->setFooter(new CLink(_('Maps').' &raquo;', 'zabbix.php?action=map.view', 'highlight'), true);

$col = CProfile::get('web.dashboard.widget.'.WIDGET_FAVOURITE_MAPS.'.col', 0);
$row = CProfile::get('web.dashboard.widget.'.WIDGET_FAVOURITE_MAPS.'.row', 2);
$dashboardGrid[$col][$row] = $favouriteMaps;

// favourite screens
$icon = new CIcon(_('Menu'), 'iconmenu');
$icon->setAttribute('id', 'favouriteScreens');
$icon->setMenuPopup(CMenuPopupHelper::getFavouriteScreens());

$favouriteScreens = new CCollapsibleUiWidget(WIDGET_FAVOURITE_SCREENS, $data['favourite_screens']);
$favouriteScreens->open = (bool) CProfile::get('web.dashboard.widget.'.WIDGET_FAVOURITE_SCREENS.'.state', true);
$favouriteScreens->setHeader(_('Favourite screens'), $icon);
$favouriteScreens->setFooter(
	array(
		new CLink(_('Screens').' &raquo;', 'screens.php', 'highlight'),
		SPACE,
		SPACE,
		SPACE,
		new CLink(_('Slide shows').' &raquo;', 'slides.php', 'highlight')
	),
	true
);

$col = CProfile::get('web.dashboard.widget.'.WIDGET_FAVOURITE_SCREENS.'.col', 0);
$row = CProfile::get('web.dashboard.widget.'.WIDGET_FAVOURITE_SCREENS.'.row', 1);
$dashboardGrid[$col][$row] = $favouriteScreens;

// status of Zabbix
if ($data['show_status_widget']) {
	$rate = CProfile::get('web.dashboard.widget.'.WIDGET_ZABBIX_STATUS.'.rf_rate', 60);

	$icon = new CIcon(_('Menu'), 'iconmenu');
	$icon->setMenuPopup(CMenuPopupHelper::getRefresh(WIDGET_ZABBIX_STATUS, $rate));

	$zabbixStatus = new CCollapsibleUiWidget(WIDGET_ZABBIX_STATUS, new CSpan(_('Loading...'), 'textcolorstyles'));
	$zabbixStatus->open = (bool) CProfile::get('web.dashboard.widget.'.WIDGET_ZABBIX_STATUS.'.state', true);
	$zabbixStatus->setHeader(_('Status of Zabbix'), $icon);
	$zabbixStatus->setFooter(new CDiv(SPACE, 'textwhite', WIDGET_ZABBIX_STATUS.'_footer'));

	$col = CProfile::get('web.dashboard.widget.'.WIDGET_ZABBIX_STATUS.'.col', 1);
	$row = CProfile::get('web.dashboard.widget.'.WIDGET_ZABBIX_STATUS.'.row', 0);
	$dashboardGrid[$col][$row] = $zabbixStatus;

	$widgetRefreshParams[WIDGET_ZABBIX_STATUS] = array(
		'frequency' => $rate,
		'url' => 'zabbix.php?action=widget.status.view',
		'counter' => 0,
		'darken' => 0,
		'params' => array('widgetRefresh' => WIDGET_ZABBIX_STATUS)
	);
}

// system status
$rate = CProfile::get('web.dashboard.widget.'.WIDGET_SYSTEM_STATUS.'.rf_rate', 60);

$icon = new CIcon(_('Menu'), 'iconmenu');
$icon->setMenuPopup(CMenuPopupHelper::getRefresh(WIDGET_SYSTEM_STATUS, $rate));

$systemStatus = new CCollapsibleUiWidget(WIDGET_SYSTEM_STATUS, new CSpan(_('Loading...'), 'textcolorstyles'));
$systemStatus->open = (bool) CProfile::get('web.dashboard.widget.'.WIDGET_SYSTEM_STATUS.'.state', true);
$systemStatus->setHeader(_('System status'), $icon);
$systemStatus->setFooter(new CDiv(SPACE, 'textwhite', WIDGET_SYSTEM_STATUS.'_footer'));

$col = CProfile::get('web.dashboard.widget.'.WIDGET_SYSTEM_STATUS.'.col', 1);
$row = CProfile::get('web.dashboard.widget.'.WIDGET_SYSTEM_STATUS.'.row', 1);
$dashboardGrid[$col][$row] = $systemStatus;

$widgetRefreshParams[WIDGET_SYSTEM_STATUS] = array(
	'frequency' => $rate,
	'url' => 'zabbix.php?action=widget.system.view',
	'counter' => 0,
	'darken' => 0,
	'params' => array('widgetRefresh' => WIDGET_SYSTEM_STATUS)
);

// host status
$rate = CProfile::get('web.dashboard.widget.'.WIDGET_HOST_STATUS.'.rf_rate', 60);

$icon = new CIcon(_('Menu'), 'iconmenu');
$icon->setMenuPopup(CMenuPopupHelper::getRefresh(WIDGET_HOST_STATUS, $rate));

$hostStatus = new CCollapsibleUiWidget(WIDGET_HOST_STATUS, new CSpan(_('Loading...'), 'textcolorstyles'));
$hostStatus->open = (bool) CProfile::get('web.dashboard.widget.'.WIDGET_HOST_STATUS.'.state', true);
$hostStatus->setHeader(_('Host status'), $icon);
$hostStatus->setFooter(new CDiv(SPACE, 'textwhite', WIDGET_HOST_STATUS.'_footer'));

$col = CProfile::get('web.dashboard.widget.'.WIDGET_HOST_STATUS.'.col', 1);
$row = CProfile::get('web.dashboard.widget.'.WIDGET_HOST_STATUS.'.row', 2);
$dashboardGrid[$col][$row] = $hostStatus;

$widgetRefreshParams[WIDGET_HOST_STATUS] = array(
	'frequency' => $rate,
	'url' => 'zabbix.php?action=widget.hosts.view',
	'counter' => 0,
	'darken' => 0,
	'params' => array('widgetRefresh' => WIDGET_HOST_STATUS)
);

// last issues
$rate = CProfile::get('web.dashboard.widget.'.WIDGET_LAST_ISSUES.'.rf_rate', 60);

$icon = new CIcon(_('Menu'), 'iconmenu');
$icon->setMenuPopup(CMenuPopupHelper::getRefresh(WIDGET_LAST_ISSUES, $rate));

$lastIssues = new CCollapsibleUiWidget(WIDGET_LAST_ISSUES, new CSpan(_('Loading...'), 'textcolorstyles'));
$lastIssues->open = (bool) CProfile::get('web.dashboard.widget.'.WIDGET_LAST_ISSUES.'.state', true);
$lastIssues->setHeader(_n('Last %1$d issue', 'Last %1$d issues', DEFAULT_LATEST_ISSUES_CNT), $icon);
$lastIssues->setFooter(new CDiv(SPACE, 'textwhite', WIDGET_LAST_ISSUES.'_footer'));

$col = CProfile::get('web.dashboard.widget.'.WIDGET_LAST_ISSUES.'.col', 1);
$row = CProfile::get('web.dashboard.widget.'.WIDGET_LAST_ISSUES.'.row', 3);
$dashboardGrid[$col][$row] = $lastIssues;

$widgetRefreshParams[WIDGET_LAST_ISSUES] = array(
	'frequency' => $rate,
	'url' => 'zabbix.php?action=widget.issues.view',
	'counter' => 0,
	'darken' => 0,
	'params' => array('widgetRefresh' => WIDGET_LAST_ISSUES)
);

// web monitoring
$rate = CProfile::get('web.dashboard.widget.'.WIDGET_WEB_OVERVIEW.'.rf_rate', 60);

$icon = new CIcon(_('Menu'), 'iconmenu');
$icon->setMenuPopup(CMenuPopupHelper::getRefresh(WIDGET_WEB_OVERVIEW, $rate));

$webMonitoring = new CCollapsibleUiWidget(WIDGET_WEB_OVERVIEW, new CSpan(_('Loading...'), 'textcolorstyles'));
$webMonitoring->open = (bool) CProfile::get('web.dashboard.widget.'.WIDGET_WEB_OVERVIEW.'.state', true);
$webMonitoring->setHeader(_('Web monitoring'), $icon);
$webMonitoring->setFooter(new CDiv(SPACE, 'textwhite', WIDGET_WEB_OVERVIEW.'_footer'));

$col = CProfile::get('web.dashboard.widget.'.WIDGET_WEB_OVERVIEW.'.col', 1);
$row = CProfile::get('web.dashboard.widget.'.WIDGET_WEB_OVERVIEW.'.row', 4);
$dashboardGrid[$col][$row] = $webMonitoring;

$widgetRefreshParams[WIDGET_WEB_OVERVIEW] = array(
	'frequency' => $rate,
	'url' => 'zabbix.php?action=widget.web.view',
	'counter' => 0,
	'darken' => 0,
	'params' => array('widgetRefresh' => WIDGET_WEB_OVERVIEW)
);

// discovery rules
if ($data['show_discovery_widget']) {
	$rate = CProfile::get('web.dashboard.widget.'.WIDGET_DISCOVERY_STATUS.'.rf_rate', 60);

	$icon = new CIcon(_('Menu'), 'iconmenu');
	$icon->setMenuPopup(CMenuPopupHelper::getRefresh(WIDGET_DISCOVERY_STATUS, $rate));

	$discoveryStatus = new CCollapsibleUiWidget(WIDGET_DISCOVERY_STATUS, new CSpan(_('Loading...'), 'textcolorstyles'));
	$discoveryStatus->open = (bool) CProfile::get('web.dashboard.widget.'.WIDGET_DISCOVERY_STATUS.'.state', true);
	$discoveryStatus->setHeader(_('Discovery status'), $icon);
	$discoveryStatus->setFooter(new CDiv(SPACE, 'textwhite', WIDGET_DISCOVERY_STATUS.'_footer'));

	$col = CProfile::get('web.dashboard.widget.'.WIDGET_DISCOVERY_STATUS.'.col', 1);
	$row = CProfile::get('web.dashboard.widget.'.WIDGET_DISCOVERY_STATUS.'.row', 5);
	$dashboardGrid[$col][$row] = $discoveryStatus;

	$widgetRefreshParams[WIDGET_DISCOVERY_STATUS] = array(
		'frequency' => $rate,
		'url' => 'zabbix.php?action=widget.discovery.view',
		'counter' => 0,
		'darken' => 0,
		'params' => array('widgetRefresh' => WIDGET_DISCOVERY_STATUS)
	);
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
$this->addPostJS('initPMaster("dashboard", '.CJs::encodeJson($widgetRefreshParams).');');

// activating blinking
$this->addPostJS('jqBlink.blink();');

?>

<script type="text/javascript">
	/**
	 * @see init.js add.popup event
	 */
	function addPopupValues(list) {
		var favourites = {graphid: 1, itemid: 1, screenid: 1, slideshowid: 1, sysmapid: 1};

		if (isset(list.object, favourites)) {
			var favouriteIds = [];

			for (var i = 0; i < list.values.length; i++) {
				favouriteIds.push(list.values[i][list.object]);
			}

			sendAjaxData('zabbix.php?action=dashboard.favourite&operation=create', {
				data: {
					object: list.object,
					'objectids[]': favouriteIds
				}
			});
		}
	}
</script>
