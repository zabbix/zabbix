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
 * Plain text widget form view.
 */
$fields = $data['dialogue']['fields'];

$form = CWidgetHelper::createForm();

$rf_rate_field = ($data['templateid'] === null) ? $fields['rf_rate'] : null;

$form_list = CWidgetHelper::createFormList($data['dialogue']['name'], $data['dialogue']['type'],
	$data['dialogue']['view_mode'], $data['known_widget_types'], $rf_rate_field
);

// Items.
$field_itemids = CWidgetHelper::getItem($fields['itemids'], $data['captions']['ms']['items']['itemids'],
	$form->getName()
);
$form_list->addRow(CWidgetHelper::getMultiselectLabel($fields['itemids']), $field_itemids);
$scripts = [$field_itemids->getPostJS()];

// Items location.
$form_list->addRow(CWidgetHelper::getLabel($fields['style']), CWidgetHelper::getRadioButtonList($fields['style']));

// Show lines.
$form_list->addRow(CWidgetHelper::getLabel($fields['show_lines']), CWidgetHelper::getIntegerBox($fields['show_lines']));

// Show text as HTML.
$form_list->addRow(CWidgetHelper::getLabel($fields['show_as_html']),
	CWidgetHelper::getCheckBox($fields['show_as_html'])
);

// Dynamic item.
if ($data['templateid'] === null) {
	$form_list->addRow(CWidgetHelper::getLabel($fields['dynamic']), CWidgetHelper::getCheckBox($fields['dynamic']));
}

$form->addItem($form_list);

return [
	'form' => $form,
	'scripts' => $scripts
];
