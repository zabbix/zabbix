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

$this->addJsFile('js/gtlc.js');
$this->addJsFile('js/flickerfreescreen.js');
$this->addJsFile('js/multiselect.js');

$filter_column1 = (new CFormList())
	->addRow(_('Show'),
		(new CRadioButtonList('filter_show', (int) $data['filter']['show']))
			->addValue(_('Recent problems'), TRIGGERS_OPTION_RECENT_PROBLEM)
			->addValue(_('Problems'), TRIGGERS_OPTION_IN_PROBLEM)
			->setModern(true)
	)
	->addRow(_('Host groups'),
		(new CMultiSelect([
			'name' => 'filter_groupids[]',
			'objectName' => 'hostGroup',
			'data' => $data['filter']['groups'],
			'popup' => [
				'parameters' => 'srctbl=host_groups&dstfrm=zbx_filter&dstfld1=filter_groupids_'.
					'&srcfld1=groupid&multiselect=1'
			]
		]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
	)
	->addRow(_('Hosts'),
		(new CMultiSelect([
			'name' => 'filter_hostids[]',
			'objectName' => 'hosts',
			'data' => $data['filter']['hosts'],
			'popup' => [
				'parameters' => 'srctbl=hosts&dstfrm=zbx_filter&dstfld1=filter_hostids_&srcfld1=hostid'.
					'&real_hosts=1&multiselect=1'
			]
		]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
	);

if ($data['config']['event_ack_enable']) {
	$filter_column1->addRow(_('Show unacknowledged only'),
		(new CCheckBox('filter_unacknowledged'))->setChecked($data['filter']['unacknowledged'] == 1)
	);
}

$filter_column1
	->addRow(_('Minimum trigger severity'),
		new CComboBox('filter_severity', $data['filter']['severity'], null, $data['filter']['severities'])
	);

$filter = (new CFilter('web.problem.filter.state'))
	->addVar('action', 'problem.view')
	->addVar('fullscreen', $data['fullscreen'])
	->addVar('page', $data['page'])
	->addColumn($filter_column1);

(new CWidget())
	->setTitle(_('Problems'))
	->setControls(
		(new CForm('get'))
			->addVar('action', 'problem.view')
			->addVar('fullscreen', $data['fullscreen'])
			->addVar('page', $data['page'])
			->addItem(
				(new CList())
					->addItem(get_icon('fullscreen', ['fullscreen' => $data['fullscreen']]))
			)
	)
	->addItem($filter)
	->addItem(
		CScreenBuilder::getScreen([
			'resourcetype' => SCREEN_RESOURCE_PROBLEM,
			'mode' => SCREEN_MODE_JS,
			'dataId' => 'problem',
			'data' => [
				'fullscreen' => $data['fullscreen'],
				'sort' => $data['sort'],
				'sortorder' => $data['sortorder'],
				'page' => $data['page'],
				'filter' => $data['filter']
			]
		])->get()
	)
	->show();

// activating blinking
$this->addPostJS('jqBlink.blink();');
