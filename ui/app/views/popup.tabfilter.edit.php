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

$form_list = (new CFormList())
	->addVar('idx', $data['idx'])
	->addVar('idx2', $data['idx2'])
	->addRow((new CLabel(_('Name'), 'name'))->setAsteriskMark(), (new CTextBox('name', $data['name']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH))
	->addRow(_('Show number of records'), (new CCheckBox('show_counter', 1))->setChecked($data['show_counter']))
	->addRow(_('Set custom time period'), (new CCheckBox('custom_time', 1))->setChecked($data['custom_time']))
	->addRow(_('From'),
		(new CDateSelector('tabfilter_from', $data['tabfilter_from']))
			->setDateFormat(ZBX_DATE_TIME)
			->setEnabled((bool) $data['custom_time'])
	)
	->addRow(_('To'),
		(new CDateSelector('tabfilter_to', $data['tabfilter_to']))
			->setDateFormat(ZBX_DATE_TIME)
			->setEnabled((bool) $data['custom_time'])
	);

$form = (new CForm())
	->cleanItems()
	->setName('tabfilter_form')
	->addVar('action', $data['action'])
	->addItem([
		$form_list,
		(new CInput('submit', 'submit'))->addStyle('display: none;')
	]);

$output = [
	'header' => $data['title'],
	'script_inline' => $this->readJsFile('popup.tabfilter.edit.js.php'),
	'body' => (new CDiv([$data['errors'], $form]))->toString(),
	'buttons' => [
		[
			'title' => _('Delete'),
			'isSubmit' => true,
			'keepOpen' => true,
			'action' => 'return tabFilterFormAction("delete", overlay)'
		],
		[
			'title' => _('Save'),
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'return tabFilterFormAction("update", overlay)'
		]
	]
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
