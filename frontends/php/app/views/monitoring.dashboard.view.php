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


$this->addJsFile('class.pmaster.js');
$this->addJsFile('dashboard.grid.js');

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

	$dashboardGrid[$col][$row] = (new CCollapsibleUiWidget($widgetid, (new CDiv())->addClass(ZBX_STYLE_PRELOADER)))
		->setExpanded($expanded)
		->setHeader($widget['header'], [$icon], true, 'zabbix.php?action=dashboard.widget')
		->setFooter((new CList())->setId($widgetid.'_footer'));

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

(new CWidget())
	->setTitle(_('Dashboard'))
	->setControls((new CForm())
		->cleanItems()
		->addItem((new CList())
			->addItem(get_icon('dashconf', ['enabled' => $data['filter_enabled']]))
			->addItem(get_icon('fullscreen', ['fullscreen' => $data['fullscreen']]))
		)
	)
	->addItem(
		(new CDiv())->addClass('dashbrd-grid-container')
	)
	->addItem(
		(new CDiv(
			(new CDiv([
				(new CDiv($dashboardGrid[0]))->addClass(ZBX_STYLE_CELL),
				(new CDiv($dashboardGrid[1]))->addClass(ZBX_STYLE_CELL),
				(new CDiv($dashboardGrid[2]))->addClass(ZBX_STYLE_CELL)
			]))
				->addClass(ZBX_STYLE_ROW)
		))
			->addClass(ZBX_STYLE_TABLE)
			->addClass('widget-placeholder')
	)
	->show();

/*
 * Javascript
 */
// start refresh process
$this->addPostJS('initPMaster("dashboard", '.CJs::encodeJson($widgetRefreshParams).');');

// activating blinking
$this->addPostJS('jqBlink.blink();');

// Initialize dashboard grid
$this->addPostJS(
	"jQuery('.dashbrd-grid-container')
		.dashboardGrid()
		.dashboardGrid('addWidget', {'row': 1, 'col': 5, 'height': 1, 'width': 1})
		.dashboardGrid('addWidget', {'row': 2, 'col': 0, 'height': 2, 'width': 2})
		.dashboardGrid('addWidget', {'row': 2, 'col': 6, 'height': 3, 'width': 6})
		.dashboardGrid('addWidget', {'row': 1, 'col': 2, 'height': 2, 'width': 1});"
);

?>

<style>
.dashbrd-grid-container {
	width: 100%;
	position: relative; }
	.dashbrd-grid-widget {
		background-color: #3b3b3b;
		position: absolute; }
		.dashbrd-grid-widget-dragging {
			opacity: 0.8;
			z-index: 1000 }
		.ui-resizable-handle {
			border: 1px dotted #707070;
			position: absolute; }
		.ui-resizable-n {
			cursor: n-resize;
			height: 7px;
			top: -5px;
			left: 2px;
			right: 2px; }
		.ui-resizable-e {
			cursor: e-resize;
			width: 7px;
			right: -5px;
			top: 2px;
			bottom: 2px; }
		.ui-resizable-s {
			cursor: s-resize;
			height: 7px;
			bottom: -5px;
			left: 2px;
			right: 2px; }
		.ui-resizable-w {
			cursor: w-resize;
			width: 7px;
			left: -5px;
			top: 2px;
			bottom: 2px; }
		.ui-resizable-ne {
			cursor: ne-resize;
			height: 7px;
			width: 7px;
			top: -5px;
			right: -5px; }
		.ui-resizable-nw {
			cursor: nw-resize;
			width: 7px;
			height: 7px;
			top: -5px;
			left: -5px; }
		.ui-resizable-se {
			cursor: se-resize;
			height: 7px;
			width: 7px;
			bottom: -5px;
			right: -5px; }
		.ui-resizable-sw {
			cursor: sw-resize;
			width: 7px;
			height: 7px;
			bottom: -5px;
			left: -5px; }
	.dashbrd-grid-placeholder {
		border: 1px dashed #505050;
		background-color: #1b1b1b;
		position: absolute;
		z-index: 999 }
</style>

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
