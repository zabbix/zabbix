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

$form_list = (new CFormList())
	->addVar('idx', $data['idx'])
	->addVar('idx2', $data['idx2'])
	->addVar('create', $data['create'])
	->addVar('support_custom_time', $data['support_custom_time'])
	->addRow((new CLabel(_('Name'), 'filter_name'))->setAsteriskMark(),
		(new CTextBox('filter_name', $data['filter_name']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	->addRow(_('Show number of records'),
		(new CCheckBox('filter_show_counter', 1))->setChecked($data['filter_show_counter'])
	);

if ($data['support_custom_time']) {
	$form_list
		->addRow(_('Set custom time period'),
			(new CCheckBox('filter_custom_time', 1))
				->setChecked($data['filter_custom_time'])
		)
		->addRow(new CLabel(_('From'), 'tabfilter_from'),
			(new CDateSelector('tabfilter_from', $data['tabfilter_from']))
				->setDateFormat(ZBX_DATE_TIME)
				->setEnabled((bool) $data['filter_custom_time'])
		)
		->addRow(new CLabel(_('To'), 'tabfilter_to'),
			(new CDateSelector('tabfilter_to', $data['tabfilter_to']))
				->setDateFormat(ZBX_DATE_TIME)
				->setEnabled((bool) $data['filter_custom_time'])
		);
}

$form = (new CForm())
	->cleanItems()
	->setName('tabfilter_form')
	->addVar('action', 'popup.tabfilter.update')
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
			'enabled' => !$data['create'],
			'class' => 'float-left',
			'confirmation' => _('Are you sure you want to delete this filter?'),
			'action' => 'return tabFilterDelete(overlay)'
		],
		[
			'title' => _('Save'),
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'return tabFilterUpdate(overlay)'
		]
	]
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
