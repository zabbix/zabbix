<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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


$chartsWidget = new CWidget('hat_charts');
$chartForm = new CForm('get');
$chartForm->addVar('fullscreen', $this->data['fullscreen']);
$chartForm->addItem(array(_('Group').SPACE, $this->data['pageFilter']->getGroupsCB(true)));
$chartForm->addItem(array(SPACE._('Host').SPACE, $this->data['pageFilter']->getHostsCB(true)));
$chartForm->addItem(array(SPACE._('Graph').SPACE, $this->data['pageFilter']->getGraphsCB(true)));

$chartsWidget->addFlicker(new CDiv(null, null, 'scrollbar_cntr'), CProfile::get('web.charts.filter.state', 1));

if ($this->data['graphid']) {
	$chartsWidget->addPageHeader(_('Graphs'), array(
		get_icon('favourite', array('fav' => 'web.favorite.graphids', 'elname' => 'graphid', 'elid' => $this->data['graphid'])),
		SPACE,
		get_icon('reset', array('id' => $this->data['graphid'])),
		SPACE,
		get_icon('fullscreen', array('fullscreen' => $this->data['fullscreen']))
	));
}
else {
	$chartsWidget->addPageHeader(_('Graphs'), array(get_icon('fullscreen', array('fullscreen' => $this->data['fullscreen']))));
}

$chartsWidget->addHeader(
	isset($this->data['pageFilter']->graphs[$this->data['graphid']])
		? $this->data['pageFilter']->graphs[$this->data['graphid']]
		: null,
	$chartForm
);
$chartsWidget->addItem(BR());

if (!empty($this->data['graphid'])) {
	// append chart to widget
	$screen = CScreenBuilder::getScreen(array(
		'resourcetype' => SCREEN_RESOURCE_CHART,
		'graphid' => $this->data['graphid'],
		'profileIdx' => 'web.screens',
		'profileIdx2' => $this->data['graphid']
	));

	$chartTable = new CTable(_('No graphs defined.'), 'maxwidth');
	$chartTable->addRow($screen->get());

	$chartsWidget->addItem($chartTable);

	CScreenBuilder::insertScreenStandardJs(array(
		'timeline' => $screen->timeline,
		'profileIdx' => $screen->profileIdx
	));
}
else {
	$screen = new CScreenBuilder();
	CScreenBuilder::insertScreenStandardJs(array(
		'timeline' => $screen->timeline
	));

	$chartsWidget->addItem(new CTableInfo(_('No graphs defined.')));
}

return $chartsWidget;
