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

$filter = (new CFormList())
	->addRow(_('User'), [
		(new CTextBox('alias', $data['alias']))
			->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
			->setAttribute('autofocus', 'autofocus'),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CButton('select_user', _('Select')))
			->addClass(ZBX_STYLE_BTN_GREY)
			->onClick('return PopUp("popup.generic",'.
				json_encode([
					'srctbl' => 'users',
					'srcfld1' => 'alias',
					'dstfrm' => 'zbx_filter',
					'dstfld1' => 'alias'
				]).', null, this);'
			)
	])
	->addRow(_('Action'), new CComboBox('auditlog_action', $data['auditlog_action'], null, $data['actions']))
	->addRow(_('Resource'), new CComboBox('resourcetype', $data['resourcetype'], null, $data['resources']));

$widget = (new CWidget())
	->setTitle(_('Audit log'))
	->addItem((new CFilter((new CUrl('zabbix.php'))->setArgument('action', $data['action'])))
		->addVar('action', $data['action'])
		->setProfile($data['timeline']['profileIdx'])
		->setActiveTab($data['active_tab'])
		->addTimeSelector($data['timeline']['from'], $data['timeline']['to'])
		->addFilterTab(_('Filter'), [$filter])
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
	'dynamic' => 0,
	'mainObject' => 1
];

(new CScriptTag('timeControl.addObject("auditlog", '.json_encode($data['timeline']).', '.json_encode($obj).');'.
	'timeControl.processObjects();')
)->show();

$widget->addItem((new CForm('get'))
	->setName('auditForm')
	->addItem([$table, $data['paging']])
)->show();
