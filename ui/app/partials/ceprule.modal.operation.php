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
		->addItem((new CTemplateTag('ceprule-operation-condition-tag-template'))
			->addItem((new CRow())
				->setAttribute('data-row_index', '#{row_index}')
				->addItem((new CCol(_('Tag')))
					->addClass('js-filter-tag-label')
					->addClass(ZBX_STYLE_RIGHT)
					->addClass(ZBX_STYLE_VISIBILITY_HIDDEN)
				)
				->addItem((new CCol())
					->addItem((new CTextAreaFlexible('filter[conditions][#{row_index}][tag]', '#{tag}'))
						->setMaxlength(DB::getFieldLength('cep_operation_condition', 'tag'))
						->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
						->setAttribute('placeholder', _('tag'))
						->setErrorContainer('operation-conditions-#{row_index}-error-container')
					)
				)
				->addItem((new CCol())
					->addItem((new CSelect('filter[conditions][#{row_index}][operator]'))
						->setValue('#{operator}')
						->addOptions(CSelect::createOptionsFromArray(
							CCepRuleHelper::getTagOperators()
						))
					)
					->addItem((new CCol())
						->addItem((new CTextAreaFlexible('filter[conditions][#{row_index}][value]', '#{value}'))
						->setMaxlength(DB::getFieldLength('cep_operation_condition', 'tag_value'))
							->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
							->setAttribute('placeholder', _('value'))
							->setErrorContainer('operation-conditions-#{row_index}-error-container')
						)
					)
					->addItem((new CCol())
						->addItem((new CButtonLink(_('Remove')))->addClass('js-tag-remove'))
						->addItem((new CVar('filter[conditions][#{row_index}][type]', '#{type}')))
					)
				)
			)
		)
		->addItem((new CTemplateTag('ceprule-operation-condition-property-template'))
			->addItem((new CRow())
				->setAttribute('data-row_index', '#{row_index}')
				->addItem((new CCol(_('Property')))
					->addClass('js-filter-property-label')
					->addClass(ZBX_STYLE_RIGHT)
					->addClass(ZBX_STYLE_VISIBILITY_HIDDEN)
				)
				->addItem((new CCol())
					->addItem((new CSelect('filter[conditions][#{row_index}][type]'))
						->addClass('js-property-type-select')
						->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
						->setValue('#{type}')
						->setErrorContainer('operation-conditions-#{row_index}-error-container')
						->addOptions(CSelect::createOptionsFromArray([
							ZBX_CONDITION_TYPE_EVENT_OPEN => _('Problem opened'),
							ZBX_CONDITION_TYPE_EVENT_SYMPTOM => _('Symptom'),
							ZBX_CONDITION_TYPE_EVENT_FIRST => _('First'),
							ZBX_CONDITION_TYPE_EVENT_LAST => _('Last'),
							ZBX_CONDITION_TYPE_EVENT_SUPPRESSED => _('Supressed'),
							ZBX_CONDITION_TYPE_EVENT_COPIED => _('Cloned')
						]))
					)
				)
				->addItem(new CCol(_('Equals')))
				->addItem((new CCol())
					->addItem((new CRadioButtonList('filter[conditions][#{row_index}][operator]', CONDITION_OPERATOR_YES))
						->addValue(_('True'), CONDITION_OPERATOR_YES)
						->addValue(_('False'), CONDITION_OPERATOR_NO)
						->setModern()
					)
				)
				->addItem((new CCol())
					->addItem((new CButtonLink(_('Remove')))->addClass('js-property-remove'))
				)
			)
		)
		->addItem((new CTemplateTag('ceprule-operation-condition-error-row-template'))
			->addItem((new CRow())
				->addItem((new CCol()))
				->addItem((new CCol())
					->setColSpan(4)
					->addClass(ZBX_STYLE_ERROR_CONTAINER)
					->setId('operation-conditions-#{row_index}-error-container')
				)
			)
		)
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
		->addItem(new CLabel('Event tag/property filter'))
		->addItem(new CFormField((new CRadioButtonList('filter[evaltype]', TAG_EVAL_TYPE_AND_OR))
			->addValue(_('And/Or'), TAG_EVAL_TYPE_AND_OR)
			->addValue(_('Or'), TAG_EVAL_TYPE_OR)
			->setModern(true)
		))
		->addItem(new CFormField(
			(new CTable())
				->addClass(ZBX_STYLE_TABLE_INITIAL_WIDTH)
				->setId('ceprule-operation-filter-table')
				->setHeader(['', 'Name', 'Type', 'Value', ''])
				->setAttribute('data-field-type', 'set')
				->setAttribute('data-field-name', 'filter[conditions]')
				->addItem((new CTag('tfoot', true))->addItem((new CCol([
					(new CButtonLink(_('Add tag')))->addClass('js-add-tag'),
					(new CButtonLink(_('Add property')))->addClass('js-add-property')
				]))->setColSpan(4)))
		))
		->addItem(new CLabel('Operation', 'ceprule-operation-type-label'))
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
