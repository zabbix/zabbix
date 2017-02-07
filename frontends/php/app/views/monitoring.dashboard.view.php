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


$this->addJsFile('dashboard.grid.js');

/*
 * Dashboard grid
 */
$widgets = [
	WIDGET_FAVOURITE_GRAPHS => [
		'header' => _('Favourite graphs'),
		'pos' => ['row' => 0, 'col' => 0, 'height' => 3, 'width' => 2],
		'rf_rate' => 15 * SEC_PER_MIN
	],
	WIDGET_FAVOURITE_SCREENS => [
		'header' => _('Favourite screens'),
		'pos' => ['row' => 0, 'col' => 2, 'height' => 3, 'width' => 2],
		'rf_rate' => 15 * SEC_PER_MIN
	],
	WIDGET_FAVOURITE_MAPS => [
		'header' => _('Favourite maps'),
		'pos' => ['row' => 0, 'col' => 4, 'height' => 3, 'width' => 2],
		'rf_rate' => 15 * SEC_PER_MIN
	],
	WIDGET_LAST_ISSUES => [
		'header' => _n('Last %1$d issue', 'Last %1$d issues', DEFAULT_LATEST_ISSUES_CNT),
		'pos' => ['row' => 3, 'col' => 0, 'height' => 6, 'width' => 6],
		'rf_rate' => SEC_PER_MIN
	],
	WIDGET_WEB_OVERVIEW => [
		'header' => _('Web monitoring'),
		'pos' => ['row' => 9, 'col' => 0, 'height' => 4, 'width' => 3],
		'rf_rate' => SEC_PER_MIN
	],
	WIDGET_HOST_STATUS => [
		'header' => _('Host status'),
		'pos' => ['row' => 0, 'col' => 6, 'height' => 4, 'width' => 6],
		'rf_rate' => SEC_PER_MIN
	],
	WIDGET_SYSTEM_STATUS => [
		'header' => _('System status'),
		'pos' => ['row' => 4, 'col' => 6, 'height' => 4, 'width' => 6],
		'rf_rate' => SEC_PER_MIN
	]
];

if ($data['show_status_widget']) {
	$widgets[WIDGET_ZABBIX_STATUS] = [
		'header' => _('Status of Zabbix'),
		'pos' => ['row' => 8, 'col' => 6, 'height' => 5, 'width' => 6],
		'rf_rate' => 15 * SEC_PER_MIN
	];
}
if ($data['show_discovery_widget']) {
	$widgets[WIDGET_DISCOVERY_STATUS] = [
		'header' => _('Discovery status'),
		'pos' => ['row' => 9, 'col' => 3, 'height' => 4, 'width' => 3],
		'rf_rate' => SEC_PER_MIN
	];
}

$grid_widgets = [];

foreach ($widgets as $widgetid => $widget) {
	$grid_widgets[] = [
		'widgetid' => $widgetid,
		'header' => $widget['header'],
		'pos' => [
			'col' => (int) CProfile::get('web.dashbrd.widget.'.$widgetid.'.col', $widget['pos']['col']),
			'row' => (int) CProfile::get('web.dashbrd.widget.'.$widgetid.'.row', $widget['pos']['row']),
			'height' => (int) CProfile::get('web.dashbrd.widget.'.$widgetid.'.height', $widget['pos']['height']),
			'width' => (int) CProfile::get('web.dashbrd.widget.'.$widgetid.'.width', $widget['pos']['width'])
		],
		'rf_rate' => (int) CProfile::get('web.dashbrd.widget.'.$widgetid.'.rf_rate', $widget['rf_rate'])
	];
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
	->addItem((new CDiv())->addClass(ZBX_STYLE_DASHBRD_GRID_WIDGET_CONTAINER))
	->show();

/*
 * Javascript
 */
// activating blinking
$this->addPostJS('jqBlink.blink();');

// Initialize dashboard grid
$this->addPostJS(
	'jQuery(".'.ZBX_STYLE_DASHBRD_GRID_WIDGET_CONTAINER.'")'.
		'.dashboardGrid()'.
		'.dashboardGrid("addWidgets", '.CJs::encodeJson($grid_widgets).');'
);
