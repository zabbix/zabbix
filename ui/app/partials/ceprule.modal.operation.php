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

$execute_when = new CSelect('execute_when');
foreach (CCepRuleHelper::getOperationExecuteWhenStrings() as $value => $name) {
	$execute_when->addOption(new CSelectOption($value, $name));
}

$events_operations = new CSelectOptionGroup(_('Events'));
$tags_operations = new CSelectOptionGroup(_('Tags'));
$labels = CCepRuleHelper::getOperationLabelStrings();

$events_group_opts = [CCepRuleHelper::OP_SET_NAME, CCepRuleHelper::OP_CLOSE, CCepRuleHelper::OP_DISCARD,
	CCepRuleHelper::OP_SET_SEVERITY, CCepRuleHelper::OP_INCREASE_SEVERITY, CCepRuleHelper::OP_DECREASE_SEVERITY,
	CCepRuleHelper::OP_SUPPRESS, CCepRuleHelper::OP_COPY_LAST, CCepRuleHelper::OP_COPY_FIRST
];

foreach ($labels as $option => $label) {
	$is_events_group = in_array($option, $events_group_opts);
	$option = new CSelectOption($option, $label);
	$option->setExtra('is_events_group', $is_events_group);

	if ($is_events_group) {
		$events_operations->addOption($option);
	}
	else {
		$tags_operations->addOption($option);
	}
}

(new CForm())
	// Enable form submitting on Enter.
	->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN))
	->addVar('sortorder', '0')
	->addVar('window_type', '0')
	->addClass(ZBX_STYLE_DISPLAY_NONE)
	->addItem((new CFormGrid())
		->addItem((new CTemplateTag('ceprule-operation-tag-template'))
			->addItem((new CRow())
				->setAttribute('data-row_index', '#{row_index}')
				->addItem((new CCol())
					->addItem((new CTextBox('tags[#{row_index}][tag]', '#{tag}'))
						->setAttribute('is', 'z-cep-tagsuggest')
						->setAttribute('placeholder', _('tag or $'))
					)
				)
				->addItem((new CCol())
					->addItem((new CSelect('tags[#{row_index}][operator]'))
						->setValue('#{operator}')
						->addOptions(CSelect::createOptionsFromArray([
							TAG_OPERATOR_EXISTS => _('Exists'),
							TAG_OPERATOR_EQUAL => _('Equals'),
							TAG_OPERATOR_LIKE => _('Contains'),
							TAG_OPERATOR_NOT_EXISTS => _('Does not exist'),
							TAG_OPERATOR_NOT_EQUAL => _('Does not equal'),
							TAG_OPERATOR_NOT_LIKE => _('Does not contain')
						]))
				)
				->addItem((new CCol())
					->addItem((new CTextBox('tags[#{row_index}][value]', '#{value}'))
						->setAttribute('placeholder', _('value'))
					)
				)
				->addItem((new CCol())
					->addItem((new CButtonLink(_('Remove')))->addClass('js-tag-remove'))
				))
			)
		)
		->addItem(new CLabel(_('Execute when'), 'ceprule-operation-execute-when-label'))
		->addItem(new CFormField(
			$execute_when
				->setAttribute('autofocus', '')
				->setFocusableElementId('ceprule-operation-execute-when-label')
				->setId('ceprule-operation-execute-when')
		))
		->addItem(new CLabel('Event tags'))
		->addItem(new CFormField((new CRadioButtonList('evaltype', TAG_EVAL_TYPE_AND_OR))
			->addValue(_('And/Or'), TAG_EVAL_TYPE_AND_OR)
			->addValue(_('Or'), TAG_EVAL_TYPE_OR)
			->setModern(true)
		))
		->addItem(new CFormField(
			(new CTable())
				->addClass(ZBX_STYLE_TABLE_INITIAL_WIDTH)
				->setId('ceprule-operation-tags-table')
				->setAttribute('data-field-type', 'set')
				->setAttribute('data-field-name', 'tags')
				->addItem((new CTag('tfoot', true))->addItem((new CCol(
					(new CButtonLink(_('Add')))->addClass('js-tag-add')
				))->setColSpan(4)))
		))
		->addItem(new CLabel('Operation', 'ceprule-operation-type-label'))
		->addItem((new CFormField())
			->addItem((new CSelect('type'))
				->setFocusableElementId('ceprule-operation-type-label')
				->setId('ceprule-operation-type')
				->addOptionGroup($events_operations)
				->addOptionGroup($tags_operations)
			)
			->addItem(new CObject('&nbsp;'))
			->addItem((new CTextBox('event_name'))
				->setId('ceprule-operation-name-argument')
				->setAttribute('placeholder', 'name')
			)
			->addItem((new CTextBox('tag'))
				->setId('ceprule-operation-tag-argument')
				->setAttribute('placeholder', 'tag')
			)
			->addItem((new CDateSelector('suppress_duration'))
				->setId('ceprule-operation-period-argument')
				->setDateFormat(ZBX_DATE_TIME)
				->setPlaceholder(_('YYYY-MM-DD hh:mm'))
				->setAriaRequired()
			)
		)
		->addItem((new CFormField())
			->setId('ceprule-operation-tag-rename-argument')
			->addItem((new CTextBox('tag'))->setAttribute('placeholder', _('old name')))
			->addItem(new CObject('&nbsp;'))
			->addItem((new CTextBox('new_tag'))->setAttribute('placeholder', _('new name')))
		)
		->addItem((new CFormField())
			->setId('ceprule-operation-tag-pair-argument')
			->addItem((new CTextBox('tag'))->setAttribute('placeholder', _('tag')))
			->addItem(new CObject('&nbsp;'))
			->addItem((new CTextBox('tag_value'))->setAttribute('placeholder', _('value')))
		)
		->addItem((new CFormField())
			->setId('ceprule-operation-severity-argument')
			->addItem(new CSeverity('severity', TRIGGER_SEVERITY_INFORMATION))
		)
	)
	->show();
