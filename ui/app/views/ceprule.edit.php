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

if ($data['ceprule']['cepruleid'] === null) {
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
			'title' => _('Reset time windows'),
			'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-reset-time-windows']),
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
	// Enable form submitting on Enter.
	->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN))
	->addVar('cepruleid', $data['ceprule']['cepruleid'])
	->addClass(ZBX_STYLE_DISPLAY_NONE)
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
		->addItem(new CPartial('ceprule.conditions', [
			'filter' => $data['ceprule']['filter']
		]))

		->addItem(new CPartial('ceprule.window', [
			'window_type' => $data['ceprule']['window_type'],
			'window' => $data['ceprule']['window']
		]))

		->addItem((new CTemplateTag('ceprule-operation-modal-template'))->addItem(
			new CPartial('ceprule.modal.operation')
		))
		->addItem((new CTemplateTag('ceprule-condition-row-template'))->addItem(
			(new CRow(['#{formulaid}', '#{*description_html}',
				[
					(new CButtonLink(_('Edit')))->addClass('js-condition-edit'),
					(new CButtonLink(_('Remove')))->addClass('js-condition-remove'),
					(new CVar('filter[conditions][#{row_index}][type]', '#{type}'))->removeId(),
					(new CVar('filter[conditions][#{row_index}][operator]', '#{operator}'))->removeId(),
					(new CVar('filter[conditions][#{row_index}][severity]', '#{severity}'))->removeId(),
					(new CVar('filter[conditions][#{row_index}][event_name]', '#{event_name}'))->removeId(),
					(new CVar('filter[conditions][#{row_index}][tag_operator]', '#{tag_operator}'))->removeId(),
					(new CVar('filter[conditions][#{row_index}][tag]', '#{tag}'))->removeId(),
					(new CVar('filter[conditions][#{row_index}][tag_value]', '#{tag_value}'))->removeId(),
					(new CVar('filter[conditions][#{row_index}][host]', '#{host}'))->removeId(),
					(new CVar('filter[conditions][#{row_index}][host_group]', '#{host_group}'))->removeId(),
					(new CVar('filter[conditions][#{row_index}][time_period]', '#{time_period}'))->removeId(),
					(new CVar('filter[conditions][#{row_index}][formulaid]', '#{formulaid}'))->removeId(),
					(new CVar('filter[conditions][#{row_index}][row_index]', '#{row_index}'))->removeId()
				]
			]))->setAttribute('data-row_index', '#{row_index}')
		))
		->addItem((new CTemplateTag('ceprule-operation-row-template'))->addItem(
			(new CRow([
				(new CCol([
					(new CDiv())->addClass(ZBX_STYLE_DRAG_ICON),
					(new CSpan(':'))->addClass(ZBX_STYLE_LIST_NUMBERED_ITEM)
				]))->addClass(ZBX_STYLE_TD_DRAG_ICON),
				(new CCol([
					_('Execute when'),
					' #{execute_when_str} : #{label_str}',
					new CTag('em', true, ' #{arguments_str}')
				]))->addClass('text'),
				[
					(new CButtonLink(_('Edit')))->addClass('js-operation-edit'),
					(new CButtonLink(_('Remove')))->addClass('js-operation-remove'),
					'#{*conditions_input_html}',
					(new CVar('operations[#{sortorder}][sortorder]', '#{sortorder}'))->removeId(),
					(new CVar('operations[#{sortorder}][execute_when]', '#{execute_when}'))->removeId(),
					(new CVar('operations[#{sortorder}][type]', '#{type}'))->removeId(),
					(new CVar('operations[#{sortorder}][filter][evaltype]', '#{filter.evaltype}'))->removeId(),
					(new CVar('operations[#{sortorder}][event_name]', '#{event_name}'))->removeId(),
					(new CVar('operations[#{sortorder}][tag]', '#{tag}'))->removeId(),
					(new CVar('operations[#{sortorder}][old_tag]', '#{old_tag}'))->removeId(),
					(new CVar('operations[#{sortorder}][new_tag]', '#{new_tag}'))->removeId(),
					(new CVar('operations[#{sortorder}][tag_name]', '#{tag_name}'))->removeId(),
					(new CVar('operations[#{sortorder}][tag_value]', '#{tag_value}'))->removeId(),
					(new CVar('operations[#{sortorder}][severity]', '#{severity}'))->removeId(),
					(new CVar('operations[#{sortorder}][suppress_duration]', '#{suppress_duration}'))->removeId()
				]
			]))->setAttribute('data-sortorder', '#{sortorder}')
		))
		->addItem((new CLabel('Operations'))->setAsteriskMark()->setId('ceprule-operations-label'))
		->addItem((new CFormField())
			->addItem((new CDiv())
				->setAttribute('data-field-type', 'set')
				->setAttribute('data-field-name', 'operations')
				->setAttribute('data-error-container', 'ceprule-operations-error-container')
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->addItem((new CTable())
					->setId('ceprule-operations-table')
					->addClass('list-numbered')
					->setColumns([
						(new CTableColumn(new CColHeader((new CDiv())
								->addItem(makeWarningIcon(
									_s('Execute when %1$s: There is no "%2$s" operation defined.',
										CCepRuleHelper::getOperationExecuteWhenString([
											'execute_when' => CCepRuleHelper::WHEN_WINDOW_CLOSED
										]),
										CCepRuleHelper::getOperationLabelString([
											'type' => CCepRuleHelper::OP_CLOSE_WINDOW
										])
									)
								))
								->addClass('js-operations-info')
								->addClass(ZBX_STYLE_DISPLAY_NONE)
						))),
						(new CTableColumn(new CColHeader(_('Details'))))
							->setAttribute('width', ZBX_TEXTAREA_BIG_WIDTH.'px'),
						(new CTableColumn(new CColHeader('')))
					])
					->addItem((new CTag('tfoot', true))
						->addItem((new CCol(
							(new CButtonLink(_('Add')))->addClass('js-operation-add')
						))->setColSpan(3))
					)
				)
			)
			->addItem((new CDiv())->setId('ceprule-operations-error-container'))
		)
		->addItem([
			new CLabel(_('Stop processing'), 'stop'),
			new CFormField((new CCheckBox('stop', CCepRuleHelper::EXECUTION_STOP))
				->setChecked($data['ceprule']['stop'] == CCepRuleHelper::EXECUTION_STOP)
				->setUncheckedValue(CCepRuleHelper::EXECUTION_CONTINUE)
			)
		])
		->addItem([
			(new CLabel(_('Sort order'), 'sortorder'))->setAsteriskMark(),
			new CFormField((new CTextBox('sortorder', $data['ceprule']['sortorder'])))
		])
		->addItem([
			new CLabel(_('Description'), 'description'),
			new CFormField(
				(new CTextArea('description', $data['ceprule']['description']))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			)
		])
		->addItem([
			new CLabel(_('Enabled'), 'status'),
			new CFormField(
				(new CCheckBox('status', CCepRuleHelper::STATUS_ENABLED))
					->setChecked($data['ceprule']['status'] == CCepRuleHelper::STATUS_ENABLED)
					->setUncheckedValue(CCepRuleHelper::STATUS_DISABLED)
			)
		])
	);

$output = [
	'header' => $data['ceprule']['cepruleid'] === null
		? _('New complex event processing')
		: _('Complex event processing'),
	'doc_url' => CDocHelper::getUrl(CDocHelper::DATA_COLLECTION_CEPRULE_EDIT),
	'body' => $form->toString(),
	'buttons' => $buttons,
	'script_inline' => getPagePostJs().$this->readJsFile('ceprule.condition.edit.js.php')
		.$this->readJsFile('ceprule.operation.edit.js.php')
		.$this->readJsFile('ceprule.edit.js.php')
		.'ceprule_edit_popup.init('.json_encode([
			'rules' => $data['js_validation_rules'],
			'rules_for_clone' => $data['js_validation_rules_for_clone'],
			'condition_rules' => $data['condition_js_validation_rules'],
			'operation_rules' => $data['operation_js_validation_rules'],
			'operation_types_by_execute_when' => CCepRuleHelper::OPERATION_TYPES_BY_EXECUTE_WHEN,
			'execute_when_by_window_type' => CCepRuleHelper::EXECUTE_WHEN_BY_WINDOW_TYPE,
			'ceprule' => $data['ceprule']
		]).');',
	'dialogue_class' => 'modal-popup-large'
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
