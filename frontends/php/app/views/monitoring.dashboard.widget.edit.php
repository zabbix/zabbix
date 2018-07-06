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


$form = (new CForm('post'))
	->cleanItems()
	->setId('widget_dialogue_form')
	->setName('widget_dialogue_form');
$jq_templates = [];
$js_scripts = [];

$form_list = new CFormList();

// common fields
$form_list->addRow((new CLabel(_('Type'), 'type')),
	(new CComboBox('type', $data['dialogue']['type'], 'updateWidgetConfigDialogue()', $data['known_widget_types']))
);

$form_list->addRow(_('Name'),
	(new CTextBox('name', $data['dialogue']['name']))
		->setAttribute('placeholder', _('default'))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
);

// Add widget specific fields that are not included in tabs.
CControllerDashboardWidgetEdit::parseViewField($data['dialogue']['fields'], $form_list, $form, $js_scripts, $jq_templates, $data);
$form->addItem($form_list);

// Add tabs and fields.
if ($data['dialogue']['tabs']) {
	$form_tabs = (new CTabView())
		->addClass(ZBX_STYLE_TABS_LEFT_PADDING_15_PERCENTS) // TODO miks: ugly solution. Should make more pretty.
		->addStyle('width: 1000px;'); // TODO miks: later graph preview will define actual width of window.

	foreach ($data['dialogue']['tabs'] as $tab_key => $tab) {
		$tab_form_list = new CFormList();
		CControllerDashboardWidgetEdit::parseViewField($tab['fields'], $tab_form_list, $form, $js_scripts, $jq_templates, $data);
		$form_tabs->addTab($tab_key, $tab['name'], $tab_form_list);
	}

	$form->addItem($form_tabs);
	$js_scripts[] = $form_tabs->makeJavascript();
}

// Submit button is needed to enable submit event on Enter on inputs.
$form->addItem((new CInput('submit', 'dashboard_widget_config_submit'))->addStyle('display: none;'));

$output = [
	'body' => $form->toString()
];

foreach ($jq_templates as $id => $jq_template) {
	$output['body'] .= '<script type="text/x-jquery-tmpl" id="'.$id.'">'.$jq_template.'</script>';
}
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
