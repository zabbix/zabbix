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

$operators = array_intersect_key(CCepRuleHelper::getConditionOperatorLabels(), array_flip([
	CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE, CONDITION_OPERATOR_NOT_LIKE,
	CONDITION_OPERATOR_MORE_EQUAL, CONDITION_OPERATOR_LESS_EQUAL, CONDITION_OPERATOR_YES, CONDITION_OPERATOR_NO
]));
$operator_values = [];
foreach ($operators as $value => $name) {
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
						CCepRuleHelper::getOperationConditionLabels()
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
		->addItem(
			(new CInput('hidden', 'formulaid', ''))
				->setAttribute('data-field-type', 'hidden')
				->removeId()
		)
		->addItem(
			(new CInput('hidden', 'execute_when', ''))
				->setAttribute('data-field-type', 'hidden')
				->removeId()
		)
	)
	->show();
