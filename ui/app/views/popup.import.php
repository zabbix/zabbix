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

$rules_table = (new CTable())
	->setId('rules_table')
	->addClass(ZBX_STYLE_TABLE_INITIAL_WIDTH);

$titles = [
	'template_groups' => _('Template groups'),
	'host_groups' => _('Host groups'),
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
	'maps' => _('Maps'),
	'images' => _('Images'),
	'mediaTypes' => _('Media types')
];

switch ($data['rules_preset']) {
	case 'map':
		$doc_url = CDocHelper::POPUP_MAPS_IMPORT;
		break;
	case 'template':
		$doc_url = CDocHelper::POPUP_TEMPLATE_IMPORT;
		break;
	case 'host':
		$doc_url = CDocHelper::POPUP_HOST_IMPORT;
		break;
	case 'mediatype':
		$doc_url = CDocHelper::POPUP_MEDIA_IMPORT;
		break;
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

if ($data['advanced_config']) {
	$rules_table->addRow([
		(new CCol('All'))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
		$col_update
			? 	(new CCol(
					(new CCheckBox('update_all'))->setChecked(true)
				))->addClass(ZBX_STYLE_CENTER)
			: null,
		$col_create
			? 	(new CCol(
					(new CCheckBox('create_all'))->setChecked(true)
				))->addClass(ZBX_STYLE_CENTER)
			: null,
		$col_delete
			? 	(new CCol(
					(new CCheckBox('delete_all'))->setChecked(true)
				))->addClass(ZBX_STYLE_CENTER)
			: null
	]);
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
			->setChecked($data['rules'][$key]['updateExisting'])
			->addClass('js-update');
	}

	if (array_key_exists('createMissing', $data['rules'][$key])) {
		$checkbox_create = (new CCheckBox('rules['.$key.'][createMissing]'))
			->setChecked($data['rules'][$key]['createMissing'])
			->addClass('js-create');
	}

	if (array_key_exists('deleteMissing', $data['rules'][$key])) {
		$checkbox_delete = (new CCheckBox('rules['.$key.'][deleteMissing]'))
			->setChecked($data['rules'][$key]['deleteMissing'])
			->addClass('js-delete');
	}

	$checkbox_row = (new CRow([
		$title,
		$col_update ? (new CCol($checkbox_update))->addClass(ZBX_STYLE_CENTER) : null,
		$col_create ? (new CCol($checkbox_create))->addClass(ZBX_STYLE_CENTER) : null,
		$col_delete ? (new CCol($checkbox_delete))->addClass(ZBX_STYLE_CENTER) : null
	]));

	if ($data['advanced_config']) {
		$checkbox_row
			->addClass(ZBX_STYLE_DISPLAY_NONE)
			->addClass('js-advanced-configuration');
	}

	$rules_table->addItem($checkbox_row);
}

$rules_table->setHeader([
	'',
	$col_update ? (new CColHeader(_('Update existing')))->addClass(ZBX_STYLE_CENTER) : null,
	$col_create ? (new CColHeader( _('Create new')))->addClass(ZBX_STYLE_CENTER) : null,
	$col_delete ? (new CColHeader(_('Delete missing')))->addClass(ZBX_STYLE_CENTER) : null
]);

$advanced_config_checkbox = $data['advanced_config']
	? [new CLabel(_('Advanced options'), 'advanced_options'), new CFormField(
			(new CCheckBox('advanced_options'))
				->setChecked(false)
		)]
	: null;

$form_grid = (new CFormGrid())
	->addItem([
		(new CLabel(_('Import file'), 'import_file'))->setAsteriskMark(),
		new CFormField(
			(new CFile('import_file'))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
				->setAttribute('autofocus', 'autofocus')
		)
	])
	->addItem($advanced_config_checkbox)
	->addItem([new CLabel(_('Rules')), new CFormField($rules_table)]);

$form = (new CForm('post', null, 'multipart/form-data'))
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('import')))->removeId())
	->setId('import-form')
	->addVar('import', 1)
	->addVar('rules_preset', $data['rules_preset'])
	->addItem($form_grid)
	->addItem((new CScriptTag('popup_import.init();'))->setOnDocumentReady());

$output = [
	'header' => $data['title'],
	'doc_url' => CDocHelper::getUrl($doc_url),
	'body' => $form->toString(),
	'buttons' => [
		[
			'title' => _('Import'),
			'class' => '',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'popup_import.submitPopup();'
		]
	],
	'script_inline' => getPagePostJs().
		$this->readJsFile('popup.import.js.php')
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
