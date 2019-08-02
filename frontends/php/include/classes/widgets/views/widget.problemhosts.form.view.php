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
 * Problem hosts widget form view.
 */
$fields = $data['dialogue']['fields'];

$form = CWidgetHelper::createForm();

$form_list = CWidgetHelper::createFormList($data['dialogue']['name'], $data['dialogue']['type'],
	$data['known_widget_types'], $fields['rf_rate']
);

$scripts = [];

// Host groups.
$fields['groupids']->addToForm($form, $form_list, $scripts);

// Exclude host groups.
$fields['exclude_groupids']->addToForm($form, $form_list, $scripts);

// Hosts.
$fields['hostids']->addToForm($form, $form_list, $scripts);

// Problem.
$form_list->addRow(CWidgetHelper::getLabel($fields['problem']), CWidgetHelper::getTextBox($fields['problem']));

// Severity.
$form_list->addRow(
	CWidgetHelper::getLabel($fields['severities']),
	CWidgetHelper::getSeverities($fields['severities'], $data['config'])
);

// Show suppressed problems.
$form_list->addRow(CWidgetHelper::getLabel($fields['show_suppressed']),
	CWidgetHelper::getCheckBox($fields['show_suppressed'])
);

// Hide groups without problems.
$form_list->addRow(
	CWidgetHelper::getLabel($fields['hide_empty_groups']),
	CWidgetHelper::getCheckBox($fields['hide_empty_groups'])
);

// Problem display.
$form_list->addRow(CWidgetHelper::getLabel($fields['ext_ack']), CWidgetHelper::getRadioButtonList($fields['ext_ack']));

$form->addItem($form_list);

return [
	'form' => $form,
	'scripts' => $scripts
];
