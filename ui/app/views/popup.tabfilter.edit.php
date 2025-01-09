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
		->addRow(_('Override time period selector'),
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
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('tabfilter')))->removeId())
	->setName('tabfilter_form')
	->addVar('action', 'popup.tabfilter.update')
	->addItem($form_list);

// Enable form submitting on Enter.
$form->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN));

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
