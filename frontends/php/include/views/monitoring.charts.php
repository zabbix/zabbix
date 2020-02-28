<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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


/**
 * @var CView $this
 */

$web_layout_mode = CViewHelper::loadLayoutMode();

$controls = (new CForm('get'))
	->cleanItems()
	->setAttribute('aria-label', _('Main filter'))
	->addItem((new CList())
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
		])
		->addItem([
			new CLabel(_('View as'), 'action'),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CComboBox('action', $data['action'], 'submit()', $data['actions']))->setEnabled((bool) $data['graphid'])
		])
	);

$content_control = (new CList());

if ($this->data['graphid']) {
	$content_control->addItem(get_icon('favourite', ['fav' => 'web.favorite.graphids', 'elname' => 'graphid',
		'elid' => $this->data['graphid']])
	);
}

$content_control->addItem(get_icon('fullscreen', ['mode' => $web_layout_mode]));
$content_control = (new CTag('nav', true, $content_control))->setAttribute('aria-label', _('Content controls'));

$chartsWidget = (new CWidget())
	->setTitle(_('Graphs'))
	->setWebLayoutMode($web_layout_mode)
	->setControls(new CList([$controls, $content_control]))
	->addItem(
		(new CFilter(new CUrl('charts.php')))
			->setProfile($data['timeline']['profileIdx'], $data['timeline']['profileIdx2'])
			->setActiveTab($data['active_tab'])
			->addTimeSelector($data['timeline']['from'], $data['timeline']['to'],
				$web_layout_mode != ZBX_LAYOUT_KIOSKMODE)
	);

if (!empty($this->data['graphid'])) {
	// append chart to widget

	if ($data['action'] === HISTORY_VALUES) {
		$screen = CScreenBuilder::getScreen([
			'resourcetype' => SCREEN_RESOURCE_HISTORY,
			'action' => HISTORY_VALUES,
			'graphid' => $data['graphid'],
			'pageFile' => (new CUrl('charts.php'))
				->setArgument('groupid', $data['groupid'])
				->setArgument('hostid', $data['hostid'])
				->setArgument('graphid', $data['graphid'])
				->setArgument('action', $data['action'])
				->getUrl(),
			'profileIdx' => $data['timeline']['profileIdx'],
			'profileIdx2' => $data['timeline']['profileIdx2'],
			'from' => $data['timeline']['from'],
			'to' => $data['timeline']['to'],
			'page' => $data['page']
		]);
	}
	else {
		$screen = CScreenBuilder::getScreen([
			'resourcetype' => SCREEN_RESOURCE_CHART,
			'graphid' => $this->data['graphid'],
			'profileIdx' => $data['timeline']['profileIdx'],
			'profileIdx2' => $data['timeline']['profileIdx2']
		]);
	}

	$chartTable = (new CTable())
		->setAttribute('style', 'width: 100%;')
		->addRow($screen->get());

	$chartsWidget->addItem($chartTable);

	CScreenBuilder::insertScreenStandardJs($screen->timeline);
}
else {
	$screen = new CScreenBuilder();
	CScreenBuilder::insertScreenStandardJs($screen->timeline);

	$chartsWidget->addItem(new CTableInfo());
}

$chartsWidget->show();
