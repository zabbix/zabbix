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
$this->addJsFile('flickerfreescreen.js');
$this->addJsFile('gtlc.js');
$this->addJsFile('class.calendar.js');
$this->addJsFile('multiselect.js');

$this->includeJsFile('reports.auditlog.list.js.php');

$filter = (new CFilter((new CUrl('zabbix.php'))->setArgument('action', $data['action'])));

$select_filter_resourcetype = (new CSelect('filter_resourcetype'))
	->setId('resourcetype-select')
	->setValue($data['resourcetype'])
	->setFocusableElementId('filter-resourcetype')
	->addOptions(CSelect::createOptionsFromArray($data['resources']));

$select_filter_action = (new CSelect('filter_action'))
	->setId('action-select')
	->setValue($data['auditlog_action'])
	->setFocusableElementId('filter-action')
	->addOptions(CSelect::createOptionsFromArray($data['actions']));

$filter_form = (new CFormList())
	->addRow(new CLabel(_('Users'), 'filter_userids__ms'), [
		(new CMultiSelect([
			'name' => 'filter_userids[]',
			'object_name' => 'users',
			'data' => $data['userids'],
			'placeholder' => '',
			'popup' => [
				'parameters' => [
					'srctbl' => 'users',
					'srcfld1' => 'userid',
					'srcfld2' => 'fullname',
					'dstfrm' => $filter->getName(),
					'dstfld1' => 'filter_userids_'
				]
			]
		]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
	])
	->addRow(new CLabel(_('Resource'), $select_filter_resourcetype->getFocusableElementId()),
		$select_filter_resourcetype
	)
	->addRow(_('Resource ID'), (new CTextBox('filter_resourceid', $data['resourceid']))
		->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
	)
	->addRow(new CLabel(_('Action'), $select_filter_action->getFocusableElementId()),
		$select_filter_action
	);

$widget = (new CWidget())
	->setTitle(_('Audit log'))
	->addItem($filter
		->addVar('action', $data['action'])
		->setProfile($data['timeline']['profileIdx'])
		->setActiveTab($data['active_tab'])
		->addTimeSelector($data['timeline']['from'], $data['timeline']['to'])
		->addFilterTab(_('Filter'), [$filter_form])
);

$table = (new CTableInfo())
	->setHeader([
		_('Time'),
		_('User'),
		_('IP'),
		_('Resource'),
		_('Action'),
		_('ID'),
		_('Description'),
		_('Details')
	]);

foreach ($data['auditlogs'] as $auditlog) {
	$details = [];

	foreach ($auditlog['details'] as $detail) {
		$details[] = [$detail['table_name'].'.'.$detail['field_name'].': '.$detail['oldvalue'].
			' => '.$detail['newvalue'], BR()
		];
	}

	$table->addRow([
		zbx_date2str(DATE_TIME_FORMAT_SECONDS, $auditlog['clock']),
		$data['users'][$auditlog['userid']],
		$auditlog['ip'],
		array_key_exists($auditlog['resourcetype'], $data['resources'])
			? $data['resources'][$auditlog['resourcetype']]
			: _('Unknown resource'),
		array_key_exists($auditlog['action'], $data['actions'])
			? $data['actions'][$auditlog['action']]
			: _('Unknown action'),
		$auditlog['resourceid'],
		$auditlog['resourcename'],
		$details ? $details : $auditlog['note']
	]);
}

$obj = [
	'id' => 'timeline_1',
	'domid' => 'auditlog',
	'loadSBox' => 0,
	'loadImage' => 0,
	'dynamic' => 0
];

(new CScriptTag('timeControl.addObject("auditlog", '.json_encode($data['timeline']).', '.json_encode($obj).');'.
	'timeControl.processObjects();')
)->show();

$widget->addItem((new CForm('get'))
	->setName('auditForm')
	->addItem([$table, $data['paging']])
)->show();
