<?php declare(strict_types = 0);
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
 * @var array $data
 */

$form = (new CForm())
	->setName('maintenance.edit')
	->setId('maintenance-form')
	->addVar('maintenanceid', $data['maintenanceid'] ?: 0)
	->addItem((new CInput('submit', null))->addStyle('display: none;'));

$maintenance_tab = (new CFormGrid())
	->addItem([
		(new CLabel(_('Name'), 'mname'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('mname', $data['maintenance']['mname'] ?: ''))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
				->setAttribute('autofocus', 'autofocus')
				->setAttribute('maxlength', DB::getFieldLength('maintenances', 'name'))
		)
	]);

$form
	->addItem($maintenance_tab)
	->addItem(
		(new CScriptTag('maintenance_edit_popup.init();'))->setOnDocumentReady()
	);

if ($data['maintenanceid'] !== 0) {
	$buttons = [
		[
			'title' => _('Update'),
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'maintenance_edit_popup.submit();'
		],
		[
			'title' => _('Clone'),
			'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-clone']),
			'keepOpen' => true,
			'isSubmit' => false,
			'action' => 'maintenance_edit_popup.clone();'
		],
		[
			'title' => _('Delete'),
			'confirmation' => _('Delete maintenance period?'),
			'class' => ZBX_STYLE_BTN_ALT,
			'keepOpen' => true,
			'isSubmit' => false,
			'action' => 'maintenance_edit_popup.delete();'
		]
	];
}
else {
	$buttons = [
		[
			'title' => _('Add'),
			'class' => 'js-add',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'maintenance_edit_popup.submit();'
		]
	];
}

$output = [
	'header' => $data['maintenanceid'] !== 0 ? _('Maintenance period') : _('New maintenance period'),
	'doc_url' => CDocHelper::getUrl(CDocHelper::DATA_COLLECTION_MAINTENANCE_EDIT),
	'body' => $form->toString(),
	'buttons' => $buttons,
	'script_inline' => getPagePostJs().
		$this->readJsFile('popup.maintenance.edit.js.php')
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
