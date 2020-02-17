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

$filter = (new CFilter(new CUrl('charts.php')))
	->setProfile($data['timeline']['profileIdx'], $data['timeline']['profileIdx2'])
	->setActiveTab($data['active_tab'])
	->addTimeSelector($data['timeline']['from'], $data['timeline']['to'],
		$web_layout_mode != ZBX_LAYOUT_KIOSKMODE
	);

$chartsWidget = (new CWidget())
	->setTitle(_('Graphs'))
	->setWebLayoutMode($web_layout_mode)
	->setControls(new CList([$controls, $content_control]));

if (in_array($web_layout_mode, [ZBX_LAYOUT_NORMAL, ZBX_LAYOUT_FULLSCREEN])) {
	$filter->addFilterTab(_('Filter'), [
		(new CFormList())
			->addRow((new CLabel(_('Host'), 'filter_hostids__ms')),
				(new CMultiSelect([
					'multiple' => true,
					'name' => 'filter_hostids[]',
					'object_name' => 'host',
					'data' => [['name' => 'Zabbix server', 'id' => 10084]],
					'popup' => [
						'parameters' => [
							'srctbl' => 'hosts',
							'srcfld1' => 'hostid',
							'dstfrm' => 'zbx_filter',
							'dstfld1' => 'filter_hostids_',
							'with_graphs' => true
						]
					]
				]))
					->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
			)
			->addRow((new CLabel(_('Search type'), 'waa')),
				(new CRadioButtonList('search_type', $data['search_type']))
					->addValue(_('Strict'), ZBX_SEARCH_TYPE_STRICT, null,
						'$("#filter_graph_pattern_").multiSelect("hide");$("#filter_graph_").multiSelect("show");'
					)
					->addValue(_('Pattern'), ZBX_SEARCH_TYPE_PATTERN, null,
						'$("#filter_graph_").multiSelect("hide");$("#filter_graph_pattern_").multiSelect("show");'
					)
					->setModern(true)
			)
			->addRow((new CLabel(_('Graph'), 'filter_hostids_strict__ms')),
				[
					(new CMultiSelect([
						'multiple' => true,
						'name' => 'filter_graph[]',
						'object_name' => 'graph',
						'data' => [['name' => 'CPU Jumps', 'id' => 1]],
						'popup' => [
							'parameters' => [
								'srctbl' => 'graphs',
								'srcfld1' => 'graphid',
								'dstfrm' => 'zbx_filter',
								'dstfld1' => 'filter_hostids_',
								'templated' => false
							]
						],
						'hidden' => ($data['search_type'] == ZBX_SEARCH_TYPE_PATTERN)
					]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH),
					(new CPatternSelect([
						'placeholder' => _('graph pattern'),
						'name' => 'filter_graph_pattern[]',
						'object_name' => 'graph',
						'data' => [['name' => 'CPU Jumps', 'id' => 1]],
						'popup' => [
							'parameters' => [
								'srctbl' => 'graphs',
								'srcfld1' => 'graphid',
								'dstfrm' => 'zbx_filter',
								'dstfld1' => 'filter_graph_pattern_',
								'templated' => false
							]
						],
						'hidden' => ($data['search_type'] == ZBX_SEARCH_TYPE_STRICT)
					]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
				]
			)
	]);
}

$chartsWidget->addItem($filter);

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
