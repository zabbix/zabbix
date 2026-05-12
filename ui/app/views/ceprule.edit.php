<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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

$id = 'cep';
$id_filter = "$id-filter";
$id_timewindow = $id.'-timewindow';
$id_operations = "$id-operations";

if ($data['ceprule']['cep_ruleid'] === null) {
	$buttons = [
		[
			'title' => _('Add'),
			'class' => 'js-submit',
			'keepOpen' => true,
			'isSubmit' => true
		]
	];
}
else {
	$buttons = [
		[
			'title' => _('Update'),
			'class' => 'js-submit',
			'keepOpen' => true,
			'isSubmit' => true
		],
		[
			'title' => _('Clone'),
			'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-clone']),
			'keepOpen' => true,
			'isSubmit' => false
		],
		[
			'title' => _('Delete'),
			'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-delete']),
			'keepOpen' => true,
			'isSubmit' => false
		]
	];
}

$form = (new CForm())
	->addVar('cepruleid', $data['ceprule']['cep_ruleid'] ?? null)
	->setId($id)
	->addStyle('display: none;')
	->addItem((new CFormGrid())
		->addItem([
			(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
			new CFormField(
				(new CTextBox('name', $data['ceprule']['name']))
					->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
					->setAriaRequired()
					->setAttribute('autofocus', 'autofocus')
			)
		])
		->addItem((new CTemplateTag('cep-filter-condition-modal-template'))->addItem(
			new CPartial('ceprule.modal.condition', ['id' => '$id_modal', 'id_template' => '$id_modal_template'])
		))
		->addItem(new CPartial('ceprule.filter', [
			'id' => $id_filter,
			'filter' => $data['ceprule']['filter']
		]))
		/* ->addItem(new CPartial('ceprule.window', [ */
		/* 	'id' => $id_timewindow, */
		/* 	'type' => $data['ceprule']['window_type'], */
		/* 	'window' => $data['ceprule']['window'] */
		/* ])) */
		/* ->addItem(new CPartial('ceprule.operations', [ */
		/* 	'id' => $id_operations, */
		/* 	'operations' => $data['ceprule']['operations'] */
		/* ])) */
		/* ->addItem([ */
		/* 	new CLabel(_('Stop processing'), 'stop'), */
		/* 	new CFormField((new CCheckBox('stop')) */
		/* 		->setChecked($data['ceprule']['stop'] == ZBX_CEP_EXECUTION_STOP) */
		/* 		->setUncheckedValue(ZBX_CEP_EXECUTION_CONTINUE) */
		/* 	) */
		/* ]) */
		/* ->addItem([ */
		/* 	(new CLabel(_('Sort order'), 'sortorder'))->setAsteriskMark(), */
		/* 	new CFormField((new CTextBox('sortorder', $data['ceprule']['sortorder']))) */
		/* ]) */
		/* ->addItem([ */
		/* 	new CLabel(_('Description'), 'description'), */
		/* 	new CFormField( */
		/* 		(new CTextArea('description', $data['ceprule']['description'])) */
		/* 			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH) */
		/* 	) */
		/* ]) */
		/* ->addItem([ */
		/* 	new CLabel(_('Enabled'), 'status'), */
		/* 	new CFormField( */
		/* 		(new CCheckBox('status', ZBX_CEP_STATUS_ENABLED)) */
		/* 			->setChecked($data['ceprule']['status'] == ZBX_CEP_STATUS_ENABLED) */
		/* 			->setUncheckedValue(ZBX_CEP_STATUS_DISABLED) */
		/* 	) */
		/* ]) */
	);


$output = [
	'header' => $data['ceprule']['cep_ruleid'] === null ? _('New complex event processing') : _('Complex event processing'),
	'doc_url' => CDocHelper::getUrl(CDocHelper::DATA_COLLECTION_CEPRULE_EDIT),
	'body' => $form->toString(),
	'buttons' => $buttons,
	'script_inline' => $this->readJsFile('ceprule.condition.edit.js.php')
		.$this->readJsFile('ceprule.edit.js.php')
		.'ceprule_edit_popup.init('.json_encode([
			'rules' => $data['js_validation_rules'],
			'condition_rules' => $data['condition_js_validation_rules'],
			'operation_rules' => $data['operation_js_validation_rules'],
			'ceprule' => $data['ceprule']
		]).');',
	'dialogue_class' => 'modal-popup-large'
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
