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


/**
 * Map widget form view.
 */
$fields = $data['dialogue']['fields'];

$form = CWidgetHelper::createForm();
$form_list = CWidgetHelper::createFormList($data['dialogue']['name'], $data['dialogue']['type'],
	$data['known_widget_types']
);

// Refresh rate.
$form_list->addRow(CWidgetHelper::getLabel($fields['rf_rate']), CWidgetHelper::getComboBox($fields['rf_rate']));

// Map widget reference.
$field = $fields['reference'];
$form->addVar($field->getName(), $field->getValue() ? $field->getValue() : '');

if (!$field->getValue()) {
	$form->addItem(new CJsScript(get_js($field->getJavascript('#'.$form->getAttribute('id')), true)));
}

// Source.
$form_list->addRow(
	CWidgetHelper::getLabel($fields['source_type']),
	CWidgetHelper::getRadioButtonList($fields['source_type'])
);

// Filter.
if (array_key_exists('filter_widget_reference', $fields)) {
	$form->addItem(new CJsScript(get_js($fields['filter_widget_reference']->getJavascript(), true)));
	$form_list->addRow(
		CWidgetHelper::getLabel($fields['filter_widget_reference']),
		CWidgetHelper::getListComboBox($fields['filter_widget_reference'])
	);
}

// Map.
if (array_key_exists('sysmapid', $fields)) {
	$field = $fields['sysmapid'];

	$form->addVar($field->getName(), $field->getValue());

	$field_sysmapid = CWidgetHelper::getSelectResource($field,
		($field->getValue() != 0) ? $data['captions']['simple'][$field->getResourceType()][$field->getValue()] : '',
		$form->getName()
	);
	$form_list->addRow(CWidgetHelper::getLabel($field), $field_sysmapid);
}

$form->addItem($form_list);

return [
	'form' => $form
];
