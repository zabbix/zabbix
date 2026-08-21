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

$operator_values = [];
foreach (CCepRuleHelper::getConditionOperatorLabels() as $value => $name) {
	$operator_values[] = [
		'value' => $value,
		'name' => $name
	];
}

(new CForm())
	// Enable form submitting on Enter.
	->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN))
	->addClass(ZBX_STYLE_DISPLAY_NONE)
	->addItem((new CFormGrid())
		->addItem([
			new CLabel(_('Type'), 'ceprule-condition-type-focus'),
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
			(new CLabel(_('Tag'), 'ceprule-condition-tag-name'))->setAsteriskMark(),
			(new CFormField((new CTextAreaFlexible('tag_name'))
				->setMaxlength(DB::getFieldLength('cep_condition', 'tag'))
				->setId('ceprule-condition-tag-name')
				->setAttribute('placeholder', 'tag')
			))->setAttribute('for-type', CCepRuleHelper::CONDITION_TAG_VALUE)
		])
		->addItem([
			new CLabel(_('Operator'), 'ceprule-condition-operator'),
			new CFormField((new CRadioButtonList('operator'))
				->setId('ceprule-condition-operator')
				->setValues($operator_values)
				->setModern()
			)
		])
		->addItem([
			(new CLabel(_('Tag'), 'ceprule-condition-tag'))->setAsteriskMark(),
			(new CFormField((new CTextAreaFlexible('tag'))
				->setMaxlength(DB::getFieldLength('cep_condition', 'tag'))
				->setId('ceprule-condition-tag')
				->setAttribute('placeholder', 'tag')
			))->setAttribute('for-type', CCepRuleHelper::CONDITION_TAG)
		])
		->addItem([
			(new CLabel(_('Tag value'), 'ceprule-condition-tag-value')),
			(new CFormField((new CTextAreaFlexible('tag_value'))
				->setMaxlength(DB::getFieldLength('cep_condition', 'tag_value'))
				->setId('ceprule-condition-tag-value')
				->setAttribute('placeholder', 'value')
			))->setAttribute('for-type', CCepRuleHelper::CONDITION_TAG_VALUE)
		])
		->addItem([
			(new CLabel(_('Event name'), 'ceprule-condition-event-name'))->setAsteriskMark(),
			(new CFormField((new CTextAreaFlexible('event_name'))
				->setMaxlength(DB::getFieldLength('cep_condition', 'event_name'))
				->setId('ceprule-condition-event-name')
				->setAttribute('placeholder', 'event name')
			))->setAttribute('for-type', CCepRuleHelper::CONDITION_EVENT_NAME)
		])
		->addItem([
			(new CLabel(_('Host'), 'ceprule-condition-host'))->setAsteriskMark(),
			(new CFormField((new CTextAreaFlexible('host'))
				->setId('ceprule-condition-host')
				->setAttribute('placeholder', 'host name')
			))->setAttribute('for-type', CCepRuleHelper::CONDITION_HOST)
		])
		->addItem([
			(new CLabel(_('Host group'), 'ceprule-condition-host-group'))->setAsteriskMark(),
			(new CFormField((new CTextAreaFlexible('host_group'))
				->setId('ceprule-condition-host-group')
				->setAttribute('placeholder', 'host group name')
			))->setAttribute('for-type', CCepRuleHelper::CONDITION_HOST_GROUP)
		])
		->addItem([
			new CLabel(_('Severity')),
			(new CFormField(new CSeverity('severity')))
				->setAttribute('for-type', CCepRuleHelper::CONDITION_SEVERITY)
		])
		->addItem([
			(new CLabel(_('Time period'), 'ceprule-condition-time-period'))->setAsteriskMark(),
			(new CFormField((new CTextAreaFlexible('time_period'))
				->setId('ceprule-condition-time-period')
				->setAttribute('placeholder', '1-7,00:00-24:00')
			))->setAttribute('for-type', CCepRuleHelper::CONDITION_TIME_PERIOD)
		])
		->addItem(
			(new CInput('hidden', 'formulaid', ''))
				->setAttribute('data-field-type', 'hidden')
				->removeId()
		)
	)
	->show();
