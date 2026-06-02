<?php declare(strict_types = 0);
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
 * @var CPartial $this
 * @var array    $data
 */

$condition_type = (new CSelect('type'))
	->setId('ceprule-condition-type')
	->setFocusableElementId('ceprule-condition-type-focus');

foreach (CCepRuleHelper::getConditionLabelStrings() as $value => $name) {
	$condition_type->addOption(new CSelectOption($value, $name));
}

echo (new CForm())
	// Enable form submitting on Enter.
	->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN))
	->addItem((new CFormGrid())
		->addItem([
			new CLabel('Type', 'ceprule-condition-type-focus'),
			new CFormField($condition_type)
		])
		->addItem(
			(new CTag('template', true))
				->setAttribute('for-type', CCepRuleHelper::CONDITION_EVENT_NAME)
				->addItem([
					new CLabel('Operator'),
					new CFormField(
						(new CRadioButtonList('operator', CONDITION_OPERATOR_EQUAL))
							->setModern()
							->addValue('Equals', CONDITION_OPERATOR_EQUAL)
							->addValue('Does not equal', CONDITION_OPERATOR_NOT_EQUAL)
							->addValue('Contains', CONDITION_OPERATOR_LIKE)
							->addValue('Does not contain', CONDITION_OPERATOR_NOT_LIKE)
					),
					new CLabel('Event name', 'ceprule-condition-event-name'),
					new CFormField(
						(new CTextBox('event_name'))
							->setId('ceprule-condition-event-name')
							->setAttribute('placeholder', 'event name')
					)
				])
		)
		->addItem(
			(new CTag('template', true))
				->setAttribute('for-type', CCepRuleHelper::CONDITION_HOST)
				->addItem([
					new CLabel('Operator'),
					new CFormField(
						(new CRadioButtonList('operator', CONDITION_OPERATOR_EQUAL))
							->setModern()
							->addValue('Equals', CONDITION_OPERATOR_EQUAL)
							->addValue('Does not equal', CONDITION_OPERATOR_NOT_EQUAL)
							->addValue('Contains', CONDITION_OPERATOR_LIKE)
							->addValue('Does not contain', CONDITION_OPERATOR_NOT_LIKE)
					),
					new CLabel('Host', 'ceprule-condition-host'),
					new CFormField(
						(new CTextBox('host'))
							->setId('ceprule-condition-host')
							->setAttribute('placeholder', 'host name')
					)
				])
		)
		->addItem(
			(new CTag('template', true))
				->setAttribute('for-type', CCepRuleHelper::CONDITION_HOST_GROUP)
				->addItem([
					new CLabel('Operator'),
					new CFormField(
						(new CRadioButtonList('operator', CONDITION_OPERATOR_EQUAL))
							->setModern()
							->addValue('Equals', CONDITION_OPERATOR_EQUAL)
							->addValue('Does not equal', CONDITION_OPERATOR_NOT_EQUAL)
							->addValue('Contains', CONDITION_OPERATOR_LIKE)
							->addValue('Does not contain', CONDITION_OPERATOR_NOT_LIKE)
					),
					new CLabel('Host group', 'ceprule-condition-host-group'),
					new CFormField(
						(new CTextBox('host_group'))
							->setId('ceprule-condition-host-group')
							->setAttribute('placeholder', 'host group name')
					)
				])
		)
		->addItem(
			(new CTag('template', true))
				->setAttribute('for-type', CCepRuleHelper::CONDITION_TAG_NAME)
				->addItem([
					new CLabel('Operator'),
					new CFormField(
						(new CRadioButtonList('operator', CONDITION_OPERATOR_EQUAL))
							->setModern()
							->addValue('Equals', CONDITION_OPERATOR_EQUAL)
							->addValue('Does not equal', CONDITION_OPERATOR_NOT_EQUAL)
							->addValue('Contains', CONDITION_OPERATOR_LIKE)
							->addValue('Does not contain', CONDITION_OPERATOR_NOT_LIKE)
							->addValue('Does not exist', CONDITION_OPERATOR_NOT_EXISTS)
					),
					new CLabel('Tag', 'ceprule-condition-tag'),
					new CFormField(
						(new CTextBox('tag'))
							->setId('ceprule-condition-tag')
							->setAttribute('placeholder', 'tag')
					)
				])
		)
		->addItem(
			(new CTag('template', true))
				->setAttribute('for-type', CCepRuleHelper::CONDITION_TAG_VALUE)
				->addItem([
					new CLabel('Operator'),
					new CFormField(
						(new CRadioButtonList('operator', CONDITION_OPERATOR_EQUAL))
							->setModern()
							->addValue('Equals', CONDITION_OPERATOR_EQUAL)
							->addValue('Does not equal', CONDITION_OPERATOR_NOT_EQUAL)
							->addValue('Contains', CONDITION_OPERATOR_LIKE)
							->addValue('Does not contain', CONDITION_OPERATOR_NOT_LIKE)
							->addValue('is greater than or equals', CONDITION_OPERATOR_MORE_EQUAL)
							->addValue('is less than or equals', CONDITION_OPERATOR_LESS_EQUAL)
					),
					new CLabel('Tag value', 'ceprule-condition-tag-value'),
					new CFormField(
						(new CTextBox('tag_value'))
							->setId('ceprule-condition-tag-value')
							->setAttribute('placeholder', 'tag value')
					)
				])
		)
		->addItem(
			(new CTag('template', true))
				->setAttribute('for-type', CCepRuleHelper::CONDITION_SEVERITY)
				->addItem([
					new CLabel('Operator'),
					new CFormField(
						(new CRadioButtonList('operator', CONDITION_OPERATOR_EQUAL))
							->setModern()
							->addValue('Equals', CONDITION_OPERATOR_EQUAL)
							->addValue('Does not equal', CONDITION_OPERATOR_NOT_EQUAL)
							->addValue('is greater than or equals', CONDITION_OPERATOR_MORE_EQUAL)
							->addValue('is less than or equals', CONDITION_OPERATOR_LESS_EQUAL)
					),
					new CLabel('Severity'),
					new CFormField(
						new CSeverity('severity')
					)
				])
		)
		->addItem(
			(new CTag('template', true))
				->setAttribute('for-type', CCepRuleHelper::CONDITION_TIME_PERIOD)
				->addItem([
					new CLabel('Operator'),
					new CFormField(
						(new CRadioButtonList('operator', CONDITION_OPERATOR_IN))
							->setModern()
							->addValue('In', CONDITION_OPERATOR_IN)
							->addValue('Not in', CONDITION_OPERATOR_NOT_IN)
					),
					new CLabel('Time period', 'ceprule-condition-time-period'),
					new CFormField(
						(new CTextBox('time_period'))
							->setId('ceprule-condition-time-period')
							->setAttribute('placeholder', '1-7,00:00-24:00')
					)
				])
		)
		->addItem(
			(new CInput('hidden', 'formulaid', ''))
				->setAttribute('data-field-type', 'hidden')
				->removeId()
		)
	);
