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

$widgets = [
	WIDGET_FAVOURITE_GRAPHS => [
		'id' => 'favouriteGraphs',
		'menu_popup' => ['CMenuPopupHelper', 'getFavouriteGraphs'],
		'data' => $data['favourite_graphs'],
		'header' => _('Favourite graphs'),
		'links' => [
			['name' => _('Graphs'), 'url' => 'charts.php']
		],
		'defaults' => ['col' => 0, 'row' => 0]
	],
	WIDGET_FAVOURITE_SCREENS => [
		'id' => 'favouriteScreens',
		'menu_popup' => ['CMenuPopupHelper', 'getFavouriteScreens'],
		'data' => $data['favourite_screens'],
		'header' => _('Favourite screens'),
		'links' => [
			['name' => _('Screens'), 'url' => 'screens.php'],
			['name' => _('Slide shows'), 'url' => 'slides.php']
		],
		'defaults' => ['col' => 0, 'row' => 1]
	],
	WIDGET_FAVOURITE_MAPS => [
		'id' => 'favouriteMaps',
		'menu_popup' => ['CMenuPopupHelper', 'getFavouriteMaps'],
		'data' => $data['favourite_maps'],
		'header' => _('Favourite maps'),
		'links' => [
			['name' => _('Maps'), 'url' => 'zabbix.php?action=map.view']
		],
		'defaults' => ['col' => 0, 'row' => 2]
	]
];

foreach ($widgets as $widgetid => $widget) {
	$icon = (new CButton(null))
		->addClass(ZBX_STYLE_BTN_WIDGET_ACTION)
		->setTitle(_('Action'))
		->setId($widget['id'])
		->setMenuPopup(call_user_func($widget['menu_popup']));

	$footer = new CList();
	foreach ($widget['links'] as $link) {
		$footer->addItem(new CLink($link['name'], $link['url']));
	}

	$col = CProfile::get('web.dashboard.widget.'.$widgetid.'.col', $widget['defaults']['col']);
	$row = CProfile::get('web.dashboard.widget.'.$widgetid.'.row', $widget['defaults']['row']);

	$dashboardGrid[$col][$row] = (new CCollapsibleUiWidget($widgetid, $widget['data']))
		->setExpanded((bool) CProfile::get('web.dashboard.widget.'.$widgetid.'.state', true))
		->setHeader($widget['header'], [$icon], true, 'zabbix.php?action=dashboard.widget')
		->setFooter($footer);
}

$widgets = [
	WIDGET_SYSTEM_STATUS => [
		'action' => 'widget.system.view',
		'header' => _('System status'),
		'defaults' => ['col' => 1, 'row' => 1]
	],
	WIDGET_HOST_STATUS => [
		'action' => 'widget.hosts.view',
		'header' => _('Host status'),
		'defaults' => ['col' => 1, 'row' => 2]
	],
	WIDGET_LAST_ISSUES => [
		'action' => 'widget.issues.view',
		'header' => _n('Last %1$d issue', 'Last %1$d issues', DEFAULT_LATEST_ISSUES_CNT),
		'defaults' => ['col' => 1, 'row' => 3]
	],
	WIDGET_WEB_OVERVIEW => [
		'action' => 'widget.web.view',
		'header' => _('Web monitoring'),
		'defaults' => ['col' => 1, 'row' => 4]
	],
];

if ($data['show_status_widget']) {
	$widgets[WIDGET_ZABBIX_STATUS] = [
		'action' => 'widget.status.view',
		'header' => _('Status of Zabbix'),
		'defaults' => ['col' => 1, 'row' => 0]
	];
}
if ($data['show_discovery_widget']) {
	$widgets[WIDGET_DISCOVERY_STATUS] = [
		'action' => 'widget.discovery.view',
		'header' => _('Discovery status'),
		'defaults' => ['col' => 1, 'row' => 5]
	];
}

foreach ($widgets as $widgetid => $widget) {
	$profile = 'web.dashboard.widget.'.$widgetid;

	$rate = CProfile::get($profile.'.rf_rate', 60);
	$expanded = (bool) CProfile::get($profile.'.state', true);
	$col = CProfile::get($profile.'.col', $widget['defaults']['col']);
	$row = CProfile::get($profile.'.row', $widget['defaults']['row']);

	$icon = (new CButton(null))
		->addClass(ZBX_STYLE_BTN_WIDGET_ACTION)
		->setTitle(_('Action'))
		->setMenuPopup(CMenuPopupHelper::getRefresh($widgetid, $rate));

	$dashboardGrid[$col][$row] = (new CCollapsibleUiWidget($widgetid, (new CDiv())->addClass('preloader')))
		->setExpanded($expanded)
		->setHeader($widget['header'], [$icon], true, 'zabbix.php?action=dashboard.widget')
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
