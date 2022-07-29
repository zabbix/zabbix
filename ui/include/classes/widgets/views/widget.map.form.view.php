<?php declare(strict_types = 0);
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
 *
 * @var CView $this
 * @var array $data
 */

$fields = $data['dialogue']['fields'];

$form = CWidgetHelper::createForm();

$scripts = [];

$form_grid = CWidgetHelper::createFormGrid($data['dialogue']['name'], $data['dialogue']['type'],
	$data['dialogue']['view_mode'], $data['known_widget_types'],
	$data['templateid'] === null ? $fields['rf_rate'] : null
);

// Map widget reference.
$form->addVar($fields[CWidgetFieldReference::FIELD_NAME]->getName(),
	$fields[CWidgetFieldReference::FIELD_NAME]->getValue()
);

// Source type.
$form_grid->addItem([
	CWidgetHelper::getLabel($fields['source_type']),
	new CFormField(CWidgetHelper::getRadioButtonList($fields['source_type']))
]);

// Map.
if (array_key_exists('sysmapid', $fields)) {
	$field = $fields['sysmapid'];

	$form->addVar($field->getName(), $field->getValue());

	$form_grid->addItem([
		CWidgetHelper::getLabel($field),
		new CFormField(
			CWidgetHelper::getSelectResource(
				$field,
				$field->getValue() != 0
					? $data['captions']['simple'][$field->getResourceType()][$field->getValue()]
					: '',
				$form->getName()
			)
		)
	]);
}

// Filter.
if (array_key_exists('filter_widget_reference', $fields)) {
	$form_grid->addItem([
		CWidgetHelper::getLabel($fields['filter_widget_reference']),
		new CFormField(CWidgetHelper::getEmptySelect($fields['filter_widget_reference']))
	]);
	$scripts[] = $fields['filter_widget_reference']->getJavascript();
}

$form->addItem($form_grid);

return [
	'form' => $form,
	'scripts' => $scripts
];
