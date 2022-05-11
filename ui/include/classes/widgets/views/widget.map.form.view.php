<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

$rf_rate_field = ($data['templateid'] === null) ? $fields['rf_rate'] : null;

$form_list = CWidgetHelper::createFormList($data['dialogue']['name'], $data['dialogue']['type'],
	$data['dialogue']['view_mode'], $data['known_widget_types'], $rf_rate_field
);

$scripts = [];

// Map widget reference.
$field = $fields[CWidgetFieldReference::FIELD_NAME];
$form->addVar($field->getName(), $field->getValue());

// Source.
$form_list->addRow(
	CWidgetHelper::getLabel($fields['source_type']),
	CWidgetHelper::getRadioButtonList($fields['source_type'])
);

// Filter.
if (array_key_exists('filter_widget_reference', $fields)) {
	$form_list->addRow(
		CWidgetHelper::getLabel($fields['filter_widget_reference']),
		CWidgetHelper::getEmptySelect($fields['filter_widget_reference'])
	);
	$scripts[] = $fields['filter_widget_reference']->getJavascript();
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
	'form' => $form,
	'scripts' => $scripts
];
