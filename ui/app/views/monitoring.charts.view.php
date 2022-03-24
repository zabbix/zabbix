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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * @var CView $this
 */

if ($data['error']) {
	show_error_message($data['error']);
}

$this->addJsFile('layout.mode.js');
$this->addJsFile('class.calendar.js');
$this->addJsFile('gtlc.js');

$this->includeJsFile('monitoring.charts.view.js.php');

$this->enableLayoutModes();
$web_layout_mode = $this->getLayoutMode();

$widget = (new CWidget())
	->setTitle(_('Graphs'))
	->setWebLayoutMode($web_layout_mode)
	->setDocUrl(CDocHelper::getUrl(CDocHelper::MONITORING_CHARTS_VIEW))
	->setControls(
		(new CTag('nav', true, (new CList())
			->addItem(get_icon('kioskmode', ['mode' => $web_layout_mode]))
		))->setAttribute('aria-label', _('Content controls'))
	);

$filter = (new CFilter())
	->setResetUrl((new CUrl('zabbix.php'))->setArgument('action', 'charts.view'))
	->setProfile($data['timeline']['profileIdx'], $data['timeline']['profileIdx2'])
	->setActiveTab($data['active_tab'])
	->addTimeSelector($data['timeline']['from'], $data['timeline']['to'],
		$web_layout_mode != ZBX_LAYOUT_KIOSKMODE
	)
	->addFormItem((new CVar('action', 'charts.view'))->removeId());

if ($web_layout_mode == ZBX_LAYOUT_NORMAL) {
	$filter->addFilterTab(_('Filter'), [
		(new CFormList())
			->addRow((new CLabel(_('Hosts'), 'filter_hostids__ms')),
				(new CMultiSelect([
					'name' => 'filter_hostids[]',
					'object_name' => 'hosts',
					'data' => $data['ms_hosts'],
					'popup' => [
						'parameters' => [
							'srctbl' => 'hosts',
							'srcfld1' => 'hostid',
							'dstfrm' => 'zbx_filter',
							'dstfld1' => 'filter_hostids_',
							'real_hosts' => true,
							'with_graphs' => true
						]
					]
				]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
			)
			->addRow((new CLabel(_('Name'), 'filter_name')),
				(new CTextBox('filter_name', $data['filter_name']))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
			)
			->addRow((new CLabel(_('Show'), 'filter_show')),
				(new CRadioButtonList('filter_show', $data['filter_show']))
					->addValue(_('All graphs'), GRAPH_FILTER_ALL)
					->addValue(_('Host graphs'), GRAPH_FILTER_HOST)
					->addValue(_('Simple graphs'), GRAPH_FILTER_SIMPLE)
					->setModern(true)
			)
	],
	new CPartial('monitoring.charts.subfilter', $data['subfilters']));
}

$widget->addItem($filter);

if (!$data['filter_hostids']) {
	$widget->addItem((new CTableInfo())->setNoDataMessage(_('Specify host to see the graphs.')));
}
elseif ($data['charts']) {
	$table = (new CTable())
		->setAttribute('style', 'width: 100%;')
		->setId('charts');
	$widget
		->addItem($table)
		->addItem($data['paging']);
}
else {
	$widget->addItem(new CTableInfo());
}

$widget->show();

(new CScriptTag('
	view.init('.json_encode([
		'filter_form_name' => 'zbx_filter',
		'data' => [
			'charts' => $data['charts'],
			'timeline' => $data['timeline'],
			'config' => [
				'refresh_interval' => CWebUser::getRefresh(),
				'filter_hostids' => $data['filter_hostids'],
				'filter_name' => $data['filter_name'],
				'filter_show' => $data['filter_show'],
				'subfilter_tagnames' => $data['subfilter_tagnames'],
				'subfilter_tags' => $data['subfilter_tags'],
				'page' => $data['page']
			]
		]
	]).');
'))->show();
