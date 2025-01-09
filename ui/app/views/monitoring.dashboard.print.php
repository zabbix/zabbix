<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * @var CView $this
 * @var array $data
 */

const HEADER_TITLE_HEIGHT = 60;
const PAGE_TITLE_HEIGHT = 45;
const PAGE_WIDTH = 1960;
const PAGE_MARGIN_TOP = 10;
const PAGE_MARGIN_BOTTOM = 12;

$this->addJsFile('class.dashboard.js');
$this->addJsFile('class.dashboard.page.js');
$this->addJsFile('class.dashboard.print.js');
$this->addJsFile('class.dashboard.widget.placeholder.js');
$this->addJsFile('class.widgets-data.js');
$this->addJsFile('class.widget-base.js');
$this->addJsFile('class.widget.js');
$this->addJsFile('class.widget.inaccessible.js');
$this->addJsFile('class.widget.iterator.js');
$this->addJsFile('class.widget.misconfigured.js');
$this->addJsFile('class.widget.paste-placeholder.js');

if (array_key_exists('error', $data)) {
	show_error_message($data['error']);

	return;
}

$this->addJsFile('class.csvggraph.js');
$this->addJsFile('class.svg.canvas.js');
$this->addJsFile('class.svg.map.js');
$this->addJsFile('d3.js');
$this->addJsFile('flickerfreescreen.js');
$this->addJsFile('gtlc.js');
$this->addJsFile('leaflet.js');
$this->addJsFile('leaflet.markercluster.js');
$this->addJsFile('class.geomaps.js');

$this->includeJsFile('monitoring.dashboard.print.js.php');

$this->addCssFile('assets/styles/vendors/Leaflet/leaflet.css');

$page_count = count($data['dashboard']['pages']);
$page_styles = '';

$header_title_tag = (new CTag('h1', true, $data['dashboard']['name']));

(new CTag('header', true, $header_title_tag))
	->addClass('header-title page_1')
	->show();

foreach ($data['dashboard']['pages'] as $index => $dashboard_page) {
	$page_number = $index + 1;
	$page_name = 'page_'.$page_number;

	$page_height = PAGE_MARGIN_TOP;

	if ($index === 0) {
		$page_height += HEADER_TITLE_HEIGHT;
	}

	if ($page_count > 1) {
		$page_height += PAGE_TITLE_HEIGHT;
	}

	$num_rows = 0;

	foreach ($dashboard_page['widgets'] as $widget) {
		$num_rows = max($num_rows, $widget['pos']['y'] + $widget['pos']['height']);
	}

	$page_height += $num_rows * DASHBOARD_ROW_HEIGHT + PAGE_MARGIN_BOTTOM;

	$page_styles .= '@page '.$page_name.' { size: '.PAGE_WIDTH.'px '.$page_height.'px; } ';
	$page_styles .= '.'.$page_name.' { page: '.$page_name.'; } ';

	$page_container = (new CDiv())->addClass('dashboard-page page_'.$page_number);

	if ($page_count > 1) {
		$page_container->addItem(new CTag('h1', true,
			$dashboard_page['name'] !== '' ? $dashboard_page['name'] : _s('Page %1$d', $page_number)
		));
	}

	$page_container
		->addItem((new CDiv())->addClass(ZBX_STYLE_DASHBOARD_GRID))
		->show();
}

(new CTag('style', true, $page_styles))->show();

(new CScriptTag('
	view.init('.json_encode([
		'dashboard' => $data['dashboard'],
		'widget_defaults' => $data['widget_defaults'],
		'dashboard_time_period' => $data['dashboard_time_period']
	]).');
'))
	->setOnDocumentReady()
	->show();
