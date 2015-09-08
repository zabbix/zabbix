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

$dashboard = (new CWidget())
	->setTitle(_('Dashboard'))
	->setControls((new CForm())
		->cleanItems()
		->addItem((new CList())
			->addItem(get_icon('dashconf', ['enabled' => $data['filter_enabled']]))
			->addItem(get_icon('fullscreen', ['fullscreen' => $data['fullscreen']]))
		)
	);

/*
 * Dashboard grid
 */
$dashboardGrid = [[], [], []];
$widgetRefreshParams = [];

$icon = (new CButton(null))
	->addClass(ZBX_STYLE_BTN_WIDGET_ACTION)
	->setTitle(_('Action'))
	->setId('favouriteGraphs')
	->setMenuPopup(CMenuPopupHelper::getFavouriteGraphs());

$favouriteGraphs = (new CCollapsibleUiWidget(WIDGET_FAVOURITE_GRAPHS, $data['favourite_graphs']))
	->setExpanded((bool) CProfile::get('web.dashboard.widget.'.WIDGET_FAVOURITE_GRAPHS.'.state', true))
	->setHeader(_('Favourite graphs'), [$icon], true, 'zabbix.php?action=dashboard.widget')
	->setFooter(new CList([
		new CLink(_('Graphs'), 'charts.php')
	]));

$col = CProfile::get('web.dashboard.widget.'.WIDGET_FAVOURITE_GRAPHS.'.col', 0);
$row = CProfile::get('web.dashboard.widget.'.WIDGET_FAVOURITE_GRAPHS.'.row', 0);
$dashboardGrid[$col][$row] = $favouriteGraphs;

// favourite maps
$icon = (new CButton(null))
	->addClass(ZBX_STYLE_BTN_WIDGET_ACTION)
	->setTitle(_('Action'))
	->setId('favouriteMaps')
	->setMenuPopup(CMenuPopupHelper::getFavouriteMaps());

$favouriteMaps = (new CCollapsibleUiWidget(WIDGET_FAVOURITE_MAPS, $data['favourite_maps']))
	->setExpanded((bool) CProfile::get('web.dashboard.widget.'.WIDGET_FAVOURITE_MAPS.'.state', true))
	->setHeader(_('Favourite maps'), [$icon], true, 'zabbix.php?action=dashboard.widget')
	->setFooter(new CList([
		new CLink(_('Maps'), 'zabbix.php?action=map.view')
	]));

$col = CProfile::get('web.dashboard.widget.'.WIDGET_FAVOURITE_MAPS.'.col', 0);
$row = CProfile::get('web.dashboard.widget.'.WIDGET_FAVOURITE_MAPS.'.row', 2);
$dashboardGrid[$col][$row] = $favouriteMaps;

// favourite screens
$icon = (new CButton(null))
	->addClass(ZBX_STYLE_BTN_WIDGET_ACTION)
	->setTitle(_('Action'))
	->setId('favouriteScreens')
	->setMenuPopup(CMenuPopupHelper::getFavouriteScreens());

$favouriteScreens = (new CCollapsibleUiWidget(WIDGET_FAVOURITE_SCREENS, $data['favourite_screens']))
	->setExpanded((bool) CProfile::get('web.dashboard.widget.'.WIDGET_FAVOURITE_SCREENS.'.state', true))
	->setHeader(_('Favourite screens'), [$icon], true, 'zabbix.php?action=dashboard.widget')
	->setFooter(new CList([
		new CLink(_('Screens'), 'screens.php'),
		new CLink(_('Slide shows'), 'slides.php')
	]));

$col = CProfile::get('web.dashboard.widget.'.WIDGET_FAVOURITE_SCREENS.'.col', 0);
$row = CProfile::get('web.dashboard.widget.'.WIDGET_FAVOURITE_SCREENS.'.row', 1);
$dashboardGrid[$col][$row] = $favouriteScreens;

$widgets = [
	WIDGET_SYSTEM_STATUS => [
		'action' => 'widget.system.view',
		'col' => 1,
		'row' => 1
	],
	WIDGET_HOST_STATUS => [
		'action' => 'widget.hosts.view',
		'col' => 1,
		'row' => 2
	],
	WIDGET_LAST_ISSUES => [
		'action' => 'widget.issues.view',
		'col' => 1,
		'row' => 3
	],
	WIDGET_WEB_OVERVIEW => [
		'action' => 'widget.web.view',
		'col' => 1,
		'row' => 4
	],
];

if ($data['show_status_widget']) {
	$widgets[WIDGET_ZABBIX_STATUS] = [
		'action' => 'widget.status.view',
		'col' => 1,
		'row' => 0
	];
}
if ($data['show_discovery_widget']) {
	$widgets[WIDGET_DISCOVERY_STATUS] = [
		'action' => 'widget.discovery.view',
		'col' => 1,
		'row' => 5
	];
}

foreach ($widgets as $widgetid => $widget) {
	$profile = 'web.dashboard.widget.'.$widgetid;

	$rate = CProfile::get($profile.'.rf_rate', 60);
	$expanded = (bool) CProfile::get($profile.'.state', true);
	$col = CProfile::get($profile.'.col', $widget['col']);
	$row = CProfile::get($profile.'.row', $widget['row']);

	$icon = (new CButton(null))
		->addClass(ZBX_STYLE_BTN_WIDGET_ACTION)
		->setTitle(_('Action'))
		->setMenuPopup(CMenuPopupHelper::getRefresh($widgetid, $rate));

	$dashboardGrid[$col][$row] = (new CCollapsibleUiWidget($widgetid, (new CDiv())->addClass('preloader')))
		->setExpanded($expanded)
		->setHeader(null, [$icon], true, 'zabbix.php?action=dashboard.widget')
		->setFooter(new CList([
			(new CListItem(''))->setId($widgetid.'_footer')
		]));

	$widgetRefreshParams[$widgetid] = [
		'frequency' => $rate,
		'url' => 'zabbix.php?action='.$widget['action'],
		'counter' => 0,
		'darken' => 0,
		'params' => ['widgetRefresh' => $widgetid]
	];
}

// sort dashboard grid
foreach ($dashboardGrid as $key => $val) {
	ksort($dashboardGrid[$key]);
}

$dashboardRow = (new CDiv(
	[
		(new CDiv($dashboardGrid[0]))->addClass('cell'),
		(new CDiv($dashboardGrid[1]))->addClass('cell'),
		(new CDiv($dashboardGrid[2]))->addClass('cell')
	]))
	->addClass('row');

$dashboardTable = (new CDiv($dashboardRow))
	->addClass('table')
	->addClass('widget-placeholder');

$dashboard->addItem($dashboardTable)->show();

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
