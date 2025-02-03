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

$this->addJsFile('flickerfreescreen.js');
$this->addJsFile('gtlc.js');

$this->includeJsFile('reports.auditlog.list.js.php');

$filter = (new CFilter())
	->setResetUrl((new CUrl('zabbix.php'))->setArgument('action', $data['action']));

$filter_resourcetype = (new CSelect('filter_resourcetype'))
	->setId('resourcetype-select')
	->setValue($data['resourcetype'])
	->setFocusableElementId('filter-resourcetype')
	->addOptions(CSelect::createOptionsFromArray($data['resources']));

$filter_actions_options = [];

foreach ($data['actions'] as $value => $name) {
	$filter_actions_options[] = [
		'label' => $name,
		'value' => $value,
		'checked' => in_array($value, $data['auditlog_actions'])
	];
}

$filter_actions = (new CCheckBoxList('filter_actions'))
	->setId('filter-actions')
	->setColumns(3)
	->setOptions($filter_actions_options);

$html_page = (new CHtmlPage())
	->setTitle(_('Audit log'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::REPORTS_AUDITLOG_LIST))
	->addItem($filter
		->addVar('action', $data['action'])
		->setProfile($data['timeline']['profileIdx'])
		->setActiveTab($data['active_tab'])
		->addTimeSelector($data['timeline']['from'], $data['timeline']['to'], true, 'web.auditlog.filter')
		->addFilterTab(_('Filter'), [
			(new CFormList())
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
								'context' => 'audit',
								'dstfld1' => 'filter_userids_'
							]
						]
					]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
				])
				->addRow(_('Actions'), $filter_actions
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				),
			(new CFormList())
				->addRow(new CLabel(_('Resource'), $filter_resourcetype->getFocusableElementId()),
					$filter_resourcetype
				)
				->addRow(_('Resource ID'), (new CTextBox('filter_resourceid', $data['resourceid']))
					->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
				)
				->addRow(_('Recordset ID'), (new CTextBox('filter_recordsetid', $data['recordsetid']))
					->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
				)
				->addRow(_('IP'), (new CTextBox('filter_ip', $data['ip']))
					->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
				)
		])
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
	])
	->setPageNavigation($data['paging']);

foreach ($data['auditlogs'] as $auditlog) {
	$table->addRow([
		zbx_date2str(DATE_TIME_FORMAT_SECONDS, $auditlog['clock']),
		in_array($auditlog['userid'], $data['non_existent_userids'])
			? new CTag('em', true, $auditlog['username'])
			: $data['users'][$auditlog['userid']],
		new CLink($auditlog['ip'],
			(new CUrl('zabbix.php'))
				->setArgument('action', $data['action'])
				->setArgument('filter_ip', $auditlog['ip'])
				->setArgument('filter_set', 1)
		),
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
			(new CDiv(zbx_nl2br($auditlog['short_details'])))->addClass(ZBX_STYLE_WORDBREAK),
			($auditlog['details_button'] == 1)
				? (new CDiv(
					(new CLinkAction(_('Details')))->setAttribute('data-details', json_encode($auditlog['details']))
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

$html_page
	->addItem(
		(new CForm('get'))
			->setName('auditForm')
			->addItem($table)
	)
	->show();

(new CScriptTag('view.init();'))
	->setOnDocumentReady()
	->show();
