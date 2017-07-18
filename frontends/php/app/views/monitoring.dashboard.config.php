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


$form = (new CForm('post'))
	->cleanItems()
	->setId('widget_dialogue_form')
	->setName('widget_dialogue_form');
$js_scripts = [];

$form_list = new CFormList();

$known_widget_types = CWidgetConfig::getKnownWidgetTypes();
natsort($known_widget_types);

// Common fields
$form_list->addRow(_('Type'),
	new CComboBox('type', $data['dialogue']['type'], 'updateWidgetConfigDialogue()', $known_widget_types)
);

$form_list->addRow(_('Name'),
	(new CTextBox('name', $data['dialogue']['name']))
		->setAttribute('placeholder', _('default'))
		->setAttribute('tabindex', 1)
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
);

// Widget specific fields
foreach ($data['dialogue']['form']->getFields() as $field) {
	if ($field instanceof CWidgetFieldComboBox) {
		$form_list->addRow($field->getLabel(),
			new CComboBox($field->getName(), $field->getValue(), $field->getAction(), $field->getValues())
		);
	}
	elseif ($field instanceof CWidgetFieldTextBox) {
		$form_list->addRow($field->getLabel(),
			(new CTextBox($field->getName(), $field->getValue()))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		);
	}
	elseif ($field instanceof CWidgetFieldCheckBox) {
		$form_list->addRow($field->getLabel(), [
			new CVar($field->getName(), '0'),
			(new CCheckBox($field->getName()))->setChecked((bool) $field->getValue())
		]);
	}
	elseif ($field instanceof CWidgetFieldGroup) {
		// multiselect.js must be preloaded in parent view`

		$field_groupids = (new CMultiSelect([
			'name' => $field->getName().'[]',
			'objectName' => 'hostGroup',
			'data' => $data['captions']['ms']['groups'][$field->getName()],
			'popup' => [
				'parameters' => 'srctbl=host_groups&dstfrm='.$form->getName().'&dstfld1='.$field->getName().'_'.
					'&srcfld1=groupid&multiselect=1'
			],
			'add_post_js' => false
		]))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);

		$form_list->addRow($field->getLabel(), $field_groupids);

		$js_scripts[] = $field_groupids->getPostJS();
	}
	elseif ($field instanceof CWidgetFieldHost) {
		// multiselect.js must be preloaded in parent view`

		$field_hostids = (new CMultiSelect([
			'name' => $field->getName().'[]',
			'objectName' => 'hosts',
			'data' => $data['captions']['ms']['hosts'][$field->getName()],
			'popup' => [
				'parameters' => 'srctbl=hosts&dstfrm='.$form->getName().'&dstfld1='.$field->getName().'_'.
					'&srcfld1=hostid&multiselect=1'
			],
			'add_post_js' => false
		]))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);

		$form_list->addRow($field->getLabel(), $field_hostids);

		$js_scripts[] = $field_hostids->getPostJS();
	}
	elseif ($field instanceof CWidgetFieldReference) {
		$form->addVar($field->getName(), $field->getValue() ?: '');

		if (!$field->getValue()) {
			$javascript = $field->getJavascript('#'.$form->getAttribute('id'));
			$form->addItem(new CJsScript(get_js($javascript, true))); // TODO VM: rewrite to use js_scripts
		}
	}
	elseif ($field instanceof CWidgetFieldHidden) {
		$form->addVar($field->getName(), $field->getValue());
	}
	elseif ($field instanceof CWidgetFieldSelectResource) {
		$resource_type = $field->getResourceType();
		$id = $field->getValue();

		if (array_key_exists($id, $data['captions']['simple'][$resource_type])) {
			if ($data['captions']['simple'][$resource_type][$id] !== null) {
				$caption = $data['captions']['simple'][$resource_type][$id];
			}
			else {
				switch ($resource_type) {
					case WIDGET_FIELD_SELECT_RES_ITEM:
						$caption = _('Inaccessible item');
						break;

					case WIDGET_FIELD_SELECT_RES_SYSMAP:
						$caption = _('Inaccessible map');
						break;
				}
			}
		}
		else {
			$caption = '';
		}

		// needed for popup script
		$form->addVar($field->getName(), $id);
		$form_list->addRow($field->getLabel(), [
			(new CTextBox($field->getName().'_caption', $caption, true))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CButton('select', _('Select')))
				->addClass(ZBX_STYLE_BTN_GREY)
				->onClick('javascript: return PopUp("'.$field->getPopupUrl().'&dstfrm='.$form->getName().'");')
		]);
	}
	elseif ($field instanceof CWidgetFieldWidgetListComboBox) {
		$form_list->addRow($field->getLabel(), new CComboBox($field->getName(), [], $field->getAction(), []));

		$form->addItem(new CJsScript(get_js($field->getJavascript(), true))); // TODO VM: rewrite to use js_scripts
	}
	elseif ($field instanceof CWidgetFieldNumericBox) {
		$form_list->addRow($field->getLabel(),
			(new CNumericBox($field->getName(), $field->getValue(), $field->getMaxLength()))
				->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
		);
	}
	elseif ($field instanceof CWidgetFieldRadioButtonList) {
		$radio_button_list = (new CRadioButtonList($field->getName(), $field->getValue()))
			->setModern($field->getModern());
		foreach ($field->getValues() as $key => $value) {
			$radio_button_list->addValue($value, $key, null, $field->getAction());
		}
		$form_list->addRow($field->getLabel(), $radio_button_list);
	}
	elseif ($field instanceof CWidgetFieldSeverities) {
		$severities = (new CList())->addClass(ZBX_STYLE_LIST_CHECK_RADIO);

		for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
			$severities->addItem(
				(new CCheckBox('severities[]', $severity))
					->setLabel(getSeverityName($severity, $data['config']))
					->setId('severities_'.$severity)
					->setChecked(in_array($severity, $field->getValue(true)))
			);
		}

		$form_list->addRow($field->getLabel(), $severities);
	}
}

$form->addItem($form_list);

$output = [
	'body' => $form->toString()
];

if ($js_scripts) {
	$output['body'] .= get_js(implode("\n", $js_scripts));
}

if (($messages = getMessages()) !== null) {
	$output['messages'] = $messages->toString();
}

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo (new CJson())->encode($output);
