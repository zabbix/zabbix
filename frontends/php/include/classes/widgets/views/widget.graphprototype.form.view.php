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
 * Graph prototype widget form view.
 */
$fields = $data['dialogue']['fields'];

$form = CWidgetHelper::createForm();

$form_list = CWidgetHelper::createFormList($data['dialogue']['name'], $data['dialogue']['type'],
	$data['known_widget_types'], $fields['rf_rate']
);

$scripts = [];

// Source.
$form_list->addRow(
	CWidgetHelper::getLabel($fields['source_type']),
	CWidgetHelper::getRadioButtonList($fields['source_type'])
);

// Graph prototype.
if (array_key_exists('graphid', $fields)) {
	$field = $fields['graphid'];

	// Needed for popup script.
	$form->addVar($field->getName(), $field->getValue());

	$field_graphid = CWidgetHelper::getSelectResource(
		$field,
		($field->getValue() != 0) ? $data['captions']['simple'][$field->getResourceType()][$field->getValue()] : '',
		$form->getName()
	);
	$form_list->addRow(CWidgetHelper::getLabel($fields['graphid']), $field_graphid);
}

// Item prototype.
if (array_key_exists('itemid', $fields)) {
	$field = $fields['itemid'];

	// Needed for popup script.
	$form->addVar($field->getName(), $field->getValue());

	$field_itemid = CWidgetHelper::getSelectResource(
		$field,
		($field->getValue() != 0) ? $data['captions']['simple'][$field->getResourceType()][$field->getValue()] : '',
		$form->getName()
	);
	$form_list->addRow(CWidgetHelper::getLabel($fields['itemid']), $field_itemid);
}

// Dynamic item.
$form_list->addRow(CWidgetHelper::getLabel($fields['dynamic']), CWidgetHelper::getCheckBox($fields['dynamic']));

CWidgetHelper::addIteratorFields($form_list, $fields);

$form->addItem($form_list);

return [
	'form' => $form,
	'scripts' => $scripts
];
