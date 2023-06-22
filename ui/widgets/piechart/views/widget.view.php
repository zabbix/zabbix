<?php declare(strict_types = 0);
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
 * Pie chart widget view.
 *
 * @var CView $this
 * @var array $data
 */

$view = new CWidgetView($data);

$svgContainer = new CDiv('SVG');
$svgContainer->addClass('svg-pie-chart');

if ($data['vars']['legend'] && $data['vars']['legend']['show']){
	$legendItems = [];

	foreach ($data['vars']['legend']['data'] as $sector) {
		$legendItem = (new CDiv(new CSpan($sector['name'])))
			->addClass('pie-chart-legend-item')
			->setAttribute('style', '--color: ' . $sector['color']);
		$legendItems[] = $legendItem;
	}

	$legendContainer = (new CDiv($legendItems))
		->addClass('pie-chart-legend')
		->addStyle('--lines: ' . $data['vars']['legend']['lines'] . ';')
		->addStyle('--columns: ' . $data['vars']['legend']['columns'] . ';');

	$view->addItem($svgContainer);
	$view->addItem($legendContainer);
}
else {
	$view->addItem($svgContainer);
}

if ($data['info'] !== null) {
	$view->setVar('info', $data['info']);
}

foreach ($data['vars'] as $name => $value) {
	if ($value !== null) {
		$view->setVar($name, $value);
	}
}

$view->show();
