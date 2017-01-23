<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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
//		'header' => _('Favourite graphs'),
		'pos' => ['row' => 0, 'col' => 0, 'height' => 4, 'width' => 2]
	],
	WIDGET_FAVOURITE_SCREENS => [
//		'header' => _('Favourite screens'),
		'pos' => ['row' => 4, 'col' => 0, 'height' => 3, 'width' => 2]
	],
	WIDGET_FAVOURITE_MAPS => [
//		'header' => _('Favourite maps'),
		'pos' => ['row' => 7, 'col' => 0, 'height' => 3, 'width' => 2]
	],
	WIDGET_SYSTEM_STATUS => [
//		'header' => _('System status'),
		'pos' => ['row' => 0, 'col' => 2, 'height' => 3, 'width' => 5]
	],
	WIDGET_HOST_STATUS => [
//		'header' => _('Host status'),
		'pos' => ['row' => 3, 'col' => 2, 'height' => 3, 'width' => 5]
	],
	WIDGET_LAST_ISSUES => [
//		'header' => _n('Last %1$d issue', 'Last %1$d issues', DEFAULT_LATEST_ISSUES_CNT),
		'pos' => ['row' => 6, 'col' => 2, 'height' => 4, 'width' => 5]
	],
	WIDGET_WEB_OVERVIEW => [
//		'header' => _('Web monitoring'),
		'pos' => ['row' => 7, 'col' => 7, 'height' => 3, 'width' => 5]
	]
];

if ($data['show_status_widget']) {
	$widgets[WIDGET_ZABBIX_STATUS] = [
//		'header' => _('Status of Zabbix'),
		'pos' => ['row' => 0, 'col' => 7, 'height' => 4, 'width' => 5]
	];
}
if ($data['show_discovery_widget']) {
	$widgets[WIDGET_DISCOVERY_STATUS] = [
//		'header' => _('Discovery status'),
		'pos' => ['row' => 4, 'col' => 7, 'height' => 3, 'width' => 5]
	];
}

$grid_widgets = [];

foreach ($widgets as $widgetid => $widget) {
	$grid_widgets[] = [
		'widgetid' => $widgetid,
		'pos' => [
			'col' => (int) CProfile::get('web.dashbrd.widget.'.$widgetid.'.col', $widget['pos']['col']),
			'row' => (int) CProfile::get('web.dashbrd.widget.'.$widgetid.'.row', $widget['pos']['row']),
			'height' => (int) CProfile::get('web.dashbrd.widget.'.$widgetid.'.height', $widget['pos']['height']),
			'width' => (int) CProfile::get('web.dashbrd.widget.'.$widgetid.'.width', $widget['pos']['width'])
		]
	];
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
		(new CDiv())
			->addClass(ZBX_STYLE_DASHBRD_GRID_WIDGET_CONTAINER)
			->addStyle('padding-bottom: 120px;')	// TODO: remove this line
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
	'jQuery(".'.ZBX_STYLE_DASHBRD_GRID_WIDGET_CONTAINER.'")'.
		'.dashboardGrid()'.
		'.dashboardGrid("addWidgets", '.CJs::encodeJson($grid_widgets).');'
);
