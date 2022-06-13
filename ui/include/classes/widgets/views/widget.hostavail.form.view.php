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
 * Host availability widget form view.
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

// Host groups.
$field_groupids = CWidgetHelper::getGroup($fields['groupids'], $data['captions']['ms']['groups']['groupids'],
	$form->getName()
);
$form_grid->addItem([
	CWidgetHelper::getMultiselectLabel($fields['groupids']),
	new CFormField($field_groupids)
]);
$scripts[] = $field_groupids->getPostJS();

// Interface type.
$form_grid->addItem([
	CWidgetHelper::getLabel($fields['interface_type']),
	new CFormField(
		CWidgetHelper::getCheckBoxList($fields['interface_type'], [
			INTERFACE_TYPE_AGENT => _('Zabbix agent'),
			INTERFACE_TYPE_SNMP => _('SNMP'),
			INTERFACE_TYPE_JMX => _('JMX'),
			INTERFACE_TYPE_IPMI => _('IPMI')
		])
	)
]);

// Layout.
$form_grid->addItem([
	CWidgetHelper::getLabel($fields['layout']),
	new CFormField(CWidgetHelper::getRadioButtonList($fields['layout']))
]);

// Show hosts in maintenance.
$form_grid->addItem([
	CWidgetHelper::getLabel($fields['maintenance']),
	new CFormField(CWidgetHelper::getCheckBox($fields['maintenance']))
]);

$form->addItem($form_grid);

return [
	'form' => $form,
	'scripts' => $scripts
];
