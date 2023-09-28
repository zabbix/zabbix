<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


/**
 * @var CView $this
 * @var array $data
 */

if (array_key_exists('error', $data)) {
	show_error_message($data['error']);

	return;
}

$this->addJsFile('flickerfreescreen.js');
$this->addJsFile('gtlc.js');
$this->addJsFile('leaflet.js');
$this->addJsFile('leaflet.markercluster.js');
$this->addJsFile('class.dashboard.js');
$this->addJsFile('class.dashboard.page.js');
$this->addJsFile('class.dashboard.widget.placeholder.js');
$this->addJsFile('class.geomaps.js');
$this->addJsFile('class.widget-base.js');
$this->addJsFile('class.widget.js');
$this->addJsFile('class.widget.inaccessible.js');
$this->addJsFile('class.widget.iterator.js');
$this->addJsFile('class.widget.paste-placeholder.js');
$this->addJsFile('class.csvggraph.js');
$this->addJsFile('class.svg.canvas.js');
$this->addJsFile('class.svg.map.js');
$this->addJsFile('class.csvggauge.js');
$this->addJsFile('class.sortable.js');

$this->includeJsFile('monitoring.dashboard.print.js.php');

$this->addCssFile('assets/styles/vendors/Leaflet/Leaflet/leaflet.css');

$this->enableLayoutModes();
$this->setLayoutMode(ZBX_LAYOUT_KIOSKMODE);

$page_count = count($data['dashboard']['pages']);

$header_title_tag = (new CTag('h1', true, $data['dashboard']['name']));

(new CTag('header', true, $header_title_tag))
	->addClass('header-title page_1')
	->show();

if ($page_count > 1) {
	foreach ($data['dashboard']['pages'] as $index => $dashboard_page) {
		$page_number = $index + 1;

		(new CDiv())
			->addClass('dashboard-page page_'.$page_number)
			->setAttribute('data-height', $data['page_sizes'][$index])
			->addItem(
				new CTag('h1', true,
					$dashboard_page['name'] !== '' ? $dashboard_page['name'] : _s('Page %1$d', $page_number)
				)
			)
			->addItem(
				(new CDiv())->addClass(ZBX_STYLE_DASHBOARD_GRID)
			)
			->show();
	}
}
else {
	(new CDiv())
		->addClass('dashboard-page page_1')
		->setAttribute('data-height', $data['page_sizes'][0])
		->addItem(
			(new CDiv())->addClass(ZBX_STYLE_DASHBOARD_GRID)
		)
		->show();
}

(new CScriptTag('
	view.init('.json_encode([
		'dashboard' => $data['dashboard'],
		'widget_defaults' => $data['widget_defaults'],
		'time_period' => $data['time_period']
	]).');
'))
	->setOnDocumentReady()
	->show();
