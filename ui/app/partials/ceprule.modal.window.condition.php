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

$condition_type = (new CSelect('type'))
	->setId('ceprule-window-condition-type')
	->setFocusableElementId('ceprule-window-condition-type-label');

foreach (CCepRuleHelper::getWindowConditionLabelStrings() as $value => $name) {
	$condition_type->addOption(new CSelectOption($value, $name));
}

echo (new CForm())
	// Enable form submitting on Enter.
	->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN))
	->addItem((new CFormGrid())
		->addItem([
			new CLabel('Type', 'ceprule-window-condition-type-label'),
			new CFormField($condition_type)
		])
		->addItem(
			(new CTemplateTag(null))
				->setAttribute('for-type', CCepRuleHelper::WINDOW_CONDITION_TAG_PAIR)
				->addItem([
					new CLabel('Past event tag name', 'ceprule-window-past-event-tag-name'),
					new CFormField(
						(new CTextBox('past_tag'))
							->setId('ceprule-window-past-event-tag-name')
							->setAttribute('placeholder', _('tag name'))
					),
					new CLabel('Operator'),
					new CFormField(
						(new CRadioButtonList('operator', CONDITION_OPERATOR_EQUAL))
							->setModern()
							->addValue(_('Equals'), CONDITION_OPERATOR_EQUAL)
					),
					new CLabel('Current event tag name', 'ceprule-window-event-tag-name'),
					new CFormField(
						(new CTextBox('tag'))
							->setId('ceprule-window-event-tag-name')
							->setAttribute('placeholder', _('tag name'))
					)
				])
		)
		->addItem(
			(new CTemplateTag(null))
				->setAttribute('for-type', CCepRuleHelper::WINDOW_CONDITION_OLD_TAG)
				->addItem([
					new CLabel('Operator'),
					new CFormField(
						(new CRadioButtonList('operator', CONDITION_OPERATOR_EQUAL))
							->setModern()
							->addValue(_('Equals'), CONDITION_OPERATOR_EQUAL)
					),
					new CLabel('Tag', 'ceprule-window-past-event-tag-name'),
					new CFormField(
						(new CTextBox('past_tag'))
							->setId('ceprule-window-past-event-tag-name')
							->setAttribute('placeholder', _('tag name'))
					)
				])
		)
		->addItem(
			(new CTemplateTag(null))
				->setAttribute('for-type', CCepRuleHelper::WINDOW_CONDITION_OLD_TAG_VALUE)
				->addItem([
					new CLabel('Tag', 'ceprule-window-past-event-tag-name'),
					new CFormField(
						(new CTextBox('past_tag'))
							->setId('ceprule-window-past-event-tag-name')
							->setAttribute('placeholder', _('tag name'))
					),
					new CLabel('Operator'),
					new CFormField(
						(new CRadioButtonList('operator', CONDITION_OPERATOR_EQUAL))
							->setModern()
							->addValue(_('Equals'), CONDITION_OPERATOR_EQUAL)
							->addValue(_('Does not equal'), CONDITION_OPERATOR_NOT_EQUAL)
					),
					new CLabel(_('Value'), 'ceprule-window-past-event-tag-value'),
					new CFormField(
						(new CTextBox('tag_value'))
							->setId('ceprule-window-past-event-tag-value')
							->setAttribute('placeholder', _('tag name'))
					)
				])
		)
		->addItem(
			(new CInput('hidden', 'formulaid', ''))
				->setAttribute('data-field-type', 'hidden')
				->removeId()
		)
	);
