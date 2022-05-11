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
 * Graph widget form view.
 */
$fields = $data['dialogue']['fields'];

$form = CWidgetHelper::createForm();

$rf_rate_field = ($data['templateid'] === null) ? $fields['rf_rate'] : null;

$form_list = CWidgetHelper::createFormList($data['dialogue']['name'], $data['dialogue']['type'],
	$data['dialogue']['view_mode'], $data['known_widget_types'], $rf_rate_field
);

$scripts = [];

// Source.
$form_list->addRow(
	CWidgetHelper::getLabel($fields['source_type']),
	CWidgetHelper::getRadioButtonList($fields['source_type'])
);

// Graph.
if (array_key_exists('graphid', $fields)) {
	$field_graphid = CWidgetHelper::getGraph($fields['graphid'], $data['captions']['ms']['graphs']['graphid'],
		$form->getName()
	);
	$form_list->addRow(CWidgetHelper::getMultiselectLabel($fields['graphid']), $field_graphid);
	$scripts[] = $field_graphid->getPostJS();
}

// Item.
if (array_key_exists('itemid', $fields)) {
	$field_itemid = CWidgetHelper::getItem($fields['itemid'], $data['captions']['ms']['items']['itemid'],
		$form->getName()
	);
	$form_list->addRow(CWidgetHelper::getMultiselectLabel($fields['itemid']), $field_itemid);
	$scripts[] = $field_itemid->getPostJS();
}

// Show legend.
$form_list->addRow(CWidgetHelper::getLabel($fields['show_legend']), CWidgetHelper::getCheckBox($fields['show_legend']));

// Dynamic item.
if ($data['templateid'] === null) {
	$form_list->addRow(CWidgetHelper::getLabel($fields['dynamic']), CWidgetHelper::getCheckBox($fields['dynamic']));
}

$form->addItem($form_list);

return [
	'form' => $form,
	'scripts' => $scripts
];
