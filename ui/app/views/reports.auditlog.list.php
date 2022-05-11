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
$this->addJsFile('flickerfreescreen.js');
$this->addJsFile('gtlc.js');
$this->addJsFile('class.calendar.js');

$this->includeJsFile('reports.auditlog.list.js.php');

$filter = (new CFilter())
	->setResetUrl((new CUrl('zabbix.php'))->setArgument('action', $data['action']));

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
					'dstfrm' => 'zbx_filter',
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
	)
	->addRow(_('Recordset ID'), (new CTextBox('filter_recordsetid', $data['recordsetid']))
		->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
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
		_('ID'),
		_('Action'),
		_('Recordset ID'),
		_('Details')
	]);

foreach ($data['auditlogs'] as $auditlog) {
	$table->addRow([
		zbx_date2str(DATE_TIME_FORMAT_SECONDS, $auditlog['clock']),
		in_array($auditlog['userid'], $data['non_existent_userids'])
			? new CTag('em', true, $auditlog['username'])
			: $data['users'][$auditlog['userid']],
		$auditlog['ip'],
		array_key_exists($auditlog['resourcetype'], $data['resources'])
			? $data['resources'][$auditlog['resourcetype']]
			: _('Unknown resource'),
		new CLink($auditlog['resourceid'],
			(new CUrl('zabbix.php'))
				->setArgument('action', $data['action'])
				->setArgument('filter_resourceid', $auditlog['resourceid'])
				->setArgument('filter_resourcetype', $auditlog['resourcetype'])
				->setArgument('filter_set', 1)
		),
		array_key_exists($auditlog['action'], $data['actions'])
			? $data['actions'][$auditlog['action']]
			: _('Unknown action'),
		new CLink($auditlog['recordsetid'],
			(new CUrl('zabbix.php'))
				->setArgument('action', $data['action'])
				->setArgument('filter_recordsetid', $auditlog['recordsetid'])
				->setArgument('filter_set', 1)
		),
		(new CDiv([
			new CDiv(zbx_nl2br($auditlog['short_details'])),
			($auditlog['details_button'] == 1)
				? (new CDiv (
					(new CLinkAction(_('Details')))->onClick('openAuditDetails('.json_encode($auditlog['details']).')')
				))->addClass('audit-show-details-btn-wrapper')
				: ''
		]))->addClass('audit-details-wrapper')

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
