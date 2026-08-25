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
 * @var CPartial $this
 * @var array    $data
 */

$events_operations = new CSelectOptionGroup(_('Event'));
$tags_operations = new CSelectOptionGroup(_('Tag'));
$window_operations = new CSelectOptionGroup(_('Window'));
$labels = CCepRuleHelper::getOperationLabelStrings();

$events_group_opts = [CCepRuleHelper::OP_SET_NAME, CCepRuleHelper::OP_CLOSE_EVENT, CCepRuleHelper::OP_DISCARD,
	CCepRuleHelper::OP_SET_SEVERITY, CCepRuleHelper::OP_INCREASE_SEVERITY, CCepRuleHelper::OP_DECREASE_SEVERITY,
	CCepRuleHelper::OP_SUPPRESS, CCepRuleHelper::OP_UNSUPPRESS, CCepRuleHelper::OP_CLONE_LAST,
	CCepRuleHelper::OP_CLONE_FIRST
];

foreach ($labels as $option => $label) {
	$optgroupid = 'optgroup_tags';
	$optgroup = $tags_operations;

	if (in_array($option, $events_group_opts)) {
		$optgroupid = 'optgroup_events';
		$optgroup = $events_operations;
	}
	else if ($option == CCepRuleHelper::OP_CLOSE_WINDOW) {
		$optgroupid = 'optgroup_window';
		$optgroup = $window_operations;
	}

	$optgroup->addOption((new CSelectOption($option, $label))->setExtra('optgroupid', $optgroupid));
}

(new CForm())
	// Enable form submitting on Enter.
	->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN))
	->addVar('sortorder', '0')
	->addVar('window_type', '0')
	->addClass(ZBX_STYLE_DISPLAY_NONE)
	->addItem((new CFormGrid())
		->addItem((new CTemplateTag('ceprule-operation-condition-row-template'))->addItem([
			(new CRow(['#{formulaid}', '#{*description_html}',
				[
					(new CButtonLink(_('Edit')))->addClass('js-condition-edit'),
					(new CButtonLink(_('Remove')))->addClass('js-condition-remove'),
					(new CInput('hidden', 'filter[conditions][#{row_index}][type]', '#{type}'))
						->removeId()
						->setAttribute('data-field-type', 'hidden')
						->setErrorLabel(_('Type'))
						->setErrorContainer('operation-conditions-#{row_index}-error-container'),
					(new CInput('hidden', 'filter[conditions][#{row_index}][operator]', '#{operator}'))
						->removeId()
						->setAttribute('data-field-type', 'hidden')
						->setErrorLabel(_('Operator'))
						->setErrorContainer('operation-conditions-#{row_index}-error-container'),
					(new CInput('hidden', 'filter[conditions][#{row_index}][tag]', '#{tag}'))
						->removeId()
						->setAttribute('data-field-type', 'hidden')
						->setErrorLabel(_('Tag'))
						->setErrorContainer('operation-conditions-#{row_index}-error-container'),
					(new CInput('hidden', 'filter[conditions][#{row_index}][tag_name]', '#{tag_name}'))
						->removeId()
						->setAttribute('data-field-type', 'hidden')
						->setErrorLabel(_('Tag'))
						->setErrorContainer('operation-conditions-#{row_index}-error-container'),
					(new CInput('hidden', 'filter[conditions][#{row_index}][tag_value]', '#{tag_value}'))
						->removeId()
						->setAttribute('data-field-type', 'hidden')
						->setErrorLabel(_('Tag value'))
						->setErrorContainer('operation-conditions-#{row_index}-error-container'),
					(new CVar('filter[conditions][#{row_index}][formulaid]', '#{formulaid}'))->removeId(),
					(new CVar('filter[conditions][#{row_index}][row_index]', '#{row_index}'))->removeId()
				]
			]))->setAttribute('data-row_index', '#{row_index}'),
			(new CRow(
				(new CCol())
					->setColSpan(3)
					->addClass(ZBX_STYLE_ERROR_CONTAINER)
					->setId('operation-conditions-#{row_index}-error-container')
			))
		]))
		->addItem((new CTemplateTag('ceprule-operation-condition-modal-template'))->addItem(
			new CPartial('ceprule.modal.operation.condition')
		))
		->addItem(new CLabel(_('Execute when'), 'ceprule-operation-execute-when-label'))
		->addItem(new CFormField(
			(new CSelect('execute_when'))
				->setId('ceprule-operation-execute-when')
				->setAttribute('autofocus', '')
				->setFocusableElementId('ceprule-operation-execute-when-label')
				->addOptions(CSelect::createOptionsFromArray(
					CCepRuleHelper::getOperationExecuteWhenStrings()
				))
		))
		->addItem(new CLabel(_('Type of calculation'), 'ceprule-operation-filter-evaltype-select'))
		->addItem(new CFormField([
			(new CDiv(
				(new CSelect('filter[evaltype]'))
					->setId('ceprule-operation-filter-evaltype')
					->setFocusableElementId('ceprule-operation-filter-evaltype-select')
					->addOptions(CSelect::createOptionsFromArray([
						CONDITION_EVAL_TYPE_AND_OR => _('And/Or'),
						CONDITION_EVAL_TYPE_AND => _('And'),
						CONDITION_EVAL_TYPE_OR => _('Or'),
						CONDITION_EVAL_TYPE_EXPRESSION => _('Custom expression')
					]))
					->addClass(ZBX_STYLE_FORM_INPUT_MARGIN)
			))->addClass(ZBX_STYLE_CELL),
			(new CDiv([
				(new CSpan())->setId('ceprule-operation-filter-expression-preview'),
				(new CTextAreaFlexible('filter[formula]'))
					->setMaxlength(DB::getFieldLength('cep_rule', 'formula'))
					->setId('ceprule-operation-filter-expression')
					->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
					->setAttribute('placeholder', 'A or (B and C) ...')
			]))->addClass(ZBX_STYLE_CELL)
		]))
		->addItem(new CLabel(_('Conditions'), 'ceprule-operation-filter-conditions'))
		->addItem((new CFormField())
			->addItem((new CDiv())
				->setAttribute('data-field-type', 'set')
				->setAttribute('data-field-name', 'filter[conditions]')
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->addItem((new CTable())
					->setColumns([
						new CTableColumn(_('Label')),
						(new CTableColumn(_('Name')))
							->setAttribute('width', ZBX_TEXTAREA_BIG_WIDTH.'px'),
						new CTableColumn(_('Actions'))
					])
					->setId('ceprule-operation-filter-conditions')
					->addItem((new CTag('tfoot', true))
						->addItem((new CCol(
							(new CButtonLink(_('Add')))->addClass('js-condition-add')
						))->setColSpan(4))
					)
				)
			)
		)
		->addItem(new CLabel(_('Operation'), 'ceprule-operation-type-label'))
		->addItem((new CFormField())
			->addItem((new CSelect('type'))
				->setFocusableElementId('ceprule-operation-type-label')
				->setId('ceprule-operation-type')
				->addOptionGroup($events_operations)
				->addOptionGroup($tags_operations)
				->addOptionGroup($window_operations)
			)
			->addItem(new CObject('&nbsp;'))
			->addItem((new CTextAreaFlexible('event_name'))
				->setMaxlength(DB::getFieldLength('cep_operation', 'event_name'))
				->setAttribute('placeholder', 'name')
			)
			->addItem((new CTextAreaFlexible('tag'))
				->setMaxlength(DB::getFieldLength('cep_operation', 'tag'))
				->setAttribute('placeholder', 'tag')
			)
			->addItem((new CTextAreaFlexible('suppress_duration'))
				->setMaxlength(DB::getFieldLength('cep_operation', 'suppress_duration'))
			)
		)
		->addItem(
			(new CFormField([
				new CHorList([
					(new CTextAreaFlexible('old_tag'))
						->setMaxlength(DB::getFieldLength('cep_operation', 'tag'))
						->setAttribute('placeholder', _('old name'))
						->setErrorLabel(_('old name'))
						->setErrorContainer('ceprule-operation-tag-rename-error-container'),
					(new CTextAreaFlexible('new_tag'))
						->setMaxlength(DB::getFieldLength('cep_operation', 'new_tag'))
						->setAttribute('placeholder', _('new name'))
						->setErrorLabel(_('new name'))
						->setErrorContainer('ceprule-operation-tag-rename-error-container')
				]),
				(new CDiv())
					->setId('ceprule-operation-tag-rename-error-container')
					->addClass(ZBX_STYLE_ERROR_CONTAINER)
			]))
				->setId('ceprule-operation-tag-rename-argument')
		)
		->addItem(
			(new CFormField([
				new CHorList([
					(new CTextAreaFlexible('tag_name'))
						->setMaxlength(DB::getFieldLength('cep_operation', 'tag'))
						->setAttribute('placeholder', _('tag'))
						->setErrorContainer('ceprule-operation-tag-pair-error-container'),
					(new CTextAreaFlexible('tag_value'))
						->setMaxlength(DB::getFieldLength('cep_operation', 'tag_value'))
						->setAttribute('placeholder', _('value'))
						->setErrorContainer('ceprule-operation-tag-pair-error-container')
				]),
				(new CDiv())
					->setId('ceprule-operation-tag-pair-error-container')
					->addClass(ZBX_STYLE_ERROR_CONTAINER)
			]))
				->setId('ceprule-operation-tag-pair-argument')
		)
		->addItem((new CFormField())
			->setId('ceprule-operation-severity-argument')
			->addItem(new CSeverity('severity', TRIGGER_SEVERITY_INFORMATION))
		)
	)
	->show();
