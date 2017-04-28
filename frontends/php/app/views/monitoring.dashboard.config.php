<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/

$body = '';
if (array_key_exists('dialogue', $data)) {
	/* @var $data['dialogue']['form'] CWidgetForm */
	$formFields = $data['dialogue']['form']->getFields();

	$form = (new CForm('post'))
		->cleanItems()
		->setId('widget_dialogue_form')
		->setName('widget_dialogue_form');

	$formList = (new CFormList());

	foreach ($formFields as $field) {
		/* ComboBox */
		if ($field instanceof CWidgetFieldComboBox) {
			$formList->addRow(
				$field->getLabel(),
				(new CComboBox($field->getName(), $field->getValue(true), $field->getAction(), $field->getValues()))
			);
		}

		/* TextBox */
		elseif ($field instanceof CWidgetFieldTextBox) {
			$formList->addRow(
				$field->getLabel(),
				(new CTextBox($field->getName(), $field->getValue(true)))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			);
		}

		/* ItemId */
		elseif ($field instanceof CWidgetFieldItemId) {
			$form->addVar($field->getName(), $field->getValue(true)); // needed for popup script

			$selectButton = (new CButton('select', _('Select')))
					->addClass(ZBX_STYLE_BTN_GREY)
					->onClick("javascript: return PopUp('popup.php?dstfrm=".$form->getName().'&dstfld1='.$field->getName().
						"&dstfld2=".$field->getCaptionName()."&srctbl=items&srcfld1=itemid&srcfld2=name&real_hosts=1');");
			$cell = (new CDiv([
				(new CTextBox($field->getCaptionName(), $field->getCaption(true), true))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				$selectButton
			]))->addStyle('display: flex;'); // TODO VM: move style to scss
			$formList->addRow($field->getLabel(), $cell);
		}
	}

	$form->addItem($formList);
	$body = $form->toString();
}

$output = [
	'body' => $body
];

if (($messages = getMessages()) !== null) {
	$output['messages'] = $messages->toString();
}

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo (new CJson())->encode($output);
