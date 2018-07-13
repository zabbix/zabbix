<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


/*
foreach ($data['dialogue']['fields'] as $field) {
	$label = '';
	$item = null;
	$script = null;
	$template = null;

	if ($field instanceof CWidgetFieldComboBox) {
		list($label, $item) = getComboBoxField($field);
	}
	elseif ($field instanceof CWidgetFieldTextBox || $field instanceof CWidgetFieldUrl) {
		list($label, $item) = getTextBoxField($field);
	}
	elseif ($field instanceof CWidgetFieldCheckBox) {
		list($label, $item) = getCheckBoxField($field);
	}
	elseif ($field instanceof CWidgetFieldGroup) {
		list($label, $item, $script) = getGroupField($field, $data['captions']['ms']['groups'][$field->getName()],
			$form->getName()
		);
	}
	elseif ($field instanceof CWidgetFieldHost) {
		list($label, $item, $script) = getHostField($field, $data['captions']['ms']['hosts'][$field->getName()],
			$form->getName()
		);
	}
	elseif ($field instanceof CWidgetFieldItem) {
		list($label, $item, $script) = getItemField($field, $data['captions']['ms']['items'][$field->getName()],
			$form->getName()
		);
	}
	elseif ($field instanceof CWidgetFieldSelectResource) {
		$form->addVar($field->getName(), $field->getValue());

		$caption = ($field->getValue() != 0)
			? $data['captions']['simple'][$field->getResourceType()][$field->getValue()]
			: '';

		list($label, $item) = getSelectResourceField($field, $caption, $form->getName());
	}
	elseif ($field instanceof CWidgetFieldWidgetListComboBox) {
		$form->addItem(new CJsScript(get_js($field->getJavascript(), true)));
		list($label, $item) = getListComboBoxField($field);
	}
	elseif ($field instanceof CWidgetFieldNumericBox) {
		list($label, $item) = getNumericBoxField($field);
	}
	elseif ($field instanceof CWidgetFieldRadioButtonList) {
		list($label, $item) = getRadioButtonListField($field);
	}
	elseif ($field instanceof CWidgetFieldSeverities) {
		list($label, $item) = getSeveritiesField($field, $data['config']);
	}
	elseif ($field instanceof CWidgetFieldTags) {
		list($label, $item, $script, $template['tag-row']) = getTagsField($field);
	}
	elseif ($field instanceof CWidgetFieldReference) {
		$form->addVar($field->getName(), $field->getValue() ? $field->getValue() : '');

		if (!$field->getValue()) {
			$form->addItem(new CJsScript(get_js($field->getJavascript('#' . $form->getAttribute('id')), true)));
		}
	}
	elseif ($field instanceof CWidgetFieldHidden) {
		$form->addVar($field->getName(), $field->getValue());
	}

	if ($item !== null) {
		$form_list->addRow($label, $item);
	}

	if ($script !== null) {
		$js_scripts[] = $script;
	}

	if ($template !== null) {
		$jq_templates += $template;
	}
}
*/

$widget = include('include/classes/widgets/views/widget.'.$data['dialogue']['type'].'.form.view.php');

$form = $widget['form'];

// Submit button is needed to enable submit event on Enter on inputs.
$form->addItem((new CInput('submit', 'dashboard_widget_config_submit'))->addStyle('display: none;'));

$output = [
	'body' => $form->toString()
];

if(array_key_exists('jq_templates', $widget)) {
	foreach ($widget['jq_templates'] as $id => $jq_template) {
		$output['body'] .= '<script type="text/x-jquery-tmpl" id="'.$id.'">'.$jq_template.'</script>';
	}
}
if(array_key_exists('scripts', $widget)) {
	$output['body'] .= get_js(implode("\n", $widget['scripts']));
}

if (($messages = getMessages()) !== null) {
	$output['messages'] = $messages->toString();
}

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo (new CJson())->encode($output);
