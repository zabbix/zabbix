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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

$controls = (new CList())
	->addItem([
		new CLabel(_('Group'), 'groupid'),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		$this->data['pageFilter']->getGroupsCB()
	])
	->addItem([
		new CLabel(_('Host'), 'hostid'),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		$this->data['pageFilter']->getHostsCB()
	])
	->addItem([
		new CLabel(_('Graph'), 'graphid'),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		$this->data['pageFilter']->getGraphsCB()
	]);

if ($this->data['graphid']) {
	$controls->addItem(get_icon('favourite', ['fav' => 'web.favorite.graphids', 'elname' => 'graphid', 'elid' => $this->data['graphid']]));
	$controls->addItem(get_icon('reset', ['id' => $this->data['graphid']]));
}

$controls->addItem(get_icon('fullscreen', ['fullscreen' => $this->data['fullscreen']]));

$chartsWidget = (new CWidget())
	->setTitle(_('Graphs'))
	->setControls((new CForm('get'))
		->cleanItems()
		->addVar('fullscreen', $this->data['fullscreen'])
		->addItem($controls)
	);

$filterForm = (new CFilter('web.charts.filter.state'))->addNavigator();
$chartsWidget->addItem($filterForm);

if (!empty($this->data['graphid'])) {
	// append chart to widget
	$screen = CScreenBuilder::getScreen([
		'resourcetype' => SCREEN_RESOURCE_CHART,
		'graphid' => $this->data['graphid'],
		'profileIdx' => 'web.screens',
		'profileIdx2' => $this->data['graphid']
	]);

	$chartTable = (new CTable())
		->setAttribute('style', 'width: 100%;')
		->addRow($screen->get());

	$chartsWidget->addItem($chartTable);

	CScreenBuilder::insertScreenStandardJs([
		'timeline' => $screen->timeline,
		'profileIdx' => $screen->profileIdx
	]);
}
else {
	$screen = new CScreenBuilder();
	CScreenBuilder::insertScreenStandardJs([
		'timeline' => $screen->timeline
	]);

	$chartsWidget->addItem(new CTableInfo());
}

return $chartsWidget;
