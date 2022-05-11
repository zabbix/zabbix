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

$rules_table = new CTable();

$titles = [
	'groups' => _('Groups'),
	'hosts' => _('Hosts'),
	'templates' => _('Templates'),
	'valueMaps' => _('Value mappings'),
	'templateDashboards' => _('Template dashboards'),
	'templateLinkage' => _('Template linkage'),
	'items' => _('Items'),
	'discoveryRules' => _('Discovery rules'),
	'triggers' => _('Triggers'),
	'graphs' => _('Graphs'),
	'httptests' => _('Web scenarios'),
	'maps' => _('Maps')
];

if ($data['user']['type'] == USER_TYPE_SUPER_ADMIN) {
	$titles['images'] = _('Images');
	$titles['mediaTypes'] = _('Media types');
}

$col_update = false;
$col_create = false;
$col_delete = false;

foreach ($titles as $key => $title) {
	if (array_key_exists($key, $data['rules'])) {
		$col_update = ($col_update || array_key_exists('updateExisting', $data['rules'][$key]));
		$col_create = ($col_create || array_key_exists('createMissing', $data['rules'][$key]));
		$col_delete = ($col_delete || array_key_exists('deleteMissing', $data['rules'][$key]));
	}
}

foreach ($titles as $key => $title) {
	if (!array_key_exists($key, $data['rules'])) {
		continue;
	}

	$checkbox_update = null;
	$checkbox_create = null;
	$checkbox_delete = null;

	if (array_key_exists('updateExisting', $data['rules'][$key])) {
		$checkbox_update = (new CCheckBox('rules['.$key.'][updateExisting]'))
			->setChecked($data['rules'][$key]['updateExisting']);

		if ($key === 'images') {
			$checkbox_update->onClick('updateWarning(this, '.json_encode(_('Images for all maps will be updated!')).')');
		}
	}

	if (array_key_exists('createMissing', $data['rules'][$key])) {
		$checkbox_create = (new CCheckBox('rules['.$key.'][createMissing]'))
			->setChecked($data['rules'][$key]['createMissing']);
	}

	if (array_key_exists('deleteMissing', $data['rules'][$key])) {
		$checkbox_delete = (new CCheckBox('rules['.$key.'][deleteMissing]'))
			->setChecked($data['rules'][$key]['deleteMissing'])
			->addClass('deleteMissing');

		if ($key === 'templateLinkage') {
			$checkbox_delete->onClick('updateWarning(this, '.json_encode(
				_('Template and host properties that are inherited through template linkage will be unlinked and cleared.')
			).')');
		}
	}

	switch ($key) {
		case 'maps':
			if (!$data['user']['can_edit_maps']) {
				$checkbox_update->setAttribute('disabled', 'disabled');
				$checkbox_create->setAttribute('disabled', 'disabled');
			}
			break;

		default:
			if ($data['user']['type'] != USER_TYPE_SUPER_ADMIN && $data['user']['type'] != USER_TYPE_ZABBIX_ADMIN) {
				if ($checkbox_update !== null) {
					$checkbox_update->setAttribute('disabled', 'disabled');
				}

				if ($checkbox_create !== null) {
					$checkbox_create->setAttribute('disabled', 'disabled');
				}

				if ($checkbox_delete !== null) {
					$checkbox_delete->setAttribute('disabled', 'disabled');
				}
			}
	}

	$rules_table->addRow([
		$title,
		$col_update ? (new CCol($checkbox_update))->addClass(ZBX_STYLE_CENTER) : null,
		$col_create ? (new CCol($checkbox_create))->addClass(ZBX_STYLE_CENTER) : null,
		$col_delete ? (new CCol($checkbox_delete))->addClass(ZBX_STYLE_CENTER) : null
	]);
}

$rules_table->setHeader([
	'',
	$col_update ? _('Update existing') : null,
	$col_create ? _('Create new') : null,
	$col_delete ? _('Delete missing') : null
]);

$form_list = (new CFormList())
	->addRow((new CLabel(_('Import file'), 'import_file'))->setAsteriskMark(),
		(new CFile('import_file'))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
			->setAttribute('autofocus', 'autofocus')
	)
	->addRow(_('Rules'), new CDiv($rules_table));

$form = (new CForm('post', null, 'multipart/form-data'))
	->setId('import-form')
	->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE)
	->addVar('import', 1)
	->addVar('rules_preset', $data['rules_preset'])
	->addItem($form_list);

$output = [
	'header' => $data['title'],
	'script_inline' => trim($this->readJsFile('popup.import.js.php')),
	'body' => $form->toString(),
	'buttons' => [
		[
			'title' => _('Import'),
			'class' => '',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'return submitPopup(overlay);'
		]
	]
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
