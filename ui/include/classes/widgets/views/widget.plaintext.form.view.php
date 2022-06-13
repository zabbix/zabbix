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
 * Plain text widget form view.
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

// Items.
$field_itemids = CWidgetHelper::getItem($fields['itemids'], $data['captions']['ms']['items']['itemids'],
	$form->getName()
);
$form_grid->addItem([
	CWidgetHelper::getMultiselectLabel($fields['itemids']),
	new CFormField($field_itemids)
]);
$scripts[] = $field_itemids->getPostJS();

// Items location.
$form_grid->addItem([
	CWidgetHelper::getLabel($fields['style']),
	new CFormField(CWidgetHelper::getRadioButtonList($fields['style']))
]);

// Show lines.
$form_grid->addItem([
	CWidgetHelper::getLabel($fields['show_lines']),
	new CFormField(CWidgetHelper::getIntegerBox($fields['show_lines']))
]);

// Show text as HTML.
$form_grid->addItem([
	CWidgetHelper::getLabel($fields['show_as_html']),
	new CFormField(CWidgetHelper::getCheckBox($fields['show_as_html']))
]);

// Dynamic item.
if ($data['templateid'] === null) {
	$form_grid->addItem([
		CWidgetHelper::getLabel($fields['dynamic']),
		new CFormField(CWidgetHelper::getCheckBox($fields['dynamic']))
	]);
}

$form->addItem($form_grid);

return [
	'form' => $form,
	'scripts' => $scripts
];
