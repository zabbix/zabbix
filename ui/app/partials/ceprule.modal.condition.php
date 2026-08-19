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

(new CForm())
	// Enable form submitting on Enter.
	->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN))
	->addClass(ZBX_STYLE_DISPLAY_NONE)
	->addItem((new CFormGrid())
		->addItem([
			new CLabel('Type', 'ceprule-condition-type-focus'),
			new CFormField(
				(new CSelect('type'))
					->setId('ceprule-condition-type')
					->setAttribute('autofocus', 'autofocus')
					->setFocusableElementId('ceprule-condition-type-focus')
					->addOptions(CSelect::createOptionsFromArray(
						CCepRuleHelper::getConditionLabels()
					))
			)
		])
		->addItem([
			(new CLabel('Tag', 'ceprule-condition-tag-name'))->setAsteriskMark(),
			(new CFormField([
				(new CTextBox('tag'))
					->setId('ceprule-condition-tag-name')
					->setAttribute('placeholder', 'tag'),
				new CObject('&nbsp;'),
				(new CSelect('tag_operator'))
					->setId('ceprule-condition-tag-operator')
					->setFocusableElementId('ceprule-condition-type-focus')
					->addOptions(CSelect::createOptionsFromArray(
						CCepRuleHelper::getTagOperators()
					)),
				new CObject('&nbsp;'),
				(new CTextBox('tag_value'))
					->setId('ceprule-condition-tag-value')
					->setAttribute('placeholder', 'value')
			]))->setAttribute('for-type', CCepRuleHelper::CONDITION_TAG)
		])
		->addItem([
			new CLabel('Operator'),
			new CFormField((new CRadioButtonList('operator'))
				->setId('ceprule-condition-operator')
				->setModern()
				->addValue('Equals', CONDITION_OPERATOR_EQUAL)
				->addValue('Does not equal', CONDITION_OPERATOR_NOT_EQUAL)
				->addValue('Contains', CONDITION_OPERATOR_LIKE)
				->addValue('Does not contain', CONDITION_OPERATOR_NOT_LIKE)
				->addValue('Does not exist', CONDITION_OPERATOR_NOT_EXISTS)
				->addValue('In', CONDITION_OPERATOR_IN)
				->addValue('Not in', CONDITION_OPERATOR_NOT_IN)
				->addValue('Is greater than or equals', CONDITION_OPERATOR_MORE_EQUAL)
				->addValue('Is less than or equals', CONDITION_OPERATOR_LESS_EQUAL)
			)
		])
		->addItem([
			(new CLabel('Event name', 'ceprule-condition-event-name'))->setAsteriskMark(),
			(new CFormField((new CTextAreaFlexible('event_name'))
				->setMaxlength(DB::getFieldLength('cep_condition', 'event_name'))
				->setId('ceprule-condition-event-name')
				->setAttribute('placeholder', 'event name')
			))->setAttribute('for-type', CCepRuleHelper::CONDITION_EVENT_NAME)
		])
		->addItem([
			(new CLabel('Host', 'ceprule-condition-host'))->setAsteriskMark(),
			(new CFormField((new CTextBox('host'))
				->setId('ceprule-condition-host')
				->setAttribute('placeholder', 'host name')
			))->setAttribute('for-type', CCepRuleHelper::CONDITION_HOST)
		])
		->addItem([
			(new CLabel('Host group', 'ceprule-condition-host-group'))->setAsteriskMark(),
			(new CFormField((new CTextBox('host_group'))
				->setId('ceprule-condition-host-group')
				->setAttribute('placeholder', 'host group name')
			))->setAttribute('for-type', CCepRuleHelper::CONDITION_HOST_GROUP)
		])
		->addItem([
			new CLabel('Severity'),
			(new CFormField(new CSeverity('severity')))
				->setAttribute('for-type', CCepRuleHelper::CONDITION_SEVERITY)
		])
		->addItem([
			(new CLabel('Time period', 'ceprule-condition-time-period'))->setAsteriskMark(),
			(new CFormField((new CTextBox('time_period'))
				->setId('ceprule-condition-time-period')
				->setAttribute('placeholder', '1-7,00:00-24:00')
			))
				->setAttribute('for-type', CCepRuleHelper::CONDITION_TIME_PERIOD)
		])
		->addItem(
			(new CInput('hidden', 'formulaid', ''))
				->setAttribute('data-field-type', 'hidden')
				->removeId()
		)
	)
	->show();
