<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


/**
 * Problems widget form view.
 */
$fields = $data['dialogue']['fields'];

$form = CWidgetHelper::createForm();

$form_list = CWidgetHelper::createFormList($data['dialogue']['name'], $data['dialogue']['type'],
	$data['dialogue']['view_mode'], $data['known_widget_types'], $fields['rf_rate']
);

$scripts = [];
$jq_templates = [];

// Show.
$form_list->addRow(CWidgetHelper::getLabel($fields['show']), CWidgetHelper::getRadioButtonList($fields['show']));

// Host groups.
$field_groupids = CWidgetHelper::getGroup($fields['groupids'],
	$data['captions']['ms']['groups']['groupids'],
	$form->getName()
);
$form_list->addRow(CWidgetHelper::getMultiselectLabel($fields['groupids']), $field_groupids);
$scripts[] = $field_groupids->getPostJS();

// Exclude host groups.
$field_exclude_groupids = CWidgetHelper::getGroup($fields['exclude_groupids'],
	$data['captions']['ms']['groups']['exclude_groupids'],
	$form->getName()
);
$form_list->addRow(CWidgetHelper::getMultiselectLabel($fields['exclude_groupids']), $field_exclude_groupids);
$scripts[] = $field_exclude_groupids->getPostJS();

// Hosts.
$field_hostids = CWidgetHelper::getHost($fields['hostids'],
	$data['captions']['ms']['hosts']['hostids'],
	$form->getName()
);
$form_list->addRow(CWidgetHelper::getMultiselectLabel($fields['hostids']), $field_hostids);
$scripts[] = $field_hostids->getPostJS();

// Problem.
$form_list->addRow(CWidgetHelper::getLabel($fields['problem']), CWidgetHelper::getTextBox($fields['problem']));

// Severity.
$form_list->addRow(
	CWidgetHelper::getLabel($fields['severities']),
	CWidgetHelper::getSeverities($fields['severities'], $data['config'])
);

// Tags.
$form_list->addRow(CWidgetHelper::getLabel($fields['evaltype']), CWidgetHelper::getRadioButtonList($fields['evaltype']));

// Tags filter list.
$form_list->addRow(CWidgetHelper::getLabel($fields['tags']), CWidgetHelper::getTags($fields['tags']));
$scripts[] = $fields['tags']->getJavascript();
$jq_templates['tag-row-tmpl'] = CWidgetHelper::getTagsTemplate($fields['tags']);

// Show tags.
$form_list->addRow(CWidgetHelper::getLabel($fields['show_tags']), CWidgetHelper::getRadioButtonList($fields['show_tags']));

// Tag name.
$form_list->addRow(CWidgetHelper::getLabel($fields['tag_name_format']),
	CWidgetHelper::getRadioButtonList($fields['tag_name_format'])
		->setEnabled($fields['show_tags']->getValue() !== PROBLEMS_SHOW_TAGS_NONE)
);

// Tag display priority.
$form_list->addRow(CWidgetHelper::getLabel($fields['tag_priority']),
	CWidgetHelper::getTextBox($fields['tag_priority'])
		->setAttribute('placeholder', _('comma-separated list'))
		->setEnabled($fields['show_tags']->getValue() !== PROBLEMS_SHOW_TAGS_NONE)
);

// Show suppressed problems.
$form_list->addRow(CWidgetHelper::getLabel($fields['show_suppressed']),
	CWidgetHelper::getCheckBox($fields['show_suppressed'])
);

// Show unacknowledged only.
$form_list->addRow(CWidgetHelper::getLabel($fields['unacknowledged']),
	CWidgetHelper::getCheckBox($fields['unacknowledged'])
);

// Show operational data.
$form_list->addRow(CWidgetHelper::getLabel($fields['show_opdata']), CWidgetHelper::getCheckBox($fields['show_opdata']));

// Sort entries by.
$form_list->addRow(CWidgetHelper::getLabel($fields['sort_triggers']),
	CWidgetHelper::getComboBox($fields['sort_triggers'])
);

// Show timeline.
$form_list->addRow(CWidgetHelper::getLabel($fields['show_timeline']),
	CWidgetHelper::getCheckBox($fields['show_timeline'])
);

// Show lines.
$form_list->addRow(CWidgetHelper::getLabel($fields['show_lines']), CWidgetHelper::getIntegerBox($fields['show_lines']));

$form->addItem($form_list);

return [
	'form' => $form,
	'scripts' => $scripts,
	'jq_templates' => $jq_templates
];
