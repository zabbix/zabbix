<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
$this->addJsFile('class.widget.js');
$this->addJsFile('class.widget.iterator.js');
$this->addJsFile('class.widget.clock.js');
$this->addJsFile('class.widget.geomap.js');
$this->addJsFile('class.widget.graph.js');
$this->addJsFile('class.widget.graph-prototype.js');
$this->addJsFile('class.widget.item.js');
$this->addJsFile('class.widget.map.js');
$this->addJsFile('class.widget.navtree.js');
$this->addJsFile('class.widget.paste-placeholder.js');
$this->addJsFile('class.widget.problems.js');
$this->addJsFile('class.widget.problemsbysv.js');
$this->addJsFile('class.widget.svggraph.js');
$this->addJsFile('class.widget.trigerover.js');
$this->addJsFile('class.csvggraph.js');
$this->addJsFile('class.svg.canvas.js');
$this->addJsFile('class.svg.map.js');
$this->addJsFile('class.sortable.js');

$this->includeJsFile('monitoring.dashboard.print.js.php');

$this->addCssFile('assets/styles/vendors/Leaflet/Leaflet/leaflet.css');

$this->enableLayoutModes();
$this->setLayoutMode(ZBX_LAYOUT_KIOSKMODE);

(new CWidget())
	->addItem(
		(new CDiv())
			->addClass(ZBX_STYLE_DASHBOARD)
			->addItem((new CDiv())->addClass(ZBX_STYLE_DASHBOARD_GRID))
	)
	->show();

(new CScriptTag('
	view.init('.json_encode([
		'dashboard' => $data['dashboard'],
		'widget_defaults' => $data['widget_defaults'],
		'time_period' => $data['time_period']
	]).');
'))
	->setOnDocumentReady()
	->show();
