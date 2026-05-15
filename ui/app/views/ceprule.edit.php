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
		->addItem((new CTemplateTag('ceprule-condition-modal-template'))->addItem(
			new CPartial('ceprule.modal.condition')
		))
		->addItem(new CPartial('ceprule.filter', [ // TODO: rename ceprule.filter => ceprule.conditions
			'id' => $id_filter,
			'filter' => $data['ceprule']['filter']
		]))

		->addItem(new CLabel(_('Time window'), 'ceprule-time-window'))
		->addItem(new CFormField((new CRadioButtonList('window_type', (int) $data['ceprule']['window_type']))
			->setId('ceprule-time-window')
			->addValue(_('None'), ZBX_CEP_WINDOW_NONE)
			->addValue(_('Simple'), ZBX_CEP_WINDOW_SIMPLE)
			->addValue(_('Cause and symptoms grouping'), ZBX_CEP_WINDOW_CAUSE_SYMPTOM)
			->addValue(_('Tag correlation'), ZBX_CEP_WINDOW_TAG_MATCH)
			->addValue(_('Event pattern match'), ZBX_CEP_WINDOW_PATTERN_MATCH)
			->setModern(true)
		))

		/* ->addItem(new CPartial('ceprule.window', [ */
		/* 	'id' => $id_timewindow, */
		/* 	'type' => $data['ceprule']['window_type'], */
		/* 	'window' => $data['ceprule']['window'] */
		/* ])) */

		->addItem((new CTemplateTag('ceprule-operation-modal-template'))->addItem(
			new CPartial('ceprule.modal.operation')
		))
		->addItem((new CLabel('Operations'))->setAsteriskMark())
		->addItem((new CFormField(
			(new CTable())
				->setColumns([
					(new CTableColumn(new CColHeader('')))->setAttribute('width', '35px'),
					(new CTableColumn(new CColHeader(_('Details')))),
					(new CTableColumn(new CColHeader(''))),
				])
				->addClass('list-numbered')
				->setAttribute('data-field-type', 'set')
				->setAttribute('data-field-name', 'operations')
				->setId('ceprule-operations-table')
				->addItem(
					(new CTag('tfoot', true))
						->addItem(
							(new CCol(
								(new CButtonLink(_('Add')))->addClass('js-operation-add')
							))->setColSpan(3)
						)
				)
			))->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		)
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


// Enable form submitting on Enter.
$form->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN));

$output = [
	'header' => $data['ceprule']['cep_ruleid'] === null ? _('New complex event processing') : _('Complex event processing'),
	'doc_url' => CDocHelper::getUrl(CDocHelper::DATA_COLLECTION_CEPRULE_EDIT),
	'body' => $form->toString(),
	'buttons' => $buttons,
	'script_inline' => $this->readJsFile('ceprule.condition.edit.js.php')
		.$this->readJsFile('ceprule.operation.edit.js.php')
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
