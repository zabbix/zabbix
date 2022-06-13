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
 * Graph prototype widget form view.
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

// Source.
$form_grid->addItem([
	CWidgetHelper::getLabel($fields['source_type']),
	new CFormField(CWidgetHelper::getRadioButtonList($fields['source_type']))
]);

// Graph prototype.
if (array_key_exists('graphid', $fields)) {
	$field_graphid = CWidgetHelper::getGraphPrototype($fields['graphid'],
		$data['captions']['ms']['graph_prototypes']['graphid'], $form->getName()
	);
	$form_grid->addItem([
		CWidgetHelper::getMultiselectLabel($fields['graphid']),
		new CFormField($field_graphid)
	]);
	$scripts[] = $field_graphid->getPostJS();
}

// Item prototype.
if (array_key_exists('itemid', $fields)) {
	$field_itemid = CWidgetHelper::getItemPrototype($fields['itemid'],
		$data['captions']['ms']['item_prototypes']['itemid'], $form->getName()
	);
	$form_grid->addItem([
		CWidgetHelper::getMultiselectLabel($fields['itemid']),
		new CFormField($field_itemid)
	]);
	$scripts[] = $field_itemid->getPostJS();
}

// Show legend.
$form_grid->addItem([
	CWidgetHelper::getLabel($fields['show_legend']),
	new CFormField(CWidgetHelper::getCheckBox($fields['show_legend']))
]);

// Dynamic item.
if ($data['templateid'] === null) {
	$form_grid->addItem([
		CWidgetHelper::getLabel($fields['dynamic']),
		new CFormField(CWidgetHelper::getCheckBox($fields['dynamic']))
	]);
}

// Columns.
$form_grid->addItem([
	CWidgetHelper::getLabel($fields['columns']),
	new CFormField(CWidgetHelper::getIntegerBox($fields['columns']))
]);

// Rows.
$form_grid->addItem([
	CWidgetHelper::getLabel($fields['rows']),
	new CFormField(CWidgetHelper::getIntegerBox($fields['rows']))
]);

$form->addItem($form_grid);

return [
	'form' => $form,
	'scripts' => $scripts
];
